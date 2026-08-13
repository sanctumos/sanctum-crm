<?php
/**
 * Outbound webhook delivery queue (async; retries + dead-letter).
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class WebhookQueue
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_DEAD = 'dead';

    private Database $db;
    private WebhookDispatcher $dispatcher;

    public function __construct(?Database $db = null, ?WebhookDispatcher $dispatcher = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->dispatcher = $dispatcher ?? new WebhookDispatcher($this->db);
    }

    /**
     * Enqueue one delivery row per subscribed active webhook. No HTTP.
     *
     * @return int Number of rows enqueued
     */
    public function enqueue(string $event, array $data): int
    {
        try {
            $webhooks = $this->db->fetchAll(
                'SELECT * FROM webhooks WHERE is_active = 1 ORDER BY id'
            );
        } catch (Exception $e) {
            error_log('WebhookQueue::enqueue load failed: ' . $e->getMessage());
            return 0;
        }

        $payload = [
            'event' => $event,
            'timestamp' => gmdate('c'),
            'data' => $data,
        ];
        $payloadJson = json_encode($payload);
        if ($payloadJson === false) {
            error_log('WebhookQueue::enqueue JSON encode failed');
            return 0;
        }

        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($webhooks as $webhook) {
            if (!$this->webhookSubscribed($webhook, $event)) {
                continue;
            }
            $url = trim((string) ($webhook['url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $this->db->insert('webhook_delivery_queue', [
                'webhook_id' => (int) $webhook['id'],
                'url' => $url,
                'event' => $event,
                'payload' => $payloadJson,
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'max_attempts' => 5,
                'next_attempt_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Process due pending/retry rows. Returns outcome counts.
     *
     * @return array{claimed:int,succeeded:int,requeued:int,dead:int}
     */
    public function processDue(int $limit = 25): array
    {
        $stats = ['claimed' => 0, 'succeeded' => 0, 'requeued' => 0, 'dead' => 0];
        $now = date('Y-m-d H:i:s');
        $limit = max(1, min(200, $limit));

        $rows = $this->db->fetchAll(
            "SELECT * FROM webhook_delivery_queue
             WHERE status = ?
               AND datetime(next_attempt_at) <= datetime(?)
             ORDER BY id ASC
             LIMIT {$limit}",
            [self::STATUS_PENDING, $now]
        );

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            // Optimistic claim
            $this->db->update(
                'webhook_delivery_queue',
                ['status' => self::STATUS_PROCESSING, 'updated_at' => $now],
                'id = ? AND status = ?',
                [$id, self::STATUS_PENDING]
            );
            $claimed = $this->db->fetchOne(
                'SELECT * FROM webhook_delivery_queue WHERE id = ? AND status = ?',
                [$id, self::STATUS_PROCESSING]
            );
            if (!$claimed) {
                continue;
            }
            $stats['claimed']++;

            $payload = json_decode((string) $claimed['payload'], true);
            if (!is_array($payload)) {
                $this->markDead($id, 'Invalid payload JSON', $claimed);
                $stats['dead']++;
                continue;
            }

            $result = $this->dispatcher->deliverToUrl((string) $claimed['url'], $payload);
            $attempts = (int) $claimed['attempts'] + 1;
            $max = (int) ($claimed['max_attempts'] ?: 5);

            if (!empty($result['success'])) {
                $this->db->update(
                    'webhook_delivery_queue',
                    [
                        'status' => self::STATUS_SUCCEEDED,
                        'attempts' => $attempts,
                        'last_http_code' => (int) ($result['http_code'] ?? 200),
                        'last_error' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'completed_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = ?',
                    [$id]
                );
                $stats['succeeded']++;
                continue;
            }

            $error = (string) ($result['error'] ?? 'Delivery failed');
            $http = (int) ($result['http_code'] ?? 0);

            if ($attempts >= $max) {
                $this->db->update(
                    'webhook_delivery_queue',
                    [
                        'status' => self::STATUS_DEAD,
                        'attempts' => $attempts,
                        'last_http_code' => $http,
                        'last_error' => substr($error, 0, 1000),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'completed_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = ?',
                    [$id]
                );
                $stats['dead']++;
                error_log(sprintf(
                    'WebhookQueue: dead-letter id=%d webhook_id=%s event=%s error=%s',
                    $id,
                    $claimed['webhook_id'] ?? '?',
                    $claimed['event'] ?? '?',
                    $error
                ));
            } else {
                $delay = $this->backoffSeconds($attempts);
                $next = date('Y-m-d H:i:s', time() + $delay);
                $this->db->update(
                    'webhook_delivery_queue',
                    [
                        'status' => self::STATUS_PENDING,
                        'attempts' => $attempts,
                        'next_attempt_at' => $next,
                        'last_http_code' => $http,
                        'last_error' => substr($error, 0, 1000),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = ?',
                    [$id]
                );
                $stats['requeued']++;
            }
        }

        return $stats;
    }

    /** @return array{pending:int,processing:int,succeeded:int,dead:int} */
    public function counts(): array
    {
        $out = [
            self::STATUS_PENDING => 0,
            self::STATUS_PROCESSING => 0,
            self::STATUS_SUCCEEDED => 0,
            self::STATUS_DEAD => 0,
        ];
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS c FROM webhook_delivery_queue GROUP BY status'
        );
        foreach ($rows as $row) {
            $s = (string) $row['status'];
            if (isset($out[$s])) {
                $out[$s] = (int) $row['c'];
            }
        }
        return [
            'pending' => $out[self::STATUS_PENDING],
            'processing' => $out[self::STATUS_PROCESSING],
            'succeeded' => $out[self::STATUS_SUCCEEDED],
            'dead' => $out[self::STATUS_DEAD],
        ];
    }

    private function markDead(int $id, string $error, array $row): void
    {
        $this->db->update(
            'webhook_delivery_queue',
            [
                'status' => self::STATUS_DEAD,
                'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                'last_error' => substr($error, 0, 1000),
                'updated_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$id]
        );
    }

    private function backoffSeconds(int $attempt): int
    {
        // 60, 120, 240, 480, 960… capped at 1 hour
        return min(3600, 60 * (2 ** max(0, $attempt - 1)));
    }

    private function webhookSubscribed(array $webhook, string $event): bool
    {
        $raw = $webhook['events'] ?? '[]';
        $events = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($events)) {
            return false;
        }
        return in_array($event, $events, true);
    }
}

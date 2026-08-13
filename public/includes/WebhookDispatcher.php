<?php
/**
 * Deliver CRM event payloads to registered outbound webhooks.
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class WebhookDispatcher
{
    /** @var callable|null */
    private $sender;

    private Database $db;

    /**
     * @param callable|null $sender function(string $url, array $payload): array{success:bool,http_code:int,error:?string}
     */
    public function __construct(?Database $db = null, ?callable $sender = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->sender = $sender;
    }

    /**
     * POST to every active webhook subscribed to $event. Never throws.
     *
     * @return array{attempted:int,succeeded:int,failed:int}
     */
    public function dispatch(string $event, array $data): array
    {
        $stats = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0];

        try {
            $webhooks = $this->db->fetchAll(
                'SELECT * FROM webhooks WHERE is_active = 1 ORDER BY id'
            );
        } catch (Exception $e) {
            error_log('WebhookDispatcher: failed to load webhooks: ' . $e->getMessage());
            return $stats;
        }

        $payload = [
            'event' => $event,
            'timestamp' => gmdate('c'),
            'data' => $data,
        ];

        foreach ($webhooks as $webhook) {
            if (!$this->webhookSubscribed($webhook, $event)) {
                continue;
            }

            $url = trim((string) ($webhook['url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $stats['attempted']++;
            $result = $this->send($url, $payload);
            if ($result['success']) {
                $stats['succeeded']++;
            } else {
                $stats['failed']++;
                error_log(sprintf(
                    'WebhookDispatcher: delivery failed webhook_id=%s event=%s http=%s error=%s',
                    $webhook['id'] ?? '?',
                    $event,
                    $result['http_code'],
                    $result['error'] ?? ''
                ));
            }
        }

        return $stats;
    }

    /**
     * Send a test payload to a single URL (used by /webhooks/{id}/test).
     *
     * @return array{success:bool,http_code:int,error:?string}
     */
    public function sendTest(string $url): array
    {
        return $this->send($url, [
            'event' => 'webhook.test',
            'timestamp' => gmdate('c'),
            'data' => [
                'message' => 'Test webhook from Sanctum CRM',
            ],
        ]);
    }

    /**
     * Deliver a pre-built payload to one URL (queue worker path).
     *
     * @param array<string,mixed> $payload
     * @return array{success:bool,http_code:int,error:?string}
     */
    public function deliverToUrl(string $url, array $payload): array
    {
        return $this->send($url, $payload);
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

    /**
     * @return array{success:bool,http_code:int,error:?string}
     */
    private function send(string $url, array $payload): array
    {
        if ($this->sender !== null) {
            return ($this->sender)($url, $payload);
        }

        if (defined('CRM_TESTING') && CRM_TESTING && getenv('CRM_WEBHOOKS_DRY') === '1') {
            return ['success' => true, 'http_code' => 200, 'error' => null];
        }

        if (function_exists('sendWebhookDetailed')) {
            return sendWebhookDetailed($url, $payload);
        }

        $ok = sendWebhook($url, $payload);
        return [
            'success' => $ok,
            'http_code' => $ok ? 200 : 0,
            'error' => $ok ? null : 'Delivery failed',
        ];
    }
}

/**
 * Enqueue webhook deliveries (async). Worker: tools/process_webhook_queue.php
 */
function crm_dispatch_webhook(string $event, array $data): void
{
    try {
        if (!class_exists('WebhookQueue', false)) {
            require_once __DIR__ . '/WebhookQueue.php';
        }
        (new WebhookQueue())->enqueue($event, $data);
    } catch (Exception $e) {
        error_log('crm_dispatch_webhook: ' . $e->getMessage());
    }
}

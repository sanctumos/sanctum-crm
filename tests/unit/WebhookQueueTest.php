<?php
/**
 * WebhookQueue unit tests — enqueue + retry/dead-letter processing
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/includes/WebhookDispatcher.php';
require_once __DIR__ . '/../../public/includes/WebhookQueue.php';
require_once __DIR__ . '/../../public/includes/MigrationRunner.php';

class WebhookQueueTest
{
    private Database $db;

    public function __construct()
    {
        $this->db = TestUtils::getTestDatabase();
        (new MigrationRunner($this->db))->migrate(false);
    }

    public function runAllTests(): void
    {
        echo "Running WebhookQueue Unit Tests...\n";
        $this->testEnqueueDoesNotHttp();
        $this->testProcessSucceeds();
        $this->testRetryThenDeadLetter();
        echo "All WebhookQueue tests completed!\n";
    }

    private function reset(): void
    {
        $this->db->query('DELETE FROM webhook_delivery_queue');
        $this->db->query('DELETE FROM webhooks');
    }

    public function testEnqueueDoesNotHttp(): void
    {
        echo "  Testing enqueue creates pending rows without HTTP... ";
        $this->reset();
        $sent = 0;
        $dispatcher = new WebhookDispatcher($this->db, function () use (&$sent): array {
            $sent++;
            return ['success' => true, 'http_code' => 200, 'error' => null];
        });
        $this->db->insert('webhooks', [
            'user_id' => 1,
            'url' => 'https://example.test/hook',
            'events' => json_encode(['contact.created']),
            'is_active' => 1,
        ]);
        $queue = new WebhookQueue($this->db, $dispatcher);
        $n = $queue->enqueue('contact.created', ['contact' => ['id' => 1]]);
        if ($n !== 1) {
            throw new Exception("expected 1 enqueued, got {$n}");
        }
        if ($sent !== 0) {
            throw new Exception('enqueue must not HTTP');
        }
        $counts = $queue->counts();
        if ($counts['pending'] !== 1) {
            throw new Exception('expected pending=1');
        }
        echo "PASS\n";
    }

    public function testProcessSucceeds(): void
    {
        echo "  Testing processDue delivers and marks succeeded... ";
        $this->reset();
        $sent = [];
        $dispatcher = new WebhookDispatcher($this->db, function (string $url, array $payload) use (&$sent): array {
            $sent[] = $payload;
            return ['success' => true, 'http_code' => 200, 'error' => null];
        });
        $this->db->insert('webhooks', [
            'user_id' => 1,
            'url' => 'https://example.test/ok',
            'events' => json_encode(['deal.created']),
            'is_active' => 1,
        ]);
        $queue = new WebhookQueue($this->db, $dispatcher);
        $queue->enqueue('deal.created', ['deal' => ['id' => 9]]);
        $stats = $queue->processDue(10);
        if ($stats['succeeded'] !== 1 || count($sent) !== 1) {
            throw new Exception('expected one success delivery');
        }
        if (($sent[0]['event'] ?? '') !== 'deal.created') {
            throw new Exception('bad payload event');
        }
        if ($queue->counts()['succeeded'] !== 1) {
            throw new Exception('expected succeeded count 1');
        }
        echo "PASS\n";
    }

    public function testRetryThenDeadLetter(): void
    {
        echo "  Testing failures requeue then dead-letter... ";
        $this->reset();
        $dispatcher = new WebhookDispatcher($this->db, function (): array {
            return ['success' => false, 'http_code' => 500, 'error' => 'boom'];
        });
        $this->db->insert('webhooks', [
            'user_id' => 1,
            'url' => 'https://example.test/fail',
            'events' => json_encode(['contact.updated']),
            'is_active' => 1,
        ]);
        $queue = new WebhookQueue($this->db, $dispatcher);
        $queue->enqueue('contact.updated', ['contact' => ['id' => 2]]);

        // Force max_attempts=2 for speed
        $row = $this->db->fetchOne('SELECT id FROM webhook_delivery_queue LIMIT 1');
        $this->db->update('webhook_delivery_queue', ['max_attempts' => 2], 'id = ?', [(int) $row['id']]);

        $s1 = $queue->processDue(10);
        if ($s1['requeued'] !== 1) {
            throw new Exception('expected requeue after first fail');
        }
        // Make due now
        $this->db->update(
            'webhook_delivery_queue',
            ['next_attempt_at' => date('Y-m-d H:i:s', time() - 10)],
            'id = ?',
            [(int) $row['id']]
        );
        $s2 = $queue->processDue(10);
        if ($s2['dead'] !== 1) {
            throw new Exception('expected dead-letter on second fail');
        }
        if ($queue->counts()['dead'] !== 1) {
            throw new Exception('dead count mismatch');
        }
        echo "PASS\n";
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    (new WebhookQueueTest())->runAllTests();
}

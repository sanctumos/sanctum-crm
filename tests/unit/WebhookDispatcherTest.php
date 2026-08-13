<?php
/**
 * WebhookDispatcher unit tests
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/includes/WebhookDispatcher.php';

class WebhookDispatcherTest
{
    private Database $db;

    /** @var list<array{url:string,payload:array}> */
    private array $sent = [];

    public function __construct()
    {
        $this->db = TestUtils::getTestDatabase();
    }

    public function runAllTests(): void
    {
        echo "Running WebhookDispatcher Unit Tests...\n";
        $this->testDispatchesSubscribedEvent();
        $this->testSkipsInactiveWebhook();
        $this->testSkipsUnsubscribedEvent();
        echo "All WebhookDispatcher tests completed!\n";
    }

    private function resetWebhooks(): void
    {
        $this->db->query('DELETE FROM webhooks');
    }

    public function testDispatchesSubscribedEvent(): void
    {
        echo "  Testing dispatch to subscribed webhook... ";
        $this->resetWebhooks();
        $this->sent = [];

        $webhookId = $this->db->insert('webhooks', [
            'user_id' => 1,
            'url' => 'https://example.test/hook',
            'events' => json_encode(['contact.created']),
            'is_active' => 1,
        ]);

        $dispatcher = new WebhookDispatcher($this->db, function (string $url, array $payload): array {
            $this->sent[] = ['url' => $url, 'payload' => $payload];
            return ['success' => true, 'http_code' => 200, 'error' => null];
        });

        $stats = $dispatcher->dispatch('contact.created', ['contact' => ['id' => 99, 'email' => 'a@b.com']]);

        $ok = $webhookId
            && $stats['succeeded'] >= 1
            && count($this->sent) === 1
            && $this->sent[0]['url'] === 'https://example.test/hook'
            && ($this->sent[0]['payload']['event'] ?? '') === 'contact.created'
            && ($this->sent[0]['payload']['data']['contact']['id'] ?? null) === 99;

        echo $ok ? "PASS\n" : "FAIL\n";
    }

    public function testSkipsInactiveWebhook(): void
    {
        echo "  Testing inactive webhook skipped... ";
        $this->resetWebhooks();
        $this->sent = [];

        $this->db->insert('webhooks', [
            'user_id' => 1,
            'url' => 'https://example.test/inactive',
            'events' => json_encode(['contact.created']),
            'is_active' => 0,
        ]);

        $dispatcher = new WebhookDispatcher($this->db, function (string $url, array $payload): array {
            $this->sent[] = ['url' => $url, 'payload' => $payload];
            return ['success' => true, 'http_code' => 200, 'error' => null];
        });

        $stats = $dispatcher->dispatch('contact.created', ['contact' => ['id' => 1]]);

        echo ($stats['attempted'] === 0 && count($this->sent) === 0) ? "PASS\n" : "FAIL\n";
    }

    public function testSkipsUnsubscribedEvent(): void
    {
        echo "  Testing unsubscribed event skipped... ";
        $this->resetWebhooks();
        $this->sent = [];

        $this->db->insert('webhooks', [
            'user_id' => 1,
            'url' => 'https://example.test/wrong-event',
            'events' => json_encode(['deal.created']),
            'is_active' => 1,
        ]);

        $dispatcher = new WebhookDispatcher($this->db, function (string $url, array $payload): array {
            $this->sent[] = ['url' => $url, 'payload' => $payload];
            return ['success' => true, 'http_code' => 200, 'error' => null];
        });

        $stats = $dispatcher->dispatch('contact.created', ['contact' => ['id' => 1]]);

        echo ($stats['attempted'] === 0 && count($this->sent) === 0) ? "PASS\n" : "FAIL\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    (new WebhookDispatcherTest())->runAllTests();
}

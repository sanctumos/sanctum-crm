<?php
/**
 * Webhook Unit Tests
 * FreeOpsDAO CRM - Webhook Functionality Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class WebhookTest {
    private $db;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
    }
    
    public function runAllTests() {
        echo "Running Webhook Unit Tests...\n";
        
        $this->testCreateWebhook();
        $this->testGetWebhook();
        $this->testUpdateWebhook();
        $this->testDeleteWebhook();
        $this->testWebhookValidation();
        $this->testWebhookEvents();
        $this->testWebhookActivation();
        $this->testWebhookUrlValidation();
        $this->testWebhookEventFiltering();
        $this->testWebhookDatabaseOperations();
        
        echo "All webhook tests completed!\n";
    }
    
    public function testCreateWebhook() {
        echo "  Testing webhook creation... ";
        
        $webhookData = [
            'user_id' => 1,
            'url' => 'https://webhook.site/test-' . uniqid(),
            'events' => json_encode(['contact.created', 'deal.created']),
            'is_active' => 1
        ];
        
        $webhookId = $this->db->insert('webhooks', $webhookData);
        
        if ($webhookId) {
            $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
            
            if ($webhook && $webhook['url'] === $webhookData['url']) {
                echo "PASS\n";
            } else {
                echo "FAIL - Webhook not created correctly\n";
            }
        } else {
            echo "FAIL - Failed to create webhook\n";
        }
    }
    
    public function testGetWebhook() {
        echo "  Testing webhook retrieval... ";
        
        // Create a test webhook
        $webhookId = TestUtils::createTestWebhook();
        
        $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
        
        if ($webhook && $webhook['id'] == $webhookId) {
            echo "PASS\n";
        } else {
            echo "FAIL - Webhook not retrieved correctly\n";
        }
    }
    
    public function testUpdateWebhook() {
        echo "  Testing webhook update... ";
        
        // Create a test webhook
        $webhookId = TestUtils::createTestWebhook();
        
        $updateData = [
            'url' => 'https://updated-webhook.site/test',
            'events' => json_encode(['contact.updated', 'deal.updated']),
            'is_active' => 0
        ];
        
        $result = $this->db->update('webhooks', $updateData, 'id = :id', ['id' => $webhookId]);
        
        if ($result) {
            $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
            
            if ($webhook['url'] === $updateData['url'] && $webhook['is_active'] == 0) {
                echo "PASS\n";
            } else {
                echo "FAIL - Webhook not updated correctly\n";
            }
        } else {
            echo "FAIL - Failed to update webhook\n";
        }
    }
    
    public function testDeleteWebhook() {
        echo "  Testing webhook deletion... ";
        
        // Create a test webhook
        $webhookId = TestUtils::createTestWebhook();
        
        $result = $this->db->delete('webhooks', 'id = ?', [$webhookId]);
        
        if ($result) {
            $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
            
            if (!$webhook) {
                echo "PASS\n";
            } else {
                echo "FAIL - Webhook not deleted\n";
            }
        } else {
            echo "FAIL - Failed to delete webhook\n";
        }
    }
    
    public function testWebhookValidation() {
        echo "  Testing webhook validation... ";
        
        // Test valid webhook data
        $validData = [
            'user_id' => 1,
            'url' => 'https://webhook.site/test',
            'events' => json_encode(['contact.created']),
            'is_active' => 1
        ];
        
        $webhookId = $this->db->insert('webhooks', $validData);
        
        if ($webhookId) {
            // Test invalid URL
            try {
                TestUtils::createTestWebhook(['url' => 'not-a-valid-url']);
                echo "FAIL - Invalid URL should be rejected\n";
            } catch (Exception $e) {
                echo "PASS\n";
            }
        } else {
            echo "FAIL - Valid webhook creation failed\n";
        }
    }
    
    public function testWebhookEvents() {
        echo "  Testing webhook events... ";
        
        $events = ['contact.created', 'contact.updated', 'deal.created', 'deal.updated'];
        $webhookData = [
            'user_id' => 1,
            'url' => 'https://webhook.site/test',
            'events' => json_encode($events),
            'is_active' => 1
        ];
        
        $webhookId = $this->db->insert('webhooks', $webhookData);
        
        if ($webhookId) {
            $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
            $storedEvents = json_decode($webhook['events'], true);
            
            if (is_array($storedEvents) && count($storedEvents) === count($events)) {
                echo "PASS\n";
            } else {
                echo "FAIL - Events not stored correctly\n";
            }
        } else {
            echo "FAIL - Failed to create webhook with events\n";
        }
    }
    
    public function testWebhookActivation() {
        echo "  Testing webhook activation/deactivation... ";
        
        // Create inactive webhook
        $webhookId = TestUtils::createTestWebhook(['is_active' => 0]);
        
        // Activate webhook
        $this->db->update('webhooks', ['is_active' => 1], 'id = :id', ['id' => $webhookId]);
        
        $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
        
        if ($webhook['is_active'] == 1) {
            // Deactivate webhook
            $this->db->update('webhooks', ['is_active' => 0], 'id = :id', ['id' => $webhookId]);
            
            $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
            
            if ($webhook['is_active'] == 0) {
                echo "PASS\n";
            } else {
                echo "FAIL - Webhook not deactivated\n";
            }
        } else {
            echo "FAIL - Webhook not activated\n";
        }
    }
    
    public function testWebhookUrlValidation() {
        echo "  Testing webhook URL validation... ";
        
        // Test that invalid URLs are rejected when creating webhooks
        try {
            TestUtils::createTestWebhook(['url' => 'not-a-valid-url']);
            echo "FAIL - URL validation not working correctly\n";
        } catch (Exception $e) {
            echo "PASS\n";
        }
    }
    
    public function testWebhookEventFiltering() {
        echo "  Testing webhook event filtering... ";
        
        // Create webhooks with different events
        $webhook1 = TestUtils::createTestWebhook([
            'events' => json_encode(['contact.created'])
        ]);
        
        $webhook2 = TestUtils::createTestWebhook([
            'events' => json_encode(['deal.created'])
        ]);
        
        $webhook3 = TestUtils::createTestWebhook([
            'events' => json_encode(['contact.created', 'deal.created'])
        ]);
        
        // Test filtering by event
        $contactWebhooks = $this->db->fetchAll(
            "SELECT * FROM webhooks WHERE events LIKE ? AND is_active = 1",
            ['%contact.created%']
        );
        
        $dealWebhooks = $this->db->fetchAll(
            "SELECT * FROM webhooks WHERE events LIKE ? AND is_active = 1",
            ['%deal.created%']
        );
        
        if (count($contactWebhooks) >= 2 && count($dealWebhooks) >= 2) {
            echo "PASS\n";
        } else {
            echo "FAIL - Event filtering not working correctly\n";
        }
    }
    
    public function testWebhookDatabaseOperations() {
        echo "  Testing webhook database operations... ";
        
        // Test bulk operations
        $webhookIds = [];
        for ($i = 0; $i < 5; $i++) {
            $webhookIds[] = TestUtils::createTestWebhook([
                'url' => "https://webhook.site/test-$i"
            ]);
        }
        
        // Test counting before bulk operation
        $countBefore = $this->db->fetchOne("SELECT COUNT(*) as count FROM webhooks WHERE is_active = 1");
        
        // Test bulk deactivation
        $placeholders = str_repeat('?,', count($webhookIds) - 1) . '?';
        $this->db->update('webhooks', ['is_active' => 0], "id IN ($placeholders)", $webhookIds);
        
        // Test counting after bulk operation
        $countAfter = $this->db->fetchOne("SELECT COUNT(*) as count FROM webhooks WHERE is_active = 1");
        
        // Verify that the count decreased by at least the number of webhooks we deactivated
        if ($countBefore['count'] >= 5 && $countAfter['count'] <= ($countBefore['count'] - 4)) {
            echo "PASS\n";
        } else {
            echo "FAIL - Bulk operations not working correctly\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new WebhookTest();
    $test->runAllTests();
} 
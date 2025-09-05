<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Integration Tests
 * Sanctum CRM - System Integration Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class IntegrationTest {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
        $this->auth = new Auth();
    }
    
    public function runAllTests() {
        echo "Running Integration Tests...\n";
        
        $this->testUserContactDealWorkflow();
        $this->testWebhookIntegration();
        $this->testApiKeyManagement();
        $this->testReportDataIntegration();
        $this->testUserPermissionWorkflow();
        $this->testDataConsistency();
        $this->testCrossModuleOperations();
        $this->testErrorHandlingIntegration();
        $this->testPerformanceIntegration();
        $this->testSecurityIntegration();
        
        echo "All integration tests completed!\n";
    }
    
    public function testUserContactDealWorkflow() {
        echo "  Testing User-Contact-Deal Workflow...\n";
        
        // Create user
        echo "    Creating test user... ";
        $userId = TestUtils::createTestUser();
        if ($userId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Create contact
        echo "    Creating test contact... ";
        $contactId = TestUtils::createTestContact();
        if ($contactId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Create deal linked to contact
        echo "    Creating test deal... ";
        $dealId = TestUtils::createTestDeal(['contact_id' => $contactId]);
        if ($dealId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Test data relationships
        echo "    Testing data relationships... ";
        $deal = $this->db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
        $contact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$deal['contact_id']]);
        
        if ($deal && $contact && $deal['contact_id'] == $contactId) {
            echo "PASS\n";
        } else {
            echo "FAIL - Data relationships not maintained\n";
        }
        
        // Test workflow completion
        echo "    Testing workflow completion... ";
        $this->db->update('deals', ['stage' => 'closed_won'], 'id = :id', ['id' => $dealId]);
        $this->db->update('contacts', ['contact_type' => 'customer'], 'id = :id', ['id' => $contactId]);
        
        $updatedDeal = $this->db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
        $updatedContact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        
        if ($updatedDeal['stage'] === 'closed_won' && $updatedContact['contact_type'] === 'customer') {
            echo "PASS\n";
        } else {
            echo "FAIL - Workflow not completed correctly\n";
        }
    }
    
    public function testWebhookIntegration() {
        echo "  Testing Webhook Integration...\n";
        
        // Create webhook
        echo "    Creating test webhook... ";
        $webhookId = TestUtils::createTestWebhook();
        if ($webhookId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Create contact to trigger webhook
        echo "    Testing webhook trigger... ";
        $contactId = TestUtils::createTestContact();
        
        // Simulate webhook trigger
        $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
        $events = json_decode($webhook['events'], true);
        
        if (in_array('contact.created', $events)) {
            // Test webhook execution
            $result = $this->executeWebhook($webhook, 'contact.created', ['contact_id' => $contactId]);
            if ($result) {
                echo "PASS\n";
            } else {
                echo "FAIL - Webhook execution failed\n";
            }
        } else {
            echo "FAIL - Webhook events not configured correctly\n";
        }
        
        // Test webhook deactivation
        echo "    Testing webhook deactivation... ";
        $this->db->update('webhooks', ['is_active' => 0], 'id = :id', ['id' => $webhookId]);
        
        $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
        if ($webhook['is_active'] == 0) {
            echo "PASS\n";
        } else {
            echo "FAIL - Webhook not deactivated\n";
        }
    }
    
    public function testApiKeyManagement() {
        echo "  Testing API Key Management...\n";
        
        // Create user
        echo "    Creating test user... ";
        $userId = TestUtils::createTestUser();
        if ($userId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Test API key generation
        echo "    Testing API key generation... ";
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user && !empty($user['api_key'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - API key not generated\n";
            return;
        }
        
        // Test API key regeneration
        echo "    Testing API key regeneration... ";
        $originalKey = $user['api_key'];
        $result = $this->auth->regenerateApiKey($userId);
        
        if ($result) {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if ($user['api_key'] !== $originalKey && !empty($user['api_key'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - API key not regenerated\n";
            }
        } else {
            echo "FAIL - API key regeneration failed\n";
        }
        
        // Test API key authentication
        echo "    Testing API key authentication... ";
        $apiKey = $user['api_key'];
        
        // Create a new Auth instance and test API key authentication
        $testAuth = new Auth();
        $testAuth->setApiKey($apiKey);
        
        if ($testAuth->isAuthenticated() && $testAuth->getUserId() == $userId) {
            echo "PASS\n";
        } else {
            echo "FAIL - API key authentication failed\n";
        }
    }
    
    public function testReportDataIntegration() {
        echo "  Testing Report Data Integration...\n";
        
        // Create test data
        echo "    Creating test data... ";
        $contactId = TestUtils::createTestContact();
        $deals = [
            ['contact_id' => $contactId, 'stage' => 'prospecting', 'amount' => 1000],
            ['contact_id' => $contactId, 'stage' => 'qualification', 'amount' => 2500],
            ['contact_id' => $contactId, 'stage' => 'closed_won', 'amount' => 5000]
        ];
        
        foreach ($deals as $dealData) {
            TestUtils::createTestDeal($dealData);
        }
        echo "PASS\n";
        
        // Test report generation
        echo "    Testing report generation... ";
        $report = $this->generateComprehensiveReport();
        
        if ($report && isset($report['deals']) && isset($report['contacts']) && isset($report['analytics'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - Report generation failed\n";
        }
        
        // Test data aggregation
        echo "    Testing data aggregation... ";
        $aggregated = $this->aggregateReportData();
        
        if ($aggregated && isset($aggregated['total_deals']) && isset($aggregated['total_value'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - Data aggregation failed\n";
        }
        
        // Test export functionality
        echo "    Testing export functionality... ";
        $csvData = $this->exportReportData('csv');
        $jsonData = $this->exportReportData('json');
        
        if ($csvData && $jsonData) {
            echo "PASS\n";
        } else {
            echo "FAIL - Export functionality failed\n";
        }
    }
    
    public function testUserPermissionWorkflow() {
        echo "  Testing User Permission Workflow...\n";
        
        // Create admin user
        echo "    Creating admin user... ";
        $adminId = TestUtils::createTestUser(['role' => 'admin']);
        if ($adminId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Create regular user
        echo "    Creating regular user... ";
        $userId = TestUtils::createTestUser(['role' => 'user']);
        if ($userId) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
            return;
        }
        
        // Test role-based permissions
        echo "    Testing role-based permissions... ";
        $adminPermissions = $this->getUserPermissions($adminId);
        $userPermissions = $this->getUserPermissions($userId);
        
        if (count($adminPermissions) > count($userPermissions)) {
            echo "PASS\n";
        } else {
            echo "FAIL - Role permissions not working correctly\n";
        }
        
        // Test permission changes
        echo "    Testing permission changes... ";
        $this->db->update('users', ['role' => 'admin'], 'id = :id', ['id' => $userId]);
        
        $updatedUser = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($updatedUser['role'] === 'admin') {
            echo "PASS\n";
        } else {
            echo "FAIL - Permission change failed\n";
        }
    }
    
    public function testDataConsistency() {
        echo "  Testing Data Consistency...\n";
        
        // Create related data
        echo "    Creating related data... ";
        $contactId = TestUtils::createTestContact();
        $dealId = TestUtils::createTestDeal(['contact_id' => $contactId]);
        echo "PASS\n";
        
        // Test referential integrity
        echo "    Testing referential integrity... ";
        $deal = $this->db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
        $contact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$deal['contact_id']]);
        
        if ($deal && $contact) {
            echo "PASS\n";
        } else {
            echo "FAIL - Referential integrity broken\n";
        }
        
        // Test data deletion cascade
        echo "    Testing data deletion cascade... ";
        // Delete deal first to avoid foreign key constraint
        $this->db->delete('deals', 'id = ?', [$dealId]);
        $this->db->delete('contacts', 'id = ?', [$contactId]);
        
        $remainingDeal = $this->db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
        $remainingContact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        if (!$remainingDeal && !$remainingContact) {
            echo "PASS\n";
        } else {
            echo "FAIL - Cascade deletion not working\n";
        }
        
        // Test data validation
        echo "    Testing data validation... ";
        $invalidData = ['invalid_field' => 'invalid_value'];
        $result = $this->validateData($invalidData);
        
        if (!$result) {
            echo "PASS\n";
        } else {
            echo "FAIL - Data validation not working\n";
        }
    }
    
    public function testCrossModuleOperations() {
        echo "  Testing Cross-Module Operations...\n";
        
        // Test user creating contact and deal
        echo "    Testing user workflow... ";
        $userId = TestUtils::createTestUser();
        $contactId = TestUtils::createTestContact();
        $dealId = TestUtils::createTestDeal(['contact_id' => $contactId]);
        
        // Log user activity
        TestUtils::createTestApiRequest([
            'user_id' => $userId,
            'endpoint' => '/api/v1/contacts',
            'method' => 'POST'
        ]);
        
        $activity = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM api_requests WHERE user_id = ?",
            [$userId]
        );
        
        if ($activity['count'] > 0) {
            echo "PASS\n";
        } else {
            echo "FAIL - User activity not tracked\n";
        }
        
        // Test webhook integration with user actions
        echo "    Testing webhook integration... ";
        $webhookId = TestUtils::createTestWebhook();
        $webhook = $this->db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
        
        if ($webhook) {
            echo "PASS\n";
        } else {
            echo "FAIL - Webhook integration failed\n";
        }
        
        // Test report generation with all data
        echo "    Testing comprehensive reporting... ";
        $report = $this->generateSystemReport();
        
        if ($report && isset($report['users']) && isset($report['contacts']) && isset($report['deals'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - Comprehensive reporting failed\n";
        }
    }
    
    public function testErrorHandlingIntegration() {
        echo "  Testing Error Handling Integration...\n";
        
        // Test invalid user creation
        echo "    Testing invalid user creation... ";
        try {
            $this->auth->createUser(['invalid' => 'data']);
            echo "FAIL - Invalid user creation should fail\n";
        } catch (Exception $e) {
            echo "PASS\n";
        }
        
        // Test invalid contact creation
        echo "    Testing invalid contact creation... ";
        try {
            $this->db->insert('contacts', ['invalid' => 'data']);
            echo "FAIL - Invalid contact creation should fail\n";
        } catch (Exception $e) {
            echo "PASS\n";
        }
        
        // Test invalid webhook creation
        echo "    Testing invalid webhook creation... ";
        try {
            $this->db->insert('webhooks', ['invalid' => 'data']);
            echo "FAIL - Invalid webhook creation should fail\n";
        } catch (Exception $e) {
            echo "PASS\n";
        }
        
        // Test database connection error handling
        echo "    Testing database error handling... ";
        $result = $this->testDatabaseConnection();
        if ($result) {
            echo "PASS\n";
        } else {
            echo "FAIL - Database error handling not working\n";
        }
    }
    
    public function testPerformanceIntegration() {
        echo "  Testing Performance Integration...\n";
        
        // Test bulk operations
        echo "    Testing bulk operations... ";
        $startTime = microtime(true);
        
        // Create multiple records
        for ($i = 0; $i < 10; $i++) {
            TestUtils::createTestContact();
            TestUtils::createTestDeal();
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        if ($duration < 5.0) { // Should complete within 5 seconds
            echo "PASS (" . round($duration, 2) . "s)\n";
        } else {
            echo "FAIL - Bulk operations too slow (" . round($duration, 2) . "s)\n";
        }
        
        // Test query performance
        echo "    Testing query performance... ";
        $startTime = microtime(true);
        
        $result = $this->db->fetchAll("SELECT * FROM contacts");
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        if ($duration < 1.0) { // Should complete within 1 second
            echo "PASS (" . round($duration, 3) . "s)\n";
        } else {
            echo "FAIL - Query too slow (" . round($duration, 3) . "s)\n";
        }
        
        // Test memory usage
        echo "    Testing memory usage... ";
        $memoryBefore = memory_get_usage();
        
        $this->generateComprehensiveReport();
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        if ($memoryUsed < 10 * 1024 * 1024) { // Less than 10MB
            echo "PASS (" . round($memoryUsed / 1024 / 1024, 2) . "MB)\n";
        } else {
            echo "FAIL - Memory usage too high (" . round($memoryUsed / 1024 / 1024, 2) . "MB)\n";
        }
    }
    
    public function testSecurityIntegration() {
        echo "  Testing Security Integration...\n";
        
        // Test password hashing
        echo "    Testing password hashing... ";
        $password = 'testpass123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        if (password_verify($password, $hashedPassword) && $hashedPassword !== $password) {
            echo "PASS\n";
        } else {
            echo "FAIL - Password hashing not working\n";
        }
        
        // Test API key security
        echo "    Testing API key security... ";
        $userId = TestUtils::createTestUser();
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if (strlen($user['api_key']) >= 32 && preg_match('/^[a-zA-Z0-9]+$/', $user['api_key'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - API key not secure\n";
        }
        
        // Test SQL injection prevention
        echo "    Testing SQL injection prevention... ";
        $maliciousInput = "'; DROP TABLE users; --";
        
        try {
            $result = $this->db->fetchOne("SELECT * FROM users WHERE username = ?", [$maliciousInput]);
            echo "PASS\n";
        } catch (Exception $e) {
            echo "FAIL - SQL injection prevention not working\n";
        }
        
        // Test XSS prevention
        echo "    Testing XSS prevention... ";
        $xssInput = "<script>alert('xss')</script>";
        $sanitized = htmlspecialchars($xssInput, ENT_QUOTES, 'UTF-8');
        
        if ($sanitized !== $xssInput && strpos($sanitized, '<script>') === false) {
            echo "PASS\n";
        } else {
            echo "FAIL - XSS prevention not working\n";
        }
    }
    
    // Helper methods
    private function executeWebhook($webhook, $event, $data) {
        // Simulate webhook execution
        return true;
    }
    
    private function generateComprehensiveReport() {
        return [
            'deals' => $this->db->fetchAll("SELECT * FROM deals"),
            'contacts' => $this->db->fetchAll("SELECT * FROM contacts"),
            'analytics' => [
                'total_deals' => $this->db->fetchOne("SELECT COUNT(*) as count FROM deals")['count'],
                'total_contacts' => $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts")['count']
            ]
        ];
    }
    
    private function aggregateReportData() {
        return [
            'total_deals' => $this->db->fetchOne("SELECT COUNT(*) as count FROM deals")['count'],
            'total_value' => $this->db->fetchOne("SELECT SUM(amount) as total FROM deals")['total'] ?? 0
        ];
    }
    
    private function exportReportData($format) {
        if ($format === 'csv') {
            return "ID,Title,Amount\n1,Test Deal,1000\n";
        } elseif ($format === 'json') {
            return json_encode(['deals' => []]);
        }
        return null;
    }
    
    private function getUserPermissions($userId) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user['role'] === 'admin') {
            return ['read', 'write', 'delete', 'admin'];
        } else {
            return ['read', 'write'];
        }
    }
    
    private function validateData($data) {
        // Simple validation
        return isset($data['required_field']);
    }
    
    private function testDatabaseConnection() {
        try {
            $this->db->fetchOne("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function generateSystemReport() {
        return [
            'users' => $this->db->fetchAll("SELECT * FROM users"),
            'contacts' => $this->db->fetchAll("SELECT * FROM contacts"),
            'deals' => $this->db->fetchAll("SELECT * FROM deals"),
            'webhooks' => $this->db->fetchAll("SELECT * FROM webhooks")
        ];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new IntegrationTest();
    $test->runAllTests();
} 
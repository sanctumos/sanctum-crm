<?php
/**
 * Export API Test
 * Tests the contacts export API endpoint
 */

require_once __DIR__ . '/../bootstrap.php';

class ExportApiTest {
    private $baseUrl;
    private $apiKey;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8000'; // Adjust if needed
        $this->apiKey = $this->getTestApiKey();
    }
    
    private function getTestApiKey() {
        // Get or create a test user with API key
        $db = Database::getInstance();
        $auth = new Auth();
        
        // Create test user if it doesn't exist
        $testUser = $db->fetchOne("SELECT * FROM users WHERE username = 'export_test_user'");
        if (!$testUser) {
            $userId = $db->insert('users', [
                'username' => 'export_test_user',
                'email' => 'export@test.com',
                'password' => password_hash('testpass123', PASSWORD_DEFAULT),
                'is_active' => 1,
                'role' => 'admin',
                'api_key' => 'test_export_api_key_12345',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            $userId = $testUser['id'];
        }
        
        return 'test_export_api_key_12345';
    }
    
    public function runAllTests() {
        echo "Running Export API Tests...\n";
        
        $this->testExportWithoutFilters();
        $this->testExportWithTypeFilter();
        $this->testExportWithStatusFilter();
        $this->testExportWithEnrichmentFilter();
        $this->testExportAuthentication();
        
        echo "All Export API tests completed!\n";
    }
    
    private function testExportWithoutFilters() {
        echo "  Testing export without filters... ";
        
        // Create test contacts
        $this->createTestContacts();
        
        $url = $this->baseUrl . '/api/v1/contacts/export?format=csv';
        $response = $this->makeRequest('GET', $url);
        
        if ($response['code'] === 200 && strpos($response['body'], 'ID,First Name,Last Name') !== false) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
        
        $this->cleanupTestContacts();
    }
    
    private function testExportWithTypeFilter() {
        echo "  Testing export with type filter... ";
        
        // Create test contacts
        $this->createTestContacts();
        
        $url = $this->baseUrl . '/api/v1/contacts/export?format=csv&type=lead';
        $response = $this->makeRequest('GET', $url);
        
        if ($response['code'] === 200) {
            // Count lines in CSV (header + data rows)
            $lines = explode("\n", trim($response['body']));
            $dataRows = count($lines) - 1; // Subtract header
            
            if ($dataRows >= 1) { // Should have at least one lead
                echo "PASS\n";
            } else {
                echo "FAIL - No data returned\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
        
        $this->cleanupTestContacts();
    }
    
    private function testExportWithStatusFilter() {
        echo "  Testing export with status filter... ";
        
        // Create test contacts
        $this->createTestContacts();
        
        $url = $this->baseUrl . '/api/v1/contacts/export?format=csv&status=new';
        $response = $this->makeRequest('GET', $url);
        
        if ($response['code'] === 200) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
        
        $this->cleanupTestContacts();
    }
    
    private function testExportWithEnrichmentFilter() {
        echo "  Testing export with enrichment filter... ";
        
        // Create test contacts
        $this->createTestContacts();
        
        $url = $this->baseUrl . '/api/v1/contacts/export?format=csv&enrichment_status=enriched';
        $response = $this->makeRequest('GET', $url);
        
        if ($response['code'] === 200) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
        
        $this->cleanupTestContacts();
    }
    
    private function testExportAuthentication() {
        echo "  Testing export authentication... ";
        
        // Test without API key
        $url = $this->baseUrl . '/api/v1/contacts/export?format=csv';
        $response = $this->makeRequest('GET', $url, []);
        
        if ($response['code'] === 401) {
            echo "PASS\n";
        } else {
            echo "FAIL - Authentication not enforced\n";
        }
    }
    
    private function createTestContacts() {
        $db = Database::getInstance();
        
        $testContacts = [
            [
                'first_name' => 'API',
                'last_name' => 'Test1',
                'email' => 'api1@exporttest.com',
                'contact_type' => 'lead',
                'contact_status' => 'new',
                'enrichment_status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'first_name' => 'API',
                'last_name' => 'Test2',
                'email' => 'api2@exporttest.com',
                'contact_type' => 'customer',
                'contact_status' => 'active',
                'enrichment_status' => 'enriched',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Clear existing test contacts
        $db->delete('contacts', "email LIKE '%@exporttest.com'");
        
        // Insert test contacts
        foreach ($testContacts as $contact) {
            $db->insert('contacts', $contact);
        }
    }
    
    private function cleanupTestContacts() {
        $db = Database::getInstance();
        $db->delete('contacts', "email LIKE '%@exporttest.com'");
    }
    
    private function makeRequest($method, $url, $headers = null) {
        if ($headers === null) {
            $headers = [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['code' => 0, 'body' => $error];
        }
        
        return ['code' => $httpCode, 'body' => $response];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ExportApiTest();
    $test->runAllTests();
}
?>

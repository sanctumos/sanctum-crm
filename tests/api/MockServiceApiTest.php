<?php
/**
 * Mock Service API Tests
 * Best Jobs in TA - Testing API behavior when RocketReach is not configured
 */

require_once __DIR__ . '/../bootstrap.php';

class MockServiceApiTest {
    private $baseUrl;
    private $apiKey;
    private $headers;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8000';
        
        // Get admin API key from production database
        $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
        $admin = $prodDb->querySingle("SELECT api_key FROM users WHERE username = 'admin'", true);
        $this->apiKey = $admin['api_key'] ?? null;
        $prodDb->close();
        
        if (!$this->apiKey) {
            echo "    WARNING: No admin API key found in production database\n";
            echo "    Skipping API tests that require authentication\n";
        }
        
        $this->headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
    }
    
    public function runAllTests() {
        echo "Running Mock Service API Tests...\n";
        
        if (!$this->apiKey) {
            echo "FAIL - No API key available for testing\n";
            return;
        }
        
        // Ensure we're using mock service (no API key configured)
        $this->ensureMockServiceMode();
        
        $this->testIndividualEnrichmentReturnsError();
        $this->testBulkEnrichmentReturnsError();
        $this->testEnrichmentStatsShowsNotConfigured();
        $this->testEnrichmentStatusStillWorks();
        $this->testErrorMessagesAreHelpful();
        
        echo "All Mock Service API tests completed!\n";
    }
    
    private function ensureMockServiceMode() {
        echo "  Ensuring mock service mode (no API key)... ";
        
        try {
            // Clear any existing RocketReach configuration
            $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
            $prodDb->exec("UPDATE settings SET rocketreach_enabled = 0, rocketreach_api_key = '' WHERE id = 1");
            $prodDb->close();
            echo "PASS\n";
        } catch (Exception $e) {
            echo "FAIL - Could not ensure mock mode: " . $e->getMessage() . "\n";
        }
    }
    
    public function testIndividualEnrichmentReturnsError() {
        echo "  Testing individual enrichment returns error... ";
        
        try {
            // Create a test contact
            $contactId = TestUtils::createTestContact([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com'
            ]);
            
            // Test enrichment endpoint
            $url = $this->baseUrl . "/api/v1/contacts/$contactId/enrich";
            $response = $this->makeRequest('POST', $url, ['strategy' => 'auto']);
            
            if ($response['code'] === 500) {
                $data = json_decode($response['body'], true);
                if (isset($data['error']) && strpos($data['error'], 'RocketReach API key not configured') !== false) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Wrong error message: " . ($data['error'] ?? 'No error message') . "\n";
                }
            } else {
                echo "FAIL - Expected 500, got " . $response['code'] . ": " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testBulkEnrichmentReturnsError() {
        echo "  Testing bulk enrichment returns error... ";
        
        try {
            // Create test contacts
            $contactIds = [];
            for ($i = 0; $i < 3; $i++) {
                $contactIds[] = TestUtils::createTestContact([
                    'first_name' => "Bulk$i",
                    'last_name' => 'Test',
                    'email' => "bulk$i@test.com"
                ]);
            }
            
            // Test bulk enrichment endpoint
            $url = $this->baseUrl . "/api/v1/contacts/bulk-enrich";
            $response = $this->makeRequest('POST', $url, [
                'contact_ids' => $contactIds,
                'strategy' => 'auto'
            ]);
            
            if ($response['code'] === 500) {
                $data = json_decode($response['body'], true);
                if (isset($data['error']) && strpos($data['error'], 'RocketReach API key not configured') !== false) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Wrong error message: " . ($data['error'] ?? 'No error message') . "\n";
                }
            } else {
                echo "FAIL - Expected 500, got " . $response['code'] . ": " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStatsShowsNotConfigured() {
        echo "  Testing enrichment stats shows not configured... ";
        
        try {
            $url = $this->baseUrl . "/api/v1/enrichment/stats";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $data = json_decode($response['body'], true);
                if (isset($data['api_configured']) && $data['api_configured'] === false) {
                    if (isset($data['message']) && strpos($data['message'], 'RocketReach API key not configured') !== false) {
                        echo "PASS\n";
                    } else {
                        echo "FAIL - Missing or wrong message in stats\n";
                    }
                } else {
                    echo "FAIL - api_configured should be false\n";
                }
            } else {
                echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStatusStillWorks() {
        echo "  Testing enrichment status still works... ";
        
        try {
            // Create a test contact
            $contactId = TestUtils::createTestContact([
                'first_name' => 'Status',
                'last_name' => 'Test',
                'email' => 'status@test.com'
            ]);
            
            $url = $this->baseUrl . "/api/v1/contacts/$contactId/enrichment-status";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $data = json_decode($response['body'], true);
                $requiredKeys = ['status', 'attempts', 'last_error', 'enriched_at', 'source'];
                $hasAllKeys = true;
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $data)) {
                        $hasAllKeys = false;
                        break;
                    }
                }
                
                if ($hasAllKeys) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Missing required keys in response\n";
                }
            } else {
                echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testErrorMessagesAreHelpful() {
        echo "  Testing error messages are helpful... ";
        
        try {
            // Create a test contact
            $contactId = TestUtils::createTestContact([
                'first_name' => 'Error',
                'last_name' => 'Test',
                'email' => 'error@test.com'
            ]);
            
            $url = $this->baseUrl . "/api/v1/contacts/$contactId/enrich";
            $response = $this->makeRequest('POST', $url, ['strategy' => 'auto']);
            
            if ($response['code'] === 500) {
                $data = json_decode($response['body'], true);
                if (isset($data['error'])) {
                    $message = $data['error'];
                    
                    $hasApiKey = strpos($message, 'API key') !== false;
                    $hasConfigured = strpos($message, 'configured') !== false;
                    $hasSettings = strpos($message, 'Settings') !== false;
                    $hasEnrichment = strpos($message, 'enrichment') !== false;
                    
                    if ($hasApiKey && $hasConfigured && $hasSettings && $hasEnrichment) {
                        echo "PASS\n";
                    } else {
                        echo "FAIL - Error message not helpful enough: " . $message . "\n";
                    }
                } else {
                    echo "FAIL - No error message in response\n";
                }
            } else {
                echo "FAIL - Expected 500, got " . $response['code'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new MockServiceApiTest();
    $test->runAllTests();
}
?>

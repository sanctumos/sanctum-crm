<?php
/**
 * Enrichment API Tests
 * Best Jobs in TA - Enrichment API Endpoint Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class EnrichmentApiTest {
    private $baseUrl;
    private $apiKey;
    private $headers;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8181';
        
        // Get admin API key from production database (same as server)
        $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
        $admin = $prodDb->querySingle("SELECT api_key FROM users WHERE username = 'admin'", true);
        $this->apiKey = $admin['api_key'] ?? null;
        $prodDb->close();
        
        if (!$this->apiKey) {
            echo "    WARNING: No API key available for testing\n";
        }
        
        $this->headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
    }
    
    public function runAllTests() {
        echo "Running Enrichment API Tests...\n";
        
        if (!$this->apiKey) {
            echo "FAIL - No API key available for testing\n";
            return;
        }
        
        $this->testIndividualEnrichment();
        $this->testEnrichmentStatus();
        $this->testBulkEnrichment();
        $this->testEnrichmentStats();
        $this->testEnrichmentErrorHandling();
        $this->testEnrichmentAuthentication();
        
        echo "All Enrichment API tests completed!\n";
    }
    
    public function testIndividualEnrichment() {
        echo "  Testing individual contact enrichment... ";
        
        try {
            // Use an existing contact from production database
            $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
            $contact = $prodDb->querySingle("SELECT id FROM contacts ORDER BY id DESC LIMIT 1", true);
            $prodDb->close();
            
            if (!$contact) {
                echo "FAIL - No contacts found in production database\n";
                return;
            }
            
            $contactId = $contact['id'];
            
            // Test enrichment endpoint
            $url = $this->baseUrl . "/api/v1/contacts/$contactId/enrich";
            $response = $this->makeRequest('POST', $url, ['strategy' => 'auto']);
            
            // Check if we're in mock mode (no API key configured)
            if ($response['code'] === 500) {
                $data = json_decode($response['body'], true);
                if (isset($data['error']) && strpos($data['error'], 'RocketReach API key not configured') !== false) {
                    echo "PASS (Mock mode - API key not configured)\n";
                } else {
                    echo "FAIL - Wrong error message: " . ($data['error'] ?? 'No error message') . "\n";
                }
            } elseif ($response['code'] === 200) {
                $data = json_decode($response['body'], true);
                if (isset($data['success']) && $data['success'] === true) {
                    echo "PASS (Real mode - API key configured)\n";
                } else {
                    echo "FAIL - Invalid response structure\n";
                }
            } else {
                echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStatus() {
        echo "  Testing enrichment status endpoint... ";
        
        try {
            // Create test contact
            $contactId = TestUtils::createTestContact([
                'first_name' => 'Status',
                'last_name' => 'Test',
                'email' => 'status@test.com'
            ]);
            
            // Test status endpoint
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
    
    public function testBulkEnrichment() {
        echo "  Testing bulk enrichment endpoint... ";
        
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
            
            // Check if we're in mock mode (no API key configured)
            if ($response['code'] === 500) {
                $data = json_decode($response['body'], true);
                if (isset($data['error']) && strpos($data['error'], 'RocketReach API key not configured') !== false) {
                    echo "PASS (Mock mode - API key not configured)\n";
                } else {
                    echo "FAIL - Wrong error message: " . ($data['error'] ?? 'No error message') . "\n";
                }
            } elseif ($response['code'] === 200) {
                $data = json_decode($response['body'], true);
                $requiredKeys = ['success', 'total_processed', 'successful', 'failed', 'enriched_contacts', 'errors'];
                $hasAllKeys = true;
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $data)) {
                        $hasAllKeys = false;
                        break;
                    }
                }
                
                if ($hasAllKeys && $data['total_processed'] === count($contactIds)) {
                    echo "PASS (Real mode - API key configured)\n";
                } else {
                    echo "FAIL - Invalid response structure\n";
                }
            } else {
                echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStats() {
        echo "  Testing enrichment statistics endpoint... ";
        
        try {
            $url = $this->baseUrl . "/api/v1/enrichment/stats";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $data = json_decode($response['body'], true);
                $requiredKeys = ['total_contacts', 'enriched_count', 'failed_count', 'pending_count', 'enrichment_rate'];
                $hasAllKeys = true;
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $data)) {
                        $hasAllKeys = false;
                        break;
                    }
                }
                
                if ($hasAllKeys) {
                    // Check if we're in mock mode
                    if (isset($data['api_configured']) && $data['api_configured'] === false) {
                        if (isset($data['message']) && strpos($data['message'], 'RocketReach API key not configured') !== false) {
                            echo "PASS (Mock mode - API key not configured)\n";
                        } else {
                            echo "FAIL - Missing or wrong message in mock mode\n";
                        }
                    } else {
                        echo "PASS (Real mode - API key configured)\n";
                    }
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
    
    public function testEnrichmentErrorHandling() {
        echo "  Testing enrichment error handling... ";
        
        try {
            // Test with non-existent contact
            $url = $this->baseUrl . "/api/v1/contacts/99999/enrich";
            $response = $this->makeRequest('POST', $url, ['strategy' => 'auto']);
            
            if ($response['code'] === 500) {
                // Expected for non-existent contact
                echo "PASS\n";
            } else {
                echo "FAIL - Expected 500 for non-existent contact, got " . $response['code'] . "\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentAuthentication() {
        echo "  Testing enrichment authentication... ";
        
        try {
            // Test without authentication
            $url = $this->baseUrl . "/api/v1/contacts/1/enrich";
            $response = $this->makeRequestWithoutAuth('POST', $url, ['strategy' => 'auto']);
            
            if ($response['code'] === 401) {
                echo "PASS\n";
            } else {
                echo "FAIL - Expected 401 without authentication, got " . $response['code'] . "\n";
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
    
    private function makeRequestWithoutAuth($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = ['Content-Type: application/json'];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
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
    $test = new EnrichmentApiTest();
    $test->runAllTests();
}
?>

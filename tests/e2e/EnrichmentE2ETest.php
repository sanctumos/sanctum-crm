<?php
/**
 * Enrichment E2E Tests
 * Best Jobs in TA - Enrichment UI Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class EnrichmentE2ETest {
    private $baseUrl;
    private $apiKey;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8181';
        // Get admin API key from production database
        $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
        $admin = $prodDb->querySingle("SELECT api_key FROM users WHERE username = 'admin'", true);
        $this->apiKey = $admin['api_key'] ?? null;
        $prodDb->close();
    }
    
    public function runAllTests() {
        echo "Running Enrichment E2E Tests...\n";
        
        if (!$this->apiKey) {
            echo "SKIP - No API key available for E2E testing\n";
            return;
        }
        
        $this->testContactPageEnrichmentButtons();
        $this->testViewContactPageEnrichment();
        $this->testBulkEnrichmentModal();
        $this->testEnrichmentStatusDisplay();
        $this->testDashboardEnrichmentStats();
        $this->testEnrichmentJavaScript();
        
        echo "All Enrichment E2E tests completed!\n";
    }
    
    public function testContactPageEnrichmentButtons() {
        echo "  Testing contact page enrichment buttons... ";
        
        try {
            $url = $this->baseUrl . "/index.php?page=contacts";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                if (strpos($response['body'], 'enrichContact(') !== false) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Enrichment buttons not found\n";
                }
            } else {
                echo "FAIL - HTTP {$response['code']}\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testViewContactPageEnrichment() {
        echo "  Testing view contact page enrichment... ";
        
        try {
            $url = $this->baseUrl . "/index.php?page=view_contact&id=516";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $hasEnrichButton = strpos($response['body'], 'enrichContact(') !== false;
                $hasStatusBadge = strpos($response['body'], 'fa-magic') !== false;
                $hasJavaScript = strpos($response['body'], 'function enrichContact') !== false;
                
                if ($hasEnrichButton && $hasStatusBadge && $hasJavaScript) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Missing enrichment UI elements\n";
                }
            } else {
                echo "FAIL - HTTP {$response['code']}\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testBulkEnrichmentModal() {
        echo "  Testing bulk enrichment modal... ";
        
        try {
            $url = $this->baseUrl . "/index.php?page=contacts";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $hasBulkButton = strpos($response['body'], 'bulkEnrichContacts()') !== false;
                $hasModal = strpos($response['body'], 'bulkEnrichModal') !== false;
                $hasStrategySelect = strpos($response['body'], 'bulkEnrichStrategy') !== false;
                $hasJavaScript = strpos($response['body'], 'function bulkEnrichContacts') !== false;
                
                if ($hasBulkButton && $hasModal && $hasStrategySelect && $hasJavaScript) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Missing bulk enrichment UI elements\n";
                }
            } else {
                echo "FAIL - HTTP {$response['code']}\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStatusDisplay() {
        echo "  Testing enrichment status display... ";
        
        try {
            $url = $this->baseUrl . "/index.php?page=contacts";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $hasEnrichedBadge = strpos($response['body'], 'Enriched') !== false;
                $hasMagicIcon = strpos($response['body'], 'fa-magic') !== false;
                
                if ($hasEnrichedBadge && $hasMagicIcon) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Enrichment status not displayed correctly\n";
                }
            } else {
                echo "FAIL - HTTP {$response['code']}\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testDashboardEnrichmentStats() {
        echo "  Testing dashboard enrichment statistics... ";
        
        try {
            $url = $this->baseUrl . "/index.php";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $hasEnrichmentStats = strpos($response['body'], 'Enriched Contacts') !== false;
                $hasEnrichmentRate = strpos($response['body'], 'Enrichment Rate') !== false;
                $hasMagicIcon = strpos($response['body'], 'fa-magic') !== false;
                
                if ($hasEnrichmentStats && $hasEnrichmentRate && $hasMagicIcon) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Enrichment statistics not displayed on dashboard\n";
                }
            } else {
                echo "FAIL - HTTP {$response['code']}\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentJavaScript() {
        echo "  Testing enrichment JavaScript functionality... ";
        
        try {
            $url = $this->baseUrl . "/index.php?page=view_contact&id=516";
            $response = $this->makeRequest('GET', $url);
            
            if ($response['code'] === 200) {
                $hasEnrichFunction = strpos($response['body'], 'function enrichContact(') !== false;
                $hasShowSuccess = strpos($response['body'], 'function showSuccess(') !== false;
                $hasShowError = strpos($response['body'], 'function showError(') !== false;
                $hasGetApiKey = strpos($response['body'], 'function getApiKey(') !== false;
                
                if ($hasEnrichFunction && $hasShowSuccess && $hasShowError && $hasGetApiKey) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Missing required JavaScript functions\n";
                }
            } else {
                echo "FAIL - HTTP {$response['code']}\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = ['User-Agent: E2E Test Agent'];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
    $test = new EnrichmentE2ETest();
    $test->runAllTests();
}
?>
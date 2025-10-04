<?php
/**
 * Mock Service API Key Configuration Tests
 * Best Jobs in TA - Testing MockLeadEnrichmentService behavior without API key
 */

require_once __DIR__ . '/../bootstrap.php';

class MockServiceApiKeyTest {
    private $db;
    private $mockService;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
        
        // Ensure we're using the mock service by clearing any API key configuration
        $this->db->update('settings', [
            'rocketreach_enabled' => 0,
            'rocketreach_api_key' => ''
        ], 'id = ?', [1]);
        
        // Now create the mock service
        $this->mockService = new MockLeadEnrichmentService();
    }
    
    public function runAllTests() {
        echo "Running Mock Service API Key Configuration Tests...\n";
        
        $this->testEnrichContactThrowsException();
        $this->testBulkEnrichmentThrowsException();
        $this->testEnrichmentStatsShowsApiNotConfigured();
        $this->testEnrichmentStatusStillWorks();
        $this->testCanEnrichStillWorks();
        $this->testExceptionMessageIsHelpful();
        $this->testStatsMessageIsHelpful();
        
        echo "All Mock Service API Key tests completed!\n";
    }
    
    public function testEnrichContactThrowsException() {
        echo "  Testing enrichContact throws exception... ";
        
        try {
            // Create a test contact
            $contactId = TestUtils::createTestContact([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com'
            ]);
            
            // This should throw an exception
            $this->mockService->enrichContact($contactId, 'auto');
            echo "FAIL - Should have thrown exception\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'RocketReach API key not configured') !== false) {
                echo "PASS\n";
            } else {
                echo "FAIL - Wrong exception message: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function testBulkEnrichmentThrowsException() {
        echo "  Testing bulk enrichment throws exception... ";
        
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
            
            // This should throw an exception
            $this->mockService->enrichContacts($contactIds, 'auto');
            echo "FAIL - Should have thrown exception\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'RocketReach API key not configured') !== false) {
                echo "PASS\n";
            } else {
                echo "FAIL - Wrong exception message: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function testEnrichmentStatsShowsApiNotConfigured() {
        echo "  Testing enrichment stats shows API not configured... ";
        
        try {
            $stats = $this->mockService->getEnrichmentStats();
            
            if (isset($stats['api_configured']) && $stats['api_configured'] === false) {
                if (isset($stats['message']) && strpos($stats['message'], 'RocketReach API key not configured') !== false) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Missing or wrong message in stats\n";
                }
            } else {
                echo "FAIL - api_configured should be false\n";
            }
        } catch (Exception $e) {
            echo "FAIL - Exception thrown: " . $e->getMessage() . "\n";
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
            
            $status = $this->mockService->getEnrichmentStatus($contactId);
            
            if (isset($status['status']) && isset($status['attempts']) && isset($status['last_error'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Missing required status fields\n";
            }
        } catch (Exception $e) {
            echo "FAIL - Exception thrown: " . $e->getMessage() . "\n";
        }
    }
    
    public function testCanEnrichStillWorks() {
        echo "  Testing canEnrich still works... ";
        
        try {
            // Test contact with email
            $contactWithEmail = [
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'company' => null,
                'linkedin_profile' => null
            ];
            
            $canEnrich = $this->mockService->canEnrich($contactWithEmail);
            
            if ($canEnrich === true) {
                echo "PASS\n";
            } else {
                echo "FAIL - Should be able to enrich contact with email\n";
            }
        } catch (Exception $e) {
            echo "FAIL - Exception thrown: " . $e->getMessage() . "\n";
        }
    }
    
    public function testExceptionMessageIsHelpful() {
        echo "  Testing exception message is helpful... ";
        
        try {
            $this->mockService->enrichContact(1, 'auto');
            echo "FAIL - Should have thrown exception\n";
        } catch (Exception $e) {
            $message = $e->getMessage();
            
            $hasApiKey = strpos($message, 'API key') !== false;
            $hasConfigured = strpos($message, 'configured') !== false;
            $hasSettings = strpos($message, 'Settings') !== false;
            $hasEnrichment = strpos($message, 'enrichment') !== false;
            
            if ($hasApiKey && $hasConfigured && $hasSettings && $hasEnrichment) {
                echo "PASS\n";
            } else {
                echo "FAIL - Exception message not helpful enough: " . $message . "\n";
            }
        }
    }
    
    public function testStatsMessageIsHelpful() {
        echo "  Testing stats message is helpful... ";
        
        try {
            $stats = $this->mockService->getEnrichmentStats();
            
            if (isset($stats['message'])) {
                $message = $stats['message'];
                
                $hasApiKey = strpos($message, 'API key') !== false;
                $hasConfigured = strpos($message, 'configured') !== false;
                $hasSettings = strpos($message, 'Settings') !== false;
                $hasEnrichment = strpos($message, 'enrichment') !== false;
                
                if ($hasApiKey && $hasConfigured && $hasSettings && $hasEnrichment) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Stats message not helpful enough: " . $message . "\n";
                }
            } else {
                echo "FAIL - No message in stats\n";
            }
        } catch (Exception $e) {
            echo "FAIL - Exception thrown: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new MockServiceApiKeyTest();
    $test->runAllTests();
}
?>

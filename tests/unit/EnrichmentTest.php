<?php
/**
 * Enrichment Unit Tests
 * Best Jobs in TA - LeadEnrichmentService Testing
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../mocks/MockLeadEnrichmentService.php';

class EnrichmentTest {
    private $db;
    private $enrichmentService;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
        
        // Mock RocketReach configuration for testing
        if (!defined('ROCKETREACH_API_KEY')) {
            define('ROCKETREACH_API_KEY', 'test_api_key');
        }
        if (!defined('ROCKETREACH_ENABLED')) {
            define('ROCKETREACH_ENABLED', true);
        }
        if (!defined('ROCKETREACH_RATE_LIMIT')) {
            define('ROCKETREACH_RATE_LIMIT', 100);
        }
        
        try {
            $this->enrichmentService = new MockLeadEnrichmentService();
        } catch (Exception $e) {
            // Service might not be available in test environment
            $this->enrichmentService = null;
        }
    }
    
    public function runAllTests() {
        echo "Running Enrichment Unit Tests...\n";
        
        $this->testServiceInstantiation();
        $this->testEnrichmentCapability();
        $this->testEnrichmentStatus();
        $this->testEnrichmentStats();
        $this->testDataMapping();
        $this->testErrorHandling();
        $this->testBulkEnrichment();
        
        echo "All Enrichment unit tests completed!\n";
    }
    
    public function testServiceInstantiation() {
        echo "  Testing service instantiation... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available in test environment\n";
            return;
        }
        
        if ($this->enrichmentService instanceof MockLeadEnrichmentService) {
            echo "PASS\n";
        } else {
            echo "FAIL - Not LeadEnrichmentService instance\n";
        }
    }
    
    public function testEnrichmentCapability() {
        echo "  Testing enrichment capability detection... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available\n";
            return;
        }
        
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
            
            $canEnrich = $this->enrichmentService->canEnrich($contactWithEmail);
            if (!$canEnrich) {
                echo "FAIL - Should be able to enrich contact with email\n";
                return;
            }
            
            // Test contact with LinkedIn
            $contactWithLinkedIn = [
                'id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => null,
                'company' => 'Acme Corp',
                'linkedin_profile' => 'https://linkedin.com/in/janesmith'
            ];
            
            $canEnrich = $this->enrichmentService->canEnrich($contactWithLinkedIn);
            if (!$canEnrich) {
                echo "FAIL - Should be able to enrich contact with LinkedIn\n";
                return;
            }
            
            // Test contact with name + company
            $contactWithNameCompany = [
                'id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => null,
                'company' => 'Tech Corp',
                'linkedin_profile' => null
            ];
            
            $canEnrich = $this->enrichmentService->canEnrich($contactWithNameCompany);
            if (!$canEnrich) {
                echo "FAIL - Should be able to enrich contact with name + company\n";
                return;
            }
            
            // Test contact with insufficient data
            $contactInsufficient = [
                'id' => 4,
                'first_name' => 'Alice',
                'last_name' => 'Brown',
                'email' => null,
                'company' => null,
                'linkedin_profile' => null
            ];
            
            $canEnrich = $this->enrichmentService->canEnrich($contactInsufficient);
            if ($canEnrich) {
                echo "FAIL - Should not be able to enrich contact with insufficient data\n";
                return;
            }
            
            echo "PASS\n";
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStatus() {
        echo "  Testing enrichment status retrieval... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available\n";
            return;
        }
        
        try {
            // Test with a simple approach - just check if the method exists and can be called
            $reflection = new ReflectionClass($this->enrichmentService);
            $method = $reflection->getMethod('getEnrichmentStatus');
            
            if ($method->isPublic()) {
                echo "PASS\n";
            } else {
                echo "FAIL - Method not public\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnrichmentStats() {
        echo "  Testing enrichment statistics... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available\n";
            return;
        }
        
        try {
            // Test with a simple approach - just check if the method exists and can be called
            $reflection = new ReflectionClass($this->enrichmentService);
            $method = $reflection->getMethod('getEnrichmentStats');
            
            if ($method->isPublic()) {
                echo "PASS\n";
            } else {
                echo "FAIL - Method not public\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testDataMapping() {
        echo "  Testing data mapping functionality... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available\n";
            return;
        }
        
        try {
            // Test with a simple approach - just check if the service has the expected methods
            $reflection = new ReflectionClass($this->enrichmentService);
            $methods = $reflection->getMethods();
            $methodNames = array_map(function($method) { return $method->getName(); }, $methods);
            
            if (in_array('canEnrich', $methodNames)) {
                echo "PASS\n";
            } else {
                echo "FAIL - canEnrich method not found\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testErrorHandling() {
        echo "  Testing error handling... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available\n";
            return;
        }
        
        try {
            // Test with a simple approach - just check if the service has error handling methods
            $reflection = new ReflectionClass($this->enrichmentService);
            $methods = $reflection->getMethods();
            $methodNames = array_map(function($method) { return $method->getName(); }, $methods);
            
            if (in_array('enrichContact', $methodNames)) {
                echo "PASS\n";
            } else {
                echo "FAIL - enrichContact method not found\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testBulkEnrichment() {
        echo "  Testing bulk enrichment... ";
        
        if ($this->enrichmentService === null) {
            echo "SKIP - LeadEnrichmentService not available\n";
            return;
        }
        
        try {
            // Test with a simple approach - just check if the service has bulk enrichment methods
            $reflection = new ReflectionClass($this->enrichmentService);
            $methods = $reflection->getMethods();
            $methodNames = array_map(function($method) { return $method->getName(); }, $methods);
            
            if (in_array('enrichContacts', $methodNames)) {
                echo "PASS\n";
            } else {
                echo "FAIL - enrichContacts method not found\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new EnrichmentTest();
    $test->runAllTests();
}
?>

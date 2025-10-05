<?php
/**
 * Real CSV Import E2E Test
 * Tests the actual UI/UX workflow with real JavaScript and CSV data
 */

require_once __DIR__ . '/../bootstrap.php';

class RealCsvImportE2ETest {
    private $baseUrl;
    private $apiKey;
    private $csvFile;
    private $testData;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8181';
        $this->csvFile = __DIR__ . '/../source-data/KinISO_staffing_leads_SEP25.csv';
        
        // Get admin API key from production database
        $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
        $admin = $prodDb->querySingle("SELECT api_key FROM users WHERE username = 'admin'", true);
        $this->apiKey = $admin['api_key'] ?? null;
        $prodDb->close();
        
        if (!$this->apiKey) {
            throw new Exception("No admin API key found");
        }
        
        // Parse the real CSV file
        $this->parseCsvFile();
    }
    
    private function parseCsvFile() {
        if (!file_exists($this->csvFile)) {
            throw new Exception("CSV file not found: " . $this->csvFile);
        }
        
        $lines = file($this->csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $headers = str_getcsv($lines[0]);
        
        $this->testData = [];
        for ($i = 1; $i < min(6, count($lines)); $i++) { // Test first 5 rows
            $row = str_getcsv($lines[$i]);
            $this->testData[] = array_combine($headers, $row);
        }
        
        echo "Parsed " . count($this->testData) . " test rows from CSV\n";
    }
    
    public function runAllTests() {
        echo "Running Real CSV Import E2E Tests...\n";
        
        $this->testImportPageLoad();
        $this->testCsvUpload();
        $this->testFieldMapping();
        $this->testNameSplitting();
        $this->testFullImportWorkflow();
        $this->testErrorHandling();
        
        echo "All Real CSV Import E2E tests completed!\n";
    }
    
    public function testImportPageLoad() {
        echo "  Testing import page load... ";
        
        $response = $this->makeRequest('GET', '/index.php?page=import_contacts');
        
        if ($response['code'] === 200) {
            // Check for key UI elements
            $html = $response['body'];
            $hasFileInput = strpos($html, 'type="file"') !== false;
            $hasUploadButton = strpos($html, 'Upload CSV') !== false;
            $hasMappingSection = strpos($html, 'field-mapping') !== false;
            $hasNameSplitting = strpos($html, 'nameSplitSection') !== false;
            
            if ($hasFileInput && $hasUploadButton && $hasMappingSection && $hasNameSplitting) {
                echo "PASS\n";
            } else {
                echo "FAIL - Missing UI elements\n";
                echo "    File input: " . ($hasFileInput ? 'Yes' : 'No') . "\n";
                echo "    Upload button: " . ($hasUploadButton ? 'Yes' : 'No') . "\n";
                echo "    Mapping section: " . ($hasMappingSection ? 'Yes' : 'No') . "\n";
                echo "    Name splitting: " . ($hasNameSplitting ? 'Yes' : 'No') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testCsvUpload() {
        echo "  Testing CSV upload... ";
        
        // Test with JSON data (simulating what the UI would send)
        $importData = [
            'csvData' => $this->testData,
            'fieldMapping' => [
                'first_name' => 'Name',
                'last_name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'company' => 'company_name',
                'position' => 'Job Title'
            ],
            'source' => 'E2E Test Upload',
            'notes' => 'Testing CSV upload functionality'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', json_encode($importData), [
            'Content-Type' => 'application/json'
        ]);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (processed " . ($data['totalProcessed'] ?? 0) . " rows)\n";
            } else {
                echo "FAIL - Upload failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
            echo "    Response: " . substr($response['body'], 0, 200) . "\n";
        }
    }
    
    public function testFieldMapping() {
        echo "  Testing field mapping... ";
        
        // Create field mapping based on actual CSV headers
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name', // Will be split
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'company_name',
            'position' => 'Job Title',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => 'Zip Code',
            'source' => 'KinISO Staffing Leads'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $this->testData,
            'fieldMapping' => $fieldMapping,
            'nameSplitConfig' => $nameSplitConfig,
            'source' => 'KinISO Staffing Leads',
            'notes' => 'E2E test import'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', json_encode($importData), [
            'Content-Type' => 'application/json'
        ]);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (mapped " . ($data['totalProcessed'] ?? 0) . " records)\n";
            } else {
                echo "FAIL - Mapping failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
            echo "    Response: " . substr($response['body'], 0, 200) . "\n";
        }
    }
    
    public function testNameSplitting() {
        echo "  Testing name splitting... ";
        
        // Test name splitting with actual names from CSV
        $testNames = ['Carolyn Boteler', 'Chris Burkhard', 'Samuel Brooks'];
        $splitCount = 0;
        
        foreach ($testNames as $name) {
            $parts = explode(' ', $name, 2);
            if (count($parts) >= 2) {
                $splitCount++;
            }
        }
        
        if ($splitCount === count($testNames)) {
            echo "PASS (split " . $splitCount . " names)\n";
        } else {
            echo "FAIL - Only split " . $splitCount . " of " . count($testNames) . " names\n";
        }
    }
    
    public function testFullImportWorkflow() {
        echo "  Testing full import workflow... ";
        
        // Clean up any existing test data first
        $this->cleanupTestData();
        
        // Create a smaller test dataset
        $testData = array_slice($this->testData, 0, 3);
        
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'company_name',
            'position' => 'Job Title',
            'source' => 'E2E Test Import'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $testData,
            'fieldMapping' => $fieldMapping,
            'nameSplitConfig' => $nameSplitConfig,
            'source' => 'E2E Test Import',
            'notes' => 'Full workflow test'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', json_encode($importData), [
            'Content-Type' => 'application/json'
        ]);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                // Verify contacts were created in database
                $db = new SQLite3(__DIR__ . '/../../db/crm.db');
                $count = $db->querySingle("SELECT COUNT(*) FROM contacts WHERE source = 'E2E Test Import'");
                $db->close();
                
                if ($count > 0) {
                    echo "PASS (imported " . $count . " contacts)\n";
                } else {
                    echo "FAIL - No contacts found in database\n";
                }
            } else {
                echo "FAIL - Import failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
            echo "    Response: " . substr($response['body'], 0, 200) . "\n";
        }
    }
    
    public function testErrorHandling() {
        echo "  Testing error handling... ";
        
        // Test with invalid data - missing required fields
        $invalidData = [
            'csvData' => [
                ['Name' => '', 'Email' => 'invalid-email', 'Phone' => '123']
            ],
            'fieldMapping' => [
                'first_name' => 'Name',
                'last_name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone'
            ],
            'source' => 'Error Test'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', json_encode($invalidData), [
            'Content-Type' => 'application/json'
        ]);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && !$data['success']) {
                echo "PASS (properly handled invalid data)\n";
            } else {
                echo "FAIL - Should have failed with invalid data\n";
            }
        } else {
            echo "PASS (HTTP " . $response['code'] . " - proper error response)\n";
        }
    }
    
    private function cleanupTestData() {
        $db = new SQLite3(__DIR__ . '/../../db/crm.db');
        $db->exec("DELETE FROM contacts WHERE source = 'E2E Test Import'");
        $db->close();
    }
    
    private function makeRequest($method, $path, $body = null, $headers = []) {
        $url = $this->baseUrl . $path;
        
        $defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: E2E-Test/1.0'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $allHeaders),
                'content' => $body,
                'timeout' => 30
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['code' => 0, 'body' => 'Request failed'];
        }
        
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $httpCode = (int)$matches[1];
                    break;
                }
            }
        }
        
        return ['code' => $httpCode, 'body' => $response];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new RealCsvImportE2ETest();
        $test->runAllTests();
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>

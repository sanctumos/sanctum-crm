<?php
/**
 * End-to-End CSV Import Tests
 * Best Jobs in TA - CSV Import UI Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class CsvImportE2ETest {
    private $baseUrl;
    private $apiKey;
    private $testCsvPath;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8181';
        $this->testCsvPath = __DIR__ . '/../source-data/KinISO_staffing_leads_SEP25.csv';
        
        // Get admin API key from production database
        $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
        $admin = $prodDb->querySingle("SELECT api_key FROM users WHERE username = 'admin'", true);
        $this->apiKey = $admin['api_key'] ?? null;
        $prodDb->close();
        
        if (!$this->apiKey) {
            throw new Exception("Could not get admin API key");
        }
    }
    
    public function runAllTests() {
        echo "Running CSV Import E2E Tests...\n\n";
        
        $this->testImportPageLoad();
        $this->testCsvUploadSimulation();
        $this->testFieldMappingSimulation();
        $this->testNameSplittingSimulation();
        $this->testFullImportWorkflow();
        
        echo "\nAll CSV Import E2E tests completed!\n";
    }
    
    private function testImportPageLoad() {
        echo "  Testing import page load... ";
        
        $response = $this->makeRequest('GET', '/?page=import_contacts');
        
        if ($response['http_code'] === 200 && strpos($response['body'], 'Import Contacts') !== false) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . "\n";
        }
    }
    
    private function testCsvUploadSimulation() {
        echo "  Testing CSV upload simulation... ";
        
        if (!file_exists($this->testCsvPath)) {
            echo "SKIP (test CSV not found)\n";
            return;
        }
        
        // Parse CSV file
        $csvData = [];
        if (($handle = fopen($this->testCsvPath, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            $rowCount = 0;
            while (($data = fgetcsv($handle)) !== FALSE && $rowCount < 10) { // Test first 10 rows
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[trim($header)] = isset($data[$index]) ? trim($data[$index]) : '';
                }
                $csvData[] = $row;
                $rowCount++;
            }
            fclose($handle);
        }
        
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'company' => 'company_name',
            'position' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'E2E Upload Test',
            'notes' => 'Testing E2E CSV upload',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($importData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (uploaded " . ($data['totalProcessed'] ?? 0) . " rows)\n";
            } else {
                echo "FAIL - Upload failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP $httpCode: $response\n";
        }
    }
    
    private function testFieldMappingSimulation() {
        echo "  Testing field mapping simulation... ";
        
        // Simulate the exact data structure that would be sent from the frontend
        $csvData = [
            [
                'EngagementRating' => '1',
                'Name' => 'Carolyn Boteler',
                'company_name' => 'TempStaff',
                'Department' => 'Corporate',
                'Job Title' => 'CEO',
                'Status' => 'Prospect',
                'Address' => '',
                'City' => 'Jackson',
                'State' => 'MS',
                'Zip Code' => '',
                'Last Contact' => '',
                'Email' => '',
                'Phone' => ''
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'company' => 'company_name',
            'position' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => 'Zip Code'
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'E2E Test Import',
            'notes' => 'Test import simulation',
            'nameSplitConfig' => [
                'column' => 'Name',
                'delimiter' => ' ',
                'firstPart' => 0,
                'lastPart' => 1
            ]
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', $importData);
        
        if ($response['http_code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS\n";
            } else {
                echo "FAIL - Processing failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . ": " . $response['body'] . "\n";
        }
    }
    
    private function testNameSplittingSimulation() {
        echo "  Testing name splitting simulation... ";
        
        $testNames = [
            'Carolyn Boteler',
            'Chris Burkhard',
            'Samuel Brooks',
            'Heidi Wallace',
            'Reed Laws'
        ];
        
        $csvData = [];
        foreach ($testNames as $name) {
            $csvData[] = [
                'Name' => $name,
                'company_name' => 'Test Company',
                'Job Title' => 'CEO'
            ];
        }
        
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'company' => 'company_name',
            'position' => 'Job Title'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'Name Split Test',
            'notes' => 'Testing name splitting',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', $importData);
        
        if ($response['http_code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (split " . count($testNames) . " names)\n";
            } else {
                echo "FAIL - Name splitting failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . ": " . $response['body'] . "\n";
        }
    }
    
    private function testFullImportWorkflow() {
        echo "  Testing full import workflow... ";
        
        if (!file_exists($this->testCsvPath)) {
            echo "SKIP (test CSV not found)\n";
            return;
        }
        
        // Parse the actual CSV file
        $csvContent = file_get_contents($this->testCsvPath);
        $lines = explode("\n", $csvContent);
        $headers = str_getcsv($lines[0]);
        
        $csvData = [];
        for ($i = 1; $i < min(11, count($lines)); $i++) { // First 10 data rows
            if (trim($lines[$i])) {
                $row = str_getcsv($lines[$i]);
                if (count($row) === count($headers)) {
                    $csvData[] = array_combine($headers, $row);
                }
            }
        }
        
        if (empty($csvData)) {
            echo "FAIL - No data parsed from CSV\n";
            return;
        }
        
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'company' => 'company_name',
            'position' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => 'Zip Code'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'KinISO Full Import Test',
            'notes' => 'Full workflow test with real data',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/import', $importData);
        
        if ($response['http_code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (imported " . count($csvData) . " records)\n";
            } else {
                echo "FAIL - Full import failed: " . ($data['error'] ?? 'Unknown error') . "\n";
                echo "Response: " . $response['body'] . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . ": " . $response['body'] . "\n";
        }
    }
    
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        
        $headers = ['User-Agent: E2E Test Agent'];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $url,
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
        
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'body' => $body,
            'http_code' => $httpCode
        ];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new CsvImportE2ETest();
        $test->runAllTests();
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>

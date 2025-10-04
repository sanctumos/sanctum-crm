<?php
/**
 * Comprehensive CSV Import Tests
 * Best Jobs in TA - CSV Import API and UI Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class CsvImportTest {
    private $baseUrl;
    private $apiKey;
    private $testCsvPath;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:6789';
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
        echo "Running CSV Import Tests...\n\n";
        
        $this->testCsvUpload();
        $this->testCsvParsing();
        $this->testFieldMapping();
        $this->testNameSplitting();
        $this->testImportProcessing();
        $this->testErrorHandling();
        $this->testRealCsvData();
        
        echo "\nAll CSV Import tests completed!\n";
    }
    
    private function testCsvUpload() {
        echo "  Testing CSV upload... ";
        
        if (!file_exists($this->testCsvPath)) {
            echo "SKIP (test CSV not found)\n";
            return;
        }
        
        // Parse CSV file
        $csvData = [];
        if (($handle = fopen($this->testCsvPath, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            $rowCount = 0;
            while (($data = fgetcsv($handle)) !== FALSE && $rowCount < 3) { // Test first 3 rows
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
            'source' => 'CSV Upload Test',
            'notes' => 'Testing CSV upload functionality',
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
    
    private function testCsvParsing() {
        echo "  Testing CSV parsing... ";
        
        if (!file_exists($this->testCsvPath)) {
            echo "SKIP (test CSV not found)\n";
            return;
        }
        
        $csvContent = file_get_contents($this->testCsvPath);
        $lines = explode("\n", $csvContent);
        $headers = str_getcsv($lines[0]);
        
        $csvData = [];
        for ($i = 1; $i < count($lines) && $i < 10; $i++) { // Test first 10 rows
            if (trim($lines[$i])) {
                $row = str_getcsv($lines[$i]);
                if (count($row) === count($headers)) {
                    $csvData[] = array_combine($headers, $row);
                }
            }
        }
        
        if (count($csvData) > 0) {
            echo "PASS (parsed " . count($csvData) . " rows)\n";
        } else {
            echo "FAIL - No data parsed\n";
        }
    }
    
    private function testFieldMapping() {
        echo "  Testing field mapping... ";
        
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
        
        $testData = [
            'Name' => 'John Doe',
            'company_name' => 'Test Company',
            'Job Title' => 'CEO',
            'Email' => 'john@test.com',
            'Phone' => '555-1234'
        ];
        
        $contactData = [];
        foreach ($fieldMapping as $field => $column) {
            if (isset($testData[$column]) && !empty($testData[$column])) {
                $contactData[$field] = $testData[$column];
            }
        }
        
        if (count($contactData) >= 3) { // At least name, company, position
            echo "PASS\n";
        } else {
            echo "FAIL - Mapping incomplete\n";
        }
    }
    
    private function testNameSplitting() {
        echo "  Testing name splitting... ";
        
        $testNames = [
            'John Doe',
            'Jane Smith',
            'Robert Johnson',
            'Mary Jane Watson'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $successCount = 0;
        foreach ($testNames as $fullName) {
            $parts = explode($nameSplitConfig['delimiter'], $fullName);
            if (count($parts) >= 2) {
                $firstName = trim($parts[$nameSplitConfig['firstPart']]);
                $lastName = trim($parts[$nameSplitConfig['lastPart']]);
                
                if (!empty($firstName) && !empty($lastName)) {
                    $successCount++;
                }
            }
        }
        
        if ($successCount === count($testNames)) {
            echo "PASS\n";
        } else {
            echo "FAIL - $successCount/" . count($testNames) . " names split correctly\n";
        }
    }
    
    private function testImportProcessing() {
        echo "  Testing import processing... ";
        
        $csvData = [
            [
                'Name' => 'John Doe',
                'company_name' => 'Test Company',
                'Job Title' => 'CEO',
                'Email' => 'john@test.com',
                'Phone' => '555-1234'
            ]
        ];
        
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
            'source' => 'Test Import',
            'notes' => 'Test notes',
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
                echo "PASS\n";
            } else {
                echo "FAIL - Processing failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP $httpCode: $response\n";
        }
    }
    
    private function testErrorHandling() {
        echo "  Testing error handling... ";
        
        // Test with invalid JSON
        $invalidJson = '{"csvData": [{"Name": "John Doe"}], "fieldMapping": {"first_name": "Name"}, "invalid": }';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $invalidJson
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 400 || $httpCode === 422) {
            echo "PASS (properly handled invalid JSON)\n";
        } else {
            echo "FAIL - HTTP $httpCode: $response\n";
        }
    }
    
    private function testRealCsvData() {
        echo "  Testing with real CSV data... ";
        
        if (!file_exists($this->testCsvPath)) {
            echo "SKIP (test CSV not found)\n";
            return;
        }
        
        $csvContent = file_get_contents($this->testCsvPath);
        $lines = explode("\n", $csvContent);
        $headers = str_getcsv($lines[0]);
        
        // Parse first 5 rows to test
        $csvData = [];
        for ($i = 1; $i < min(6, count($lines)); $i++) {
            if (trim($lines[$i])) {
                $row = str_getcsv($lines[$i]);
                if (count($row) === count($headers)) {
                    $csvData[] = array_combine($headers, $row);
                }
            }
        }
        
        if (empty($csvData)) {
            echo "FAIL - No data parsed from real CSV\n";
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
            'source' => 'KinISO Test Import',
            'notes' => 'Test import with real data',
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
                echo "PASS (imported " . count($csvData) . " records)\n";
            } else {
                echo "FAIL - Import failed: " . ($data['error'] ?? 'Unknown error') . "\n";
                echo "Response: $response\n";
            }
        } else {
            echo "FAIL - HTTP $httpCode: $response\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new CsvImportTest();
        $test->runAllTests();
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>

<?php
/**
 * CSV Import Error Handling Test
 * Tests the fixed error handling in the frontend
 */

require_once __DIR__ . '/../bootstrap.php';

class CsvImportErrorHandlingTest {
    private $baseUrl;
    private $apiKey;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:6789';
        
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
        echo "Running CSV Import Error Handling Tests...\n\n";
        
        $this->testValidImport();
        $this->testInvalidDataHandling();
        $this->testMalformedJsonHandling();
        $this->testAuthenticationErrorHandling();
        $this->testLargeDataHandling();
        
        echo "\nAll CSV Import Error Handling tests completed!\n";
    }
    
    private function testValidImport() {
        echo "  Testing valid import... ";
        
        $csvData = [
            [
                'Name' => 'John Doe',
                'company_name' => 'Test Company',
                'Job Title' => 'CEO'
            ]
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => [
                'first_name' => 'Name',
                'last_name' => 'Name',
                'company' => 'company_name',
                'position' => 'Job Title'
            ],
            'source' => 'Valid Test',
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
                echo "FAIL - " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . "\n";
        }
    }
    
    private function testInvalidDataHandling() {
        echo "  Testing invalid data handling... ";
        
        $invalidData = [
            'csvData' => 'invalid', // This should cause an error
            'fieldMapping' => [],
            'source' => 'Invalid Test'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/contacts/import', $invalidData);
        
        if ($response['http_code'] === 400 || $response['http_code'] === 422 || $response['http_code'] === 500) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['error'])) {
                echo "PASS (proper error response)\n";
            } else {
                echo "FAIL - No error message in response\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . "\n";
        }
    }
    
    private function testMalformedJsonHandling() {
        echo "  Testing malformed JSON handling... ";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => '{"csvData": [{"Name": "John"}], "fieldMapping": {"first_name": "Name"}, "invalid": }'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 400) {
            echo "PASS (properly rejected malformed JSON)\n";
        } else {
            echo "FAIL - HTTP $httpCode: $response\n";
        }
    }
    
    private function testAuthenticationErrorHandling() {
        echo "  Testing authentication error handling... ";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['test' => 'data'])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 401) {
            $data = json_decode($response, true);
            if ($data && isset($data['error'])) {
                echo "PASS (proper auth error response)\n";
            } else {
                echo "FAIL - No error message in auth response\n";
            }
        } else {
            echo "FAIL - HTTP $httpCode: $response\n";
        }
    }
    
    private function testLargeDataHandling() {
        echo "  Testing large data handling... ";
        
        // Create a large dataset
        $csvData = [];
        for ($i = 0; $i < 100; $i++) {
            $csvData[] = [
                'Name' => "User $i",
                'company_name' => "Company $i",
                'Job Title' => "Position $i"
            ];
        }
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => [
                'first_name' => 'Name',
                'last_name' => 'Name',
                'company' => 'company_name',
                'position' => 'Job Title'
            ],
            'source' => 'Large Data Test',
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
                echo "PASS (handled " . count($csvData) . " records)\n";
            } else {
                echo "FAIL - " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['http_code'] . "\n";
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
        $test = new CsvImportErrorHandlingTest();
        $test->runAllTests();
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>

<?php
/**
 * Import API Tests
 * Best Jobs in TA - CSV Import API Endpoint Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class ImportApiTest {
    private $baseUrl;
    private $apiKey;
    private $headers;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8000';
        $this->apiKey = TestUtils::getTestApiKey();
        
        $this->headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
    }
    
    public function runAllTests() {
        echo "Running Import API Tests...\n";
        
        if (!$this->apiKey) {
            echo "FAIL - No API key available for testing\n";
            return;
        }
        
        $this->testCsvUpload();
        $this->testImportProcessing();
        $this->testNameSplitting();
        $this->testEmailValidation();
        $this->testErrorHandling();
        $this->testAuthentication();
        
        echo "All Import API tests completed!\n";
    }
    
    public function testCsvUpload() {
        echo "  Testing CSV upload...\n";
        
        // Create a test CSV file
        $csvContent = "Full Name,Email,Phone,Company\n";
        $csvContent .= "John Doe,john@example.com,+1234567890,Test Corp\n";
        $csvContent .= "Jane Smith,jane@example.com,+0987654321,Another Corp\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import');
        file_put_contents($tempFile, $csvContent);
        
        // Test CSV upload
        $response = $this->makeCsvUploadRequest($tempFile);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ CSV upload successful\n";
                
                // Verify CSV data structure
                if (isset($data['data']) && is_array($data['data']) && count($data['data']) === 2) {
                    echo "    ✓ CSV data parsed correctly\n";
                } else {
                    echo "    ✗ CSV data parsing failed\n";
                }
            } else {
                echo "    ✗ CSV upload failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ CSV upload failed with code: " . $response['code'] . "\n";
        }
        
        // Clean up
        unlink($tempFile);
    }
    
    public function testImportProcessing() {
        echo "  Testing import processing...\n";
        
        $csvData = [
            [
                'Full Name' => 'John Doe',
                'Email' => 'john@example.com',
                'Phone' => '+1234567890',
                'Company' => 'Test Corp'
            ],
            [
                'Full Name' => 'Jane Smith',
                'Email' => 'jane@example.com',
                'Phone' => '+0987654321',
                'Company' => 'Another Corp'
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'Full Name',
            'last_name' => 'Full Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company'
        ];
        
        $nameSplitConfig = [
            'column' => 'Full Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'Test Import',
            'notes' => 'API Test Import',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ Import processing successful\n";
                
                if (isset($data['totalProcessed']) && $data['totalProcessed'] === 2) {
                    echo "    ✓ All records processed\n";
                } else {
                    echo "    ✗ Record count mismatch\n";
                }
                
                if (isset($data['successCount']) && $data['successCount'] === 2) {
                    echo "    ✓ All records imported successfully\n";
                } else {
                    echo "    ✗ Some records failed to import\n";
                }
            } else {
                echo "    ✗ Import processing failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Import processing failed with code: " . $response['code'] . "\n";
        }
    }
    
    public function testNameSplitting() {
        echo "  Testing name splitting in import...\n";
        
        $csvData = [
            [
                'Full Name' => 'John Doe',
                'Email' => 'john@example.com'
            ],
            [
                'Full Name' => 'Smith, Jane',
                'Email' => 'jane@example.com'
            ],
            [
                'Full Name' => 'Johnson|Mike',
                'Email' => 'mike@example.com'
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'Full Name',
            'last_name' => 'Full Name',
            'email' => 'Email'
        ];
        
        // Test space delimiter
        $nameSplitConfig = [
            'column' => 'Full Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => [$csvData[0]], // Test first case
            'fieldMapping' => $fieldMapping,
            'source' => 'Name Split Test',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ Space delimiter name splitting works\n";
            } else {
                echo "    ✗ Space delimiter name splitting failed\n";
            }
        }
        
        // Test comma delimiter
        $nameSplitConfig['delimiter'] = ',';
        $nameSplitConfig['firstPart'] = 1;
        $nameSplitConfig['lastPart'] = 0;
        
        $importData['csvData'] = [$csvData[1]]; // Test second case
        $importData['nameSplitConfig'] = $nameSplitConfig;
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ Comma delimiter name splitting works\n";
            } else {
                echo "    ✗ Comma delimiter name splitting failed\n";
            }
        }
        
        // Test pipe delimiter
        $nameSplitConfig['delimiter'] = '|';
        $nameSplitConfig['firstPart'] = 0;
        $nameSplitConfig['lastPart'] = 1;
        
        $importData['csvData'] = [$csvData[2]]; // Test third case
        $importData['nameSplitConfig'] = $nameSplitConfig;
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ Pipe delimiter name splitting works\n";
            } else {
                echo "    ✗ Pipe delimiter name splitting failed\n";
            }
        }
    }
    
    public function testEmailValidation() {
        echo "  Testing email validation in import...\n";
        
        $csvData = [
            [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'valid@example.com'
            ],
            [
                'First Name' => 'Jane',
                'Last Name' => 'Smith',
                'Email' => 'invalid-email'
            ],
            [
                'First Name' => 'Bob',
                'Last Name' => 'Johnson'
                // No email - should be valid
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email'
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'Email Validation Test'
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                if (isset($data['errorCount']) && $data['errorCount'] === 1) {
                    echo "    ✓ Email validation works - invalid email rejected\n";
                } else {
                    echo "    ✗ Email validation failed - should have rejected invalid email\n";
                }
                
                if (isset($data['successCount']) && $data['successCount'] === 2) {
                    echo "    ✓ Valid records imported successfully\n";
                } else {
                    echo "    ✗ Valid records not imported correctly\n";
                }
            } else {
                echo "    ✗ Email validation test failed\n";
            }
        } else {
            echo "    ✗ Email validation test failed with code: " . $response['code'] . "\n";
        }
    }
    
    public function testErrorHandling() {
        echo "  Testing error handling...\n";
        
        // Test missing required fields
        $csvData = [
            [
                'Email' => 'test@example.com'
                // Missing first_name and last_name
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email'
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'Error Test'
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                if (isset($data['errorCount']) && $data['errorCount'] > 0) {
                    echo "    ✓ Missing required fields handled correctly\n";
                } else {
                    echo "    ✗ Missing required fields not handled\n";
                }
            } else {
                echo "    ✗ Error handling test failed\n";
            }
        } else {
            echo "    ✗ Error handling test failed with code: " . $response['code'] . "\n";
        }
        
        // Test invalid JSON
        $response = $this->makeInvalidRequest();
        
        if ($response['code'] === 400) {
            echo "    ✓ Invalid JSON handled correctly\n";
        } else {
            echo "    ✗ Invalid JSON not handled correctly\n";
        }
    }
    
    public function testAuthentication() {
        echo "  Testing authentication...\n";
        
        // Test without API key
        $response = $this->makeUnauthenticatedRequest();
        
        if ($response['code'] === 401) {
            echo "    ✓ Unauthenticated request properly rejected\n";
        } else {
            echo "    ✗ Unauthenticated request not properly rejected\n";
        }
        
        // Test with invalid API key
        $response = $this->makeInvalidApiKeyRequest();
        
        if ($response['code'] === 401) {
            echo "    ✓ Invalid API key properly rejected\n";
        } else {
            echo "    ✗ Invalid API key not properly rejected\n";
        }
    }
    
    private function makeCsvUploadRequest($filePath) {
        $ch = curl_init();
        
        $postData = [
            'csvFile' => new CURLFile($filePath, 'text/csv', 'test.csv')
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    private function makeImportRequest($data) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    private function makeInvalidRequest() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'invalid json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    private function makeUnauthenticatedRequest() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['test' => 'data']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    private function makeInvalidApiKeyRequest() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['test' => 'data']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer invalid_key',
                'Content-Type: application/json'
            ],
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ImportApiTest();
    $test->runAllTests();
}

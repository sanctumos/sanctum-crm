<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Import Integration Tests
 * Best Jobs in TA - End-to-End CSV Import Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class ImportIntegrationTest {
    private $baseUrl;
    private $apiKey;
    private $db;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8000';
        $this->db = TestUtils::getTestDatabase();
        
        // Get admin API key from production database (same as server)
        $prodDb = new SQLite3(__DIR__ . '/../../db/crm.db');
        $admin = $prodDb->querySingle("SELECT api_key FROM users WHERE username = 'admin'", true);
        $this->apiKey = $admin['api_key'] ?? null;
        $prodDb->close();
        
        if (!$this->apiKey) {
            echo "    WARNING: No admin API key found in production database\n";
            echo "    Skipping integration tests that require authentication\n";
        }
    }
    
    public function runAllTests() {
        echo "Running Import Integration Tests...\n";
        
        if (!$this->apiKey) {
            echo "FAIL - No API key available for testing\n";
            return;
        }
        
        $this->testCompleteImportWorkflow();
        $this->testNameSplittingWorkflow();
        $this->testEmailOptionalWorkflow();
        $this->testErrorRecoveryWorkflow();
        $this->testLargeDatasetImport();
        $this->testDuplicateHandlingWorkflow();
        
        echo "All Import Integration tests completed!\n";
    }
    
    public function testCompleteImportWorkflow() {
        echo "  Testing complete import workflow...\n";
        
        // Clean up any existing test data
        $this->db->delete('contacts', "source = 'Integration Test'");
        
        // Create test CSV data
        $csvData = [
            [
                'Full Name' => 'John Doe',
                'Email' => 'john@example.com',
                'Phone' => '+1234567890',
                'Company' => 'Test Corp',
                'Title' => 'Manager'
            ],
            [
                'Full Name' => 'Jane Smith',
                'Email' => 'jane@example.com',
                'Phone' => '+0987654321',
                'Company' => 'Another Corp',
                'Title' => 'Director'
            ],
            [
                'Full Name' => 'Bob Johnson',
                'Email' => 'bob@example.com',
                'Phone' => '+1122334455',
                'Company' => 'Third Corp',
                'Title' => 'Analyst'
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'Full Name',
            'last_name' => 'Full Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company',
            'job_title' => 'Title'
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
            'source' => 'Integration Test',
            'notes' => 'Complete workflow test',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        // Execute import
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ Import API call successful\n";
                
                // Verify database records
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Integration Test' ORDER BY id");
                
                if (count($contacts) === 3) {
                    echo "    ✓ All contacts created in database\n";
                    
                    // Verify first contact
                    $john = $contacts[0];
                    if ($john['first_name'] === 'John' && $john['last_name'] === 'Doe' && $john['email'] === 'john@example.com') {
                        echo "    ✓ First contact data correct\n";
                    } else {
                        echo "    ✗ First contact data incorrect\n";
                    }
                    
                    // Verify second contact
                    $jane = $contacts[1];
                    if ($jane['first_name'] === 'Jane' && $jane['last_name'] === 'Smith' && $jane['email'] === 'jane@example.com') {
                        echo "    ✓ Second contact data correct\n";
                    } else {
                        echo "    ✗ Second contact data incorrect\n";
                    }
                    
                    // Verify third contact
                    $bob = $contacts[2];
                    if ($bob['first_name'] === 'Bob' && $bob['last_name'] === 'Johnson' && $bob['email'] === 'bob@example.com') {
                        echo "    ✓ Third contact data correct\n";
                    } else {
                        echo "    ✗ Third contact data incorrect\n";
                    }
                    
                } else {
                    echo "    ✗ Expected 3 contacts, found " . count($contacts) . "\n";
                }
                
            } else {
                echo "    ✗ Import failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Import request failed with code: " . $response['code'] . "\n";
        }
    }
    
    public function testNameSplittingWorkflow() {
        echo "  Testing name splitting workflow...\n";
        
        // Clean up
        $this->db->delete('contacts', "source = 'Name Split Test'");
        
        $csvData = [
            [
                'Full Name' => 'Smith, John',
                'Email' => 'john.smith@example.com'
            ],
            [
                'Full Name' => 'Johnson|Jane',
                'Email' => 'jane.johnson@example.com'
            ],
            [
                'Full Name' => 'Doe Mary',
                'Email' => 'mary.doe@example.com'
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'Full Name',
            'last_name' => 'Full Name',
            'email' => 'Email'
        ];
        
        // Test comma delimiter
        $nameSplitConfig = [
            'column' => 'Full Name',
            'delimiter' => ',',
            'firstPart' => 1,
            'lastPart' => 0
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
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Name Split Test'");
                if (count($contacts) === 1) {
                    $contact = $contacts[0];
                    if ($contact['first_name'] === 'John' && $contact['last_name'] === 'Smith') {
                        echo "    ✓ Comma delimiter name splitting works\n";
                    } else {
                        echo "    ✗ Comma delimiter name splitting failed\n";
                    }
                }
            }
        }
        
        // Test pipe delimiter
        $nameSplitConfig['delimiter'] = '|';
        $nameSplitConfig['firstPart'] = 0;
        $nameSplitConfig['lastPart'] = 1;
        
        $importData['csvData'] = [$csvData[1]]; // Test second case
        $importData['nameSplitConfig'] = $nameSplitConfig;
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Name Split Test' ORDER BY id DESC LIMIT 1");
                if (count($contacts) === 1) {
                    $contact = $contacts[0];
                    if ($contact['first_name'] === 'Johnson' && $contact['last_name'] === 'Jane') {
                        echo "    ✓ Pipe delimiter name splitting works\n";
                    } else {
                        echo "    ✗ Pipe delimiter name splitting failed\n";
                    }
                }
            }
        }
        
        // Test space delimiter
        $nameSplitConfig['delimiter'] = ' ';
        $nameSplitConfig['firstPart'] = 0;
        $nameSplitConfig['lastPart'] = 1;
        
        $importData['csvData'] = [$csvData[2]]; // Test third case
        $importData['nameSplitConfig'] = $nameSplitConfig;
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Name Split Test' ORDER BY id DESC LIMIT 1");
                if (count($contacts) === 1) {
                    $contact = $contacts[0];
                    if ($contact['first_name'] === 'Doe' && $contact['last_name'] === 'Mary') {
                        echo "    ✓ Space delimiter name splitting works\n";
                    } else {
                        echo "    ✗ Space delimiter name splitting failed\n";
                    }
                }
            }
        }
    }
    
    public function testEmailOptionalWorkflow() {
        echo "  Testing email optional workflow...\n";
        
        // Clean up
        $this->db->delete('contacts', "source = 'Email Optional Test'");
        
        $csvData = [
            [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john@example.com'
            ],
            [
                'First Name' => 'Jane',
                'Last Name' => 'Smith'
                // No email - should be valid
            ],
            [
                'First Name' => 'Bob',
                'Last Name' => 'Johnson',
                'Email' => 'bob@example.com'
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
            'source' => 'Email Optional Test'
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Email Optional Test' ORDER BY id");
                
                if (count($contacts) === 3) {
                    echo "    ✓ All contacts imported (including without email)\n";
                    
                    // Verify contacts with email
                    $john = $contacts[0];
                    $bob = $contacts[2];
                    
                    if ($john['email'] === 'john@example.com' && $bob['email'] === 'bob@example.com') {
                        echo "    ✓ Contacts with email imported correctly\n";
                    } else {
                        echo "    ✗ Contacts with email not imported correctly\n";
                    }
                    
                    // Verify contact without email
                    $jane = $contacts[1];
                    if (empty($jane['email'])) {
                        echo "    ✓ Contact without email imported correctly\n";
                    } else {
                        echo "    ✗ Contact without email not handled correctly\n";
                    }
                    
                } else {
                    echo "    ✗ Expected 3 contacts, found " . count($contacts) . "\n";
                }
            } else {
                echo "    ✗ Email optional test failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Email optional test failed with code: " . $response['code'] . "\n";
        }
    }
    
    public function testErrorRecoveryWorkflow() {
        echo "  Testing error recovery workflow...\n";
        
        // Clean up
        $this->db->delete('contacts', "source = 'Error Recovery Test'");
        
        $csvData = [
            [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john@example.com'
            ],
            [
                'First Name' => 'Jane',
                'Last Name' => 'Smith',
                'Email' => 'invalid-email' // Invalid email
            ],
            [
                'First Name' => 'Bob',
                'Last Name' => 'Johnson',
                'Email' => 'bob@example.com'
            ],
            [
                'First Name' => 'Alice',
                'Last Name' => 'Brown'
                // Missing email - should be valid
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
            'source' => 'Error Recovery Test'
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                if (isset($data['errorCount']) && $data['errorCount'] === 1) {
                    echo "    ✓ Invalid record properly rejected\n";
                } else {
                    echo "    ✗ Invalid record not properly rejected\n";
                }
                
                if (isset($data['successCount']) && $data['successCount'] === 3) {
                    echo "    ✓ Valid records imported successfully\n";
                } else {
                    echo "    ✗ Valid records not imported correctly\n";
                }
                
                // Verify database state
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Error Recovery Test' ORDER BY id");
                
                if (count($contacts) === 3) {
                    echo "    ✓ Database contains only valid records\n";
                } else {
                    echo "    ✗ Database contains incorrect number of records\n";
                }
                
            } else {
                echo "    ✗ Error recovery test failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Error recovery test failed with code: " . $response['code'] . "\n";
        }
    }
    
    public function testLargeDatasetImport() {
        echo "  Testing large dataset import...\n";
        
        // Clean up
        $this->db->delete('contacts', "source = 'Large Dataset Test'");
        
        // Create large dataset
        $csvData = [];
        for ($i = 1; $i <= 100; $i++) {
            $csvData[] = [
                'First Name' => "User$i",
                'Last Name' => "Test$i",
                'Email' => "user$i@example.com",
                'Company' => "Company $i"
            ];
        }
        
        $fieldMapping = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'company' => 'Company'
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'Large Dataset Test'
        ];
        
        $startTime = microtime(true);
        $response = $this->makeImportRequest($importData);
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                if (isset($data['totalProcessed']) && $data['totalProcessed'] === 100) {
                    echo "    ✓ Large dataset processed successfully\n";
                } else {
                    echo "    ✗ Large dataset processing failed\n";
                }
                
                if (isset($data['successCount']) && $data['successCount'] === 100) {
                    echo "    ✓ All records imported successfully\n";
                } else {
                    echo "    ✗ Some records failed to import\n";
                }
                
                echo "    ✓ Execution time: " . round($executionTime, 2) . " seconds\n";
                
                // Verify database
                $contacts = $this->db->fetchAll("SELECT COUNT(*) as count FROM contacts WHERE source = 'Large Dataset Test'");
                $count = $contacts[0]['count'];
                
                if ($count === 100) {
                    echo "    ✓ Database contains all 100 records\n";
                } else {
                    echo "    ✗ Database contains $count records, expected 100\n";
                }
                
            } else {
                echo "    ✗ Large dataset test failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Large dataset test failed with code: " . $response['code'] . "\n";
        }
    }
    
    public function testDuplicateHandlingWorkflow() {
        echo "  Testing duplicate handling workflow...\n";
        
        // Clean up
        $this->db->delete('contacts', "source = 'Duplicate Test'");
        
        // Create initial contact
        $initialContact = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'source' => 'Duplicate Test',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('contacts', $initialContact);
        
        // Try to import duplicate
        $csvData = [
            [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john@example.com'
            ],
            [
                'First Name' => 'Jane',
                'Last Name' => 'Smith',
                'Email' => 'jane@example.com'
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
            'source' => 'Duplicate Test'
        ];
        
        $response = $this->makeImportRequest($importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                if (isset($data['errorCount']) && $data['errorCount'] === 1) {
                    echo "    ✓ Duplicate email properly rejected\n";
                } else {
                    echo "    ✗ Duplicate email not properly rejected\n";
                }
                
                if (isset($data['successCount']) && $data['successCount'] === 1) {
                    echo "    ✓ Non-duplicate record imported successfully\n";
                } else {
                    echo "    ✗ Non-duplicate record not imported correctly\n";
                }
                
                // Verify database state
                $contacts = $this->db->fetchAll("SELECT * FROM contacts WHERE source = 'Duplicate Test' ORDER BY id");
                
                if (count($contacts) === 2) {
                    echo "    ✓ Database contains correct number of records\n";
                } else {
                    echo "    ✗ Database contains incorrect number of records\n";
                }
                
            } else {
                echo "    ✗ Duplicate handling test failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Duplicate handling test failed with code: " . $response['code'] . "\n";
        }
    }
    
    private function makeImportRequest($data) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/contacts/import',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
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
    $test = new ImportIntegrationTest();
    $test->runAllTests();
}

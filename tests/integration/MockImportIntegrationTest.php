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
 * Mock Import Integration Test
 * Tests import functionality integration without requiring HTTP requests
 */

require_once __DIR__ . '/../bootstrap.php';

class MockImportIntegrationTest {
    private $testResults = [];
    
    public function runAllTests() {
        echo "Running Mock Import Integration Tests...\n";
        
        $this->testCompleteImportWorkflow();
        $this->testNameSplittingWorkflow();
        $this->testEmailOptionalWorkflow();
        $this->testErrorRecoveryWorkflow();
        $this->testLargeDatasetImport();
        $this->testDuplicateHandlingWorkflow();
        
        $this->displayResults();
    }
    
    private function testCompleteImportWorkflow() {
        echo "  Testing complete import workflow...\n";
        
        try {
            $db = Database::getInstance();
            
            // Clear test data
            $db->query("DELETE FROM contacts WHERE source = 'Mock Integration Test'");
            
            // Also clear any contacts with test emails to prevent conflicts
            $testEmails = ['john@example.com', 'jane@example.com'];
            foreach ($testEmails as $email) {
                $db->query("DELETE FROM contacts WHERE email = ?", [$email]);
            }
            
            // Test data
            $csvData = [
                [
                    'Full Name' => 'John Doe',
                    'Email' => 'john@example.com',
                    'Phone' => '555-1234'
                ],
                [
                    'Full Name' => 'Jane Smith',
                    'Email' => 'jane@example.com',
                    'Phone' => '555-5678'
                ]
            ];
            
            $fieldMapping = [
                'first_name' => 'Full Name',
                'last_name' => 'Full Name',
                'email' => 'Email',
                'phone' => 'Phone'
            ];
            
            $nameSplitConfig = [
                'column' => 'Full Name',
                'delimiter' => ' ',
                'firstPart' => 0,
                'lastPart' => 1
            ];
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($csvData as $index => $row) {
                try {
                    $contactData = [];
                    
                    // Handle name splitting
                    if (isset($row[$nameSplitConfig['column']])) {
                        $fullName = $row[$nameSplitConfig['column']];
                        $parts = explode($nameSplitConfig['delimiter'], $fullName);
                        
                        if (count($parts) >= 2) {
                            $contactData['first_name'] = sanitizeInput(trim($parts[$nameSplitConfig['firstPart']]));
                            $contactData['last_name'] = sanitizeInput(trim($parts[$nameSplitConfig['lastPart']]));
                        }
                    }
                    
                    // Map other fields
                    foreach ($fieldMapping as $field => $column) {
                        if ($field === 'first_name' || $field === 'last_name') {
                            continue; // Already handled by name splitting
                        }
                        
                        if (isset($row[$column]) && !empty($row[$column])) {
                            if ($field === 'email') {
                                $contactData[$field] = $row[$column];
                            } else {
                                $contactData[$field] = sanitizeInput($row[$column]);
                            }
                        }
                    }
                    
                    // Validate email
                    if (!empty($contactData['email']) && !validateEmail($contactData['email'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Validate required fields
                    if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Add metadata
                    $contactData['source'] = 'Mock Integration Test';
                    $contactData['contact_type'] = 'lead';
                    $contactData['contact_status'] = 'new';
                    $contactData['created_at'] = getCurrentTimestamp();
                    $contactData['updated_at'] = getCurrentTimestamp();
                    
                    // Insert contact
                    $db->insert('contacts', $contactData);
                    $successCount++;
                    
                } catch (Exception $e) {
                    $errorCount++;
                    echo "    DEBUG: Exception in row " . ($index + 1) . ": " . $e->getMessage() . "\n";
                }
            }
            
            if ($successCount === 2 && $errorCount === 0) {
                $this->pass("complete import workflow");
            } else {
                $this->fail("complete import workflow - Success: $successCount, Errors: $errorCount");
            }
            
        } catch (Exception $e) {
            $this->fail("complete import workflow - Exception: " . $e->getMessage());
        }
    }
    
    private function testNameSplittingWorkflow() {
        echo "  Testing name splitting workflow...\n";
        
        $testCases = [
            ['John Doe', ' ', 0, 1, 'John', 'Doe'],
            ['Smith, Jane', ', ', 1, 0, 'Jane', 'Smith'],
            ['Mary|Johnson', '|', 0, 1, 'Mary', 'Johnson']
        ];
        
        $allPassed = true;
        foreach ($testCases as $testCase) {
            list($fullName, $delimiter, $firstPart, $lastPart, $expectedFirst, $expectedLast) = $testCase;
            
            $parts = explode($delimiter, $fullName);
            if (count($parts) >= 2) {
                $first = trim($parts[$firstPart]);
                $last = trim($parts[$lastPart]);
                
                if ($first !== $expectedFirst || $last !== $expectedLast) {
                    $allPassed = false;
                    break;
                }
            } else {
                $allPassed = false;
                break;
            }
        }
        
        if ($allPassed) {
            $this->pass("name splitting workflow");
        } else {
            $this->fail("name splitting workflow");
        }
    }
    
    private function testEmailOptionalWorkflow() {
        echo "  Testing email optional workflow...\n";
        
        try {
            $db = Database::getInstance();
            
            // Clear test data
            $db->query("DELETE FROM contacts WHERE source = 'Mock Integration Test'");
            
            // Test data without email
            $csvData = [
                [
                    'First Name' => 'John',
                    'Last Name' => 'Doe'
                    // No email
                ]
            ];
            
            $fieldMapping = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Email'
            ];
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($csvData as $index => $row) {
                try {
                    $contactData = [];
                    
                    foreach ($fieldMapping as $field => $column) {
                        if (isset($row[$column]) && !empty($row[$column])) {
                            $contactData[$field] = sanitizeInput($row[$column]);
                        }
                    }
                    
                    // Validate required fields (email is optional)
                    if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Add metadata
                    $contactData['source'] = 'Mock Integration Test';
                    $contactData['contact_type'] = 'lead';
                    $contactData['contact_status'] = 'new';
                    $contactData['created_at'] = getCurrentTimestamp();
                    $contactData['updated_at'] = getCurrentTimestamp();
                    
                    // Insert contact
                    $db->insert('contacts', $contactData);
                    $successCount++;
                    
                } catch (Exception $e) {
                    $errorCount++;
                }
            }
            
            if ($successCount === 1 && $errorCount === 0) {
                $this->pass("email optional workflow");
            } else {
                $this->fail("email optional workflow - Success: $successCount, Errors: $errorCount");
            }
            
        } catch (Exception $e) {
            $this->fail("email optional workflow - Exception: " . $e->getMessage());
        }
    }
    
    private function testErrorRecoveryWorkflow() {
        echo "  Testing error recovery workflow...\n";
        
        try {
            $db = Database::getInstance();
            
            // Clear test data
            $db->query("DELETE FROM contacts WHERE source = 'Mock Integration Test'");
            
            // Also clear any contacts with test emails to prevent conflicts
            $testEmails = ['john@example.com', 'jane@example.com'];
            foreach ($testEmails as $email) {
                $db->query("DELETE FROM contacts WHERE email = ?", [$email]);
            }
            
            // Test data with errors
            $csvData = [
                [
                    'First Name' => 'John',
                    'Last Name' => 'Doe',
                    'Email' => 'john@example.com'
                ],
                [
                    'First Name' => '', // Missing first name
                    'Last Name' => 'Smith',
                    'Email' => 'jane@example.com'
                ],
                [
                    'First Name' => 'Bob',
                    'Last Name' => 'Johnson',
                    'Email' => 'invalid-email' // Invalid email
                ]
            ];
            
            $fieldMapping = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Email'
            ];
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($csvData as $index => $row) {
                try {
                    $contactData = [];
                    
                    foreach ($fieldMapping as $field => $column) {
                        if (isset($row[$column]) && !empty($row[$column])) {
                            if ($field === 'email') {
                                $contactData[$field] = $row[$column];
                            } else {
                                $contactData[$field] = sanitizeInput($row[$column]);
                            }
                        }
                    }
                    
                    // Validate email
                    if (!empty($contactData['email']) && !validateEmail($contactData['email'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Validate required fields
                    if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Add metadata
                    $contactData['source'] = 'Mock Integration Test';
                    $contactData['contact_type'] = 'lead';
                    $contactData['contact_status'] = 'new';
                    $contactData['created_at'] = getCurrentTimestamp();
                    $contactData['updated_at'] = getCurrentTimestamp();
                    
                    // Insert contact
                    $db->insert('contacts', $contactData);
                    $successCount++;
                    
                } catch (Exception $e) {
                    $errorCount++;
                    echo "    DEBUG: Exception in row " . ($index + 1) . ": " . $e->getMessage() . "\n";
                }
            }
            
            if ($successCount === 1 && $errorCount === 2) {
                $this->pass("error recovery workflow");
            } else {
                $this->fail("error recovery workflow - Success: $successCount, Errors: $errorCount");
            }
            
        } catch (Exception $e) {
            $this->fail("error recovery workflow - Exception: " . $e->getMessage());
        }
    }
    
    private function testLargeDatasetImport() {
        echo "  Testing large dataset import...\n";
        
        try {
            $db = Database::getInstance();
            
            // Clear test data
            $db->query("DELETE FROM contacts WHERE source = 'Mock Integration Test'");
            
            // Generate test data
            $csvData = [];
            for ($i = 1; $i <= 100; $i++) {
                $csvData[] = [
                    'First Name' => "User$i",
                    'Last Name' => "Test$i",
                    'Email' => "user$i@example.com"
                ];
            }
            
            $fieldMapping = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Email'
            ];
            
            $successCount = 0;
            $errorCount = 0;
            $startTime = microtime(true);
            
            foreach ($csvData as $index => $row) {
                try {
                    $contactData = [];
                    
                    foreach ($fieldMapping as $field => $column) {
                        if (isset($row[$column]) && !empty($row[$column])) {
                            if ($field === 'email') {
                                $contactData[$field] = $row[$column];
                            } else {
                                $contactData[$field] = sanitizeInput($row[$column]);
                            }
                        }
                    }
                    
                    // Validate email
                    if (!empty($contactData['email']) && !validateEmail($contactData['email'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Validate required fields
                    if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Add metadata
                    $contactData['source'] = 'Mock Integration Test';
                    $contactData['contact_type'] = 'lead';
                    $contactData['contact_status'] = 'new';
                    $contactData['created_at'] = getCurrentTimestamp();
                    $contactData['updated_at'] = getCurrentTimestamp();
                    
                    // Insert contact
                    $db->insert('contacts', $contactData);
                    $successCount++;
                    
                } catch (Exception $e) {
                    $errorCount++;
                }
            }
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            if ($successCount === 100 && $errorCount === 0) {
                $this->pass("large dataset import ($duration seconds)");
            } else {
                $this->fail("large dataset import - Success: $successCount, Errors: $errorCount");
            }
            
        } catch (Exception $e) {
            $this->fail("large dataset import - Exception: " . $e->getMessage());
        }
    }
    
    private function testDuplicateHandlingWorkflow() {
        echo "  Testing duplicate handling workflow...\n";
        
        try {
            $db = Database::getInstance();
            
            // Clear any existing contacts with this email first
            $db->query("DELETE FROM contacts WHERE email = 'john@example.com'");
            
            // First, create a contact
            $existingContact = [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'source' => 'Mock Integration Test',
                'contact_type' => 'lead',
                'contact_status' => 'new',
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp()
            ];
            
            $db->insert('contacts', $existingContact);
            
            // Now try to import the same contact again
            $csvData = [
                [
                    'First Name' => 'John',
                    'Last Name' => 'Doe',
                    'Email' => 'john@example.com'
                ]
            ];
            
            $fieldMapping = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Email'
            ];
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($csvData as $index => $row) {
                try {
                    $contactData = [];
                    
                    foreach ($fieldMapping as $field => $column) {
                        if (isset($row[$column]) && !empty($row[$column])) {
                            if ($field === 'email') {
                                $contactData[$field] = $row[$column];
                            } else {
                                $contactData[$field] = sanitizeInput($row[$column]);
                            }
                        }
                    }
                    
                    // Validate email
                    if (!empty($contactData['email']) && !validateEmail($contactData['email'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Validate required fields
                    if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Check for duplicate email
                    if (!empty($contactData['email'])) {
                        $existing = $db->fetchOne("SELECT id FROM contacts WHERE email = ?", [$contactData['email']]);
                        if ($existing) {
                            $errorCount++;
                            continue;
                        }
                    }
                    
                    // Add metadata
                    $contactData['source'] = 'Mock Integration Test';
                    $contactData['contact_type'] = 'lead';
                    $contactData['contact_status'] = 'new';
                    $contactData['created_at'] = getCurrentTimestamp();
                    $contactData['updated_at'] = getCurrentTimestamp();
                    
                    // Insert contact
                    $db->insert('contacts', $contactData);
                    $successCount++;
                    
                } catch (Exception $e) {
                    $errorCount++;
                }
            }
            
            if ($successCount === 0 && $errorCount === 1) {
                $this->pass("duplicate handling workflow");
            } else {
                $this->fail("duplicate handling workflow - Success: $successCount, Errors: $errorCount");
            }
            
        } catch (Exception $e) {
            $this->fail("duplicate handling workflow - Exception: " . $e->getMessage());
        }
    }
    
    private function pass($testName) {
        echo "    ✓ $testName\n";
        $this->testResults[] = ['name' => $testName, 'status' => 'PASS'];
    }
    
    private function fail($testName) {
        echo "    ✗ $testName\n";
        $this->testResults[] = ['name' => $testName, 'status' => 'FAIL'];
    }
    
    private function displayResults() {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($test) {
            return $test['status'] === 'PASS';
        }));
        $failed = $total - $passed;
        
        echo "\nMock Import Integration Test Results:\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n";
        
        if ($failed > 0) {
            echo "\nFailed Tests:\n";
            foreach ($this->testResults as $test) {
                if ($test['status'] === 'FAIL') {
                    echo "  - " . $test['name'] . "\n";
                }
            }
        }
    }
}

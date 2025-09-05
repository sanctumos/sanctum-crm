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
 * Mock Import API Test
 * Tests import functionality without requiring HTTP requests
 */

require_once __DIR__ . '/../bootstrap.php';

class MockImportApiTest {
    private $testResults = [];
    
    public function runAllTests() {
        echo "Running Mock Import API Tests...\n";
        
        $this->testImportProcessing();
        $this->testEmailValidation();
        $this->testNameSplitting();
        $this->testErrorHandling();
        
        $this->displayResults();
    }
    
    private function testImportProcessing() {
        echo "  Testing import processing...\n";
        
        // Test data
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
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($csvData as $index => $row) {
            try {
                $contactData = [];
                
                // Map CSV columns to contact fields
                foreach ($fieldMapping as $field => $column) {
                    if (isset($row[$column]) && !empty($row[$column])) {
                        if ($field === 'email') {
                            $contactData[$field] = $row[$column];
                        } else {
                            $contactData[$field] = sanitizeInput($row[$column]);
                        }
                    }
                }
                
                // Validate email if provided
                if (!empty($contactData['email']) && !validateEmail($contactData['email'])) {
                    $errors[] = [
                        'row' => $index + 1,
                        'message' => 'Invalid email address: ' . $contactData['email']
                    ];
                    $errorCount++;
                    continue;
                }
                
                // Validate required fields
                if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                    $missingFields = [];
                    if (empty($contactData['first_name'])) $missingFields[] = 'first_name';
                    if (empty($contactData['last_name'])) $missingFields[] = 'last_name';
                    
                    $errors[] = [
                        'row' => $index + 1,
                        'message' => 'Missing required fields: ' . implode(', ', $missingFields)
                    ];
                    $errorCount++;
                    continue;
                }
                
                // Check for duplicate email
                if (!empty($contactData['email'])) {
                    $db = Database::getInstance();
                    $existing = $db->fetchOne("SELECT id FROM contacts WHERE email = ?", [$contactData['email']]);
                    if ($existing) {
                        $errors[] = [
                            'row' => $index + 1,
                            'message' => 'Contact with this email already exists'
                        ];
                        $errorCount++;
                        continue;
                    }
                }
                
                // Add metadata
                $contactData['source'] = 'Mock Import Test';
                $contactData['contact_type'] = 'lead';
                $contactData['contact_status'] = 'new';
                $contactData['created_at'] = getCurrentTimestamp();
                $contactData['updated_at'] = getCurrentTimestamp();
                
                // Insert contact
                $db = Database::getInstance();
                $db->insert('contacts', $contactData);
                $successCount++;
                
            } catch (Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'message' => 'Error processing row: ' . $e->getMessage()
                ];
                $errorCount++;
            }
        }
        
        if ($successCount === 2 && $errorCount === 0) {
            $this->pass("import processing");
        } else {
            $this->fail("import processing - Success: $successCount, Errors: $errorCount");
        }
    }
    
    private function testEmailValidation() {
        echo "  Testing email validation...\n";
        
        $testEmails = [
            'valid@example.com' => true,
            'invalid-email' => false,
            'test@' => false,
            '@example.com' => false,
            'user.name@domain.co.uk' => true,
            'test+tag@example.org' => true
        ];
        
        $allPassed = true;
        foreach ($testEmails as $email => $expected) {
            $result = validateEmail($email);
            if ($result !== $expected) {
                $allPassed = false;
                break;
            }
        }
        
        if ($allPassed) {
            $this->pass("email validation");
        } else {
            $this->fail("email validation");
        }
    }
    
    private function testNameSplitting() {
        echo "  Testing name splitting...\n";
        
        $testCases = [
            ['John Doe', ' ', 0, 1, 'John', 'Doe'],
            ['Smith, John', ', ', 1, 0, 'John', 'Smith'],
            ['Mary|Smith', '|', 0, 1, 'Mary', 'Smith']
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
            $this->pass("name splitting");
        } else {
            $this->fail("name splitting");
        }
    }
    
    private function testErrorHandling() {
        echo "  Testing error handling...\n";
        
        // Test with missing required fields
        $csvData = [
            [
                'First Name' => 'John',
                'Last Name' => '', // Missing last name
                'Email' => 'john@example.com'
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email'
        ];
        
        $errorCount = 0;
        foreach ($csvData as $index => $row) {
            $contactData = [];
            
            foreach ($fieldMapping as $field => $column) {
                if (isset($row[$column]) && !empty($row[$column])) {
                    $contactData[$field] = sanitizeInput($row[$column]);
                }
            }
            
            if (empty($contactData['first_name']) || empty($contactData['last_name'])) {
                $errorCount++;
            }
        }
        
        if ($errorCount === 1) {
            $this->pass("error handling");
        } else {
            $this->fail("error handling");
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
        
        echo "\nMock Import API Test Results:\n";
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

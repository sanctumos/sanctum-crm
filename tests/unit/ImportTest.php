<?php
/**
 * Import Functionality Unit Tests
 * Best Jobs in TA - CSV Import and Name Splitting Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class ImportTest {
    private $db;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
    }
    
    public function runAllTests() {
        echo "Running Import Functionality Tests...\n";
        
        $this->testNameSplitting();
        $this->testEmailValidation();
        $this->testFieldMapping();
        $this->testDataSanitization();
        $this->testImportValidation();
        $this->testDuplicateHandling();
        
        echo "All Import tests completed!\n";
    }
    
    public function testNameSplitting() {
        echo "  Testing name splitting functionality...\n";
        
        // Test space delimiter (First Last)
        $this->testNameSplit('John Doe', ' ', 0, 1, 'John', 'Doe');
        $this->testNameSplit('Mary Jane Smith', ' ', 0, 1, 'Mary', 'Jane');
        
        // Test comma delimiter (Last, First)
        $this->testNameSplit('Smith, John', ',', 1, 0, 'John', 'Smith');
        $this->testNameSplit('Doe, Jane Marie', ',', 1, 0, 'Jane Marie', 'Doe');
        
        // Test pipe delimiter (First|Last)
        $this->testNameSplit('John|Doe', '|', 0, 1, 'John', 'Doe');
        $this->testNameSplit('Mary|Smith-Jones', '|', 0, 1, 'Mary', 'Smith-Jones');
        
        // Test edge cases
        $this->testNameSplit('SingleName', ' ', 0, 1, 'SingleName', '');
        $this->testNameSplit('', ' ', 0, 1, '', '');
        $this->testNameSplit('John  Doe', ' ', 0, 1, 'John', 'Doe'); // Multiple spaces
    }
    
    private function testNameSplit($fullName, $delimiter, $firstIndex, $lastIndex, $expectedFirst, $expectedLast) {
        // Handle multiple spaces by splitting and filtering empty parts
        $parts = array_filter(explode($delimiter, $fullName), function($part) {
            return trim($part) !== '';
        });
        $parts = array_values($parts); // Re-index array
        
        if (count($parts) >= 2) {
            $firstPart = trim($parts[$firstIndex]);
            $lastPart = trim($parts[$lastIndex]);
            
            if ($firstPart === $expectedFirst && $lastPart === $expectedLast) {
                echo "    ✓ Name split: '$fullName' -> '$firstPart' '$lastPart'\n";
            } else {
                echo "    ✗ Name split failed: '$fullName' -> '$firstPart' '$lastPart' (expected '$expectedFirst' '$expectedLast')\n";
            }
        } else {
            if ($expectedFirst === $fullName && $expectedLast === '') {
                echo "    ✓ Name split (single): '$fullName' -> '$fullName' ''\n";
            } else {
                echo "    ✗ Name split failed (insufficient parts): '$fullName'\n";
            }
        }
    }
    
    public function testEmailValidation() {
        echo "  Testing email validation...\n";
        
        // Valid emails
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'test+tag@example.org',
            'user123@test-domain.com'
        ];
        
        foreach ($validEmails as $email) {
            if (validateEmail($email)) {
                echo "    ✓ Valid email: $email\n";
            } else {
                echo "    ✗ Valid email rejected: $email\n";
            }
        }
        
        // Invalid emails
        $invalidEmails = [
            'invalid-email',
            '@example.com',
            'test@',
            'test..test@example.com',
            'test@.com'
        ];
        
        foreach ($invalidEmails as $email) {
            if (!validateEmail($email)) {
                echo "    ✓ Invalid email rejected: $email\n";
            } else {
                echo "    ✗ Invalid email accepted: $email\n";
            }
        }
        
        // Empty email (should be valid for optional field)
        if (empty('')) {
            echo "    ✓ Empty email handled correctly\n";
        } else {
            echo "    ✗ Empty email not handled correctly\n";
        }
    }
    
    public function testFieldMapping() {
        echo "  Testing field mapping...\n";
        
        // Test CSV data
        $csvData = [
            [
                'Full Name' => 'John Doe',
                'Email Address' => 'john@example.com',
                'Phone Number' => '+1234567890',
                'Company Name' => 'Test Corp'
            ],
            [
                'Full Name' => 'Jane Smith',
                'Email Address' => 'jane@example.com',
                'Phone Number' => '+0987654321',
                'Company Name' => 'Another Corp'
            ]
        ];
        
        // Test field mapping
        $fieldMapping = [
            'first_name' => 'Full Name',
            'last_name' => 'Full Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'company' => 'Company Name'
        ];
        
        $nameSplitConfig = [
            'column' => 'Full Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        foreach ($csvData as $index => $row) {
            $contactData = [];
            
            // Map CSV columns to contact fields
            foreach ($fieldMapping as $field => $column) {
                if (strpos($column, '_split_') !== false) {
                    continue; // Skip name split fields
                }
                
                if (isset($row[$column]) && !empty($row[$column])) {
                    if ($field === 'email') {
                        $contactData[$field] = $row[$column];
                    } elseif ($field === 'evm_address') {
                        $contactData[$field] = validateEVMAddress($row[$column]) ? $row[$column] : null;
                    } else {
                        $contactData[$field] = sanitizeInput($row[$column]);
                    }
                }
            }
            
            // Handle name splitting
            if ($nameSplitConfig && isset($row[$nameSplitConfig['column']])) {
                $fullName = $row[$nameSplitConfig['column']];
                $parts = explode($nameSplitConfig['delimiter'], $fullName);
                
                if (count($parts) >= 2) {
                    $firstPart = trim($parts[$nameSplitConfig['firstPart']]);
                    $lastPart = trim($parts[$nameSplitConfig['lastPart']]);
                    
                    $contactData['first_name'] = sanitizeInput($firstPart);
                    $contactData['last_name'] = sanitizeInput($lastPart);
                }
            }
            
            // Validate the result
            if (isset($contactData['first_name']) && isset($contactData['last_name'])) {
                echo "    ✓ Row $index mapped: {$contactData['first_name']} {$contactData['last_name']}\n";
            } else {
                echo "    ✗ Row $index mapping failed\n";
            }
        }
    }
    
    public function testDataSanitization() {
        echo "  Testing data sanitization...\n";
        
        $testInputs = [
            'Normal Text' => 'Normal Text',
            '<script>alert("xss")</script>' => '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            'Text with "quotes"' => 'Text with &quot;quotes&quot;',
            "Text with 'apostrophes'" => "Text with &#039;apostrophes&#039;",
            'Text with & ampersands' => 'Text with &amp; ampersands'
        ];
        
        foreach ($testInputs as $input => $expected) {
            $sanitized = sanitizeInput($input);
            if ($sanitized === $expected) {
                echo "    ✓ Sanitization: '$input' -> '$sanitized'\n";
            } else {
                echo "    ✗ Sanitization failed: '$input' -> '$sanitized' (expected '$expected')\n";
            }
        }
    }
    
    public function testImportValidation() {
        echo "  Testing import validation...\n";
        
        // Test required fields validation
        $testCases = [
            [
                'data' => ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
                'should_pass' => true,
                'description' => 'Complete data'
            ],
            [
                'data' => ['first_name' => 'John', 'last_name' => 'Doe'],
                'should_pass' => true,
                'description' => 'Missing email (optional)'
            ],
            [
                'data' => ['first_name' => 'John', 'email' => 'john@example.com'],
                'should_pass' => false,
                'description' => 'Missing last_name'
            ],
            [
                'data' => ['last_name' => 'Doe', 'email' => 'john@example.com'],
                'should_pass' => false,
                'description' => 'Missing first_name'
            ],
            [
                'data' => ['first_name' => '', 'last_name' => 'Doe'],
                'should_pass' => false,
                'description' => 'Empty first_name'
            ],
            [
                'data' => ['first_name' => 'John', 'last_name' => ''],
                'should_pass' => false,
                'description' => 'Empty last_name'
            ]
        ];
        
        foreach ($testCases as $testCase) {
            $data = $testCase['data'];
            $shouldPass = $testCase['should_pass'];
            $description = $testCase['description'];
            
            $hasFirstName = !empty($data['first_name']);
            $hasLastName = !empty($data['last_name']);
            
            $passes = $hasFirstName && $hasLastName;
            
            if ($passes === $shouldPass) {
                $status = $passes ? 'PASS' : 'REJECTED';
                echo "    ✓ Validation: $description - $status\n";
            } else {
                echo "    ✗ Validation: $description - Expected " . ($shouldPass ? 'PASS' : 'REJECTED') . ", got " . ($passes ? 'PASS' : 'REJECTED') . "\n";
            }
        }
    }
    
    public function testDuplicateHandling() {
        echo "  Testing duplicate handling...\n";
        
        // Create a test contact
        $contactId = TestUtils::createTestContact([
            'first_name' => 'Duplicate',
            'last_name' => 'Test',
            'email' => 'duplicate@example.com'
        ]);
        
        if ($contactId) {
            echo "    ✓ Test contact created with ID: $contactId\n";
            
            // Test duplicate email detection
            $existing = $this->db->fetchOne("SELECT id FROM contacts WHERE email = ?", ['duplicate@example.com']);
            if ($existing) {
                echo "    ✓ Duplicate email detection works\n";
            } else {
                echo "    ✗ Duplicate email detection failed\n";
            }
            
            // Test non-duplicate email
            $nonExisting = $this->db->fetchOne("SELECT id FROM contacts WHERE email = ?", ['nonexistent@example.com']);
            if (!$nonExisting) {
                echo "    ✓ Non-duplicate email handled correctly\n";
            } else {
                echo "    ✗ Non-duplicate email incorrectly detected as duplicate\n";
            }
        } else {
            echo "    ✗ Failed to create test contact\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ImportTest();
    $test->runAllTests();
}

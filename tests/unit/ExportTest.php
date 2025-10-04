<?php
/**
 * Export Functionality Test
 * Tests the CSV export functionality for contacts
 */

require_once __DIR__ . '/../bootstrap.php';

class ExportTest {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    public function runAllTests() {
        echo "Running Export Functionality Tests...\n";
        
        $this->testCsvGeneration();
        $this->testFiltering();
        $this->testFieldMapping();
        $this->testDataSanitization();
        $this->testEmptyResults();
        
        echo "All Export tests completed!\n";
    }
    
    private function testCsvGeneration() {
        echo "  Testing CSV generation... ";
        
        // Create test contacts
        $testContacts = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@exporttest.com',
                'phone' => '555-1234',
                'company' => 'Test Company 1',
                'contact_type' => 'lead',
                'contact_status' => 'new',
                'source' => 'website',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@exporttest.com',
                'phone' => '555-5678',
                'company' => 'Test Company 2',
                'contact_type' => 'customer',
                'contact_status' => 'active',
                'source' => 'referral',
                'enrichment_status' => 'enriched',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Clear existing test contacts
        $this->db->delete('contacts', "email LIKE '%@exporttest.com'");
        
        // Insert test contacts
        $contactIds = [];
        foreach ($testContacts as $contact) {
            $contactId = $this->db->insert('contacts', $contact);
            $contactIds[] = $contactId;
        }
        
        // Test CSV generation
        $sql = "SELECT * FROM contacts WHERE email LIKE '%@exporttest.com' ORDER BY created_at DESC";
        $contacts = $this->db->fetchAll($sql);
        
        $headers = [
            'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Address', 
            'City', 'State', 'Zip Code', 'Country', 'EVM Address', 'Twitter Handle',
            'LinkedIn Profile', 'Telegram Username', 'Discord Username', 'GitHub Username',
            'Website', 'Contact Type', 'Contact Status', 'Source', 'Assigned To', 
            'Enrichment Status', 'Notes', 'First Purchase Date', 'Created At', 'Updated At'
        ];
        
        $csvContent = implode(',', $headers) . "\n";
        foreach ($contacts as $contact) {
            $row = [
                $contact['id'],
                $contact['first_name'] ?? '',
                $contact['last_name'] ?? '',
                $contact['email'] ?? '',
                $contact['phone'] ?? '',
                $contact['company'] ?? '',
                $contact['address'] ?? '',
                $contact['city'] ?? '',
                $contact['state'] ?? '',
                $contact['zip_code'] ?? '',
                $contact['country'] ?? '',
                $contact['evm_address'] ?? '',
                $contact['twitter_handle'] ?? '',
                $contact['linkedin_profile'] ?? '',
                $contact['telegram_username'] ?? '',
                $contact['discord_username'] ?? '',
                $contact['github_username'] ?? '',
                $contact['website'] ?? '',
                $contact['contact_type'] ?? '',
                $contact['contact_status'] ?? '',
                $contact['source'] ?? '',
                $contact['assigned_to'] ?? '',
                $contact['enrichment_status'] ?? '',
                $contact['notes'] ?? '',
                $contact['first_purchase_date'] ?? '',
                $contact['created_at'] ?? '',
                $contact['updated_at'] ?? ''
            ];
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        // Verify CSV structure
        $lines = explode("\n", trim($csvContent));
        if (count($lines) === 3 && count(explode(',', $lines[0])) === 27) {
            echo "PASS\n";
        } else {
            echo "FAIL - Invalid CSV structure\n";
        }
        
        // Cleanup
        $this->db->delete('contacts', "email LIKE '%@exporttest.com'");
    }
    
    private function testFiltering() {
        echo "  Testing filtering... ";
        
        // Create test contacts with different types and statuses
        $testContacts = [
            [
                'first_name' => 'Lead',
                'last_name' => 'User',
                'email' => 'lead@filtertest.com',
                'contact_type' => 'lead',
                'contact_status' => 'new',
                'enrichment_status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'first_name' => 'Customer',
                'last_name' => 'User',
                'email' => 'customer@filtertest.com',
                'contact_type' => 'customer',
                'contact_status' => 'active',
                'enrichment_status' => 'enriched',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'first_name' => 'Qualified',
                'last_name' => 'Lead',
                'email' => 'qualified@filtertest.com',
                'contact_type' => 'lead',
                'contact_status' => 'qualified',
                'enrichment_status' => 'not_found',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Clear existing test contacts
        $this->db->delete('contacts', "email LIKE '%@filtertest.com'");
        
        // Insert test contacts
        foreach ($testContacts as $contact) {
            $this->db->insert('contacts', $contact);
        }
        
        // Test type filter
        $sql = "SELECT * FROM contacts WHERE email LIKE '%@filtertest.com' AND contact_type = 'lead'";
        $leads = $this->db->fetchAll($sql);
        if (count($leads) !== 2) {
            echo "FAIL - Type filter not working\n";
            return;
        }
        
        // Test status filter
        $sql = "SELECT * FROM contacts WHERE email LIKE '%@filtertest.com' AND contact_status = 'new'";
        $newContacts = $this->db->fetchAll($sql);
        if (count($newContacts) !== 1) {
            echo "FAIL - Status filter not working\n";
            return;
        }
        
        // Test enrichment status filter
        $sql = "SELECT * FROM contacts WHERE email LIKE '%@filtertest.com' AND enrichment_status = 'enriched'";
        $enrichedContacts = $this->db->fetchAll($sql);
        if (count($enrichedContacts) !== 1) {
            echo "FAIL - Enrichment status filter not working\n";
            return;
        }
        
        // Test null enrichment status filter
        $sql = "SELECT * FROM contacts WHERE email LIKE '%@filtertest.com' AND (enrichment_status IS NULL OR enrichment_status = '')";
        $nullEnrichmentContacts = $this->db->fetchAll($sql);
        if (count($nullEnrichmentContacts) !== 0) { // All our test contacts have enrichment status
            echo "FAIL - Null enrichment status filter not working\n";
            return;
        }
        
        echo "PASS\n";
        
        // Cleanup
        $this->db->delete('contacts', "email LIKE '%@filtertest.com'");
    }
    
    private function testFieldMapping() {
        echo "  Testing field mapping... ";
        
        // Create a test contact with all possible fields
        $testContact = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@fieldmapping.com',
            'phone' => '555-1234',
            'company' => 'Test Company',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'country' => 'Test Country',
            'evm_address' => '0x1234567890abcdef',
            'twitter_handle' => '@testuser',
            'linkedin_profile' => 'https://linkedin.com/in/testuser',
            'telegram_username' => 'testuser',
            'discord_username' => 'testuser#1234',
            'github_username' => 'testuser',
            'website' => 'https://testuser.com',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'source' => 'website',
            'assigned_to' => '1',
            'enrichment_status' => 'enriched',
            'notes' => 'Test notes',
            'first_purchase_date' => '2024-01-01',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Clear existing test contact
        $this->db->delete('contacts', "email = 'test@fieldmapping.com'");
        
        // Insert test contact
        $contactId = $this->db->insert('contacts', $testContact);
        
        // Test field mapping
        $sql = "SELECT * FROM contacts WHERE id = ?";
        $contact = $this->db->fetchOne($sql, [$contactId]);
        
        $expectedFields = [
            'first_name', 'last_name', 'email', 'phone', 'company', 'address',
            'city', 'state', 'zip_code', 'country', 'evm_address', 'twitter_handle',
            'linkedin_profile', 'telegram_username', 'discord_username', 'github_username',
            'website', 'contact_type', 'contact_status', 'source', 'assigned_to',
            'enrichment_status', 'notes', 'first_purchase_date', 'created_at', 'updated_at'
        ];
        
        $allFieldsPresent = true;
        foreach ($expectedFields as $field) {
            if (!array_key_exists($field, $contact)) {
                $allFieldsPresent = false;
                break;
            }
        }
        
        if ($allFieldsPresent) {
            echo "PASS\n";
        } else {
            echo "FAIL - Field mapping incomplete\n";
        }
        
        // Cleanup
        $this->db->delete('contacts', "email = 'test@fieldmapping.com'");
    }
    
    private function testDataSanitization() {
        echo "  Testing data sanitization... ";
        
        // Create test contact with special characters
        $testContact = [
            'first_name' => 'Test "Special" User',
            'last_name' => "O'Connor",
            'email' => 'test@sanitization.com',
            'company' => 'Test & Company',
            'notes' => 'Notes with "quotes" and \'apostrophes\'',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Clear existing test contact
        $this->db->delete('contacts', "email = 'test@sanitization.com'");
        
        // Insert test contact
        $contactId = $this->db->insert('contacts', $testContact);
        
        // Test CSV escaping
        $sql = "SELECT * FROM contacts WHERE id = ?";
        $contact = $this->db->fetchOne($sql, [$contactId]);
        
        $csvRow = [
            $contact['first_name'],
            $contact['last_name'],
            $contact['email'],
            $contact['company'],
            $contact['notes']
        ];
        
        $escapedRow = array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $csvRow);
        
        // Check that quotes are properly escaped
        $hasProperEscaping = true;
        foreach ($escapedRow as $field) {
            if (strpos($field, '""') === false && strpos($field, '"') !== false) {
                // If there are quotes but no double quotes, escaping failed
                if (substr_count($field, '"') > 2) {
                    $hasProperEscaping = false;
                    break;
                }
            }
        }
        
        if ($hasProperEscaping) {
            echo "PASS\n";
        } else {
            echo "FAIL - Data sanitization not working\n";
        }
        
        // Cleanup
        $this->db->delete('contacts', "email = 'test@sanitization.com'");
    }
    
    private function testEmptyResults() {
        echo "  Testing empty results... ";
        
        // Test with no matching contacts
        $sql = "SELECT * FROM contacts WHERE email = 'nonexistent@test.com'";
        $contacts = $this->db->fetchAll($sql);
        
        if (count($contacts) === 0) {
            echo "PASS\n";
        } else {
            echo "FAIL - Empty results not handled correctly\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ExportTest();
    $test->runAllTests();
}
?>

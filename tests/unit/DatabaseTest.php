<?php
/**
 * Database Unit Tests
 * FreeOpsDAO CRM - Database Class Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class DatabaseTest {
    private $db;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
    }
    
    public function runAllTests() {
        echo "Running Database Tests...\n";
        
        $this->testConnection();
        $this->testInsert();
        $this->testFetchOne();
        $this->testFetchAll();
        $this->testUpdate();
        $this->testDelete();
        $this->testTransactions();
        $this->testTableInfo();
        
        echo "All Database tests completed!\n";
    }
    
    public function testConnection() {
        echo "  Testing database connection... ";
        
        try {
            $connection = $this->db->getConnection();
            if ($connection instanceof PDO) {
                echo "PASS\n";
            } else {
                echo "FAIL - Connection not PDO instance\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testInsert() {
        echo "  Testing insert operation... ";
        
        try {
            $data = [
                'first_name' => 'Test',
                'last_name' => 'Insert',
                'email' => 'testinsert@example.com',
                'contact_type' => 'lead',
                'contact_status' => 'new'
            ];
            
            $id = $this->db->insert('contacts', $data);
            
            if ($id && is_numeric($id)) {
                echo "PASS (ID: $id)\n";
                
                // Clean up
                $this->db->delete('contacts', 'id = ?', [$id]);
            } else {
                echo "FAIL - Invalid ID returned\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testFetchOne() {
        echo "  Testing fetchOne operation... ";
        
        try {
            // Create test data
            $contactId = TestUtils::createTestContact();
            
            $result = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
            
            if ($result && isset($result['id']) && $result['id'] == $contactId) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid result\n";
            }
            
            // Clean up
            $this->db->delete('contacts', 'id = ?', [$contactId]);
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testFetchAll() {
        echo "  Testing fetchAll operation... ";
        
        try {
            // Create test data
            $contactId1 = TestUtils::createTestContact(['email' => 'test1@example.com']);
            $contactId2 = TestUtils::createTestContact(['email' => 'test2@example.com']);
            
            $results = $this->db->fetchAll("SELECT * FROM contacts WHERE id IN (?, ?)", [$contactId1, $contactId2]);
            
            if (is_array($results) && count($results) == 2) {
                echo "PASS (" . count($results) . " records)\n";
            } else {
                echo "FAIL - Invalid result count\n";
            }
            
            // Clean up
            $this->db->delete('contacts', 'id IN (?, ?)', [$contactId1, $contactId2]);
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testUpdate() {
        echo "  Testing update operation... ";
        
        try {
            // Create test data
            $uniq = uniqid();
            $contactId = TestUtils::createTestContact(['first_name' => 'ToUpdate_' . $uniq]);
            
            $updateData = [
                'first_name' => 'Updated_' . $uniq,
                'contact_status' => 'qualified'
            ];
            
            $affected = $this->db->update('contacts', $updateData, 'id = :id', ['id' => $contactId]);
            
            if ($affected == 1) {
                // Verify update
                $result = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
                if ($result['first_name'] === $updateData['first_name'] && $result['contact_status'] === 'qualified') {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Update not reflected\n";
                }
            } else {
                echo "FAIL - No rows affected\n";
            }
            
            // Clean up
            $this->db->delete('contacts', 'id = ?', [$contactId]);
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testDelete() {
        echo "  Testing delete operation... ";
        
        try {
            // Create test data
            $contactId = TestUtils::createTestContact();
            
            $deleted = $this->db->delete('contacts', 'id = ?', [$contactId]);
            
            if ($deleted == 1) {
                // Verify deletion
                $result = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
                if (!$result) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Record still exists\n";
                }
            } else {
                echo "FAIL - No rows deleted\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testTransactions() {
        echo "  Testing transaction operations... ";
        
        try {
            $this->db->beginTransaction();
            
            // Create test data
            $contactId = TestUtils::createTestContact();
            
            // Rollback
            $this->db->rollback();
            
            // Verify rollback
            $result = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
            if (!$result) {
                echo "PASS (rollback)\n";
            } else {
                echo "FAIL - Rollback not working\n";
            }
            
            // Test commit
            $this->db->beginTransaction();
            $contactId = TestUtils::createTestContact();
            $this->db->commit();
            
            // Verify commit
            $result = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
            if ($result) {
                echo "  Testing transaction commit... PASS\n";
                
                // Clean up
                $this->db->delete('contacts', 'id = ?', [$contactId]);
            } else {
                echo "  Testing transaction commit... FAIL\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testTableInfo() {
        echo "  Testing table info... ";
        
        try {
            $tableInfo = $this->db->getTableInfo('contacts');
            
            if (is_array($tableInfo) && count($tableInfo) > 0) {
                echo "PASS (" . count($tableInfo) . " columns)\n";
            } else {
                echo "FAIL - No table info returned\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $test = new DatabaseTest();
    $test->runAllTests();
} 
<?php
/**
 * Authentication Unit Tests
 * Best Jobs in TA - Auth Class Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class AuthTest {
    private $auth;
    private $db;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->db = TestUtils::getTestDatabase();
    }
    
    public function runAllTests() {
        echo "Running Authentication Tests...\n";
        
        $this->testUserCreation();
        $this->testUserLogin();
        $this->testApiKeyAuthentication();
        $this->testUserUpdate();
        $this->testUserDeletion();
        $this->testAdminFunctions();
        $this->testPasswordValidation();
        $this->testEmailValidation();
        
        echo "All Authentication tests completed!\n";
    }
    
    public function testUserCreation() {
        echo "  Testing user creation... ";
        
        try {
            $userData = [
                'username' => 'testcreate',
                'email' => 'testcreate@example.com',
                'password' => 'testpass123',
                'first_name' => 'Test',
                'last_name' => 'Create',
                'role' => 'user'
            ];
            
            $user = $this->auth->createUser($userData);
            
            if ($user && isset($user['id']) && isset($user['api_key'])) {
                echo "PASS (ID: {$user['id']})\n";
                
                // Clean up
                $this->db->delete('users', 'id = ?', [$user['id']]);
            } else {
                echo "FAIL - Invalid user data returned\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testUserLogin() {
        echo "  Testing user login... ";
        
        try {
            // Create test user
            $userData = [
                'username' => 'testlogin',
                'email' => 'testlogin@example.com',
                'password' => 'testpass123'
            ];
            
            $user = $this->auth->createUser($userData);
            
            // Test login with username
            $loginResult = $this->auth->login('testlogin', 'testpass123');
            
            if ($loginResult) {
                echo "PASS (username login)\n";
            } else {
                echo "FAIL - Username login failed\n";
            }
            
            // Test login with email
            $loginResult = $this->auth->login('testlogin@example.com', 'testpass123');
            
            if ($loginResult) {
                echo "  Testing email login... PASS\n";
            } else {
                echo "  Testing email login... FAIL\n";
            }
            
            // Test invalid password
            $loginResult = $this->auth->login('testlogin', 'wrongpass');
            
            if (!$loginResult) {
                echo "  Testing invalid password... PASS\n";
            } else {
                echo "  Testing invalid password... FAIL\n";
            }
            
            // Clean up
            $this->db->delete('users', 'id = ?', [$user['id']]);
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testApiKeyAuthentication() {
        echo "  Testing API key authentication... ";
        
        try {
            // Create test user with unique data
            $userData = [
                'username' => 'testapikey',
                'email' => 'testapikey@example.com',
                'password' => 'testpass123'
            ];
            
            $user = $this->auth->createUser($userData);
            
            // Get user with API key
            $userWithKey = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
            
            if ($userWithKey && $userWithKey['api_key']) {
                echo "PASS (API key: " . substr($userWithKey['api_key'], 0, 10) . "...)\n";
                
                // Clean up
                $this->db->delete('users', 'id = ?', [$user['id']]);
            } else {
                echo "FAIL - No API key generated\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testUserUpdate() {
        echo "  Testing user update... ";
        
        try {
            // Check if admin user exists
            $adminUser = $this->db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
            if (!$adminUser) {
                echo "FAIL - Admin user does not exist\n";
                return;
            }
            // Login as admin first
            $loginResult = $this->auth->login('admin', 'admin123');
            if (!$loginResult) {
                echo "FAIL - Could not login as admin (password might be wrong)\n";
                return;
            }
            
            // Create test user with unique data
            $uniq = uniqid();
            $userData = [
                'username' => 'testupdate_' . $uniq,
                'email' => 'testupdate_' . $uniq . '@example.com',
                'password' => 'testpass123'
            ];
            
            $user = $this->auth->createUser($userData);
            // Debug: fetch and log user before update
            $fetchedUser = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
            if (!$fetchedUser) {
                echo "DEBUG: User not found in DB immediately after creation (ID: {$user['id']})\n";
            } else {
                echo "DEBUG: User found in DB before update (ID: {$user['id']})\n";
            }
            
            // Update user data with unique values
            $updateData = [
                'first_name' => 'Updated_' . $uniq,
                'last_name' => 'User_' . $uniq,
                'email' => 'updated_' . $uniq . '@example.com'
            ];
            
            $result = $this->auth->updateUser($user['id'], $updateData, 'id = :id', ['id' => $user['id']]);
            
            if ($result) {
                // Verify update
                $updatedUser = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
                if ($updatedUser['first_name'] === $updateData['first_name'] && $updatedUser['email'] === $updateData['email']) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - Update not reflected\n";
                }
            } else {
                echo "FAIL - Update failed\n";
            }
            
            // Clean up
            $this->db->delete('users', 'id = ?', [$user['id']]);
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testUserDeletion() {
        echo "  Testing user deletion... ";
        
        try {
            // Check if admin user exists
            $adminUser = $this->db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
            if (!$adminUser) {
                echo "FAIL - Admin user does not exist\n";
                return;
            }
            // Login as admin first
            $loginResult = $this->auth->login('admin', 'admin123');
            if (!$loginResult) {
                echo "FAIL - Could not login as admin (password might be wrong)\n";
                return;
            }
            
            // Create test user with unique data
            $userData = [
                'username' => 'testdelete',
                'email' => 'testdelete@example.com',
                'password' => 'testpass123'
            ];
            
            $user = $this->auth->createUser($userData);
            
            $result = $this->auth->deleteUser($user['id']);
            
            if ($result) {
                // Verify deletion
                $deletedUser = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
                if (!$deletedUser) {
                    echo "PASS\n";
                } else {
                    echo "FAIL - User still exists\n";
                }
            } else {
                echo "FAIL - Deletion failed\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testAdminFunctions() {
        echo "  Testing admin functions... ";
        
        try {
            // Create admin user
            $adminData = [
                'username' => 'testadmin',
                'email' => 'testadmin@example.com',
                'password' => 'testpass123',
                'role' => 'admin'
            ];
            
            $admin = $this->auth->createUser($adminData);
            
            // Test admin access
            $this->auth->login('testadmin', 'testpass123');
            
            if ($this->auth->isAdmin()) {
                echo "PASS (admin role)\n";
            } else {
                echo "FAIL - Admin role not working\n";
            }
            
            // Test non-admin access
            $this->auth->logout();
            $this->auth->login('testuser', 'testpass123');
            
            if (!$this->auth->isAdmin()) {
                echo "  Testing non-admin access... PASS\n";
            } else {
                echo "  Testing non-admin access... FAIL\n";
            }
            
            // Clean up
            $this->db->delete('users', 'id = ?', [$admin['id']]);
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testPasswordValidation() {
        echo "  Testing password validation... ";
        
        try {
            // Test valid password
            $validPassword = 'validpass123';
            if (strlen($validPassword) >= PASSWORD_MIN_LENGTH) {
                echo "PASS (valid password)\n";
            } else {
                echo "FAIL - Valid password rejected\n";
            }
            
            // Test short password
            $shortPassword = 'short';
            if (strlen($shortPassword) < PASSWORD_MIN_LENGTH) {
                echo "  Testing short password... PASS\n";
            } else {
                echo "  Testing short password... FAIL\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
    
    public function testEmailValidation() {
        echo "  Testing email validation... ";
        
        try {
            // Test valid email
            $validEmail = 'test@example.com';
            if (validateEmail($validEmail)) {
                echo "PASS (valid email)\n";
            } else {
                echo "FAIL - Valid email rejected\n";
            }
            
            // Test invalid email
            $invalidEmail = 'invalid-email';
            if (!validateEmail($invalidEmail)) {
                echo "  Testing invalid email... PASS\n";
            } else {
                echo "  Testing invalid email... FAIL\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $test = new AuthTest();
    $test->runAllTests();
} 
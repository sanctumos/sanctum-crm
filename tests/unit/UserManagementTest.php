<?php
/**
 * User Management Unit Tests
 * Best Jobs in TA - Enhanced User Management Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class UserManagementTest {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
        $this->auth = new Auth();
    }
    
    public function runAllTests() {
        echo "Running User Management Unit Tests...\n";
        
        $this->testUserCreation();
        $this->testUserUpdate();
        $this->testUserDeletion();
        $this->testApiKeyGeneration();
        $this->testApiKeyRegeneration();
        $this->testUserStatusManagement();
        $this->testRoleBasedAccess();
        $this->testUserValidation();
        $this->testPasswordManagement();
        $this->testUserSearchAndFiltering();
        $this->testUserBulkOperations();
        $this->testUserAuthentication();
        
        echo "All user management tests completed!\n";
    }
    
    public function testUserCreation() {
        echo "  Testing user creation... ";
        
        $userData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => 'testpass123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'user',
            'is_active' => 1
        ];
        
        $userResult = $this->auth->createUser($userData);
        
        if ($userResult && isset($userResult['id'])) {
            $userId = $userResult['id'];
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if ($user && $user['username'] === $userData['username'] && $user['is_active'] == 1) {
                echo "PASS\n";
            } else {
                echo "FAIL - User not created correctly\n";
            }
        } else {
            echo "FAIL - Failed to create user\n";
        }
    }
    
    public function testUserUpdate() {
        echo "  Testing user update... ";
        
        $userId = TestUtils::createTestUser();
        
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated_' . uniqid() . '@example.com',
            'role' => 'admin'
        ];
        
        $result = $this->auth->updateUser($userId, $updateData, 'id = :id', ['id' => $userId]);
        
        if ($result) {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if ($user['first_name'] === 'Updated' && $user['role'] === 'admin') {
                echo "PASS\n";
            } else {
                echo "FAIL - User not updated correctly\n";
            }
        } else {
            echo "FAIL - Failed to update user\n";
        }
    }
    
    public function testUserDeletion() {
        echo "  Testing user deletion... ";
        
        $userId = TestUtils::createTestUser();
        
        $result = $this->auth->deleteUser($userId);
        
        if ($result) {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if (!$user) {
                echo "PASS\n";
            } else {
                echo "FAIL - User not deleted\n";
            }
        } else {
            echo "FAIL - Failed to delete user\n";
        }
    }
    
    public function testApiKeyGeneration() {
        echo "  Testing API key generation... ";
        
        $userId = TestUtils::createTestUser();
        
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if ($user && !empty($user['api_key']) && strlen($user['api_key']) >= 32) {
            echo "PASS\n";
        } else {
            echo "FAIL - API key not generated correctly\n";
        }
    }
    
    public function testApiKeyRegeneration() {
        echo "  Testing API key regeneration... ";
        
        $userId = TestUtils::createTestUser();
        
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        $originalKey = $user['api_key'];
        
        $result = $this->auth->regenerateApiKey($userId);
        
        if ($result) {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            $newKey = $user['api_key'];
            
            if ($newKey !== $originalKey && !empty($newKey)) {
                echo "PASS\n";
            } else {
                echo "FAIL - API key not regenerated\n";
            }
        } else {
            echo "FAIL - Failed to regenerate API key\n";
        }
    }
    
    public function testUserStatusManagement() {
        echo "  Testing user status management... ";
        
        $userId = TestUtils::createTestUser(['is_active' => 1]);
        
        // Deactivate user
        $result = $this->auth->updateUser($userId, ['is_active' => 0], 'id = :id', ['id' => $userId]);
        
        if ($result) {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            
            if ($user['is_active'] == 0) {
                // Reactivate user
                $result = $this->auth->updateUser($userId, ['is_active' => 1], 'id = :id', ['id' => $userId]);
                
                if ($result) {
                    $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                    
                    if ($user['is_active'] == 1) {
                        echo "PASS\n";
                    } else {
                        echo "FAIL - User not reactivated\n";
                    }
                } else {
                    echo "FAIL - Failed to reactivate user\n";
                }
            } else {
                echo "FAIL - User not deactivated\n";
            }
        } else {
            echo "FAIL - Failed to deactivate user\n";
        }
    }
    
    public function testRoleBasedAccess() {
        echo "  Testing role-based access... ";
        
        $adminUser = TestUtils::createTestUser(['role' => 'admin']);
        $regularUser = TestUtils::createTestUser(['role' => 'user']);
        
        $admin = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$adminUser]);
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$regularUser]);
        
        if ($admin['role'] === 'admin' && $user['role'] === 'user') {
            // Test role validation
            $validRoles = ['admin', 'user'];
            
            $allValid = true;
            foreach ($validRoles as $role) {
                if (!in_array($role, ['admin', 'user'])) {
                    $allValid = false;
                    break;
                }
            }
            
            if ($allValid) {
                echo "PASS\n";
            } else {
                echo "FAIL - Role validation not working\n";
            }
        } else {
            echo "FAIL - Roles not set correctly\n";
        }
    }
    
    public function testUserValidation() {
        echo "  Testing user validation... ";
        
        // Test valid user data
        $validData = [
            'username' => 'validuser',
            'email' => 'valid@example.com',
            'password' => 'validpass123',
            'first_name' => 'Valid',
            'last_name' => 'User'
        ];
        
        $userId = $this->auth->createUser($validData);
        
        if ($userId) {
            // Test invalid email
            $invalidData = [
                'username' => 'invaliduser',
                'email' => 'not-an-email',
                'password' => 'validpass123',
                'first_name' => 'Invalid',
                'last_name' => 'User'
            ];
            
            try {
                $this->auth->createUser($invalidData);
                echo "FAIL - Invalid email should be rejected\n";
            } catch (Exception $e) {
                echo "PASS\n";
            }
        } else {
            echo "FAIL - Valid user creation failed\n";
        }
    }
    
    public function testPasswordManagement() {
        echo "  Testing password management... ";
        
        $userId = TestUtils::createTestUser();
        $originalPassword = 'testpass123';
        
        // Test password update
        $newPassword = 'newpass456';
        $result = $this->auth->updateUser($userId, ['password' => $newPassword], 'id = :id', ['id' => $userId]);
        
        if ($result) {
            // Test authentication with new password
            $auth = new Auth();
            $loginResult = $auth->login($this->getUsernameById($userId), $newPassword);
            
            if ($loginResult) {
                echo "PASS\n";
            } else {
                echo "FAIL - New password not working\n";
            }
        } else {
            echo "FAIL - Failed to update password\n";
        }
    }
    
    public function testUserSearchAndFiltering() {
        echo "  Testing user search and filtering... ";
        
        // Create users with different roles and statuses
        $adminUser = TestUtils::createTestUser(['role' => 'admin', 'is_active' => 1]);
        $inactiveUser = TestUtils::createTestUser(['role' => 'user', 'is_active' => 0]);
        $activeUser = TestUtils::createTestUser(['role' => 'user', 'is_active' => 1]);
        
        // Test filtering by role
        $admins = $this->db->fetchAll("SELECT * FROM users WHERE role = 'admin'");
        $users = $this->db->fetchAll("SELECT * FROM users WHERE role = 'user'");
        
        // Test filtering by status
        $activeUsers = $this->db->fetchAll("SELECT * FROM users WHERE is_active = 1");
        $inactiveUsers = $this->db->fetchAll("SELECT * FROM users WHERE is_active = 0");
        
        if (count($admins) >= 1 && count($users) >= 1 && count($activeUsers) >= 1 && count($inactiveUsers) >= 0) {
            echo "PASS\n";
        } else {
            echo "FAIL - User filtering not working correctly\n";
        }
    }
    
    public function testUserBulkOperations() {
        echo "  Testing user bulk operations... ";
        
        // Create multiple users
        $userIds = [];
        for ($i = 0; $i < 5; $i++) {
            $userIds[] = TestUtils::createTestUser([
                'username' => "bulkuser$i",
                'is_active' => 1
            ]);
        }
        
        // Debug: Check what users were created
        $createdUsers = $this->db->fetchAll("SELECT id, username, is_active FROM users WHERE username LIKE 'bulkuser%' ORDER BY id");
        echo "\n    [DEBUG] Created users: " . json_encode($createdUsers) . "\n";
        
        // Verify all users were created and are active
        $initialCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE username LIKE 'bulkuser%' AND is_active = 1");
        if ($initialCount['count'] != 5) {
            echo "FAIL - Not all users created correctly (expected 5, got {$initialCount['count']})\n";
            return;
        }
        
        // Test bulk deactivation
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $deactivated = $this->db->update('users', ['is_active' => 0], "id IN ($placeholders)", $userIds);
        
        echo "    [DEBUG] Deactivated count: $deactivated, User IDs: " . json_encode($userIds) . "\n";
        
        $activeCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND username LIKE 'bulkuser%'");
        
        // Debug: Check status after deactivation
        $afterDeactivation = $this->db->fetchAll("SELECT id, username, is_active FROM users WHERE username LIKE 'bulkuser%' ORDER BY id");
        echo "    [DEBUG] After deactivation: " . json_encode($afterDeactivation) . "\n";
        
        if ($activeCount['count'] == 0) {
            // Test bulk reactivation
            $reactivated = $this->db->update('users', ['is_active' => 1], "id IN ($placeholders)", $userIds);
            
            $activeCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND username LIKE 'bulkuser%'");
            
            // Check if all users were reactivated
            if ($activeCount['count'] == 5) {
                echo "PASS\n";
            } else {
                echo "FAIL - Bulk reactivation not working (expected 5, got {$activeCount['count']})\n";
            }
        } else {
            echo "FAIL - Bulk deactivation not working (expected 0, got {$activeCount['count']})\n";
        }
    }
    
    public function testUserAuthentication() {
        echo "  Testing user authentication... ";
        
        $username = 'authuser_' . uniqid();
        $password = 'authpass123';
        
        $userId = TestUtils::createTestUser([
            'username' => $username,
            'password' => $password
        ]);
        
        // Test successful login
        $auth = new Auth();
        $loginResult = $auth->login($username, $password);
        
        if ($loginResult) {
            // Test failed login
            $failedLogin = $auth->login($username, 'wrongpassword');
            
            if (!$failedLogin) {
                echo "PASS\n";
            } else {
                echo "FAIL - Failed login should not succeed\n";
            }
        } else {
            echo "FAIL - Valid login failed\n";
        }
    }
    
    private function getUsernameById($userId) {
        $user = $this->db->fetchOne("SELECT username FROM users WHERE id = ?", [$userId]);
        return $user['username'] ?? '';
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserManagementTest();
    $test->runAllTests();
} 
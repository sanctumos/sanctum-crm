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
 * Authentication System
 * Best Jobs in TA - Authentication and Authorization
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class Auth {
    private $db;
    private $user = null;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->initSession();
        $this->authenticate();
    }
    
    private function initSession() {
        if (defined('CRM_TESTING')) return; // Skip session in test mode
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_name('crm_session');
            session_start();
        }
    }
    
    private function authenticate() {
        // Check for API key authentication first
        if ($this->authenticateApiKey()) {
            return;
        }
        
        // Check for session authentication
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }
    }
    
    private function authenticateApiKey() {
        $apiKey = null;
        
        // Check Authorization header
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                $apiKey = substr($auth, 7);
            }
        }
        
        // Check query parameter as fallback
        if (!$apiKey && isset($_GET['api_key'])) {
            $apiKey = $_GET['api_key'];
        }
        
        if ($apiKey) {
            $sql = "SELECT * FROM users WHERE api_key = ? AND is_active = 1";
            $user = $this->db->fetchOne($sql, [$apiKey]);
            
            if ($user) {
                $this->user = $user;
                return true;
            }
        }
        
        return false;
    }
    
    private function loadUser($userId) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        $user = $this->db->fetchOne($sql, [$userId]);
        
        if ($user) {
            $this->user = $user;
        }
    }
    
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1";
        $user = $this->db->fetchOne($sql, [$username, $username]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->user = $user;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['last_activity'] = time();
            
            logActivity($user['id'], 'login', 'User logged in successfully');
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        if ($this->user) {
            logActivity($this->user['id'], 'logout', 'User logged out');
        }
        $this->user = null;
        if (!defined('CRM_TESTING')) session_destroy();
        return true;
    }
    
    public function isAuthenticated() {
        return $this->user !== null;
    }
    
    public function isAdmin() {
        return $this->user && $this->user['role'] === 'admin';
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function getUserId() {
        return $this->user ? $this->user['id'] : null;
    }
    
    public function getUserRole() {
        return $this->user ? $this->user['role'] : null;
    }
    
    public function setApiKey($apiKey) {
        // Set API key for testing purposes
        if (defined('CRM_TESTING')) {
            $sql = "SELECT * FROM users WHERE api_key = ? AND is_active = 1";
            $user = $this->db->fetchOne($sql, [$apiKey]);
            
            if ($user) {
                $this->user = $user;
                return true;
            }
        }
        return false;
    }
    
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            if (isApiRequest()) {
                http_response_code(401);
                echo json_encode([
                    'error' => 'Authentication required',
                    'code' => 401
                ]);
            } else {
                if (!defined('CRM_TESTING')) header('Location: /login.php');
            }
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireAuth();
        
        if (!$this->isAdmin()) {
            if (isApiRequest()) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'Admin access required',
                    'code' => 403
                ]);
            } else {
                if (!defined('CRM_TESTING')) header('Location: /pages/error.php?error=access_denied');
            }
            exit;
        }
    }
    
    public function createUser($data) {
        // Validate required fields
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception('Username, email, and password are required');
        }
        
        // Validate email
        if (!validateEmail($data['email'])) {
            throw new Exception('Invalid email address');
        }
        
        // Check if username or email already exists
        $sql = "SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ?";
        $result = $this->db->fetchOne($sql, [$data['username'], $data['email']]);
        
        if ($result['count'] > 0) {
            throw new Exception('Username or email already exists');
        }
        
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Generate API key
        $apiKey = generateApiKey();
        
        // Prepare user data
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'role' => $data['role'] ?? 'user',
            'api_key' => $apiKey
        ];
        
        // Insert user
        $userId = $this->db->insert('users', $userData);
        
        logActivity($this->getUserId(), 'create_user', "Created user: {$data['username']}");
        
        return [
            'id' => $userId,
            'username' => $data['username'],
            'email' => $data['email'],
            'api_key' => $apiKey
        ];
    }
    
    public function updateUser($userId, $data) {
        if (!defined('CRM_TESTING')) {
            $this->requireAdmin();
        }
        
        $updateData = [];
        
        if (isset($data['first_name'])) {
            $updateData['first_name'] = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $updateData['last_name'] = $data['last_name'];
        }
        
        if (isset($data['email']) && validateEmail($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        
        if (isset($data['password']) && strlen($data['password']) >= PASSWORD_MIN_LENGTH) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($updateData)) {
            throw new Exception('No valid data to update');
        }
        
        $updateData['updated_at'] = getCurrentTimestamp();
        
        $this->db->update('users', $updateData, 'id = :id', ['id' => $userId]);
        
        logActivity($this->getUserId(), 'update_user', "Updated user: $userId");
        
        return true;
    }
    
    public function regenerateApiKey($userId) {
        if (!defined('CRM_TESTING')) {
            $this->requireAdmin();
        }
        
        $apiKey = generateApiKey();
        
        $this->db->update('users', ['api_key' => $apiKey], 'id = :id', ['id' => $userId]);
        
        logActivity($this->getUserId(), 'regenerate_api_key', "Regenerated API key for user: $userId");
        
        return $apiKey;
    }
    
    public function deleteUser($userId) {
        if (!defined('CRM_TESTING')) {
            $this->requireAdmin();
        }
        
        // Don't allow self-deletion
        if ($userId == $this->getUserId()) {
            throw new Exception('Cannot delete your own account');
        }
        
        $this->db->delete('users', 'id = ?', [$userId]);
        
        logActivity($this->getUserId(), 'delete_user', "Deleted user: $userId");
        
        return true;
    }
    
    public function getAllUsers() {
        // Note: Admin check should be done by the calling code to avoid exit()
        $sql = "SELECT id, username, email, first_name, last_name, role, is_active, api_key, created_at 
                FROM users ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getUserById($userId) {
        // Note: Admin check should be done by the calling code to avoid exit()
        $sql = "SELECT id, username, email, first_name, last_name, role, is_active, api_key, created_at 
                FROM users WHERE id = ?";
        
        return $this->db->fetchOne($sql, [$userId]);
    }

    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
    
    public function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
} 
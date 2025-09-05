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
 * Installation Manager
 * Handles first boot installation process for Sanctum CRM
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class InstallationManager {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
    }
    
    /**
     * Check if first boot is needed
     */
    public function isFirstBoot() {
        return $this->config->isFirstBoot();
    }
    
    /**
     * Get current installation step
     */
    public function getCurrentStep() {
        return $this->config->getCurrentInstallationStep();
    }
    
    /**
     * Get installation progress
     */
    public function getInstallationProgress() {
        return $this->config->getInstallationProgress();
    }
    
    /**
     * Complete an installation step
     */
    public function completeStep($step, $data = null) {
        return $this->config->completeInstallationStep($step, $data);
    }
    
    /**
     * Validate environment requirements
     */
    public function validateEnvironment() {
        $errors = [];
        $warnings = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $errors[] = 'PHP 8.0 or higher is required. Current version: ' . PHP_VERSION;
        }
        
        // Check required extensions
        $requiredExtensions = ['sqlite3', 'json', 'curl', 'mbstring', 'openssl', 'session', 'pdo', 'pdo_sqlite'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "Required PHP extension '{$ext}' is not loaded";
            }
        }
        
        // Check database directory permissions
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            if (!mkdir($dbDir, 0755, true)) {
                $errors[] = 'Cannot create database directory: ' . $dbDir;
            }
        } elseif (!is_writable($dbDir)) {
            $errors[] = 'Database directory is not writable: ' . $dbDir;
        }
        
        // Check if database file can be created
        if (!file_exists(DB_PATH)) {
            if (!touch(DB_PATH)) {
                $errors[] = 'Cannot create database file: ' . DB_PATH;
            }
        } elseif (!is_writable(DB_PATH)) {
            $errors[] = 'Database file is not writable: ' . DB_PATH;
        }
        
        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1' && $memoryLimit < 128 * 1024 * 1024) {
            $warnings[] = 'Memory limit is low. Consider increasing to 128M or higher. Current: ' . $memoryLimit;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Initialize database
     */
    public function initializeDatabase() {
        try {
            // Database initialization is handled by Database class constructor
            // This method just ensures it's working
            $this->db->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Set up company information
     */
    public function setupCompany($companyName, $timezone = 'UTC') {
        // Validate company name
        if (empty($companyName) || trim($companyName) === '') {
            return false;
        }
        
        if (strlen($companyName) > 255) {
            return false;
        }
        
        if (preg_match('/<[^>]*>/', $companyName)) {
            return false;
        }
        
        // Validate timezone
        if (!in_array($timezone, timezone_identifiers_list())) {
            return false;
        }
        
        return $this->config->setCompanyInfo([
            'company_name' => trim($companyName),
            'timezone' => $timezone
        ]);
    }
    
    /**
     * Create initial admin user
     */
    public function createAdminUser($username, $email, $password, $firstName = 'Admin', $lastName = 'User') {
        // Check if admin already exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE role = 'admin'");
        if ($existing) {
            return false; // Admin already exists
        }
        
        // Validate input
        if (empty($username) || trim($username) === '') {
            return false;
        }
        
        if (empty($email) || trim($email) === '') {
            return false;
        }
        
        if (empty($password) || trim($password) === '') {
            return false;
        }
        
        if (empty($firstName) || trim($firstName) === '') {
            return false;
        }
        
        if (empty($lastName) || trim($lastName) === '') {
            return false;
        }
        
        // Validate lengths
        if (strlen($username) > 255) {
            return false;
        }
        
        if (strlen($firstName) > 255) {
            return false;
        }
        
        if (strlen($lastName) > 255) {
            return false;
        }
        
        // Validate special characters
        if (preg_match('/<[^>]*>/', $username) || preg_match('/<[^>]*>/', $firstName) || preg_match('/<[^>]*>/', $lastName)) {
            return false;
        }
        
        if (!validateEmail($email)) {
            return false;
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return false;
        }
        
        // Check for existing username or email
        $existingUser = $this->db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existingUser) {
            return false;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $apiKey = generateApiKey();
        
        // Insert admin user
        $result = $this->db->insert('users', [
            'username' => trim($username),
            'email' => trim($email),
            'password_hash' => $passwordHash,
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'role' => 'admin',
            'api_key' => $apiKey,
            'is_active' => 1,
            'created_at' => getCurrentTimestamp(),
            'updated_at' => getCurrentTimestamp()
        ]);
        
        return $result !== false;
    }
    
    /**
     * Set up default configuration
     */
    public function setupDefaultConfig() {
        $defaultConfigs = [
            'application' => [
                'app_name' => 'Sanctum CRM',
                'app_url' => 'http://localhost',
                'timezone' => 'UTC'
            ],
            'security' => [
                'session_lifetime' => 3600,
                'api_rate_limit' => 1000,
                'password_min_length' => 8
            ],
            'database' => [
                'backup_enabled' => false
            ]
        ];
        
        foreach ($defaultConfigs as $category => $configs) {
            $this->config->setCategory($category, $configs);
        }
        
        return true;
    }
    
    /**
     * Complete installation
     */
    public function completeInstallation() {
        // Mark all steps as completed
        $steps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($steps as $step) {
            $this->completeStep($step);
        }
        
        return true;
    }
    
    /**
     * Reset installation (for testing)
     */
    public function resetInstallation() {
        // Clear installation state
        $this->db->query("DELETE FROM installation_state");
        
        // Clear company info
        $this->db->query("DELETE FROM company_info");
        
        // Clear system config
        $this->db->query("DELETE FROM system_config");
        
        // Clear admin users
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
        
        return true;
    }
    
    /**
     * Get installation status
     */
    public function getInstallationStatus() {
        $steps = [
            'environment' => 'Environment Check',
            'database' => 'Database Setup',
            'company' => 'Company Configuration',
            'admin' => 'Admin Account',
            'complete' => 'Installation Complete'
        ];
        
        $progress = $this->getInstallationProgress();
        $completedSteps = array_column($progress, 'step');
        
        $status = [];
        foreach ($steps as $step => $name) {
            $status[$step] = [
                'name' => $name,
                'completed' => in_array($step, $completedSteps),
                'current' => $this->getCurrentStep() === $step
            ];
        }
        
        return $status;
    }
    
    /**
     * Validate step data
     */
    public function validateStep($step, $data) {
        switch ($step) {
            case 'environment':
                return $this->validateEnvironment();
                
            case 'database':
                return $this->initializeDatabase() ? 
                    ['valid' => true, 'errors' => [], 'warnings' => []] : 
                    ['valid' => false, 'errors' => ['Database initialization failed'], 'warnings' => []];
                
            case 'company':
                $errors = [];
                if (empty($data['company_name']) || trim($data['company_name']) === '') {
                    $errors[] = 'Company name is required';
                } elseif (strlen($data['company_name']) > 255) {
                    $errors[] = 'Company name is too long';
                } elseif (preg_match('/<[^>]*>/', $data['company_name'])) {
                    $errors[] = 'Company name contains invalid characters';
                }
                
                return [
                    'valid' => empty($errors),
                    'errors' => $errors,
                    'warnings' => []
                ];
                
            case 'admin':
                $errors = [];
                if (empty($data['username']) || trim($data['username']) === '') {
                    $errors[] = 'Username is required';
                } elseif (strlen($data['username']) > 255) {
                    $errors[] = 'Username is too long';
                } elseif (preg_match('/<[^>]*>/', $data['username'])) {
                    $errors[] = 'Username contains invalid characters';
                }
                
                if (empty($data['email']) || trim($data['email']) === '') {
                    $errors[] = 'Email is required';
                } elseif (!validateEmail($data['email'])) {
                    $errors[] = 'Invalid email format';
                }
                
                if (empty($data['password']) || trim($data['password']) === '') {
                    $errors[] = 'Password is required';
                } elseif (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                    $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
                }
                
                return [
                    'valid' => empty($errors),
                    'errors' => $errors,
                    'warnings' => []
                ];
                
            case 'complete':
                return ['valid' => true, 'errors' => [], 'warnings' => []];
                
            default:
                return ['valid' => false, 'errors' => ['Invalid step'], 'warnings' => []];
        }
    }
}

<?php
/**
 * Test Bootstrap
 * FreeOpsDAO CRM - Test Environment Setup
 */

// Set test environment
if (!defined('CRM_TESTING')) define('CRM_TESTING', true);
if (!defined('CRM_LOADED')) define('CRM_LOADED', true);

// Use test database
if (!defined('DB_PATH')) define('DB_PATH', __DIR__ . '/../db/test_crm.db');

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Test utilities
class TestUtils {
    private static $db;
    
    public static function getTestDatabase() {
        if (!self::$db) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
    
    public static function createTestUser($data = []) {
        $defaultData = [
            'username' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => 'testpass123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'user'
        ];
        
        $userData = array_merge($defaultData, $data);
        
        $auth = new Auth();
        return $auth->createUser($userData);
    }
    
    public static function createTestContact($data = []) {
        $defaultData = [
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'testcontact_' . uniqid() . '@example.com',
            'contact_type' => 'lead',
            'contact_status' => 'new'
        ];
        
        $contactData = array_merge($defaultData, $data);
        
        $db = self::getTestDatabase();
        return $db->insert('contacts', $contactData);
    }
    
    public static function createTestDeal($data = []) {
        $defaultData = [
            'title' => 'Test Deal',
            'contact_id' => 1,
            'amount' => 1000.00,
            'stage' => 'prospecting',
            'probability' => 25
        ];
        
        $dealData = array_merge($defaultData, $data);
        
        $db = self::getTestDatabase();
        
        // Ensure contact exists
        if (!isset($data['contact_id'])) {
            $contactId = self::createTestContact();
            $dealData['contact_id'] = $contactId;
        }
        
        return $db->insert('deals', $dealData);
    }
    
    public static function cleanupTestDatabase() {
        $db = self::getTestDatabase();
        
        // Delete all test data
        $db->delete('deals', '1=1');
        $db->delete('contacts', '1=1');
        $db->delete('users', 'username != ?', ['admin']);
        $db->delete('webhooks', '1=1');
        $db->delete('api_requests', '1=1');
    }
    
    public static function setupTestDatabase() {
        $db = self::getTestDatabase();
        
        // Clean up first
        self::cleanupTestDatabase();
        
        // Create test data in order
        self::createTestUser();
        self::createTestContact();
        // Deal will be created with proper contact reference
        self::createTestDeal();
    }
}

// Mock functions for testing
function mock_getallheaders() {
    return [
        'Authorization' => 'Bearer test_api_key',
        'Content-Type' => 'application/json'
    ];
}

// Override getallheaders if not available
if (!function_exists('getallheaders')) {
    function getallheaders() {
        return mock_getallheaders();
    }
}

// Suppress output during testing
ob_start();

// Set up test database
TestUtils::setupTestDatabase();

// Clear any output
ob_end_clean(); 
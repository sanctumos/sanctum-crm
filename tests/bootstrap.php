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
            'role' => 'user',
            'is_active' => 1
        ];
        
        $userData = array_merge($defaultData, $data);
        
        // Ensure all fields are scalar
        foreach ($userData as $k => $v) {
            if (is_array($v)) $userData[$k] = json_encode($v);
        }
        
        $auth = new Auth();
        return $auth->createUser($userData);
    }
    
    public static function createTestContact($data = []) {
        $defaultData = [
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'testcontact_' . uniqid() . '@example.com',
            'phone' => '+1234567890',
            'company' => 'Test Company',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'source' => 'website',
            'notes' => 'Test contact notes'
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
            'probability' => 25,
            'assigned_to' => null,
            'expected_close_date' => date('Y-m-d', strtotime('+30 days')),
            'description' => 'Test deal description'
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
    
    public static function createTestWebhook($data = []) {
        $defaultData = [
            'url' => 'https://webhook.site/test-' . uniqid(),
            'events' => json_encode(['contact.created', 'deal.created']),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $webhookData = array_merge($defaultData, $data);
        // Ensure events is always a JSON string
        if (isset($webhookData['events']) && is_array($webhookData['events'])) {
            $webhookData['events'] = json_encode($webhookData['events']);
        }
        // Ensure all fields are scalar
        foreach ($webhookData as $k => $v) {
            if (is_array($v)) $webhookData[$k] = json_encode($v);
        }
        
        $db = self::getTestDatabase();
        return $db->insert('webhooks', $webhookData);
    }
    
    public static function createTestApiRequest($data = []) {
        $defaultData = [
            'user_id' => 1,
            'endpoint' => '/api/v1/contacts',
            'method' => 'GET',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'response_code' => 200,
            'response_time' => 0.1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $requestData = array_merge($defaultData, $data);
        
        // Ensure user_id is valid
        if (isset($requestData['user_id'])) {
            $db = self::getTestDatabase();
            $user = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$requestData['user_id']]);
            if (!$user) {
                // Use admin user as fallback
                $admin = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");
                $requestData['user_id'] = $admin['id'] ?? null;
            }
        }
        
        $db = self::getTestDatabase();
        return $db->insert('api_requests', $requestData);
    }
    
    public static function cleanupTestDatabase() {
        $db = self::getTestDatabase();
        
        // Delete all test data in reverse dependency order
        $db->delete('api_requests', '1=1');
        $db->delete('webhooks', '1=1');
        $db->delete('deals', '1=1');
        $db->delete('contacts', '1=1');
        $db->delete('users', 'username != ?', ['admin']);
    }
    
    public static function setupTestDatabase() {
        $db = self::getTestDatabase();
        
        // Clean up first
        self::cleanupTestDatabase();
        
        // Create test data in order (respecting foreign key constraints)
        $userId = self::createTestUser();
        
        // Wait a moment to ensure user is created
        if ($userId) {
            $contactId = self::createTestContact();
            $dealId = self::createTestDeal(['contact_id' => $contactId]);
            $webhookId = self::createTestWebhook();
            $requestId = self::createTestApiRequest(['user_id' => $userId]);
            
            return [
                'user_id' => $userId,
                'contact_id' => $contactId,
                'deal_id' => $dealId,
                'webhook_id' => $webhookId,
                'request_id' => $requestId
            ];
        }
        
        return null;
    }
    
    public static function getTestApiKey() {
        $db = self::getTestDatabase();
        $admin = $db->fetchOne("SELECT api_key FROM users WHERE username = 'admin'");
        return $admin['api_key'] ?? null;
    }
    
    public static function getTestUserApiKey($userId) {
        $db = self::getTestDatabase();
        $user = $db->fetchOne("SELECT api_key FROM users WHERE id = ?", [$userId]);
        return $user['api_key'] ?? null;
    }
    
    public static function mockWebhookServer() {
        // Create a mock webhook server for testing
        $mockUrl = 'https://webhook.site/test-' . uniqid();
        return $mockUrl;
    }
    
    public static function validateJsonResponse($response, $expectedStructure = []) {
        if (!is_array($response)) {
            return false;
        }
        
        foreach ($expectedStructure as $key => $type) {
            if (!isset($response[$key])) {
                return false;
            }
            
            if ($type === 'array' && !is_array($response[$key])) {
                return false;
            }
            
            if ($type === 'string' && !is_string($response[$key])) {
                return false;
            }
            
            if ($type === 'int' && !is_int($response[$key])) {
                return false;
            }
            
            if ($type === 'float' && !is_numeric($response[$key])) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function assertResponseCode($response, $expectedCode) {
        return $response['code'] === $expectedCode;
    }
    
    public static function assertResponseHasKey($response, $key) {
        $data = json_decode($response['body'], true);
        return isset($data[$key]);
    }
    
    public static function assertResponseStructure($response, $expectedStructure) {
        $data = json_decode($response['body'], true);
        return self::validateJsonResponse($data, $expectedStructure);
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

// Mock cURL functions for webhook testing
function mock_curl_init() {
    return 'mock_curl_handle';
}

function mock_curl_setopt($ch, $option, $value) {
    return true;
}

function mock_curl_exec($ch) {
    return '{"success": true, "status": "200"}';
}

function mock_curl_getinfo($ch, $option = null) {
    if ($option === CURLINFO_HTTP_CODE) {
        return 200;
    }
    return [
        'http_code' => 200,
        'total_time' => 0.1,
        'url' => 'https://webhook.site/test'
    ];
}

function mock_curl_close($ch) {
    return true;
}

// Override cURL functions if not available or for testing
if (!function_exists('curl_init') || defined('CRM_TESTING')) {
    function curl_init() { return mock_curl_init(); }
    function curl_setopt($ch, $option, $value) { return mock_curl_setopt($ch, $option, $value); }
    function curl_exec($ch) { return mock_curl_exec($ch); }
    function curl_getinfo($ch, $option = null) { return mock_curl_getinfo($ch, $option); }
    function curl_close($ch) { return mock_curl_close($ch); }
}

// Mock header function for CLI test runs
if (php_sapi_name() !== 'cli' && !function_exists('header')) {
    function header($string, $replace = true, $http_response_code = 0) {
        // No-op in test mode
        return true;
    }
}

// Suppress output during testing
ob_start();

// Set up test database
TestUtils::setupTestDatabase();

// Clear any output
ob_end_clean(); 
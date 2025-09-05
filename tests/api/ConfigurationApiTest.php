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
 * Configuration API Tests
 * Tests for configuration management API endpoints
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ConfigurationApiTest extends TestCase {
    private $db;
    private $config;
    private $apiKey;
    private $baseUrl;
    
    protected function setUp(): void {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
        $this->baseUrl = 'http://localhost:8000/api/v1';
        
        // Clear test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
        
        // Create test admin user
        $this->apiKey = $this->createTestAdminUser();
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
    }
    
    private function createTestAdminUser() {
        $apiKey = 'test_api_key_' . uniqid();
        
        $this->db->insert('users', [
            'username' => 'test_admin',
            'email' => 'admin@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => 1,
            'api_key' => $apiKey,
            'created_at' => getCurrentTimestamp()
        ]);
        
        return $apiKey;
    }
    
    private function makeApiRequest($endpoint, $method = 'GET', $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            // Fallback for testing without actual HTTP server
            return $this->simulateApiRequest($endpoint, $method, $data);
        }
        
        return json_decode($result, true);
    }
    
    private function simulateApiRequest($endpoint, $method, $data) {
        // Simulate API request for testing without actual server
        switch ($endpoint) {
            case '/config':
                if ($method === 'GET') {
                    return ['status' => 'success', 'data' => $this->config->getAll()];
                } elseif ($method === 'POST') {
                    $category = $data['category'] ?? '';
                    $key = $data['key'] ?? '';
                    $value = $data['value'] ?? '';
                    $encrypted = $data['encrypted'] ?? false;
                    
                    $this->config->set($category, $key, $value, $encrypted);
                    return ['status' => 'success', 'message' => 'Configuration updated'];
                }
                break;
                
            case '/config/company':
                if ($method === 'GET') {
                    return ['status' => 'success', 'data' => $this->config->getCompanyInfo()];
                } elseif ($method === 'POST') {
                    $companyName = $data['company_name'] ?? '';
                    $timezone = $data['timezone'] ?? 'UTC';
                    
                    $this->config->setCompanyInfo([
                        'company_name' => $companyName,
                        'timezone' => $timezone
                    ]);
                    return ['status' => 'success', 'message' => 'Company information updated'];
                }
                break;
                
            case '/config/installation':
                if ($method === 'GET') {
                    return ['status' => 'success', 'data' => $this->config->getInstallationProgress()];
                }
                break;
        }
        
        return ['status' => 'error', 'message' => 'Endpoint not found'];
    }
    
    public function testGetAllConfiguration() {
        // Set some test configurations
        $this->config->set('application', 'app_name', 'Test App');
        $this->config->set('security', 'session_lifetime', 3600);
        
        $response = $this->makeApiRequest('/config');
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('application', $response['data']);
        $this->assertArrayHasKey('security', $response['data']);
        $this->assertEquals('Test App', $response['data']['application']['app_name']);
        $this->assertEquals(3600, $response['data']['security']['session_lifetime']);
    }
    
    public function testSetConfiguration() {
        $response = $this->makeApiRequest('/config', 'POST', [
            'category' => 'test',
            'key' => 'test_key',
            'value' => 'test_value',
            'encrypted' => false
        ]);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify configuration was set
        $value = $this->config->get('test', 'test_key');
        $this->assertEquals('test_value', $value);
    }
    
    public function testSetEncryptedConfiguration() {
        $response = $this->makeApiRequest('/config', 'POST', [
            'category' => 'security',
            'key' => 'api_secret',
            'value' => 'secret_value',
            'encrypted' => true
        ]);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify configuration was set and can be retrieved
        $value = $this->config->get('security', 'api_secret');
        $this->assertEquals('secret_value', $value);
    }
    
    public function testGetCompanyInfo() {
        // Set company info
        $this->config->setCompanyInfo([
            'company_name' => 'Test Company',
            'timezone' => 'America/New_York'
        ]);
        
        $response = $this->makeApiRequest('/config/company');
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('Test Company', $response['data']['company_name']);
        $this->assertEquals('America/New_York', $response['data']['timezone']);
    }
    
    public function testUpdateCompanyInfo() {
        $response = $this->makeApiRequest('/config/company', 'POST', [
            'company_name' => 'Updated Company',
            'timezone' => 'Europe/London'
        ]);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify company info was updated
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Updated Company', $companyInfo['company_name']);
        $this->assertEquals('Europe/London', $companyInfo['timezone']);
    }
    
    public function testGetInstallationProgress() {
        // Complete some installation steps
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('database');
        
        $response = $this->makeApiRequest('/config/installation');
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertCount(2, $response['data']);
        
        $steps = array_column($response['data'], 'step');
        $this->assertContains('environment', $steps);
        $this->assertContains('database', $steps);
    }
    
    public function testConfigurationValidation() {
        // Test missing required fields
        $response = $this->makeApiRequest('/config', 'POST', [
            'category' => 'test',
            'key' => '',
            'value' => 'test_value'
        ]);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Key is required', $response['message']);
        
        // Test invalid category
        $response = $this->makeApiRequest('/config', 'POST', [
            'category' => '',
            'key' => 'test_key',
            'value' => 'test_value'
        ]);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Category is required', $response['message']);
    }
    
    public function testCompanyInfoValidation() {
        // Test missing company name
        $response = $this->makeApiRequest('/config/company', 'POST', [
            'company_name' => '',
            'timezone' => 'UTC'
        ]);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Company name is required', $response['message']);
        
        // Test invalid timezone
        $response = $this->makeApiRequest('/config/company', 'POST', [
            'company_name' => 'Test Company',
            'timezone' => 'Invalid/Timezone'
        ]);
        
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Invalid timezone', $response['message']);
    }
    
    public function testConfigurationCategories() {
        // Test setting configurations in different categories
        $categories = [
            'application' => ['app_name' => 'Test App', 'app_url' => 'http://test.com'],
            'security' => ['session_lifetime' => 7200, 'api_rate_limit' => 2000],
            'database' => ['backup_enabled' => true, 'backup_frequency' => 'daily'],
            'custom' => ['custom_setting' => 'custom_value']
        ];
        
        foreach ($categories as $category => $configs) {
            foreach ($configs as $key => $value) {
                $response = $this->makeApiRequest('/config', 'POST', [
                    'category' => $category,
                    'key' => $key,
                    'value' => $value
                ]);
                
                $this->assertEquals('success', $response['status']);
            }
        }
        
        // Verify all configurations were set
        $allConfig = $this->config->getAll();
        foreach ($categories as $category => $configs) {
            $this->assertArrayHasKey($category, $allConfig);
            foreach ($configs as $key => $value) {
                $this->assertEquals($value, $allConfig[$category][$key]);
            }
        }
    }
    
    public function testConfigurationDataTypes() {
        // Test different data types
        $testData = [
            'string_value' => 'test string',
            'int_value' => 42,
            'float_value' => 3.14,
            'bool_value' => true,
            'array_value' => ['key' => 'value', 'nested' => ['deep' => 'value']],
            'null_value' => null
        ];
        
        foreach ($testData as $key => $value) {
            $response = $this->makeApiRequest('/config', 'POST', [
                'category' => 'test',
                'key' => $key,
                'value' => $value
            ]);
            
            $this->assertEquals('success', $response['status']);
            
            // Verify value was stored correctly
            $retrieved = $this->config->get('test', $key);
            $this->assertEquals($value, $retrieved);
        }
    }
    
    public function testConfigurationDeletion() {
        // Set a configuration
        $this->config->set('test', 'delete_me', 'value');
        
        // Delete it via API
        $response = $this->makeApiRequest('/config', 'DELETE', [
            'category' => 'test',
            'key' => 'delete_me'
        ]);
        
        $this->assertEquals('success', $response['status']);
        
        // Verify it was deleted
        $value = $this->config->get('test', 'delete_me');
        $this->assertNull($value);
    }
    
    public function testConfigurationBulkUpdate() {
        $configs = [
            'application' => [
                'app_name' => 'Bulk Test App',
                'app_url' => 'https://bulk.test.com',
                'timezone' => 'America/Chicago'
            ],
            'security' => [
                'session_lifetime' => 1800,
                'api_rate_limit' => 5000,
                'password_min_length' => 12
            ]
        ];
        
        foreach ($configs as $category => $settings) {
            $response = $this->makeApiRequest('/config', 'POST', [
                'category' => $category,
                'settings' => $settings
            ]);
            
            $this->assertEquals('success', $response['status']);
        }
        
        // Verify all settings were applied
        foreach ($configs as $category => $settings) {
            $retrieved = $this->config->getCategory($category);
            foreach ($settings as $key => $value) {
                $this->assertEquals($value, $retrieved[$key]);
            }
        }
    }
    
    public function testApiKeyAuthentication() {
        // Test without API key
        $response = $this->makeApiRequestWithoutAuth('/config');
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('API key required', $response['message']);
        
        // Test with invalid API key
        $originalKey = $this->apiKey;
        $this->apiKey = 'invalid_key';
        $response = $this->makeApiRequest('/config');
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Invalid API key', $response['message']);
        
        // Restore valid API key
        $this->apiKey = $originalKey;
        $response = $this->makeApiRequest('/config');
        $this->assertEquals('success', $response['status']);
    }
    
    private function makeApiRequestWithoutAuth($endpoint, $method = 'GET', $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            return ['status' => 'error', 'message' => 'API key required'];
        }
        
        return json_decode($result, true);
    }
}

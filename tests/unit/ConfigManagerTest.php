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
 * ConfigManager Unit Tests
 * Tests for configuration management functionality
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase {
    private $config;
    private $db;
    
    protected function setUp(): void {
        // Use test database
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
        
        // Clear test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
    }
    
    public function testGetInstance() {
        $instance1 = ConfigManager::getInstance();
        $instance2 = ConfigManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ConfigManager::class, $instance1);
    }
    
    public function testSetAndGetConfiguration() {
        // Test setting a simple configuration
        $this->config->set('application', 'app_name', 'Test App');
        $value = $this->config->get('application', 'app_name');
        
        $this->assertEquals('Test App', $value);
    }
    
    public function testGetWithDefault() {
        // Test getting non-existent configuration with default
        $value = $this->config->get('application', 'non_existent', 'default_value');
        
        $this->assertEquals('default_value', $value);
    }
    
    public function testSetCategory() {
        $configs = [
            'app_name' => 'Test App',
            'app_url' => 'http://test.com',
            'timezone' => 'UTC'
        ];
        
        $this->config->setCategory('application', $configs);
        
        $this->assertEquals('Test App', $this->config->get('application', 'app_name'));
        $this->assertEquals('http://test.com', $this->config->get('application', 'app_url'));
        $this->assertEquals('UTC', $this->config->get('application', 'timezone'));
    }
    
    public function testGetCategory() {
        $configs = [
            'app_name' => 'Test App',
            'app_url' => 'http://test.com'
        ];
        
        $this->config->setCategory('application', $configs);
        $retrieved = $this->config->getCategory('application');
        
        $this->assertEquals($configs, $retrieved);
    }
    
    public function testDataTypes() {
        // Test different data types
        $this->config->set('test', 'string_value', 'test string');
        $this->config->set('test', 'int_value', 42);
        $this->config->set('test', 'float_value', 3.14);
        $this->config->set('test', 'bool_value', true);
        $this->config->set('test', 'array_value', ['key' => 'value']);
        
        $this->assertEquals('test string', $this->config->get('test', 'string_value'));
        $this->assertEquals(42, $this->config->get('test', 'int_value'));
        $this->assertEquals(3.14, $this->config->get('test', 'float_value'));
        $this->assertTrue($this->config->get('test', 'bool_value'));
        $this->assertEquals(['key' => 'value'], $this->config->get('test', 'array_value'));
    }
    
    public function testEncryptedConfiguration() {
        $this->config->set('security', 'api_key', 'secret_key', true);
        $value = $this->config->get('security', 'api_key');
        
        $this->assertEquals('secret_key', $value);
        
        // Verify it's stored encrypted in database
        $result = $this->db->fetchOne(
            "SELECT config_value, is_encrypted FROM system_config WHERE category = ? AND config_key = ?",
            ['security', 'api_key']
        );
        
        $this->assertEquals(1, $result['is_encrypted']);
        $this->assertNotEquals('secret_key', $result['config_value']);
    }
    
    public function testDeleteConfiguration() {
        $this->config->set('test', 'key', 'value');
        $this->config->delete('test', 'key');
        
        $value = $this->config->get('test', 'key');
        $this->assertNull($value);
    }
    
    public function testClearCache() {
        $this->config->set('test', 'key', 'value');
        $this->config->clearCache();
        
        // Cache should be cleared, but value should still be retrievable
        $value = $this->config->get('test', 'key');
        $this->assertEquals('value', $value);
    }
    
    public function testCompanyInfo() {
        $companyData = [
            'company_name' => 'Test Company',
            'timezone' => 'America/New_York'
        ];
        
        $this->config->setCompanyInfo($companyData);
        $retrieved = $this->config->getCompanyInfo();
        
        $this->assertEquals('Test Company', $retrieved['company_name']);
        $this->assertEquals('America/New_York', $retrieved['timezone']);
    }
    
    public function testCompanyInfoDefault() {
        $retrieved = $this->config->getCompanyInfo();
        
        $this->assertEquals('Sanctum CRM', $retrieved['company_name']);
        $this->assertEquals('UTC', $retrieved['timezone']);
    }
    
    public function testIsFirstBoot() {
        // Should be true when no company info exists
        $this->assertTrue($this->config->isFirstBoot());
        
        // Set company info
        $this->config->setCompanyInfo(['company_name' => 'Test Company']);
        
        // Should still be true when no admin user exists
        $this->assertTrue($this->config->isFirstBoot());
        
        // Create admin user
        $this->db->insert('users', [
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => getCurrentTimestamp()
        ]);
        
        // Should now be false
        $this->assertFalse($this->config->isFirstBoot());
    }
    
    public function testInstallationProgress() {
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('database', ['tables_created' => 5]);
        
        $progress = $this->config->getInstallationProgress();
        
        $this->assertCount(2, $progress);
        $this->assertEquals('environment', $progress[0]['step']);
        $this->assertEquals(1, $progress[0]['is_completed']);
        $this->assertEquals('database', $progress[1]['step']);
        $this->assertEquals(1, $progress[1]['is_completed']);
    }
    
    public function testGetCurrentInstallationStep() {
        // Should return first step when none completed
        $this->assertEquals('environment', $this->config->getCurrentInstallationStep());
        
        // Complete first step
        $this->config->completeInstallationStep('environment');
        $this->assertEquals('database', $this->config->getCurrentInstallationStep());
        
        // Complete all steps
        $steps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($steps as $step) {
            $this->config->completeInstallationStep($step);
        }
        
        $this->assertEquals('complete', $this->config->getCurrentInstallationStep());
    }
    
    public function testUpdateExistingConfiguration() {
        // Set initial value
        $this->config->set('test', 'key', 'initial_value');
        
        // Update value
        $this->config->set('test', 'key', 'updated_value');
        
        $value = $this->config->get('test', 'key');
        $this->assertEquals('updated_value', $value);
        
        // Verify only one record exists
        $count = $this->db->fetchOne("SELECT COUNT(*) as count FROM system_config WHERE category = 'test' AND config_key = 'key'");
        $this->assertEquals(1, $count['count']);
    }
    
    public function testSpecialCharacters() {
        $specialValue = "Test with special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";
        $this->config->set('test', 'special', $specialValue);
        
        $retrieved = $this->config->get('test', 'special');
        $this->assertEquals($specialValue, $retrieved);
    }
    
    public function testLargeConfiguration() {
        $largeArray = array_fill(0, 1000, 'test_value');
        $this->config->set('test', 'large_array', $largeArray);
        
        $retrieved = $this->config->get('test', 'large_array');
        $this->assertEquals($largeArray, $retrieved);
    }
    
    public function testGetAllConfigurations() {
        // Set some test configurations
        $this->config->set('category1', 'key1', 'value1');
        $this->config->set('category1', 'key2', 'value2');
        $this->config->set('category2', 'key1', 'value3');
        
        $allConfigs = $this->config->getAll();
        
        $this->assertIsArray($allConfigs);
        $this->assertArrayHasKey('category1', $allConfigs);
        $this->assertArrayHasKey('category2', $allConfigs);
        $this->assertEquals('value1', $allConfigs['category1']['key1']);
        $this->assertEquals('value2', $allConfigs['category1']['key2']);
        $this->assertEquals('value3', $allConfigs['category2']['key1']);
    }
    
    public function testGetAllWithEncryptedValues() {
        $this->config->set('test', 'encrypted_key', 'secret_value', true);
        $this->config->set('test', 'normal_key', 'normal_value');
        
        $allConfigs = $this->config->getAll();
        
        $this->assertEquals('secret_value', $allConfigs['test']['encrypted_key']);
        $this->assertEquals('normal_value', $allConfigs['test']['normal_key']);
    }
    
    public function testGetAllWithDifferentDataTypes() {
        $this->config->set('test', 'string_val', 'test');
        $this->config->set('test', 'int_val', 42);
        $this->config->set('test', 'float_val', 3.14);
        $this->config->set('test', 'bool_val', true);
        $this->config->set('test', 'array_val', ['key' => 'value']);
        
        $allConfigs = $this->config->getAll();
        
        $this->assertEquals('test', $allConfigs['test']['string_val']);
        $this->assertEquals(42, $allConfigs['test']['int_val']);
        $this->assertEquals(3.14, $allConfigs['test']['float_val']);
        $this->assertTrue($allConfigs['test']['bool_val']);
        $this->assertEquals(['key' => 'value'], $allConfigs['test']['array_val']);
    }
    
    public function testGetAllEmpty() {
        $allConfigs = $this->config->getAll();
        $this->assertIsArray($allConfigs);
        $this->assertEmpty($allConfigs);
    }
    
    public function testGetCategoryEmpty() {
        $configs = $this->config->getCategory('nonexistent');
        $this->assertIsArray($configs);
        $this->assertEmpty($configs);
    }
    
    public function testSetCategoryWithEmptyArray() {
        $result = $this->config->setCategory('test', []);
        $this->assertTrue($result);
        
        $configs = $this->config->getCategory('test');
        $this->assertEmpty($configs);
    }
    
    public function testSetCategoryWithNullValues() {
        $this->config->setCategory('test', [
            'null_val' => null,
            'empty_string' => '',
            'zero' => 0,
            'false_val' => false
        ]);
        
        $configs = $this->config->getCategory('test');
        $this->assertNull($configs['null_val']);
        $this->assertEquals('', $configs['empty_string']);
        $this->assertEquals(0, $configs['zero']);
        $this->assertFalse($configs['false_val']);
    }
    
    public function testDeleteNonExistentConfiguration() {
        $result = $this->config->delete('nonexistent', 'key');
        $this->assertTrue($result);
    }
    
    public function testCacheBehavior() {
        // Set a value
        $this->config->set('test', 'cached_key', 'cached_value');
        
        // Get it (should be cached)
        $value1 = $this->config->get('test', 'cached_key');
        $this->assertEquals('cached_value', $value1);
        
        // Clear cache
        $this->config->clearCache();
        
        // Get it again (should be retrieved from database)
        $value2 = $this->config->get('test', 'cached_key');
        $this->assertEquals('cached_value', $value2);
    }
    
    public function testUpdateExistingConfigurationClearsCache() {
        // Set initial value
        $this->config->set('test', 'update_key', 'initial_value');
        $this->config->get('test', 'update_key'); // Load into cache
        
        // Update value
        $this->config->set('test', 'update_key', 'updated_value');
        
        // Get value (should be updated)
        $value = $this->config->get('test', 'update_key');
        $this->assertEquals('updated_value', $value);
    }
    
    public function testDeleteConfigurationClearsCache() {
        // Set and cache a value
        $this->config->set('test', 'delete_key', 'delete_value');
        $this->config->get('test', 'delete_key'); // Load into cache
        
        // Delete it
        $this->config->delete('test', 'delete_key');
        
        // Try to get it (should return default)
        $value = $this->config->get('test', 'delete_key', 'default');
        $this->assertEquals('default', $value);
    }
    
    public function testCompanyInfoWithEmptyDatabase() {
        // Clear company info table
        $this->db->query("DELETE FROM company_info");
        
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Sanctum CRM', $companyInfo['company_name']);
        $this->assertEquals('UTC', $companyInfo['timezone']);
    }
    
    public function testSetCompanyInfoWithEmptyDatabase() {
        // Clear company info table
        $this->db->query("DELETE FROM company_info");
        
        $result = $this->config->setCompanyInfo([
            'company_name' => 'New Company',
            'timezone' => 'America/New_York'
        ]);
        
        $this->assertTrue($result);
        
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('New Company', $companyInfo['company_name']);
        $this->assertEquals('America/New_York', $companyInfo['timezone']);
    }
    
    public function testInstallationProgressEmpty() {
        $progress = $this->config->getInstallationProgress();
        $this->assertIsArray($progress);
        $this->assertEmpty($progress);
    }
    
    public function testCompleteInstallationStepWithData() {
        $testData = ['php_version' => '8.1', 'extensions' => ['sqlite3', 'json']];
        
        $result = $this->config->completeInstallationStep('environment', $testData);
        $this->assertTrue($result);
        
        $progress = $this->config->getInstallationProgress();
        $this->assertCount(1, $progress);
        $this->assertEquals('environment', $progress[0]['step']);
        $this->assertEquals(1, $progress[0]['is_completed']);
        
        $data = json_decode($progress[0]['data'], true);
        $this->assertEquals($testData, $data);
    }
    
    public function testCompleteInstallationStepWithoutData() {
        $result = $this->config->completeInstallationStep('environment');
        $this->assertTrue($result);
        
        $progress = $this->config->getInstallationProgress();
        $this->assertCount(1, $progress);
        $this->assertEquals('environment', $progress[0]['step']);
        $this->assertEquals(1, $progress[0]['is_completed']);
        $this->assertNull($progress[0]['data']);
    }
    
    public function testGetCurrentInstallationStepWithIncompleteSteps() {
        // Complete first two steps
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('database');
        
        $currentStep = $this->config->getCurrentInstallationStep();
        $this->assertEquals('company', $currentStep);
    }
    
    public function testGetCurrentInstallationStepAllComplete() {
        // Complete all steps
        $steps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($steps as $step) {
            $this->config->completeInstallationStep($step);
        }
        
        $currentStep = $this->config->getCurrentInstallationStep();
        $this->assertEquals('complete', $currentStep);
    }
    
    public function testGetCurrentInstallationStepNoSteps() {
        $currentStep = $this->config->getCurrentInstallationStep();
        $this->assertEquals('environment', $currentStep);
    }
    
    public function testGetCurrentInstallationStepWithGaps() {
        // Complete first and third steps, skip second
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('company');
        
        $currentStep = $this->config->getCurrentInstallationStep();
        $this->assertEquals('database', $currentStep);
    }
    
    public function testDataTypeDetection() {
        // Test different data types
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'array' => ['key' => 'value'],
            'object' => (object)['key' => 'value']
        ];
        
        foreach ($testCases as $expectedType => $value) {
            $this->config->set('test', $expectedType, $value);
            $retrieved = $this->config->get('test', $expectedType);
            
            if ($expectedType === 'object') {
                // Objects are converted to arrays
                $this->assertEquals((array)$value, $retrieved);
            } else {
                $this->assertEquals($value, $retrieved);
            }
        }
    }
    
    public function testEncryptionDecryption() {
        $originalValue = 'sensitive_data_123';
        
        $this->config->set('security', 'secret', $originalValue, true);
        $retrieved = $this->config->get('security', 'secret');
        
        $this->assertEquals($originalValue, $retrieved);
        
        // Verify it's stored encrypted in database
        $result = $this->db->fetchOne(
            "SELECT config_value FROM system_config WHERE category = 'security' AND config_key = 'secret'"
        );
        
        $this->assertNotEquals($originalValue, $result['config_value']);
        $this->assertEquals($originalValue, base64_decode($result['config_value']));
    }
    
    public function testEncryptionWithSpecialCharacters() {
        $specialValue = "Special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";
        
        $this->config->set('test', 'special', $specialValue, true);
        $retrieved = $this->config->get('test', 'special');
        
        $this->assertEquals($specialValue, $retrieved);
    }
    
    public function testEncryptionWithUnicode() {
        $unicodeValue = "Unicode: 你好世界 🌍 émojis";
        
        $this->config->set('test', 'unicode', $unicodeValue, true);
        $retrieved = $this->config->get('test', 'unicode');
        
        $this->assertEquals($unicodeValue, $retrieved);
    }
    
    public function testConcurrentAccess() {
        // Test that multiple instances share the same data
        $config1 = ConfigManager::getInstance();
        $config2 = ConfigManager::getInstance();
        
        $config1->set('test', 'concurrent', 'value1');
        $value = $config2->get('test', 'concurrent');
        
        $this->assertEquals('value1', $value);
        
        $config2->set('test', 'concurrent', 'value2');
        $value = $config1->get('test', 'concurrent');
        
        $this->assertEquals('value2', $value);
    }
    
    public function testErrorHandling() {
        // Test with invalid category/key combinations
        $this->config->set('', 'key', 'value');
        $this->config->set('category', '', 'value');
        
        // These should not cause errors
        $this->assertTrue(true);
    }
    
    public function testMemoryUsage() {
        // Test with large amounts of data
        $largeData = str_repeat('x', 10000);
        
        $this->config->set('test', 'large', $largeData);
        $retrieved = $this->config->get('test', 'large');
        
        $this->assertEquals($largeData, $retrieved);
    }
    
    public function testNestedArrays() {
        $nestedArray = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep_value'
                ]
            ]
        ];
        
        $this->config->set('test', 'nested', $nestedArray);
        $retrieved = $this->config->get('test', 'nested');
        
        $this->assertEquals($nestedArray, $retrieved);
    }
    
    public function testBooleanEdgeCases() {
        $this->config->set('test', 'true_bool', true);
        $this->config->set('test', 'false_bool', false);
        $this->config->set('test', 'string_true', 'true');
        $this->config->set('test', 'string_false', 'false');
        
        $this->assertTrue($this->config->get('test', 'true_bool'));
        $this->assertFalse($this->config->get('test', 'false_bool'));
        $this->assertEquals('true', $this->config->get('test', 'string_true'));
        $this->assertEquals('false', $this->config->get('test', 'string_false'));
    }
    
    public function testNumericEdgeCases() {
        $this->config->set('test', 'zero', 0);
        $this->config->set('test', 'negative', -1);
        $this->config->set('test', 'float_zero', 0.0);
        $this->config->set('test', 'string_zero', '0');
        
        $this->assertEquals(0, $this->config->get('test', 'zero'));
        $this->assertEquals(-1, $this->config->get('test', 'negative'));
        $this->assertEquals(0.0, $this->config->get('test', 'float_zero'));
        $this->assertEquals('0', $this->config->get('test', 'string_zero'));
    }
}

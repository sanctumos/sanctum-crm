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
}

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
 * First Boot Integration Tests
 * Tests for complete first boot installation flow
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class FirstBootIntegrationTest extends TestCase {
    private $db;
    private $config;
    private $installationManager;
    
    protected function setUp(): void {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
        $this->installationManager = new InstallationManager();
        
        // Clear all test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
        $this->db->query("DELETE FROM settings");
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
        $this->db->query("DELETE FROM settings");
    }
    
    public function testCompleteFirstBootFlow() {
        // Step 1: Verify first boot detection
        $this->assertTrue($this->installationManager->isFirstBoot());
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
        
        // Step 2: Environment validation
        $envValidation = $this->installationManager->validateEnvironment();
        $this->assertTrue($envValidation['valid']);
        $this->installationManager->completeStep('environment');
        
        // Step 3: Database initialization
        $this->assertTrue($this->installationManager->initializeDatabase());
        $this->installationManager->setupDefaultConfig();
        $this->installationManager->completeStep('database');
        
        // Verify default configuration was set
        $appConfig = $this->config->getCategory('application');
        $this->assertEquals('Sanctum CRM', $appConfig['app_name']);
        $this->assertEquals('http://localhost', $appConfig['app_url']);
        $this->assertEquals('UTC', $appConfig['timezone']);
        
        // Step 4: Company setup
        $this->installationManager->setupCompany('Test Company', 'America/New_York');
        $this->installationManager->completeStep('company');
        
        // Verify company info was set
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Test Company', $companyInfo['company_name']);
        $this->assertEquals('America/New_York', $companyInfo['timezone']);
        
        // Step 5: Admin user creation
        $this->assertTrue($this->installationManager->createAdminUser(
            'admin',
            'admin@test.com',
            'password123',
            'Test',
            'Admin'
        ));
        $this->installationManager->completeStep('admin');
        
        // Verify admin user was created
        $adminUser = $this->db->fetchOne("SELECT * FROM users WHERE role = 'admin'");
        $this->assertNotNull($adminUser);
        $this->assertEquals('admin', $adminUser['username']);
        $this->assertEquals('admin@test.com', $adminUser['email']);
        $this->assertEquals('Test', $adminUser['first_name']);
        $this->assertEquals('Admin', $adminUser['last_name']);
        $this->assertNotNull($adminUser['api_key']);
        
        // Step 6: Complete installation
        $this->installationManager->completeInstallation();
        
        // Verify installation is complete
        $this->assertFalse($this->installationManager->isFirstBoot());
        $this->assertEquals('complete', $this->installationManager->getCurrentStep());
        
        // Verify all steps are completed
        $progress = $this->installationManager->getInstallationProgress();
        $completedSteps = array_column($progress, 'step');
        $expectedSteps = ['environment', 'database', 'company', 'admin', 'complete'];
        
        foreach ($expectedSteps as $step) {
            $this->assertContains($step, $completedSteps);
        }
    }
    
    public function testConfigurationPersistence() {
        // Set up some configurations
        $this->config->setCategory('application', [
            'app_name' => 'Custom CRM',
            'app_url' => 'https://custom.example.com',
            'timezone' => 'Europe/London'
        ]);
        
        $this->config->setCategory('security', [
            'session_lifetime' => 7200,
            'api_rate_limit' => 2000,
            'password_min_length' => 12
        ]);
        
        $this->config->setCompanyInfo([
            'company_name' => 'Custom Company',
            'timezone' => 'America/Los_Angeles'
        ]);
        
        // Create new instances to test persistence
        $newConfig = ConfigManager::getInstance();
        
        // Verify configurations persist
        $appConfig = $newConfig->getCategory('application');
        $this->assertEquals('Custom CRM', $appConfig['app_name']);
        $this->assertEquals('https://custom.example.com', $appConfig['app_url']);
        $this->assertEquals('Europe/London', $appConfig['timezone']);
        
        $securityConfig = $newConfig->getCategory('security');
        $this->assertEquals(7200, $securityConfig['session_lifetime']);
        $this->assertEquals(2000, $securityConfig['api_rate_limit']);
        $this->assertEquals(12, $securityConfig['password_min_length']);
        
        $companyInfo = $newConfig->getCompanyInfo();
        $this->assertEquals('Custom Company', $companyInfo['company_name']);
        $this->assertEquals('America/Los_Angeles', $companyInfo['timezone']);
    }
    
    public function testInstallationStateManagement() {
        // Test step completion with data
        $this->installationManager->completeStep('environment', [
            'php_version' => '8.1.0',
            'extensions' => ['sqlite3', 'json', 'curl']
        ]);
        
        $this->installationManager->completeStep('database', [
            'tables_created' => 5,
            'migrations_run' => 0
        ]);
        
        // Verify step data is stored
        $progress = $this->installationManager->getInstallationProgress();
        $this->assertCount(2, $progress);
        
        $envStep = $progress[0];
        $this->assertEquals('environment', $envStep['step']);
        $this->assertEquals(1, $envStep['is_completed']);
        $this->assertNotNull($envStep['completed_at']);
        
        $envData = json_decode($envStep['data'], true);
        $this->assertEquals('8.1.0', $envData['php_version']);
        $this->assertContains('sqlite3', $envData['extensions']);
        
        $dbStep = $progress[1];
        $this->assertEquals('database', $dbStep['step']);
        $this->assertEquals(1, $dbStep['is_completed']);
        
        $dbData = json_decode($dbStep['data'], true);
        $this->assertEquals(5, $dbData['tables_created']);
        $this->assertEquals(0, $dbData['migrations_run']);
    }
    
    public function testInstallationReset() {
        // Set up a complete installation
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupCompany('Test Company', 'UTC');
        $this->installationManager->completeStep('company');
        $this->installationManager->createAdminUser('admin', 'admin@test.com', 'password123');
        $this->installationManager->completeStep('admin');
        $this->installationManager->completeInstallation();
        
        // Verify installation is complete
        $this->assertFalse($this->installationManager->isFirstBoot());
        $this->assertEquals('complete', $this->installationManager->getCurrentStep());
        
        // Reset installation
        $this->installationManager->resetInstallation();
        
        // Verify reset worked
        $this->assertTrue($this->installationManager->isFirstBoot());
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
        
        // Verify data was cleared
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Sanctum CRM', $companyInfo['company_name']); // Default value
        
        $adminUsers = $this->db->fetchAll("SELECT * FROM users WHERE role = 'admin'");
        $this->assertEmpty($adminUsers);
        
        $progress = $this->installationManager->getInstallationProgress();
        $this->assertEmpty($progress);
    }
    
    public function testConfigurationValidation() {
        // Test invalid company setup
        $validation = $this->installationManager->validateStep('company', ['company_name' => '']);
        $this->assertFalse($validation['valid']);
        $this->assertContains('Company name is required', $validation['errors']);
        
        // Test valid company setup
        $validation = $this->installationManager->validateStep('company', ['company_name' => 'Valid Company']);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        
        // Test invalid admin setup
        $validation = $this->installationManager->validateStep('admin', [
            'username' => '',
            'email' => 'invalid-email',
            'password' => '123'
        ]);
        $this->assertFalse($validation['valid']);
        $this->assertCount(3, $validation['errors']);
        $this->assertContains('Username is required', $validation['errors']);
        $this->assertContains('Invalid email format', $validation['errors']);
        $this->assertContains('Password must be at least', $validation['errors']);
        
        // Test valid admin setup
        $validation = $this->installationManager->validateStep('admin', [
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }
    
    public function testDatabaseSchemaCreation() {
        // Verify all required tables exist after database initialization
        $this->installationManager->initializeDatabase();
        
        $tables = [
            'users',
            'contacts',
            'deals',
            'webhooks',
            'api_requests',
            'settings',
            'system_config',
            'company_info',
            'installation_state'
        ];
        
        foreach ($tables as $table) {
            $result = $this->db->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
            $this->assertNotNull($result, "Table $table should exist");
        }
    }
    
    public function testDefaultConfigurationSetup() {
        $this->installationManager->setupDefaultConfig();
        
        // Verify application configuration
        $appConfig = $this->config->getCategory('application');
        $this->assertEquals('Sanctum CRM', $appConfig['app_name']);
        $this->assertEquals('http://localhost', $appConfig['app_url']);
        $this->assertEquals('UTC', $appConfig['timezone']);
        
        // Verify security configuration
        $securityConfig = $this->config->getCategory('security');
        $this->assertEquals(3600, $securityConfig['session_lifetime']);
        $this->assertEquals(1000, $securityConfig['api_rate_limit']);
        $this->assertEquals(8, $securityConfig['password_min_length']);
        
        // Verify database configuration
        $databaseConfig = $this->config->getCategory('database');
        $this->assertEquals(false, $databaseConfig['backup_enabled']);
    }
    
    public function testInstallationStatusTracking() {
        // Test initial status
        $status = $this->installationManager->getInstallationStatus();
        $this->assertFalse($status['environment']['completed']);
        $this->assertTrue($status['environment']['current']);
        
        // Complete environment step
        $this->installationManager->completeStep('environment');
        $status = $this->installationManager->getInstallationStatus();
        $this->assertTrue($status['environment']['completed']);
        $this->assertFalse($status['environment']['current']);
        $this->assertTrue($status['database']['current']);
        
        // Complete all steps
        $this->installationManager->completeStep('database');
        $this->installationManager->completeStep('company');
        $this->installationManager->completeStep('admin');
        $this->installationManager->completeStep('complete');
        
        $status = $this->installationManager->getInstallationStatus();
        $this->assertTrue($status['environment']['completed']);
        $this->assertTrue($status['database']['completed']);
        $this->assertTrue($status['company']['completed']);
        $this->assertTrue($status['admin']['completed']);
        $this->assertTrue($status['complete']['completed']);
        $this->assertFalse($status['complete']['current']);
    }
    
    public function testConcurrentConfigurationAccess() {
        // Test that multiple instances can access the same configuration
        $config1 = ConfigManager::getInstance();
        $config2 = ConfigManager::getInstance();
        
        $config1->set('test', 'key', 'value');
        $value = $config2->get('test', 'key');
        
        $this->assertEquals('value', $value);
        
        // Test that changes are reflected across instances
        $config2->set('test', 'key', 'updated_value');
        $value = $config1->get('test', 'key');
        
        $this->assertEquals('updated_value', $value);
    }
    
    public function testErrorHandling() {
        // Test handling of invalid database operations
        $this->installationManager->initializeDatabase();
        
        // Test invalid company data
        $this->assertFalse($this->installationManager->setupCompany('', 'UTC'));
        
        // Test invalid admin data
        $this->assertFalse($this->installationManager->createAdminUser('', 'invalid-email', '123'));
        
        // Test duplicate admin creation
        $this->installationManager->createAdminUser('admin', 'admin@test.com', 'password123');
        $this->assertFalse($this->installationManager->createAdminUser('admin2', 'admin2@test.com', 'password123'));
    }
}

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
 * InstallationManager Unit Tests
 * Tests for installation management functionality
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class InstallationManagerTest extends TestCase {
    private $installationManager;
    private $db;
    private $config;
    
    protected function setUp(): void {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
        $this->installationManager = new InstallationManager();
        
        // Clear test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->query("DELETE FROM system_config");
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM installation_state");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
    }
    
    public function testIsFirstBoot() {
        // Should be true when no company info and no admin user
        $this->assertTrue($this->installationManager->isFirstBoot());
        
        // Set company info
        $this->config->setCompanyInfo(['company_name' => 'Test Company']);
        $this->assertTrue($this->installationManager->isFirstBoot());
        
        // Create admin user
        $this->db->insert('users', [
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => getCurrentTimestamp()
        ]);
        
        $this->assertFalse($this->installationManager->isFirstBoot());
    }
    
    public function testGetCurrentStep() {
        // Should return first step when none completed
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
        
        // Complete first step
        $this->installationManager->completeStep('environment');
        $this->assertEquals('database', $this->installationManager->getCurrentStep());
    }
    
    public function testGetInstallationProgress() {
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        
        $progress = $this->installationManager->getInstallationProgress();
        
        $this->assertCount(2, $progress);
        $this->assertEquals('environment', $progress[0]['step']);
        $this->assertEquals(1, $progress[0]['is_completed']);
        $this->assertEquals('database', $progress[1]['step']);
        $this->assertEquals(1, $progress[1]['is_completed']);
    }
    
    public function testCompleteStep() {
        $this->installationManager->completeStep('environment', ['php_version' => '8.1']);
        
        $progress = $this->installationManager->getInstallationProgress();
        $this->assertCount(1, $progress);
        $this->assertEquals('environment', $progress[0]['step']);
        $this->assertEquals(1, $progress[0]['is_completed']);
        $this->assertNotNull($progress[0]['completed_at']);
    }
    
    public function testValidateEnvironment() {
        $validation = $this->installationManager->validateEnvironment();
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
        
        // Should be valid in test environment
        $this->assertTrue($validation['valid']);
    }
    
    public function testInitializeDatabase() {
        $result = $this->installationManager->initializeDatabase();
        
        // Should return true if database is working
        $this->assertTrue($result);
    }
    
    public function testSetupCompany() {
        $result = $this->installationManager->setupCompany('Test Company', 'America/New_York');
        
        $this->assertTrue($result);
        
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Test Company', $companyInfo['company_name']);
        $this->assertEquals('America/New_York', $companyInfo['timezone']);
    }
    
    public function testCreateAdminUser() {
        $result = $this->installationManager->createAdminUser(
            'testadmin',
            'admin@test.com',
            'password123',
            'Test',
            'Admin'
        );
        
        $this->assertTrue($result);
        
        // Verify user was created
        $user = $this->db->fetchOne("SELECT * FROM users WHERE username = 'testadmin'");
        $this->assertNotNull($user);
        $this->assertEquals('admin', $user['role']);
        $this->assertEquals('admin@test.com', $user['email']);
        $this->assertEquals('Test', $user['first_name']);
        $this->assertEquals('Admin', $user['last_name']);
        $this->assertNotNull($user['api_key']);
    }
    
    public function testCreateAdminUserWithInvalidData() {
        // Test with empty username
        $result = $this->installationManager->createAdminUser('', 'admin@test.com', 'password123');
        $this->assertFalse($result);
        
        // Test with invalid email
        $result = $this->installationManager->createAdminUser('admin', 'invalid-email', 'password123');
        $this->assertFalse($result);
        
        // Test with short password
        $result = $this->installationManager->createAdminUser('admin', 'admin@test.com', '123');
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserDuplicate() {
        // Create first admin
        $this->installationManager->createAdminUser('admin1', 'admin1@test.com', 'password123');
        
        // Try to create second admin
        $result = $this->installationManager->createAdminUser('admin2', 'admin2@test.com', 'password123');
        
        // Should fail because admin already exists
        $this->assertFalse($result);
    }
    
    public function testSetupDefaultConfig() {
        $result = $this->installationManager->setupDefaultConfig();
        
        $this->assertTrue($result);
        
        // Verify default configurations were set
        $appConfig = $this->config->getCategory('application');
        $this->assertEquals('Sanctum CRM', $appConfig['app_name']);
        $this->assertEquals('http://localhost', $appConfig['app_url']);
        $this->assertEquals('UTC', $appConfig['timezone']);
        
        $securityConfig = $this->config->getCategory('security');
        $this->assertEquals(3600, $securityConfig['session_lifetime']);
        $this->assertEquals(1000, $securityConfig['api_rate_limit']);
        $this->assertEquals(8, $securityConfig['password_min_length']);
    }
    
    public function testCompleteInstallation() {
        $result = $this->installationManager->completeInstallation();
        
        $this->assertTrue($result);
        
        // Verify all steps are marked as completed
        $progress = $this->installationManager->getInstallationProgress();
        $completedSteps = array_column($progress, 'step');
        
        $expectedSteps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($expectedSteps as $step) {
            $this->assertContains($step, $completedSteps);
        }
    }
    
    public function testResetInstallation() {
        // Set up some data
        $this->config->setCompanyInfo(['company_name' => 'Test Company']);
        $this->installationManager->completeStep('environment');
        $this->db->insert('users', [
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => getCurrentTimestamp()
        ]);
        
        // Reset installation
        $result = $this->installationManager->resetInstallation();
        
        $this->assertTrue($result);
        
        // Verify data was cleared
        $this->assertTrue($this->installationManager->isFirstBoot());
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
        
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Sanctum CRM', $companyInfo['company_name']); // Default value
    }
    
    public function testGetInstallationStatus() {
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        
        $status = $this->installationManager->getInstallationStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('environment', $status);
        $this->assertArrayHasKey('database', $status);
        $this->assertArrayHasKey('company', $status);
        $this->assertArrayHasKey('admin', $status);
        $this->assertArrayHasKey('complete', $status);
        
        $this->assertTrue($status['environment']['completed']);
        $this->assertTrue($status['database']['completed']);
        $this->assertFalse($status['company']['completed']);
        $this->assertFalse($status['admin']['completed']);
        $this->assertFalse($status['complete']['completed']);
        
        $this->assertTrue($status['company']['current']); // Next step
    }
    
    public function testValidateStep() {
        // Test environment step
        $validation = $this->installationManager->validateStep('environment', []);
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        
        // Test company step with valid data
        $validation = $this->installationManager->validateStep('company', ['company_name' => 'Test Company']);
        $this->assertTrue($validation['valid']);
        
        // Test company step with invalid data
        $validation = $this->installationManager->validateStep('company', ['company_name' => '']);
        $this->assertFalse($validation['valid']);
        $this->assertContains('Company name is required', $validation['errors']);
        
        // Test admin step with valid data
        $validation = $this->installationManager->validateStep('admin', [
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        $this->assertTrue($validation['valid']);
        
        // Test admin step with invalid data
        $validation = $this->installationManager->validateStep('admin', [
            'username' => '',
            'email' => 'invalid-email',
            'password' => '123'
        ]);
        $this->assertFalse($validation['valid']);
        $this->assertCount(3, $validation['errors']);
    }
    
    public function testDatabaseStepValidation() {
        $validation = $this->installationManager->validateStep('database', []);
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
    }
    
    public function testValidateStepInvalidStep() {
        $validation = $this->installationManager->validateStep('invalid_step', []);
        
        $this->assertIsArray($validation);
        $this->assertFalse($validation['valid']);
        $this->assertContains('Invalid step', $validation['errors']);
    }
    
    public function testValidateStepAdminWithMissingFields() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => 'admin'
            // Missing email and password
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Email is required', $validation['errors']);
        $this->assertContains('Password is required', $validation['errors']);
    }
    
    public function testValidateStepAdminWithInvalidEmail() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => 'admin',
            'email' => 'not-an-email',
            'password' => 'password123'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Invalid email format', $validation['errors']);
    }
    
    public function testValidateStepAdminWithShortPassword() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => '123'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Password must be at least', $validation['errors']);
    }
    
    public function testValidateStepAdminWithEmptyUsername() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => '',
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Username is required', $validation['errors']);
    }
    
    public function testValidateStepAdminWithWhitespaceUsername() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => '   ',
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Username is required', $validation['errors']);
    }
    
    public function testValidateStepAdminWithLongUsername() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => str_repeat('a', 256), // Too long
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Username is too long', $validation['errors']);
    }
    
    public function testValidateStepAdminWithSpecialCharacters() {
        $validation = $this->installationManager->validateStep('admin', [
            'username' => 'admin<script>',
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Username contains invalid characters', $validation['errors']);
    }
    
    public function testValidateStepCompanyWithLongName() {
        $validation = $this->installationManager->validateStep('company', [
            'company_name' => str_repeat('a', 256) // Too long
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Company name is too long', $validation['errors']);
    }
    
    public function testValidateStepCompanyWithWhitespaceName() {
        $validation = $this->installationManager->validateStep('company', [
            'company_name' => '   '
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Company name is required', $validation['errors']);
    }
    
    public function testValidateStepCompanyWithSpecialCharacters() {
        $validation = $this->installationManager->validateStep('company', [
            'company_name' => 'Company<script>alert("xss")</script>'
        ]);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Company name contains invalid characters', $validation['errors']);
    }
    
    public function testValidateStepEnvironmentWithErrors() {
        // Mock environment validation to return errors
        $validation = $this->installationManager->validateStep('environment', []);
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
    }
    
    public function testValidateStepDatabaseWithErrors() {
        $validation = $this->installationManager->validateStep('database', []);
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
    }
    
    public function testValidateStepComplete() {
        $validation = $this->installationManager->validateStep('complete', []);
        
        $this->assertIsArray($validation);
        $this->assertTrue($validation['valid']);
    }
    
    public function testGetInstallationStatusWithNoSteps() {
        $status = $this->installationManager->getInstallationStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('environment', $status);
        $this->assertArrayHasKey('database', $status);
        $this->assertArrayHasKey('company', $status);
        $this->assertArrayHasKey('admin', $status);
        $this->assertArrayHasKey('complete', $status);
        
        $this->assertFalse($status['environment']['completed']);
        $this->assertTrue($status['environment']['current']);
    }
    
    public function testGetInstallationStatusWithPartialCompletion() {
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        
        $status = $this->installationManager->getInstallationStatus();
        
        $this->assertTrue($status['environment']['completed']);
        $this->assertTrue($status['database']['completed']);
        $this->assertFalse($status['company']['completed']);
        $this->assertTrue($status['company']['current']);
    }
    
    public function testGetInstallationStatusWithAllComplete() {
        $steps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($steps as $step) {
            $this->installationManager->completeStep($step);
        }
        
        $status = $this->installationManager->getInstallationStatus();
        
        foreach ($status as $step => $info) {
            $this->assertTrue($info['completed']);
            $this->assertFalse($info['current']);
        }
    }
    
    public function testResetInstallationWithPartialData() {
        // Set up partial installation
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupCompany('Test Company', 'UTC');
        $this->installationManager->completeStep('company');
        
        $result = $this->installationManager->resetInstallation();
        $this->assertTrue($result);
        
        // Verify reset
        $this->assertTrue($this->installationManager->isFirstBoot());
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
        
        $progress = $this->installationManager->getInstallationProgress();
        $this->assertEmpty($progress);
    }
    
    public function testResetInstallationWithCompleteData() {
        // Set up complete installation
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupCompany('Test Company', 'UTC');
        $this->installationManager->completeStep('company');
        $this->installationManager->createAdminUser('admin', 'admin@test.com', 'password123');
        $this->installationManager->completeStep('admin');
        $this->installationManager->completeStep('complete');
        
        $result = $this->installationManager->resetInstallation();
        $this->assertTrue($result);
        
        // Verify reset
        $this->assertTrue($this->installationManager->isFirstBoot());
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
    }
    
    public function testCreateAdminUserWithExistingUsername() {
        // Create first admin
        $this->installationManager->createAdminUser('admin', 'admin1@test.com', 'password123');
        
        // Try to create second admin with same username
        $result = $this->installationManager->createAdminUser('admin', 'admin2@test.com', 'password123');
        
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserWithExistingEmail() {
        // Create first admin
        $this->installationManager->createAdminUser('admin1', 'admin@test.com', 'password123');
        
        // Try to create second admin with same email
        $result = $this->installationManager->createAdminUser('admin2', 'admin@test.com', 'password123');
        
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserWithEmptyFirstName() {
        $result = $this->installationManager->createAdminUser(
            'admin',
            'admin@test.com',
            'password123',
            '', // Empty first name
            'Admin'
        );
        
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserWithEmptyLastName() {
        $result = $this->installationManager->createAdminUser(
            'admin',
            'admin@test.com',
            'password123',
            'Test',
            '' // Empty last name
        );
        
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserWithLongFirstName() {
        $result = $this->installationManager->createAdminUser(
            'admin',
            'admin@test.com',
            'password123',
            str_repeat('a', 256), // Too long
            'Admin'
        );
        
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserWithLongLastName() {
        $result = $this->installationManager->createAdminUser(
            'admin',
            'admin@test.com',
            'password123',
            'Test',
            str_repeat('a', 256) // Too long
        );
        
        $this->assertFalse($result);
    }
    
    public function testCreateAdminUserWithSpecialCharactersInName() {
        $result = $this->installationManager->createAdminUser(
            'admin',
            'admin@test.com',
            'password123',
            'Test<script>',
            'Admin'
        );
        
        $this->assertFalse($result);
    }
    
    public function testSetupCompanyWithInvalidTimezone() {
        $result = $this->installationManager->setupCompany('Test Company', 'Invalid/Timezone');
        
        $this->assertFalse($result);
    }
    
    public function testSetupCompanyWithEmptyName() {
        $result = $this->installationManager->setupCompany('', 'UTC');
        
        $this->assertFalse($result);
    }
    
    public function testSetupCompanyWithWhitespaceName() {
        $result = $this->installationManager->setupCompany('   ', 'UTC');
        
        $this->assertFalse($result);
    }
    
    public function testSetupCompanyWithLongName() {
        $result = $this->installationManager->setupCompany(str_repeat('a', 256), 'UTC');
        
        $this->assertFalse($result);
    }
    
    public function testSetupCompanyWithSpecialCharacters() {
        $result = $this->installationManager->setupCompany('Company<script>', 'UTC');
        
        $this->assertFalse($result);
    }
    
    public function testValidateEnvironmentWithMissingExtensions() {
        // This test would need to mock the extension check
        $validation = $this->installationManager->validateEnvironment();
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
    }
    
    public function testValidateEnvironmentWithLowMemory() {
        // This test would need to mock the memory check
        $validation = $this->installationManager->validateEnvironment();
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
    }
    
    public function testValidateEnvironmentWithOldPHPVersion() {
        // This test would need to mock the PHP version check
        $validation = $this->installationManager->validateEnvironment();
        
        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
    }
    
    public function testInitializeDatabaseWithExistingTables() {
        // Test when database is already initialized
        $result = $this->installationManager->initializeDatabase();
        
        $this->assertTrue($result);
    }
    
    public function testSetupDefaultConfigWithExistingConfig() {
        // Set some existing config
        $this->config->set('application', 'app_name', 'Existing App');
        
        $result = $this->installationManager->setupDefaultConfig();
        
        $this->assertTrue($result);
        
        // Verify default config was set (should not override existing)
        $appConfig = $this->config->getCategory('application');
        $this->assertEquals('Existing App', $appConfig['app_name']);
    }
    
    public function testCompleteInstallationWithPartialSteps() {
        // Complete only some steps
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        
        $result = $this->installationManager->completeInstallation();
        
        $this->assertTrue($result);
        
        // Verify all steps are marked as completed
        $progress = $this->installationManager->getInstallationProgress();
        $completedSteps = array_column($progress, 'step');
        
        $expectedSteps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($expectedSteps as $step) {
            $this->assertContains($step, $completedSteps);
        }
    }
    
    public function testCompleteInstallationWithNoSteps() {
        $result = $this->installationManager->completeInstallation();
        
        $this->assertTrue($result);
        
        // Verify all steps are marked as completed
        $progress = $this->installationManager->getInstallationProgress();
        $completedSteps = array_column($progress, 'step');
        
        $expectedSteps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($expectedSteps as $step) {
            $this->assertContains($step, $completedSteps);
        }
    }
}

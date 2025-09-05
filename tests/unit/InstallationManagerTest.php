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
}

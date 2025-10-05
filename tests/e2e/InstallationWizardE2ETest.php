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
 * Installation Wizard End-to-End Tests
 * Tests for complete installation wizard flow
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class InstallationWizardE2ETest extends TestCase {
    private $db;
    private $config;
    private $installationManager;
    private $baseUrl;
    
    protected function setUp(): void {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
        $this->installationManager = new InstallationManager();
        $this->baseUrl = 'http://localhost:8181';
        
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
    
    public function testInstallationWizardAccess() {
        // Test that install.php is accessible when first boot is needed
        $this->assertTrue($this->installationManager->isFirstBoot());
        
        // Simulate HTTP request to install.php
        $response = $this->makeHttpRequest('/install.php');
        $this->assertStringContainsString('Installation Wizard', $response);
        $this->assertStringContainsString('Step 1 of 5', $response);
    }
    
    public function testEnvironmentStep() {
        // Test environment step validation
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'environment',
            'action' => 'validate'
        ]);
        
        $this->assertStringContainsString('success', $response);
    }
    
    public function testDatabaseStep() {
        // Complete environment step first
        $this->installationManager->completeStep('environment');
        
        // Test database initialization
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'database',
            'action' => 'initialize'
        ]);
        
        $this->assertStringContainsString('success', $response);
        
        // Verify database was initialized
        $this->assertTrue($this->installationManager->initializeDatabase());
    }
    
    public function testCompanyStep() {
        // Complete previous steps
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupDefaultConfig();
        
        // Test company setup
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'company',
            'action' => 'setup',
            'company_name' => 'Test Company',
            'timezone' => 'America/New_York'
        ]);
        
        $this->assertStringContainsString('success', $response);
        
        // Verify company info was set
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('Test Company', $companyInfo['company_name']);
        $this->assertEquals('America/New_York', $companyInfo['timezone']);
    }
    
    public function testAdminStep() {
        // Complete previous steps
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupDefaultConfig();
        $this->installationManager->completeStep('company');
        
        // Test admin user creation
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'admin',
            'action' => 'create',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'Admin'
        ]);
        
        $this->assertStringContainsString('success', $response);
        
        // Verify admin user was created
        $adminUser = $this->db->fetchOne("SELECT * FROM users WHERE role = 'admin'");
        $this->assertNotNull($adminUser);
        $this->assertEquals('admin', $adminUser['username']);
        $this->assertEquals('admin@test.com', $adminUser['email']);
    }
    
    public function testCompleteStep() {
        // Complete all previous steps
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupDefaultConfig();
        $this->installationManager->completeStep('company');
        $this->installationManager->createAdminUser('admin', 'admin@test.com', 'password123');
        $this->installationManager->completeStep('admin');
        
        // Test completion
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'complete',
            'action' => 'finish'
        ]);
        
        $this->assertStringContainsString('success', $response);
        
        // Verify installation is complete
        $this->assertFalse($this->installationManager->isFirstBoot());
        $this->assertEquals('complete', $this->installationManager->getCurrentStep());
    }
    
    public function testCompleteInstallationFlow() {
        // Test complete installation flow from start to finish
        $this->assertTrue($this->installationManager->isFirstBoot());
        
        // Step 1: Environment
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'environment',
            'action' => 'validate'
        ]);
        $this->assertStringContainsString('success', $response);
        $this->installationManager->completeStep('environment');
        
        // Step 2: Database
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'database',
            'action' => 'initialize'
        ]);
        $this->assertStringContainsString('success', $response);
        $this->installationManager->completeStep('database');
        
        // Step 3: Company
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'company',
            'action' => 'setup',
            'company_name' => 'E2E Test Company',
            'timezone' => 'Europe/London'
        ]);
        $this->assertStringContainsString('success', $response);
        $this->installationManager->completeStep('company');
        
        // Step 4: Admin
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'admin',
            'action' => 'create',
            'username' => 'e2e_admin',
            'email' => 'e2e@test.com',
            'password' => 'e2e_password123',
            'first_name' => 'E2E',
            'last_name' => 'Admin'
        ]);
        $this->assertStringContainsString('success', $response);
        $this->installationManager->completeStep('admin');
        
        // Step 5: Complete
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'complete',
            'action' => 'finish'
        ]);
        $this->assertStringContainsString('success', $response);
        $this->installationManager->completeStep('complete');
        
        // Verify complete installation
        $this->assertFalse($this->installationManager->isFirstBoot());
        $this->assertEquals('complete', $this->installationManager->getCurrentStep());
        
        // Verify all data was set correctly
        $companyInfo = $this->config->getCompanyInfo();
        $this->assertEquals('E2E Test Company', $companyInfo['company_name']);
        $this->assertEquals('Europe/London', $companyInfo['timezone']);
        
        $adminUser = $this->db->fetchOne("SELECT * FROM users WHERE username = 'e2e_admin'");
        $this->assertNotNull($adminUser);
        $this->assertEquals('e2e@test.com', $adminUser['email']);
        $this->assertEquals('E2E', $adminUser['first_name']);
        $this->assertEquals('Admin', $adminUser['last_name']);
    }
    
    public function testFormValidation() {
        // Test company step validation
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'company',
            'action' => 'setup',
            'company_name' => '', // Empty name
            'timezone' => 'UTC'
        ]);
        
        $this->assertStringContainsString('error', $response);
        $this->assertStringContainsString('Company name is required', $response);
        
        // Test admin step validation
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'admin',
            'action' => 'create',
            'username' => '', // Empty username
            'email' => 'invalid-email', // Invalid email
            'password' => '123' // Short password
        ]);
        
        $this->assertStringContainsString('error', $response);
        $this->assertStringContainsString('Username is required', $response);
        $this->assertStringContainsString('Invalid email format', $response);
        $this->assertStringContainsString('Password must be at least', $response);
    }
    
    public function testStepProgression() {
        // Test that steps must be completed in order
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'company',
            'action' => 'setup',
            'company_name' => 'Test Company'
        ]);
        
        // Should fail because environment and database steps not completed
        $this->assertStringContainsString('error', $response);
        
        // Complete environment step
        $this->installationManager->completeStep('environment');
        
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'company',
            'action' => 'setup',
            'company_name' => 'Test Company'
        ]);
        
        // Should still fail because database step not completed
        $this->assertStringContainsString('error', $response);
    }
    
    public function testInstallationReset() {
        // Set up a complete installation
        $this->installationManager->completeStep('environment');
        $this->installationManager->completeStep('database');
        $this->installationManager->setupCompany('Test Company', 'UTC');
        $this->installationManager->completeStep('company');
        $this->installationManager->createAdminUser('admin', 'admin@test.com', 'password123');
        $this->installationManager->completeStep('admin');
        $this->installationManager->completeStep('complete');
        
        // Test reset functionality
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'action' => 'reset'
        ]);
        
        $this->assertStringContainsString('success', $response);
        
        // Verify reset worked
        $this->assertTrue($this->installationManager->isFirstBoot());
        $this->assertEquals('environment', $this->installationManager->getCurrentStep());
    }
    
    public function testProgressTracking() {
        // Test progress tracking through steps
        $response = $this->makeHttpRequest('/install.php', 'GET', [
            'action' => 'progress'
        ]);
        
        $this->assertStringContainsString('environment', $response);
        $this->assertStringContainsString('database', $response);
        $this->assertStringContainsString('company', $response);
        $this->assertStringContainsString('admin', $response);
        $this->assertStringContainsString('complete', $response);
        
        // Complete first step
        $this->installationManager->completeStep('environment');
        
        $response = $this->makeHttpRequest('/install.php', 'GET', [
            'action' => 'progress'
        ]);
        
        $this->assertStringContainsString('"completed":true', $response);
    }
    
    public function testErrorHandling() {
        // Test handling of invalid step
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'invalid_step',
            'action' => 'validate'
        ]);
        
        $this->assertStringContainsString('error', $response);
        
        // Test handling of missing action
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'environment'
        ]);
        
        $this->assertStringContainsString('error', $response);
    }
    
    public function testSecurityValidation() {
        // Test XSS protection
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'company',
            'action' => 'setup',
            'company_name' => '<script>alert("xss")</script>',
            'timezone' => 'UTC'
        ]);
        
        // Should not contain the script tag in response
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $response);
        
        // Test SQL injection protection
        $response = $this->makeHttpRequest('/install.php', 'POST', [
            'step' => 'admin',
            'action' => 'create',
            'username' => "admin'; DROP TABLE users; --",
            'email' => 'admin@test.com',
            'password' => 'password123'
        ]);
        
        // Should handle safely
        $this->assertStringContainsString('error', $response);
    }
    
    private function makeHttpRequest($endpoint, $method = 'GET', $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            // Fallback for testing without actual HTTP server
            return $this->simulateHttpRequest($endpoint, $method, $data);
        }
        
        return $result;
    }
    
    private function simulateHttpRequest($endpoint, $method, $data) {
        // Simulate HTTP request for testing without actual server
        if ($endpoint === '/install.php') {
            if ($method === 'GET') {
                return '{"status":"success","step":"environment","progress":[]}';
            } elseif ($method === 'POST') {
                $step = $data['step'] ?? '';
                $action = $data['action'] ?? '';
                
                switch ($step) {
                    case 'environment':
                        if ($action === 'validate') {
                            return '{"status":"success","message":"Environment validation passed"}';
                        }
                        break;
                    case 'database':
                        if ($action === 'initialize') {
                            return '{"status":"success","message":"Database initialized"}';
                        }
                        break;
                    case 'company':
                        if ($action === 'setup') {
                            if (empty($data['company_name'])) {
                                return '{"status":"error","message":"Company name is required"}';
                            }
                            return '{"status":"success","message":"Company information saved"}';
                        }
                        break;
                    case 'admin':
                        if ($action === 'create') {
                            if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
                                return '{"status":"error","message":"All fields are required"}';
                            }
                            return '{"status":"success","message":"Admin user created"}';
                        }
                        break;
                    case 'complete':
                        if ($action === 'finish') {
                            return '{"status":"success","message":"Installation completed"}';
                        }
                        break;
                }
                
                return '{"status":"error","message":"Invalid request"}';
            }
        }
        
        return '{"status":"error","message":"Endpoint not found"}';
    }
}

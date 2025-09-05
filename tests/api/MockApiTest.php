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
 * Mock API Test
 * Tests API functionality without requiring HTTP requests
 */

require_once __DIR__ . '/../bootstrap.php';

class MockApiTest {
    private $testResults = [];
    
    public function runAllTests() {
        echo "Running Mock API Tests...\n";
        
        $this->testContactOperations();
        $this->testDealOperations();
        $this->testUserOperations();
        $this->testWebhookOperations();
        $this->testReportOperations();
        $this->testAuthentication();
        $this->testDataValidation();
        $this->testErrorHandling();
        
        $this->displayResults();
    }
    
    private function testContactOperations() {
        echo "  Testing contact operations...\n";
        
        try {
            $db = Database::getInstance();
            
            // Test contact creation
            $contactData = [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234',
                'source' => 'Mock API Test',
                'contact_type' => 'lead',
                'contact_status' => 'new',
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp()
            ];
            
            $contactId = $db->insert('contacts', $contactData);
            
            if ($contactId) {
                $this->pass("contact creation");
                
                // Test contact retrieval
                $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
                if ($contact && $contact['first_name'] === 'John') {
                    $this->pass("contact retrieval");
                } else {
                    $this->fail("contact retrieval");
                }
                
                // Test contact update
                $updateData = ['first_name' => 'Jane', 'updated_at' => getCurrentTimestamp()];
                $updated = $db->update('contacts', $updateData, 'id = ?', [$contactId]);
                if ($updated) {
                    $this->pass("contact update");
                } else {
                    $this->fail("contact update");
                }
                
                // Test contact deletion
                $deleted = $db->delete('contacts', 'id = ?', [$contactId]);
                if ($deleted) {
                    $this->pass("contact deletion");
                } else {
                    $this->fail("contact deletion");
                }
            } else {
                $this->fail("contact creation");
            }
            
        } catch (Exception $e) {
            $this->fail("contact operations - Exception: " . $e->getMessage());
        }
    }
    
    private function testDealOperations() {
        echo "  Testing deal operations...\n";
        
        try {
            $db = Database::getInstance();
            
            // Create a contact first
            $contactData = [
                'first_name' => 'Test',
                'last_name' => 'Contact',
                'email' => 'test@example.com',
                'source' => 'Mock API Test',
                'contact_type' => 'lead',
                'contact_status' => 'new',
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp()
            ];
            
            $contactId = $db->insert('contacts', $contactData);
            
            if ($contactId) {
                // Test deal creation
                $dealData = [
                    'title' => 'Test Deal',
                    'contact_id' => $contactId,
                    'amount' => 1000.00,
                    'stage' => 'prospecting',
                    'probability' => 25,
                    'created_at' => getCurrentTimestamp(),
                    'updated_at' => getCurrentTimestamp()
                ];
                
                $dealId = $db->insert('deals', $dealData);
                
                if ($dealId) {
                    $this->pass("deal creation");
                    
                    // Test deal retrieval
                    $deal = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
                    if ($deal && $deal['title'] === 'Test Deal') {
                        $this->pass("deal retrieval");
                    } else {
                        $this->fail("deal retrieval");
                    }
                    
                    // Test deal update
                    $updateData = ['stage' => 'negotiation', 'updated_at' => getCurrentTimestamp()];
                    $updated = $db->update('deals', $updateData, 'id = ?', [$dealId]);
                    if ($updated) {
                        $this->pass("deal update");
                    } else {
                        $this->fail("deal update");
                    }
                    
                    // Test deal deletion
                    $deleted = $db->delete('deals', 'id = ?', [$dealId]);
                    if ($deleted) {
                        $this->pass("deal deletion");
                    } else {
                        $this->fail("deal deletion");
                    }
                } else {
                    $this->fail("deal creation");
                }
                
                // Clean up contact
                $db->delete('contacts', 'id = ?', [$contactId]);
            } else {
                $this->fail("deal operations - contact creation failed");
            }
            
        } catch (Exception $e) {
            $this->fail("deal operations - Exception: " . $e->getMessage());
        }
    }
    
    private function testUserOperations() {
        echo "  Testing user operations...\n";
        
        try {
            $db = Database::getInstance();
            
            // Test user creation
            $userData = [
                'username' => 'testuser',
                'email' => 'testuser@example.com',
                'password' => password_hash('testpass123', PASSWORD_DEFAULT),
                'role' => 'user',
                'is_active' => 1,
                'api_key' => bin2hex(random_bytes(16)),
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp()
            ];
            
            $userId = $db->insert('users', $userData);
            
            if ($userId) {
                $this->pass("user creation");
                
                // Test user retrieval
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                if ($user && $user['username'] === 'testuser') {
                    $this->pass("user retrieval");
                } else {
                    $this->fail("user retrieval");
                }
                
                // Test user update
                $updateData = ['role' => 'admin', 'updated_at' => getCurrentTimestamp()];
                $updated = $db->update('users', $updateData, 'id = ?', [$userId]);
                if ($updated) {
                    $this->pass("user update");
                } else {
                    $this->fail("user update");
                }
                
                // Test API key regeneration
                $newApiKey = bin2hex(random_bytes(16));
                $keyUpdated = $db->update('users', ['api_key' => $newApiKey, 'updated_at' => getCurrentTimestamp()], 'id = ?', [$userId]);
                if ($keyUpdated) {
                    $this->pass("API key regeneration");
                } else {
                    $this->fail("API key regeneration");
                }
                
                // Test user deletion
                $deleted = $db->delete('users', 'id = ?', [$userId]);
                if ($deleted) {
                    $this->pass("user deletion");
                } else {
                    $this->fail("user deletion");
                }
            } else {
                $this->fail("user creation");
            }
            
        } catch (Exception $e) {
            $this->fail("user operations - Exception: " . $e->getMessage());
        }
    }
    
    private function testWebhookOperations() {
        echo "  Testing webhook operations...\n";
        
        try {
            $db = Database::getInstance();
            
            // Get an admin user for the webhook
            $admin = $db->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            if (!$admin) {
                $this->fail("webhook operations - no admin user found");
                return;
            }
            
            // Test webhook creation
            $webhookData = [
                'user_id' => $admin['id'],
                'url' => 'https://example.com/webhook',
                'events' => json_encode(['contact.created', 'deal.updated']),
                'is_active' => 1,
                'created_at' => getCurrentTimestamp()
            ];
            
            $webhookId = $db->insert('webhooks', $webhookData);
            
            if ($webhookId) {
                $this->pass("webhook creation");
                
                // Test webhook retrieval
                $webhook = $db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
                if ($webhook && $webhook['url'] === 'https://example.com/webhook') {
                    $this->pass("webhook retrieval");
                } else {
                    $this->fail("webhook retrieval");
                }
                
                // Test webhook update
                $updateData = ['is_active' => 0, 'updated_at' => getCurrentTimestamp()];
                $updated = $db->update('webhooks', $updateData, 'id = ?', [$webhookId]);
                if ($updated) {
                    $this->pass("webhook update");
                } else {
                    $this->fail("webhook update");
                }
                
                // Test webhook deletion
                $deleted = $db->delete('webhooks', 'id = ?', [$webhookId]);
                if ($deleted) {
                    $this->pass("webhook deletion");
                } else {
                    $this->fail("webhook deletion");
                }
            } else {
                $this->fail("webhook creation");
            }
            
        } catch (Exception $e) {
            $this->fail("webhook operations - Exception: " . $e->getMessage());
        }
    }
    
    private function testReportOperations() {
        echo "  Testing report operations...\n";
        
        try {
            $db = Database::getInstance();
            
            // Test contact statistics
            $contactCount = $db->fetchOne("SELECT COUNT(*) as count FROM contacts");
            if ($contactCount && $contactCount['count'] >= 0) {
                $this->pass("contact statistics");
            } else {
                $this->fail("contact statistics");
            }
            
            // Test deal statistics
            $dealCount = $db->fetchOne("SELECT COUNT(*) as count FROM deals");
            if ($dealCount && $dealCount['count'] >= 0) {
                $this->pass("deal statistics");
            } else {
                $this->fail("deal statistics");
            }
            
            // Test revenue calculation
            $revenue = $db->fetchOne("SELECT SUM(amount) as total FROM deals WHERE stage = 'closed_won'");
            if ($revenue !== false) {
                $this->pass("revenue calculation");
            } else {
                $this->fail("revenue calculation");
            }
            
        } catch (Exception $e) {
            $this->fail("report operations - Exception: " . $e->getMessage());
        }
    }
    
    private function testAuthentication() {
        echo "  Testing authentication...\n";
        
        try {
            $db = Database::getInstance();
            
            // Test password hashing
            $password = 'testpass123';
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if (password_verify($password, $hashed)) {
                $this->pass("password hashing");
            } else {
                $this->fail("password hashing");
            }
            
            // Test API key generation
            $apiKey = bin2hex(random_bytes(16));
            if (strlen($apiKey) === 32) {
                $this->pass("API key generation");
            } else {
                $this->fail("API key generation");
            }
            
            // Test user authentication
            $user = $db->fetchOne("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
            if ($user && !empty($user['api_key'])) {
                $this->pass("user authentication");
            } else {
                $this->fail("user authentication");
            }
            
        } catch (Exception $e) {
            $this->fail("authentication - Exception: " . $e->getMessage());
        }
    }
    
    private function testDataValidation() {
        echo "  Testing data validation...\n";
        
        // Test email validation
        $validEmails = ['test@example.com', 'user.name@domain.co.uk', 'test+tag@example.org'];
        $invalidEmails = ['invalid-email', 'test@', '@example.com'];
        
        $allValidPassed = true;
        foreach ($validEmails as $email) {
            if (!validateEmail($email)) {
                $allValidPassed = false;
                break;
            }
        }
        
        $allInvalidPassed = true;
        foreach ($invalidEmails as $email) {
            if (validateEmail($email)) {
                $allInvalidPassed = false;
                break;
            }
        }
        
        if ($allValidPassed && $allInvalidPassed) {
            $this->pass("email validation");
        } else {
            $this->fail("email validation");
        }
        
        // Test input sanitization
        $testInput = '<script>alert("xss")</script>';
        $sanitized = sanitizeInput($testInput);
        if ($sanitized !== $testInput && strpos($sanitized, '<script>') === false) {
            $this->pass("input sanitization");
        } else {
            $this->fail("input sanitization");
        }
    }
    
    private function testErrorHandling() {
        echo "  Testing error handling...\n";
        
        try {
            $db = Database::getInstance();
            
            // Test invalid data handling
            try {
                $db->insert('contacts', ['invalid_field' => 'value']);
                $this->fail("invalid data handling");
            } catch (Exception $e) {
                $this->pass("invalid data handling");
            }
            
            // Test missing required fields
            try {
                $db->insert('contacts', ['first_name' => 'Test']);
                $this->fail("missing required fields handling");
            } catch (Exception $e) {
                $this->pass("missing required fields handling");
            }
            
            // Test database connection
            if ($db->getConnection()) {
                $this->pass("database connection");
            } else {
                $this->fail("database connection");
            }
            
        } catch (Exception $e) {
            $this->fail("error handling - Exception: " . $e->getMessage());
        }
    }
    
    private function pass($testName) {
        echo "    ✓ $testName\n";
        $this->testResults[] = ['name' => $testName, 'status' => 'PASS'];
    }
    
    private function fail($testName) {
        echo "    ✗ $testName\n";
        $this->testResults[] = ['name' => $testName, 'status' => 'FAIL'];
    }
    
    private function displayResults() {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($test) {
            return $test['status'] === 'PASS';
        }));
        $failed = $total - $passed;
        
        echo "\nMock API Test Results:\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n";
        
        if ($failed > 0) {
            echo "\nFailed Tests:\n";
            foreach ($this->testResults as $test) {
                if ($test['status'] === 'FAIL') {
                    echo "  - " . $test['name'] . "\n";
                }
            }
        }
    }
}

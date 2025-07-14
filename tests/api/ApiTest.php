<?php
/**
 * API Integration Tests
 * FreeOpsDAO CRM - API Endpoint Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class ApiTest {
    private $baseUrl;
    private $apiKey;
    private $headers;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8000';
        
        // Get admin API key
        $db = TestUtils::getTestDatabase();
        $admin = $db->fetchOne("SELECT api_key FROM users WHERE username = 'admin'");
        $this->apiKey = $admin['api_key'] ?? '';
        
        $this->headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
    }
    
    public function runAllTests() {
        echo "Running API Integration Tests...\n";
        
        if (!$this->apiKey) {
            echo "FAIL - No API key available for testing\n";
            return;
        }
        
        $this->testContactsEndpoints();
        $this->testDealsEndpoints();
        $this->testUsersEndpoints();
        $this->testErrorHandling();
        $this->testAuthentication();
        $this->testJsonResponses();
        $this->testOpenApiDocumentation();
        
        echo "All API tests completed!\n";
    }
    
    public function testContactsEndpoints() {
        echo "  Testing Contacts API endpoints...\n";
        
        // Test GET /api/v1/contacts
        $this->testGetContacts();
        
        // Test POST /api/v1/contacts
        $contactId = $this->testCreateContact();
        
        if ($contactId) {
            // Test GET /api/v1/contacts/{id}
            $this->testGetContact($contactId);
            
            // Test PUT /api/v1/contacts/{id}
            $this->testUpdateContact($contactId);
            
            // Test PUT /api/v1/contacts/{id}/convert
            $this->testConvertContact($contactId);
            
            // Test DELETE /api/v1/contacts/{id}
            $this->testDeleteContact($contactId);
        }
    }
    
    public function testGetContacts() {
        echo "    Testing GET /api/v1/contacts... ";
        
        $response = $this->makeRequest('GET', '/api/v1/contacts');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['contacts']) && is_array($data['contacts'])) {
                echo "PASS (" . count($data['contacts']) . " contacts)\n";
            } else {
                echo "FAIL - Invalid response structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testCreateContact() {
        echo "    Testing POST /api/v1/contacts... ";
        
        $contactData = [
            'first_name' => 'API',
            'last_name' => 'Test',
            'email' => 'apitest@example.com',
            'company' => 'Test Company',
            'contact_type' => 'lead',
            'evm_address' => '0x1234567890123456789012345678901234567890',
            'twitter_handle' => '@apitest'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/contacts', $contactData);
        
        if ($response['code'] === 201) {
            $data = json_decode($response['body'], true);
            if (isset($data['id'])) {
                echo "PASS (ID: {$data['id']})\n";
                return $data['id'];
            } else {
                echo "FAIL - No ID in response\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
        }
        
        return null;
    }
    
    public function testGetContact($contactId) {
        echo "    Testing GET /api/v1/contacts/{$contactId}... ";
        
        $response = $this->makeRequest('GET', "/api/v1/contacts/{$contactId}");
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['id']) && $data['id'] == $contactId) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid contact data\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testUpdateContact($contactId) {
        echo "    Testing PUT /api/v1/contacts/{$contactId}... ";
        
        $updateData = [
            'first_name' => 'Updated',
            'contact_status' => 'qualified'
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/contacts/{$contactId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['first_name'] === 'Updated' && $data['contact_status'] === 'qualified') {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testConvertContact($contactId) {
        echo "    Testing PUT /api/v1/contacts/{$contactId}/convert... ";
        
        $response = $this->makeRequest('PUT', "/api/v1/contacts/{$contactId}/convert");
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['contact_type'] === 'customer' && $data['contact_status'] === 'active') {
                echo "PASS\n";
            } else {
                echo "FAIL - Conversion not successful\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testDeleteContact($contactId) {
        echo "    Testing DELETE /api/v1/contacts/{$contactId}... ";
        
        $response = $this->makeRequest('DELETE', "/api/v1/contacts/{$contactId}");
        
        if ($response['code'] === 204) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testDealsEndpoints() {
        echo "  Testing Deals API endpoints...\n";
        
        // Create a contact first
        $contactId = TestUtils::createTestContact();
        
        // Test GET /api/v1/deals
        $this->testGetDeals();
        
        // Test POST /api/v1/deals
        $dealId = $this->testCreateDeal($contactId);
        
        if ($dealId) {
            // Test GET /api/v1/deals/{id}
            $this->testGetDeal($dealId);
            
            // Test PUT /api/v1/deals/{id}
            $this->testUpdateDeal($dealId);
            
            // Test DELETE /api/v1/deals/{id}
            $this->testDeleteDeal($dealId);
        }
        
        // Clean up
        TestUtils::getTestDatabase()->delete('contacts', 'id = ?', [$contactId]);
    }
    
    public function testGetDeals() {
        echo "    Testing GET /api/v1/deals... ";
        
        $response = $this->makeRequest('GET', '/api/v1/deals');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['deals']) && is_array($data['deals'])) {
                echo "PASS (" . count($data['deals']) . " deals)\n";
            } else {
                echo "FAIL - Invalid response structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testCreateDeal($contactId) {
        echo "    Testing POST /api/v1/deals... ";
        
        $dealData = [
            'title' => 'API Test Deal',
            'contact_id' => $contactId,
            'amount' => 5000.00,
            'stage' => 'prospecting',
            'probability' => 50
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/deals', $dealData);
        
        if ($response['code'] === 201) {
            $data = json_decode($response['body'], true);
            if (isset($data['id'])) {
                echo "PASS (ID: {$data['id']})\n";
                return $data['id'];
            } else {
                echo "FAIL - No ID in response\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
        }
        
        return null;
    }
    
    public function testGetDeal($dealId) {
        echo "    Testing GET /api/v1/deals/{$dealId}... ";
        
        $response = $this->makeRequest('GET', "/api/v1/deals/{$dealId}");
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['id']) && $data['id'] == $dealId) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid deal data\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testUpdateDeal($dealId) {
        echo "    Testing PUT /api/v1/deals/{$dealId}... ";
        
        $updateData = [
            'title' => 'Updated Deal',
            'stage' => 'qualification',
            'probability' => 75
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/deals/{$dealId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['title'] === 'Updated Deal' && $data['stage'] === 'qualification') {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testDeleteDeal($dealId) {
        echo "    Testing DELETE /api/v1/deals/{$dealId}... ";
        
        $response = $this->makeRequest('DELETE', "/api/v1/deals/{$dealId}");
        
        if ($response['code'] === 204) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testUsersEndpoints() {
        echo "  Testing Users API endpoints...\n";
        
        // Test GET /api/v1/users
        $this->testGetUsers();
        
        // Test POST /api/v1/users
        $userId = $this->testCreateUser();
        
        if ($userId) {
            // Test GET /api/v1/users/{id}
            $this->testGetUser($userId);
            
            // Test PUT /api/v1/users/{id}
            $this->testUpdateUser($userId);
            
            // Test DELETE /api/v1/users/{id}
            $this->testDeleteUser($userId);
        }
    }
    
    public function testGetUsers() {
        echo "    Testing GET /api/v1/users... ";
        
        $response = $this->makeRequest('GET', '/api/v1/users');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['users']) && is_array($data['users'])) {
                echo "PASS (" . count($data['users']) . " users)\n";
            } else {
                echo "FAIL - Invalid response structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testCreateUser() {
        echo "    Testing POST /api/v1/users... ";
        
        $userData = [
            'username' => 'apitestuser',
            'email' => 'apitestuser@example.com',
            'password' => 'testpass123',
            'first_name' => 'API',
            'last_name' => 'TestUser',
            'role' => 'user'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/users', $userData);
        
        if ($response['code'] === 201) {
            $data = json_decode($response['body'], true);
            if (isset($data['id'])) {
                echo "PASS (ID: {$data['id']})\n";
                return $data['id'];
            } else {
                echo "FAIL - No ID in response\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . ": " . $response['body'] . "\n";
        }
        
        return null;
    }
    
    public function testGetUser($userId) {
        echo "    Testing GET /api/v1/users/{$userId}... ";
        
        $response = $this->makeRequest('GET', "/api/v1/users/{$userId}");
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['id']) && $data['id'] == $userId) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid user data\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testUpdateUser($userId) {
        echo "    Testing PUT /api/v1/users/{$userId}... ";
        
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'User'
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['first_name'] === 'Updated' && $data['last_name'] === 'User') {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testDeleteUser($userId) {
        echo "    Testing DELETE /api/v1/users/{$userId}... ";
        
        $response = $this->makeRequest('DELETE', "/api/v1/users/{$userId}");
        
        if ($response['code'] === 204) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testErrorHandling() {
        echo "  Testing Error Handling...\n";
        
        // Test 404
        echo "    Testing 404 error... ";
        $response = $this->makeRequest('GET', '/api/v1/nonexistent');
        if ($response['code'] === 404) {
            $data = json_decode($response['body'], true);
            if (isset($data['error']) && isset($data['code'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid error response structure\n";
            }
        } else {
            echo "FAIL - Expected 404, got " . $response['code'] . "\n";
        }
        
        // Test 400 (invalid data)
        echo "    Testing 400 error... ";
        $response = $this->makeRequest('POST', '/api/v1/contacts', ['invalid' => 'data']);
        if ($response['code'] === 400) {
            $data = json_decode($response['body'], true);
            if (isset($data['error']) && isset($data['code'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid error response structure\n";
            }
        } else {
            echo "FAIL - Expected 400, got " . $response['code'] . "\n";
        }
    }
    
    public function testAuthentication() {
        echo "  Testing Authentication...\n";
        
        // Test without API key
        echo "    Testing without API key... ";
        $response = $this->makeRequest('GET', '/api/v1/contacts', null, []);
        if ($response['code'] === 401) {
            echo "PASS\n";
        } else {
            echo "FAIL - Expected 401, got " . $response['code'] . "\n";
        }
        
        // Test with invalid API key
        echo "    Testing with invalid API key... ";
        $invalidHeaders = [
            'Authorization: Bearer invalid_key',
            'Content-Type: application/json'
        ];
        $response = $this->makeRequest('GET', '/api/v1/contacts', null, $invalidHeaders);
        if ($response['code'] === 401) {
            echo "PASS\n";
        } else {
            echo "FAIL - Expected 401, got " . $response['code'] . "\n";
        }
    }
    
    public function testJsonResponses() {
        echo "  Testing JSON Response Format...\n";
        
        $response = $this->makeRequest('GET', '/api/v1/contacts');
        
        echo "    Testing Content-Type header... ";
        if (strpos($response['headers'], 'Content-Type: application/json') !== false) {
            echo "PASS\n";
        } else {
            echo "FAIL - Missing JSON Content-Type\n";
        }
        
        echo "    Testing valid JSON response... ";
        $data = json_decode($response['body'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "PASS\n";
        } else {
            echo "FAIL - Invalid JSON: " . json_last_error_msg() . "\n";
        }
    }
    
    public function testOpenApiDocumentation() {
        echo "  Testing OpenAPI Documentation...\n";
        
        echo "    Testing OpenAPI endpoint... ";
        $response = $this->makeRequest('GET', '/api/openapi.json', null, ['Content-Type: application/json']);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['openapi']) && isset($data['info']) && isset($data['paths'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid OpenAPI structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    private function makeRequest($method, $endpoint, $data = null, $headers = null) {
        $url = $this->baseUrl . $endpoint;
        $requestHeaders = $headers ?: $this->headers;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Split headers and body
        $headerSize = strpos($response, "\r\n\r\n");
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize + 4);
        
        return [
            'code' => $httpCode,
            'headers' => $headers,
            'body' => $body
        ];
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $test = new ApiTest();
    $test->runAllTests();
} 
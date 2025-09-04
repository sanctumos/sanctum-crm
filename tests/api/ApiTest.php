<?php
/**
 * API Integration Tests
 * Best Jobs in TA - API Endpoint Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class ApiTest {
    private $baseUrl;
    private $apiKey;
    private $headers;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:8000';
        
        // Get admin API key
        $this->apiKey = '77440a1aab7aae86a8ed5dff27b56df0';
        
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
        $this->testWebhooksEndpoints();
        $this->testReportsEndpoints();
        $this->testErrorHandling();
        $this->testAuthentication();
        $this->testJsonResponses();
        $this->testOpenApiDocumentation();
        $this->testImportEndpoints();
        
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
        $uniqueEmail = 'apitest_' . uniqid() . '@example.com';
        $contactData = [
            'first_name' => 'API',
            'last_name' => 'Test',
            'email' => $uniqueEmail,
            'phone' => '+1234567890',
            'company' => 'Test Company',
            'position' => 'Manager',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'source' => 'website',
            'notes' => 'API test contact'
        ];
        $response = $this->makeRequest('POST', '/api/v1/contacts', $contactData);
        file_put_contents(__DIR__ . '/contact_test_debug.log', date('c') . " POST /api/v1/contacts data=" . json_encode($contactData) . " response=" . json_encode($response) . "\n", FILE_APPEND);
        
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
            'contact_status' => 'qualified',
            'notes' => 'Updated via API'
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/contacts/{$contactId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['first_name'] === 'Updated' && $data['contact_status'] === 'qualified') {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected (first_name: {$data['first_name']}, contact_status: {$data['contact_status']})\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testConvertContact($contactId) {
        echo "    Testing POST /api/v1/contacts/{$contactId}/convert... ";
        
        $response = $this->makeRequest('POST', "/api/v1/contacts/{$contactId}/convert");
        
        // Debug output
        file_put_contents(__DIR__ . '/convert_test_debug.log', date('c') . " convert test response: " . json_encode($response) . "\n", FILE_APPEND);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && $data['contact_type'] === 'customer' && $data['contact_status'] === 'active') {
                echo "PASS\n";
            } else {
                echo "FAIL - Conversion not successful (data: " . json_encode($data) . ")\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . " (body: " . substr($response['body'], 0, 100) . ")\n";
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
        
        // Create a contact via the API first
        $contactId = $this->testCreateContact();
        
        // Test GET /api/v1/deals
        $this->testGetDeals();
        
        // Test POST /api/v1/deals
        $dealId = $this->testCreateDeal($contactId);
        
        if ($dealId) {
            // Test GET /api/v1/deals/{$dealId}
            $this->testGetDeal($dealId);
            
            // Test PUT /api/v1/deals/{$dealId}
            $this->testUpdateDeal($dealId);
            
            // Test DELETE /api/v1/deals/{$dealId}
            $this->testDeleteDeal($dealId);
        }
        
        // Clean up via API
        if ($contactId) {
            $this->testDeleteContact($contactId);
        }
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
            'probability' => 25,
            'expected_close_date' => date('Y-m-d', strtotime('+30 days')),
            'description' => 'API test deal'
        ];
        file_put_contents(__DIR__ . '/deal_test_debug.log', date('c') . " POST /api/v1/deals data=" . json_encode($dealData) . "\n", FILE_APPEND);
        
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
            'probability' => 50,
            'amount' => 7500.00
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/deals/{$dealId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['title'] === 'Updated Deal' && $data['stage'] === 'qualification') {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected (title: {$data['title']}, stage: {$data['stage']})\n";
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
            
            // Test PUT /api/v1/users/{id} with API key regeneration
            $this->testRegenerateApiKey($userId);
            
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
            'username' => 'apitestuser_' . uniqid(),
            'email' => 'apitest_' . uniqid() . '@example.com',
            'password' => 'testpass123',
            'first_name' => 'API',
            'last_name' => 'Test',
            'role' => 'user',
            'is_active' => 1
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
            'last_name' => 'User',
            'role' => 'admin',
            'is_active' => 0
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['first_name'] === 'Updated' && $data['role'] === 'admin') {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testRegenerateApiKey($userId) {
        echo "    Testing PUT /api/v1/users/{$userId} (API key regeneration)... ";
        
        // Get original API key
        $response = $this->makeRequest('GET', "/api/v1/users/{$userId}");
        $originalData = json_decode($response['body'], true);
        $originalKey = $originalData['api_key'];
        
        // Regenerate API key
        $updateData = ['regenerate_api_key' => true];
        $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['api_key'] !== $originalKey && !empty($data['api_key'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - API key not regenerated\n";
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
    
    public function testWebhooksEndpoints() {
        echo "  Testing Webhooks API endpoints...\n";
        
        // Test GET /api/v1/webhooks
        $this->testGetWebhooks();
        
        // Test POST /api/v1/webhooks
        $webhookId = $this->testCreateWebhook();
        
        if ($webhookId) {
            // Test GET /api/v1/webhooks/{id}
            $this->testGetWebhook($webhookId);
            
            // Test PUT /api/v1/webhooks/{id}
            $this->testUpdateWebhook($webhookId);
            
            // Test POST /api/v1/webhooks/{id}/test
            $this->testTestWebhook($webhookId);
            
            // Test DELETE /api/v1/webhooks/{id}
            $this->testDeleteWebhook($webhookId);
        }
    }
    
    public function testGetWebhooks() {
        echo "    Testing GET /api/v1/webhooks... ";
        
        $response = $this->makeRequest('GET', '/api/v1/webhooks');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['webhooks']) && is_array($data['webhooks'])) {
                echo "PASS (" . count($data['webhooks']) . " webhooks)\n";
            } else {
                echo "FAIL - Invalid response structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testCreateWebhook() {
        echo "    Testing POST /api/v1/webhooks... ";
        
        $webhookData = [
            'url' => 'https://webhook.site/test-' . uniqid(),
            'events' => ['contact.created', 'deal.created'],
            'is_active' => 1
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/webhooks', $webhookData);
        
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
    
    public function testGetWebhook($webhookId) {
        echo "    Testing GET /api/v1/webhooks/{$webhookId}... ";
        
        $response = $this->makeRequest('GET', "/api/v1/webhooks/{$webhookId}");
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['id']) && $data['id'] == $webhookId) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid webhook data\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testUpdateWebhook($webhookId) {
        echo "    Testing PUT /api/v1/webhooks/{$webhookId}... ";
        
        $updateData = [
            'url' => 'https://updated-webhook.site/test',
            'events' => ['contact.updated', 'deal.updated'],
            'is_active' => 0
        ];
        
        $response = $this->makeRequest('PUT', "/api/v1/webhooks/{$webhookId}", $updateData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data['url'] === 'https://updated-webhook.site/test' && $data['is_active'] == 0) {
                echo "PASS\n";
            } else {
                echo "FAIL - Update not reflected\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testTestWebhook($webhookId) {
        echo "    Testing POST /api/v1/webhooks/{$webhookId}/test... ";
        
        $response = $this->makeRequest('POST', "/api/v1/webhooks/{$webhookId}/test");
        
        // Debug output
        file_put_contents(__DIR__ . '/webhook_test_debug.log', date('c') . " webhook test response: " . json_encode($response) . "\n", FILE_APPEND);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS\n";
            } else {
                echo "FAIL - Test not successful (data: " . json_encode($data) . ")\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . " (body: " . substr($response['body'], 0, 100) . ")\n";
        }
    }
    
    public function testDeleteWebhook($webhookId) {
        echo "    Testing DELETE /api/v1/webhooks/{$webhookId}... ";
        
        $response = $this->makeRequest('DELETE', "/api/v1/webhooks/{$webhookId}");
        
        if ($response['code'] === 204) {
            echo "PASS\n";
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testReportsEndpoints() {
        echo "  Testing Reports API endpoints...\n";
        
        // Test GET /api/v1/reports
        $this->testGetReports();
        
        // Test GET /api/v1/reports/analytics
        $this->testGetAnalytics();
        
        // Test GET /api/v1/reports/export
        $this->testExportReports();
    }
    
    public function testGetReports() {
        echo "    Testing GET /api/v1/reports... ";
        
        $response = $this->makeRequest('GET', '/api/v1/reports');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['reports']) && is_array($data['reports'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid response structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testGetAnalytics() {
        echo "    Testing GET /api/v1/reports/analytics... ";
        
        $response = $this->makeRequest('GET', '/api/v1/reports/analytics');
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['analytics']) && is_array($data['analytics'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid response structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testExportReports() {
        echo "    Testing GET /api/v1/reports/export... ";
        
        $response = $this->makeRequest('GET', '/api/v1/reports/export?format=csv&type=deals');
        
        if ($response['code'] === 200) {
            if (strpos($response['body'], 'ID,Title,Contact ID') !== false) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid CSV format\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    public function testErrorHandling() {
        echo "  Testing Error Handling...\n";
        
        // Test 404 for non-existent resource
        echo "    Testing 404 error... ";
        $response = $this->makeRequest('GET', '/api/v1/contacts/999999');
        if ($response['code'] === 404) {
            echo "PASS\n";
        } else {
            echo "FAIL - Expected 404, got " . $response['code'] . "\n";
        }
        
        // Test 400 for invalid data
        echo "    Testing 400 error... ";
        $response = $this->makeRequest('POST', '/api/v1/contacts', ['invalid' => 'data']);
        if ($response['code'] === 400) {
            echo "PASS\n";
        } else {
            echo "FAIL - Expected 400, got " . $response['code'] . "\n";
        }
        
        // Test 401 for invalid API key
        echo "    Testing 401 error... ";
        $headers = ['Authorization: Bearer invalid_key', 'Content-Type: application/json'];
        $response = $this->makeRequest('GET', '/api/v1/contacts', null, $headers);
        if ($response['code'] === 401) {
            echo "PASS\n";
        } else {
            echo "FAIL - Expected 401, got " . $response['code'] . "\n";
        }
    }
    
    public function testAuthentication() {
        echo "  Testing Authentication...\n";
        
        // Test valid API key
        echo "    Testing valid API key... ";
        $response = $this->makeRequest('GET', '/api/v1/contacts');
        if ($response['code'] === 200) {
            echo "PASS\n";
        } else {
            echo "FAIL - Valid API key rejected\n";
        }
        
        // Test missing API key
        echo "    Testing missing API key... ";
        $headers = ['Content-Type: application/json'];
        $response = $this->makeRequest('GET', '/api/v1/contacts', null, $headers);
        if ($response['code'] === 401) {
            echo "PASS\n";
        } else {
            echo "FAIL - Missing API key not rejected\n";
        }
    }
    
    public function testJsonResponses() {
        echo "  Testing JSON Responses...\n";
        
        // Test valid JSON response
        echo "    Testing valid JSON... ";
        $response = $this->makeRequest('GET', '/api/v1/contacts');
        $data = json_decode($response['body'], true);
        if ($data !== null) {
            echo "PASS\n";
        } else {
            echo "FAIL - Invalid JSON response\n";
        }
        
        // Test error JSON response
        echo "    Testing error JSON... ";
        $response = $this->makeRequest('GET', '/api/v1/contacts/999999');
        $data = json_decode($response['body'], true);
        if ($data !== null && isset($data['error'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - Invalid error JSON response\n";
        }
    }
    
    public function testOpenApiDocumentation() {
        echo "  Testing OpenAPI Documentation...\n";
        
        // Test OpenAPI JSON
        echo "    Testing OpenAPI JSON... ";
        $response = $this->makeRequest('GET', '/api/openapi.json');
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if ($data && isset($data['openapi'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - Invalid OpenAPI structure\n";
            }
        } else {
            echo "FAIL - HTTP " . $response['code'] . "\n";
        }
    }
    
    private function makeRequest($method, $endpoint, $data = null, $headers = null) {
        file_put_contents(__DIR__ . '/api_test_debug.log', date('c') . " makeRequest: method=$method endpoint=$endpoint\n", FILE_APPEND);
        // Check if CURL is available
        if (!function_exists('curl_init')) {
            echo "SKIP - CURL not available\n";
            return ['code' => 0, 'body' => 'CURL not available'];
        }
        
        // Define CURL constants if not defined
        if (!defined('CURLOPT_URL')) {
            define('CURLOPT_URL', 10002);
            define('CURLOPT_RETURNTRANSFER', 19913);
            define('CURLOPT_CUSTOMREQUEST', 10036);
            define('CURLOPT_HTTPHEADER', 10023);
            define('CURLOPT_TIMEOUT', 13);
            define('CURLOPT_POSTFIELDS', 10015);
            define('CURLINFO_HTTP_CODE', 2097154);
        }
        
        $url = $this->baseUrl . $endpoint;
        $requestHeaders = $headers ?: $this->headers;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    public function testImportEndpoints() {
        echo "  Testing Import API endpoints...\n";
        
        // Test CSV import endpoint
        $csvData = [
            [
                'Full Name' => 'John Doe',
                'Email' => 'john@example.com',
                'Phone' => '+1234567890',
                'Company' => 'Test Corp'
            ],
            [
                'Full Name' => 'Jane Smith',
                'Email' => 'jane@example.com',
                'Phone' => '+0987654321',
                'Company' => 'Another Corp'
            ]
        ];
        
        $fieldMapping = [
            'first_name' => 'Full Name',
            'last_name' => 'Full Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company'
        ];
        
        $nameSplitConfig = [
            'column' => 'Full Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'API Test',
            'notes' => 'API endpoint test',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/contacts/import', $importData);
        
        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                echo "    ✓ Import endpoint works\n";
                
                if (isset($data['totalProcessed']) && $data['totalProcessed'] === 2) {
                    echo "    ✓ All records processed\n";
                } else {
                    echo "    ✗ Record count mismatch\n";
                }
                
                if (isset($data['successCount']) && $data['successCount'] === 2) {
                    echo "    ✓ All records imported successfully\n";
                } else {
                    echo "    ✗ Some records failed to import\n";
                }
            } else {
                echo "    ✗ Import failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "    ✗ Import endpoint failed with code: " . $response['code'] . "\n";
        }
        
        // Test contact creation without email
        $contactData = [
            'first_name' => 'Test',
            'last_name' => 'NoEmail',
            'phone' => '+1111111111',
            'company' => 'Test Company'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/contacts', $contactData);
        
        if ($response['code'] === 201) {
            $data = json_decode($response['body'], true);
            if (isset($data['first_name']) && $data['first_name'] === 'Test') {
                echo "    ✓ Contact creation without email works\n";
            } else {
                echo "    ✗ Contact creation without email failed\n";
            }
        } else {
            echo "    ✗ Contact creation without email failed with code: " . $response['code'] . "\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ApiTest();
    $test->runAllTests();
} 
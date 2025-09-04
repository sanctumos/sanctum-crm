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
 * API Test Script
 * Best Jobs in TA - API Testing
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

echo "<h1>Best Jobs in TA - API Test</h1>\n";

try {
    // Initialize database
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful</p>\n";
    
    // Initialize auth
    $auth = new Auth();
    echo "<p style='color: green;'>✓ Authentication system initialized</p>\n";
    
    // Get default admin user
    $admin = $db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
    if ($admin) {
        echo "<p style='color: green;'>✓ Default admin user found</p>\n";
        echo "<p><strong>Admin API Key:</strong> " . htmlspecialchars($admin['api_key']) . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Default admin user not found</p>\n";
    }
    
    // Test API endpoints
    echo "<h2>API Endpoint Tests</h2>\n";
    
    $baseUrl = 'http://localhost:8000';
    $apiKey = $admin['api_key'] ?? '';
    
    if ($apiKey) {
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        // Test GET /api/v1/contacts
        echo "<h3>Testing GET /api/v1/contacts</h3>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/contacts');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "<p style='color: green;'>✓ GET /api/v1/contacts successful</p>\n";
            $data = json_decode($response, true);
            echo "<p>Found " . ($data['count'] ?? 0) . " contacts</p>\n";
        } else {
            echo "<p style='color: red;'>✗ GET /api/v1/contacts failed (HTTP $httpCode)</p>\n";
            echo "<p>Response: " . htmlspecialchars($response) . "</p>\n";
        }
        
        // Test POST /api/v1/contacts
        echo "<h3>Testing POST /api/v1/contacts</h3>\n";
        $testContact = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'contact_type' => 'lead',
            'evm_address' => '0x1234567890123456789012345678901234567890',
            'twitter_handle' => '@testuser'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/contacts');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testContact));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            echo "<p style='color: green;'>✓ POST /api/v1/contacts successful</p>\n";
            $data = json_decode($response, true);
            echo "<p>Created contact ID: " . ($data['id'] ?? 'unknown') . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ POST /api/v1/contacts failed (HTTP $httpCode)</p>\n";
            echo "<p>Response: " . htmlspecialchars($response) . "</p>\n";
        }
        
        // Test GET /api/v1/deals
        echo "<h3>Testing GET /api/v1/deals</h3>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/deals');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "<p style='color: green;'>✓ GET /api/v1/deals successful</p>\n";
            $data = json_decode($response, true);
            echo "<p>Found " . ($data['count'] ?? 0) . " deals</p>\n";
        } else {
            echo "<p style='color: red;'>✗ GET /api/v1/deals failed (HTTP $httpCode)</p>\n";
            echo "<p>Response: " . htmlspecialchars($response) . "</p>\n";
        }
        
        // Test OpenAPI documentation
        echo "<h3>Testing OpenAPI Documentation</h3>\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/openapi.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "<p style='color: green;'>✓ OpenAPI documentation accessible</p>\n";
        } else {
            echo "<p style='color: red;'>✗ OpenAPI documentation not accessible (HTTP $httpCode)</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Cannot test API endpoints without API key</p>\n";
    }
    
    echo "<h2>System Information</h2>\n";
    echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>\n";
    echo "<p><strong>SQLite Version:</strong> " . (class_exists('SQLite3') ? SQLite3::version()['versionString'] : 'Not available') . "</p>\n";
    echo "<p><strong>App Version:</strong> " . APP_VERSION . "</p>\n";
    echo "<p><strong>Debug Mode:</strong> " . (DEBUG_MODE ? 'Enabled' : 'Disabled') . "</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<h2>Next Steps</h2>\n";
echo "<p>1. Start the PHP development server: <code>php -S localhost:8000</code></p>\n";
echo "<p>2. Visit <a href='http://localhost:8000'>http://localhost:8000</a> to access the web interface</p>\n";
echo "<p>3. Use the API key above to test API endpoints</p>\n";
echo "<p>4. Default login: admin / admin123</p>\n";
?> 
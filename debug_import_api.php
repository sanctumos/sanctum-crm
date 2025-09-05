<?php
require_once 'tests/bootstrap.php';

echo "Testing Import API...\n";

// Test data with invalid email
$csvData = [
    [
        'First Name' => 'John',
        'Last Name' => 'Doe',
        'Email' => 'john@example.com'
    ],
    [
        'First Name' => 'Jane',
        'Last Name' => 'Smith',
        'Email' => 'invalid-email'
    ],
    [
        'First Name' => 'Bob',
        'Last Name' => 'Johnson'
        // No email - should be valid
    ]
];

$fieldMapping = [
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'email' => 'Email'
];

$importData = [
    'csvData' => $csvData,
    'fieldMapping' => $fieldMapping,
    'source' => 'Email Validation Test'
];

// Get API key
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
if (!$user) {
    echo "No admin user found\n";
    exit;
}

$apiKey = $user['api_key'];
echo "Using API key: " . substr($apiKey, 0, 8) . "...\n";

// Make API request
$url = 'http://localhost/public/api/v1/index.php?action=import';
$postData = json_encode($importData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "Parsed response: " . print_r($data, true) . "\n";
    
    if (isset($data['success']) && $data['success'] === true) {
        echo "Success: true\n";
        echo "Error count: " . ($data['errorCount'] ?? 'not set') . "\n";
        echo "Success count: " . ($data['successCount'] ?? 'not set') . "\n";
        
        if (isset($data['errors'])) {
            echo "Errors: " . print_r($data['errors'], true) . "\n";
        }
    } else {
        echo "Success: false\n";
        echo "Error: " . ($data['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "API request failed with HTTP code: $httpCode\n";
}

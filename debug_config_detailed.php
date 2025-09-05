<?php
require_once 'tests/bootstrap.php';
require_once 'public/includes/ConfigManager.php';

echo "Testing ConfigManager in detail...\n";

try {
    $config = ConfigManager::getInstance();
    echo "✓ ConfigManager instance created\n";
    
    // Clear any existing data
    $db = Database::getInstance();
    $db->query("DELETE FROM system_config WHERE category = 'application'");
    
    // Test getCategory functionality step by step
    echo "\n=== Testing getCategory ===\n";
    
    $configs = [
        'app_name' => 'Test App',
        'app_url' => 'http://test.com'
    ];
    
    echo "Setting category with: " . print_r($configs, true) . "\n";
    $config->setCategory('application', $configs);
    echo "✓ setCategory completed\n";
    
    echo "Retrieving category...\n";
    $retrieved = $config->getCategory('application');
    echo "Retrieved: " . print_r($retrieved, true) . "\n";
    
    echo "Comparing arrays...\n";
    echo "Original: " . print_r($configs, true) . "\n";
    echo "Retrieved: " . print_r($retrieved, true) . "\n";
    echo "Are they equal? " . ($configs === $retrieved ? 'YES' : 'NO') . "\n";
    
    if ($configs === $retrieved) {
        echo "✓ getCategory functionality works\n";
    } else {
        echo "✗ getCategory functionality failed\n";
        
        // Debug the differences
        echo "\nDebugging differences:\n";
        foreach ($configs as $key => $value) {
            if (!isset($retrieved[$key])) {
                echo "Missing key: $key\n";
            } elseif ($retrieved[$key] !== $value) {
                echo "Value mismatch for $key: expected '$value', got '{$retrieved[$key]}'\n";
            }
        }
        
        foreach ($retrieved as $key => $value) {
            if (!isset($configs[$key])) {
                echo "Extra key: $key = $value\n";
            }
        }
    }
    
    // Test company info
    echo "\n=== Testing Company Info ===\n";
    $companyData = [
        'company_name' => 'Test Company',
        'timezone' => 'America/New_York'
    ];
    
    echo "Setting company info: " . print_r($companyData, true) . "\n";
    $result = $config->setCompanyInfo($companyData);
    echo "setCompanyInfo result: " . ($result ? 'true' : 'false') . "\n";
    
    $retrieved = $config->getCompanyInfo();
    echo "Retrieved company info: " . print_r($retrieved, true) . "\n";
    
    if ($retrieved['company_name'] === 'Test Company' && $retrieved['timezone'] === 'America/New_York') {
        echo "✓ company info management works\n";
    } else {
        echo "✗ company info management failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

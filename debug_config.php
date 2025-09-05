<?php
require_once 'tests/bootstrap.php';
require_once 'public/includes/ConfigManager.php';

echo "Testing ConfigManager...\n";

try {
    $config = ConfigManager::getInstance();
    echo "✓ ConfigManager instance created\n";
    
    // Test getCategory functionality
    echo "Testing getCategory...\n";
    $configs = [
        'app_name' => 'Test App',
        'app_url' => 'http://test.com'
    ];
    
    $config->setCategory('application', $configs);
    echo "✓ setCategory completed\n";
    
    $retrieved = $config->getCategory('application');
    echo "Retrieved: " . print_r($retrieved, true) . "\n";
    
    if ($configs === $retrieved) {
        echo "✓ getCategory functionality works\n";
    } else {
        echo "✗ getCategory functionality failed\n";
        echo "Expected: " . print_r($configs, true) . "\n";
        echo "Got: " . print_r($retrieved, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

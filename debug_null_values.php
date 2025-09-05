<?php
require_once 'tests/bootstrap.php';
require_once 'public/includes/ConfigManager.php';

echo "Testing null values...\n";

try {
    $config = ConfigManager::getInstance();
    $db = Database::getInstance();
    
    // Clear test data
    $db->query("DELETE FROM system_config WHERE category = 'test'");
    
    // Test null values
    echo "Setting null values...\n";
    $config->setCategory('test', [
        'null_val' => null,
        'empty_string' => '',
        'zero' => 0,
        'false_val' => false
    ]);
    
    echo "Retrieving values...\n";
    $configs = $config->getCategory('test');
    
    echo "Retrieved: " . print_r($configs, true) . "\n";
    
    if ($configs['null_val'] === null && $configs['empty_string'] === '' &&
        $configs['zero'] === 0 && $configs['false_val'] === false) {
        echo "✓ set category with null values works\n";
    } else {
        echo "✗ set category with null values failed\n";
        echo "null_val: " . var_export($configs['null_val'], true) . " (expected null)\n";
        echo "empty_string: '" . $configs['empty_string'] . "' (expected '')\n";
        echo "zero: " . var_export($configs['zero'], true) . " (expected 0)\n";
        echo "false_val: " . var_export($configs['false_val'], true) . " (expected false)\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

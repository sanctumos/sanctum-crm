<?php
require_once 'tests/bootstrap.php';
require_once 'public/includes/ConfigManager.php';

echo "Testing encrypted values...\n";

try {
    $config = ConfigManager::getInstance();
    $db = Database::getInstance();
    
    // Clear test data
    $db->query("DELETE FROM system_config WHERE category = 'test'");
    
    // Set encrypted and normal values
    echo "Setting encrypted value...\n";
    $config->set('test', 'encrypted_key', 'secret_value', true);
    
    echo "Setting normal value...\n";
    $config->set('test', 'normal_key', 'normal_value');
    
    // Test individual gets
    $encryptedValue = $config->get('test', 'encrypted_key');
    $normalValue = $config->get('test', 'normal_key');
    
    echo "Individual get - encrypted: '$encryptedValue', normal: '$normalValue'\n";
    
    // Test getAll
    echo "Testing getAll...\n";
    $allConfigs = $config->getAll();
    
    echo "All configs: " . print_r($allConfigs, true) . "\n";
    
    if (isset($allConfigs['test']['encrypted_key']) && isset($allConfigs['test']['normal_key'])) {
        echo "✓ Both keys exist in getAll result\n";
        
        if ($allConfigs['test']['encrypted_key'] === 'secret_value' && 
            $allConfigs['test']['normal_key'] === 'normal_value') {
            echo "✓ get all with encrypted values works\n";
        } else {
            echo "✗ get all with encrypted values failed\n";
            echo "Expected encrypted: 'secret_value', got: '{$allConfigs['test']['encrypted_key']}'\n";
            echo "Expected normal: 'normal_value', got: '{$allConfigs['test']['normal_key']}'\n";
        }
    } else {
        echo "✗ Keys missing from getAll result\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

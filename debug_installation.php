<?php
require_once 'tests/bootstrap.php';
require_once 'public/includes/ConfigManager.php';

echo "Testing installation progress...\n";

try {
    $config = ConfigManager::getInstance();
    $db = Database::getInstance();
    
    // Clear installation state
    $db->query("DELETE FROM installation_state");
    echo "✓ Cleared installation state\n";
    
    // Test completeInstallationStep
    echo "Testing completeInstallationStep...\n";
    $result1 = $config->completeInstallationStep('environment');
    echo "Environment step result: " . ($result1 ? 'true' : 'false') . "\n";
    
    $result2 = $config->completeInstallationStep('database', ['tables_created' => 5]);
    echo "Database step result: " . ($result2 ? 'true' : 'false') . "\n";
    
    // Test getInstallationProgress
    echo "Testing getInstallationProgress...\n";
    $progress = $config->getInstallationProgress();
    echo "Progress: " . print_r($progress, true) . "\n";
    
    if (count($progress) === 2 && $progress[0]['step'] === 'environment' && 
        $progress[1]['step'] === 'database') {
        echo "✓ installation progress tracking works\n";
    } else {
        echo "✗ installation progress tracking failed\n";
        echo "Expected 2 steps, got " . count($progress) . "\n";
        if (count($progress) > 0) {
            echo "First step: " . $progress[0]['step'] . "\n";
        }
        if (count($progress) > 1) {
            echo "Second step: " . $progress[1]['step'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

<?php
require_once 'tests/bootstrap.php';
require_once 'public/includes/ConfigManager.php';

echo "Testing data type detection...\n";

try {
    $config = ConfigManager::getInstance();
    $db = Database::getInstance();
    
    // Clear test data
    $db->query("DELETE FROM system_config WHERE category = 'test'");
    
    $testCases = [
        'string' => 'test string',
        'integer' => 42,
        'float' => 3.14,
        'boolean' => true,
        'array' => ['key' => 'value'],
        'object' => (object)['key' => 'value']
    ];
    
    foreach ($testCases as $expectedType => $value) {
        echo "Testing $expectedType: " . print_r($value, true) . "\n";
        
        $config->set('test', $expectedType, $value);
        $retrieved = $config->get('test', $expectedType);
        
        echo "Retrieved: " . print_r($retrieved, true) . "\n";
        
        if ($expectedType === 'object') {
            $expected = (array)$value;
            $match = ($retrieved === $expected);
            echo "Object comparison: " . ($match ? 'PASS' : 'FAIL') . "\n";
            if (!$match) {
                echo "Expected: " . print_r($expected, true) . "\n";
                echo "Got: " . print_r($retrieved, true) . "\n";
            }
        } else {
            $match = ($retrieved === $value);
            echo "Direct comparison: " . ($match ? 'PASS' : 'FAIL') . "\n";
            if (!$match) {
                echo "Expected: " . print_r($value, true) . "\n";
                echo "Got: " . print_r($retrieved, true) . "\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

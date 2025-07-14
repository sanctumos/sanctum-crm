<?php
// Simple debug script to test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n";

try {
    // Test 1: Check if we can define the constant
    define('CRM_LOADED', true);
    echo "✓ CRM_LOADED constant defined\n";
    
    // Test 2: Check if config loads
    require_once __DIR__ . '/includes/config.php';
    echo "✓ Config loaded\n";
    echo "DB_PATH: " . DB_PATH . "\n";
    
    // Test 3: Check if database class loads
    require_once __DIR__ . '/includes/database.php';
    echo "✓ Database class loaded\n";
    
    // Test 4: Try to get database instance
    $db = Database::getInstance();
    echo "✓ Database instance created\n";
    
    // Test 5: Try a simple query
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "✓ Database query successful: " . $result['count'] . " users found\n";
    
    echo "All tests passed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} 
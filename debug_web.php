<?php
// Debug script that mimics the web entry point
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing web entry point...\n";

try {
    // Test 1: Define CRM loaded constant
    define('CRM_LOADED', true);
    echo "✓ CRM_LOADED constant defined\n";
    
    // Test 2: Include required files
    require_once __DIR__ . '/includes/config.php';
    echo "✓ Config loaded\n";
    
    require_once __DIR__ . '/includes/database.php';
    echo "✓ Database loaded\n";
    
    require_once __DIR__ . '/includes/auth.php';
    echo "✓ Auth loaded\n";
    
    // Test 3: Initialize authentication
    $auth = new Auth();
    echo "✓ Auth initialized\n";
    
    // Test 4: Check authentication
    $isAuth = $auth->isAuthenticated();
    echo "✓ Authentication check: " . ($isAuth ? "Authenticated" : "Not authenticated") . "\n";
    
    // Test 5: Include layout
    require_once __DIR__ . '/includes/layout.php';
    echo "✓ Layout loaded\n";
    
    echo "All web entry point tests passed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} 
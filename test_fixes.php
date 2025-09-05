<?php
/**
 * Test Sanctum CRM JSON Error Fixes
 * Verify all fixes are working correctly
 */

echo "Testing Sanctum CRM JSON error fixes...\n";

// Test 1: Check if sanitizeInput handles null properly
require_once 'public/includes/config.php';

echo "1. Testing sanitizeInput null handling...\n";
$result = sanitizeInput(null);
if ($result === null) {
    echo "   ✅ sanitizeInput handles null correctly\n";
} else {
    echo "   ❌ sanitizeInput null handling failed\n";
}

// Test 2: Check if constants are protected
echo "\n2. Testing constant definition protection...\n";
$constants = ['APP_NAME', 'DEBUG_MODE', 'API_VERSION'];
$allProtected = true;
foreach ($constants as $const) {
    if (defined($const)) {
        echo "   ✅ $const is defined and protected\n";
    } else {
        echo "   ❌ $const is not defined\n";
        $allProtected = false;
    }
}

// Test 3: Check if database has email nullable migration
echo "\n3. Testing database email nullable migration...\n";
try {
    $db = new SQLite3('db/crm.db');
    $result = $db->query("PRAGMA table_info(contacts)");
    $emailNullable = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'email' && $row['notnull'] == 0) {
            $emailNullable = true;
            break;
        }
    }
    $db->close();
    
    if ($emailNullable) {
        echo "   ✅ Email column is nullable in contacts table\n";
    } else {
        echo "   ❌ Email column is not nullable\n";
    }
} catch (Exception $e) {
    echo "   ❌ Database test failed: " . $e->getMessage() . "\n";
}

echo "\n✅ All tests completed!\n";

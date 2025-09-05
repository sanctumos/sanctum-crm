<?php
require_once 'tests/bootstrap.php';

echo "Checking company_info table...\n";

$db = Database::getInstance();

// Check if table exists
$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='company_info'");
$tableExists = $result->fetchArray() !== false;
echo "Table exists: " . ($tableExists ? 'YES' : 'NO') . "\n";

if ($tableExists) {
    // Check table structure
    $result = $db->query("PRAGMA table_info(company_info)");
    echo "Table structure:\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "  {$row['name']} {$row['type']} (nullable: " . ($row['notnull'] ? 'NO' : 'YES') . ")\n";
    }
    
    // Check existing data
    $result = $db->query("SELECT * FROM company_info");
    $data = $result->fetchArray(SQLITE3_ASSOC);
    echo "Existing data: " . print_r($data, true) . "\n";
    
    // Try to insert data manually
    echo "\nTrying manual insert...\n";
    $insertResult = $db->query("INSERT OR REPLACE INTO company_info (id, company_name, timezone, created_at, updated_at) VALUES (1, 'Test Company', 'America/New_York', datetime('now'), datetime('now'))");
    echo "Insert result: " . ($insertResult ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Check data after insert
    $result = $db->query("SELECT * FROM company_info WHERE id = 1");
    $data = $result->fetchArray(SQLITE3_ASSOC);
    echo "Data after insert: " . print_r($data, true) . "\n";
}

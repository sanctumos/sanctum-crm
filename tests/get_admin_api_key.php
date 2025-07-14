<?php
// Query the admin API key from the live database
$dbPath = __DIR__ . '/../db/crm.db';
$db = new SQLite3($dbPath);
$result = $db->querySingle("SELECT api_key FROM users WHERE username = 'admin'");
echo $result ? $result : "No admin user found\n"; 
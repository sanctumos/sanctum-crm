<?php
// Debug script to understand production file structure
echo "Current directory: " . __DIR__ . "\n";
echo "Parent directory: " . dirname(__DIR__) . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script name: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";

echo "\nChecking for includes directory:\n";
$possiblePaths = [
    dirname(__DIR__) . '/includes',
    dirname(__DIR__) . '/../includes', 
    dirname(__DIR__) . '/../../includes',
    $_SERVER['DOCUMENT_ROOT'] . '/../includes',
    $_SERVER['DOCUMENT_ROOT'] . '/../../includes',
    '/var/www/crm.freeopsdao.com/includes',
    '/var/www/crm.freeopsdao.com/html/../includes'
];

foreach ($possiblePaths as $path) {
    echo "Checking: $path - " . (is_dir($path) ? "EXISTS" : "NOT FOUND") . "\n";
    if (is_dir($path)) {
        echo "  Contents: " . implode(', ', array_slice(scandir($path), 0, 10)) . "\n";
    }
}

echo "\nChecking for config.php:\n";
foreach ($possiblePaths as $path) {
    $configFile = $path . '/config.php';
    echo "Checking: $configFile - " . (file_exists($configFile) ? "EXISTS" : "NOT FOUND") . "\n";
}
?> 
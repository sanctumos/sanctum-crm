<?php
// Comprehensive diagnostic to see production structure
echo "=== PRODUCTION FILE STRUCTURE DIAGNOSTIC ===\n\n";

echo "Current directory: " . __DIR__ . "\n";
echo "Parent directory: " . dirname(__DIR__) . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n\n";

echo "=== CONTENTS OF CURRENT DIRECTORY ===\n";
$currentFiles = scandir(__DIR__);
foreach ($currentFiles as $file) {
    if ($file != '.' && $file != '..') {
        $path = __DIR__ . '/' . $file;
        $type = is_dir($path) ? 'DIR' : 'FILE';
        echo "$type: $file\n";
    }
}

echo "\n=== CONTENTS OF PARENT DIRECTORY ===\n";
$parentFiles = scandir(dirname(__DIR__));
foreach ($parentFiles as $file) {
    if ($file != '.' && $file != '..') {
        $path = dirname(__DIR__) . '/' . $file;
        $type = is_dir($path) ? 'DIR' : 'FILE';
        echo "$type: $file\n";
    }
}

echo "\n=== SEARCHING FOR PHP FILES ===\n";
function findPhpFiles($dir, $maxDepth = 3, $currentDepth = 0) {
    if ($currentDepth > $maxDepth) return;
    
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            findPhpFiles($path, $maxDepth, $currentDepth + 1);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            echo "PHP FILE: $path\n";
        }
    }
}

findPhpFiles(dirname(__DIR__));

echo "\n=== CHECKING FOR SPECIFIC FILES ===\n";
$specificFiles = [
    'config.php',
    'database.php', 
    'auth.php',
    'layout.php',
    'crm.db',
    'database.sqlite'
];

foreach ($specificFiles as $file) {
    $paths = [
        dirname(__DIR__) . '/' . $file,
        dirname(__DIR__) . '/includes/' . $file,
        dirname(__DIR__) . '/db/' . $file,
        __DIR__ . '/' . $file,
        __DIR__ . '/includes/' . $file
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            echo "FOUND: $file at $path\n";
        }
    }
}
?> 
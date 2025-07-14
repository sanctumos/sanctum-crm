<?php
header('Content-Type: application/json');
echo json_encode([
    'file' => __FILE__,
    'timestamp' => date('c'),
    'cwd' => getcwd(),
    'message' => 'If you see this, your dev server is running the correct codebase.'
]); 
<?php
// public/router.php
file_put_contents(__DIR__ . '/router.log', date('c') . ' ' . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
if (preg_match('/^\/api\/v1\//', $_SERVER['REQUEST_URI'])) {
    require __DIR__ . '/api/v1/index.php';
    exit;
}
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (file_exists($path) && !is_dir($path)) {
    return false; // serve the requested static file
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'uri' => $_SERVER['REQUEST_URI']]);
    exit;
} 
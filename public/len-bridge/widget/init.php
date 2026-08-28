<?php
/**
 * Widget Initialization Endpoint
 * Provide widget configuration and assets
 */

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/auth.php';

// Set CORS headers
set_cors_headers();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Method not allowed', 405);
}

// Get configuration from query parameters
$config = [
    'apiKey' => $_GET['apiKey'] ?? '',
    'position' => $_GET['position'] ?? 'bottom-right',
    'theme' => $_GET['theme'] ?? 'light',
    'title' => $_GET['title'] ?? 'Chat with us',
    'primaryColor' => $_GET['primaryColor'] ?? '#007bff',
    'language' => $_GET['language'] ?? 'en',
    'autoOpen' => ($_GET['autoOpen'] ?? 'false') === 'true',
    'notifications' => ($_GET['notifications'] ?? 'true') === 'true',
    'sound' => ($_GET['sound'] ?? 'true') === 'true'
];

// Validate API key
if (empty($config['apiKey'])) {
    send_error_response('API key is required', 400);
}

// Return configuration
send_success_response([
    'config' => $config,
    'assets' => [
        'css' => '/widget/assets/css/widget.css',
        'js' => '/widget/assets/js/chat-widget.js',
        'icons' => '/widget/assets/icons/'
    ],
    'api' => [
        'baseUrl' => get_base_url(),
        'endpoint' => '/api/v1/'
    ]
], 'Widget configuration loaded');

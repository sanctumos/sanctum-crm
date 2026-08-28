<?php
/**
 * Widget Configuration Endpoint
 * Return available widget configuration options
 */

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/api_response.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error_response('Method not allowed', 405);
}

send_success_response([
    'positions' => ['bottom-right', 'bottom-left', 'top-right', 'top-left'],
    'themes' => ['light', 'dark', 'auto'],
    'languages' => ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'],
    'defaults' => [
        'position' => 'bottom-right',
        'theme' => 'light',
        'title' => 'Chat with us',
        'primaryColor' => '#007bff',
        'language' => 'en',
        'autoOpen' => false,
        'notifications' => true,
        'sound' => true
    ]
], 'Configuration options loaded');

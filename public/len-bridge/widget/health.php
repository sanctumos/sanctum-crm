<?php
/**
 * Widget Health Check Endpoint
 * Widget status and health information
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

// Check API connectivity
$api_status = 'connected';
try {
    // Test basic API functionality
    $test_response = file_get_contents(get_base_url() . '/api/v1/?action=config');
    if ($test_response === false) {
        $api_status = 'disconnected';
    }
} catch (Exception $e) {
    $api_status = 'error';
}

send_success_response([
    'status' => 'healthy',
    'version' => '1.0.0',
    'api_status' => $api_status,
    'timestamp' => date('c')
], 'Widget health check completed');

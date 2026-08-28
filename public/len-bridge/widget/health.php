<?php
/**
 * Widget Health Check Endpoint — no CRM session, no poll auth required.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/api_response.php';

set_cors_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    send_error_response('Method not allowed', 405);
}

send_success_response([
    'status' => 'healthy',
    'version' => '1.0.0',
    'product' => 'sanctum_crm',
    'bridge' => 'len-bridge',
    'timestamp' => date('c'),
], 'Widget health check completed');

<?php
/**
 * API settings and rate limits for Len Web Chat Bridge
 */

if (!defined('CRM_LOADED')) {
    define('CRM_LOADED', true);
}
require_once dirname(__DIR__, 2) . '/includes/config.php';
if (!defined('LEN_BRIDGE_DB_PATH')) {
    define(
        'LEN_BRIDGE_DB_PATH',
        crm_env_or_default('CRM_LEN_BRIDGE_DB_PATH', dirname(DB_PATH) . '/len_bridge_webchat.db')
    );
}

// API Configuration
define('API_VERSION', '1.0.0');
define('API_NAME', 'Len Web Chat Bridge API');

// Rate Limiting Configuration (legacy fallbacks — canonical: rate_limit_config.php)
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds
define('RATE_LIMIT_MAX_REQUESTS', 25000); // Overall per IP per hour
define('RATE_LIMIT_ENDPOINT_MAX', 10000); // Per-endpoint IP fallback

// Session-auth widget endpoints use per-Tasks-user keys (see auth.php).
define('RATE_LIMIT_USER_ENDPOINTS', [
    '/api/messages',
    '/api/responses',
    '/api/history',
    '/api/user_session',
]);

$USER_ENDPOINT_RATE_LIMITS = [
    '/api/messages' => 300,
    '/api/responses' => 7200,
    '/api/history' => 600,
    '/api/user_session' => 3000,
];
define('RATE_LIMIT_USER_MAX_REQUESTS', 20000);

// Endpoint-specific rate limits (per IP — Broca poll + legacy)
$ENDPOINT_RATE_LIMITS = [
    '/api/messages' => 500,
    '/api/responses' => 7200,
    '/api/history' => 600,
    '/api/user_session' => 600,
    '/api/inbox' => 10000,
    '/api/outbox' => 5000,
    '/api/sessions' => 200,
];

// Authentication
define('API_KEY_HEADER', 'Authorization');
define('API_KEY_PREFIX', 'Bearer ');

// Poll auth for Broca plugin (Bearer token Broca sends on inbox/outbox).
function len_bridge_is_placeholder_secret(string $value): bool {
    $v = strtoupper(trim($value));
    if ($v === '') {
        return true;
    }
    if (str_starts_with($v, 'CHANGE_ME')) {
        return true;
    }
    $deny = ['FREE0PS', 'DEFAULT', 'PASSWORD', 'SECRET', 'TOKEN'];
    return in_array($v, $deny, true);
}

function get_api_key() {
    $k = trim((string)(getenv('CRM_LEN_BRIDGE_POLL_API_KEY') ?: getenv('WEB_CHAT_API_KEY') ?: ''));
    if (!len_bridge_is_placeholder_secret($k)) {
        return $k;
    }
    $file = dirname(LEN_BRIDGE_DB_PATH) . '/len_bridge_poll_api_key.txt';
    if (is_file($file)) {
        $existing = trim((string)@file_get_contents($file));
        if (!len_bridge_is_placeholder_secret($existing)) {
            return $existing;
        }
    }
    return '';
}

function get_admin_key() {
    $k = trim((string)(getenv('WEB_CHAT_ADMIN_KEY') ?: getenv('CRM_LEN_BRIDGE_ADMIN_KEY') ?: ''));
    if (len_bridge_is_placeholder_secret($k)) {
        return '';
    }
    return $k;
}

// CORS Configuration
function set_cors_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Content Type Configuration
function set_json_headers() {
    header('Content-Type: application/json; charset=utf-8');
}

// Error Codes
define('ERROR_CODES', [
    'INVALID_REQUEST' => 400,
    'UNAUTHORIZED' => 401,
    'FORBIDDEN' => 403,
    'NOT_FOUND' => 404,
    'RATE_LIMITED' => 429,
    'INTERNAL_ERROR' => 500,
    'SERVICE_UNAVAILABLE' => 503
]);

// Message validation
define('MAX_MESSAGE_LENGTH', 1000000); // 1M — align with document body cap (Phase 3 composer)
define('MAX_SESSION_ID_LENGTH', 64);
define('MIN_MESSAGE_LENGTH', 1);

// Session configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('MAX_SESSIONS_PER_IP', 10); // Max concurrent sessions per IP

// Logging configuration
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/../logs/api.log');

// Log retention settings
define('LOG_RETENTION_DAYS', 30); // Keep logs for 30 days
define('LOG_MAX_SIZE_MB', 100); // Max log file size in MB
define('LOG_PRUNE_PROBABILITY', 0.01); // 1% chance to prune on each log write

// Security settings
define('ENABLE_HTTPS_ONLY', true); // Force HTTPS in production
define('SANITIZE_INPUT', true); // Sanitize all input
define('VALIDATE_SESSION_IDS', true); // Validate session ID format

// Performance settings
define('DB_QUERY_TIMEOUT', 10); // Database query timeout in seconds
define('API_RESPONSE_TIMEOUT', 30); // API response timeout in seconds
define('MAX_CONCURRENT_REQUESTS', 100); // Max concurrent requests

// Debug mode (set to false in production)
define('DEBUG_MODE', getenv('WEB_CHAT_DEBUG') === 'true' ? true : false);

// Logging function
function log_message($level, $message, $context = []) {
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf(
        "[%s] [%s] %s %s\n",
        $timestamp,
        strtoupper($level),
        $message,
        !empty($context) ? json_encode($context) : ''
    );
    
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Occasionally prune old logs
    if (mt_rand(1, 100) <= (LOG_PRUNE_PROBABILITY * 100)) {
        prune_logs();
    }
    
    if (DEBUG_MODE) {
        error_log($log_entry);
    }
}

/**
 * Prune old log entries and rotate large log files
 */
function prune_logs() {
    $log_dir = dirname(LOG_FILE);
    $current_log = LOG_FILE;
    
    // Check if current log file is too large
    if (file_exists($current_log) && filesize($current_log) > (LOG_MAX_SIZE_MB * 1024 * 1024)) {
        $backup_name = $current_log . '.' . date('Y-m-d-H-i-s');
        rename($current_log, $backup_name);
        
        // Keep only the most recent backup
        $backup_files = glob($current_log . '.*');
        if (count($backup_files) > 3) {
            // Sort by modification time and remove oldest
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest backups, keep only 3 most recent
            $files_to_remove = array_slice($backup_files, 0, count($backup_files) - 3);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }
    
    // Remove old backup files based on retention period
    $cutoff_time = time() - (LOG_RETENTION_DAYS * 24 * 60 * 60);
    $backup_files = glob($current_log . '.*');
    
    foreach ($backup_files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
        }
    }
    
    // Also clean up any other log files in the directory that are too old
    $all_log_files = glob($log_dir . '/*.log*');
    foreach ($all_log_files as $file) {
        if (filemtime($file) < $cutoff_time && $file !== $current_log) {
            unlink($file);
        }
    }
} 
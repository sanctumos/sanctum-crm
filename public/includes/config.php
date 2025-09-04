<?php
/**
 * CRM System Configuration
 * Best Jobs in TA - Main Configuration File
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

// Application Configuration
define('APP_NAME', 'Best Jobs in TA');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://bestjobsinta.com'); // Production URL
define('DEBUG_MODE', false); // Set to false in production

// Database Configuration
define('DB_PATH', dirname(dirname(__DIR__)) . '/db/crm.db');
define('DB_BACKUP_PATH', dirname(dirname(__DIR__)) . '/db/backup/');

// Security Configuration
define('SESSION_NAME', 'crm_session');
define('SESSION_LIFETIME', 3600); // 1 hour
define('API_KEY_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 1000); // requests per hour
define('API_MAX_PAYLOAD_SIZE', 1048576); // 1MB

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Email Configuration (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@bestjobsinta.com');
define('SMTP_FROM_NAME', 'Best Jobs in TA');

// Web3 Configuration
define('WEB3_ENABLED', true);
define('ETHEREUM_NETWORK', 'mainnet'); // mainnet, testnet, local

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// Session Configuration - Only set if not already sent
if (!headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Set to 1 for HTTPS production
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (DEBUG_MODE) {
        error_log("Error [$errno] $errstr on line $errline in file $errfile");
    }
    
    if (isApiRequest()) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal server error',
            'code' => 500,
            'details' => DEBUG_MODE ? "$errstr in $errfile:$errline" : null
        ]);
    } else {
        // For web requests, show error page
        include dirname(__DIR__) . '/pages/error.php';
    }
    
    return true;
}

// Set custom error handler
set_error_handler('customErrorHandler');

// Helper function to check if request is API
function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0;
}

// Helper function to generate API key
function generateApiKey() {
    return bin2hex(random_bytes(API_KEY_LENGTH / 2));
}

// Helper function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Helper function to sanitize input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    if ($input === null) {
        return null;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function to validate EVM address
function validateEVMAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

// Helper function to get current timestamp
function getCurrentTimestamp() {
    return date('Y-m-d H:i:s');
}

// Helper function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2);
}

// Helper function to log activity
function logActivity($user_id, $action, $details = null) {
    // TODO: Implement activity logging
    if (DEBUG_MODE) {
        error_log("Activity: User $user_id performed $action" . ($details ? " - $details" : ''));
    }
}

// Helper function to send webhook
function sendWebhook($url, $payload) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: BestJobsInTA/1.0'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        error_log("Webhook error: $error");
        return false;
    }
    
    // Consider 2xx status codes as success
    return $httpCode >= 200 && $httpCode < 300;
} 

// Helper function to validate URL
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
} 
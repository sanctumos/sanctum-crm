<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * CRM System Configuration
 * Sanctum CRM - Main Configuration File
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

// Application Configuration
if (!defined('APP_NAME')) define('APP_NAME', 'Sanctum CRM');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost'); // Default URL - will be overridden by configuration
// Auto-detect environment based on OS and other factors
if (!defined('DEBUG_MODE')) {
    // Enable debug mode on Windows development, disable on Ubuntu production
    define('DEBUG_MODE', PHP_OS_FAMILY === 'Windows');
}

// Database Configuration
if (!defined('DB_PATH')) define('DB_PATH', dirname(dirname(__DIR__)) . '/db/crm.db');
if (!defined('DB_BACKUP_PATH')) define('DB_BACKUP_PATH', dirname(dirname(__DIR__)) . '/db/backup/');

// Security Configuration
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'crm_session');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 hour
if (!defined('API_KEY_LENGTH')) define('API_KEY_LENGTH', 32);
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 8);

// Set session name before any session is started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
}

// API Configuration
if (!defined('API_VERSION')) define('API_VERSION', 'v1');
if (!defined('API_RATE_LIMIT')) define('API_RATE_LIMIT', 1000); // requests per hour
if (!defined('API_MAX_PAYLOAD_SIZE')) define('API_MAX_PAYLOAD_SIZE', 1048576); // 1MB

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

// File Upload Configuration
if (!defined('UPLOAD_MAX_SIZE')) define('UPLOAD_MAX_SIZE', 5242880); // 5MB
if (!defined('UPLOAD_ALLOWED_TYPES')) define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// RocketReach Configuration
if (!defined('ROCKETREACH_API_KEY')) define('ROCKETREACH_API_KEY', '');
if (!defined('ROCKETREACH_ENABLED')) define('ROCKETREACH_ENABLED', false);
if (!defined('ROCKETREACH_RATE_LIMIT')) define('ROCKETREACH_RATE_LIMIT', 100); // per hour

// Email functionality removed - use webhooks and API for integrations

// Custom Fields Configuration
// Custom field settings will be managed through the database

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}


// Helper function to validate custom field values
function validateCustomField($value, $fieldType) {
    switch ($fieldType) {
        case 'email':
            return validateEmail($value);
        case 'url':
            return validateUrl($value);
        case 'phone':
            return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $value);
        default:
            return !empty(trim($value));
    }
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
            'User-Agent: SanctumCRM/1.0'
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

// Helper function to get current application name
function getAppName() {
    if (class_exists('ConfigManager')) {
        $config = ConfigManager::getInstance();
        $appConfig = $config->getCategory('application');
        return $appConfig['app_name'] ?? APP_NAME;
    }
    return APP_NAME;
}

// Helper function to get current application URL
function getAppUrl() {
    if (class_exists('ConfigManager')) {
        $config = ConfigManager::getInstance();
        $appConfig = $config->getCategory('application');
        return $appConfig['app_url'] ?? APP_URL;
    }
    return APP_URL;
} 
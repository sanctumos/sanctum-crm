<?php
/**
 * Standardized API response handler
 */

require_once __DIR__ . '/../config/settings.php';

/**
 * Send a successful API response
 * 
 * @param mixed $data Response data
 * @param string $message Optional success message
 * @param int $status_code HTTP status code (default: 200)
 */
function send_success_response($data = null, $message = 'Success', $status_code = 200) {
    http_response_code($status_code);
    set_json_headers();
    
    $response = [
        'success' => true,
        'message' => $message,
        'timestamp' => date('c'),
        'data' => $data
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send an error API response
 * 
 * @param string $error Error message
 * @param int $status_code HTTP status code (default: 400)
 * @param array $details Additional error details
 */
function send_error_response($error, $status_code = 400, $details = []) {
    http_response_code($status_code);
    set_json_headers();
    
    $response = [
        'success' => false,
        'error' => $error,
        'code' => $status_code,
        'timestamp' => date('c')
    ];
    
    if (!empty($details)) {
        $response['details'] = $details;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send a rate limit error response
 * 
 * @param int $retry_after Seconds to wait before retrying
 */
function send_rate_limit_response($retry_after = 3600) {
    http_response_code(429);
    set_json_headers();
    header('Retry-After: ' . $retry_after);
    
    $response = [
        'success' => false,
        'error' => 'Rate limit exceeded',
        'code' => 429,
        'retry_after' => $retry_after,
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send an unauthorized response
 * 
 * @param string $message Optional error message
 */
function send_unauthorized_response($message = 'Unauthorized') {
    send_error_response($message, 401);
}

/**
 * Send a forbidden response
 * 
 * @param string $message Optional error message
 */
function send_forbidden_response($message = 'Forbidden') {
    send_error_response($message, 403);
}

/**
 * Send a not found response
 * 
 * @param string $message Optional error message
 */
function send_not_found_response($message = 'Resource not found') {
    send_error_response($message, 404);
}

/**
 * Send an internal server error response
 * 
 * @param string $message Optional error message
 */
function send_internal_error_response($message = 'Internal server error') {
    send_error_response($message, 500);
}

/**
 * Validate and sanitize input data
 * 
 * @param array $data Input data
 * @param array $required_fields Required field names
 * @param array $optional_fields Optional field names with defaults
 * @return array Sanitized data
 */
function validate_and_sanitize_input($data, $required_fields = [], $optional_fields = []) {
    $sanitized = [];
    $errors = [];
    
    // Check required fields
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = "Missing required field: {$field}";
        } else {
            $sanitized[$field] = sanitize_input($data[$field]);
        }
    }
    
    // Handle optional fields
    foreach ($optional_fields as $field => $default) {
        if (isset($data[$field]) && !empty(trim($data[$field]))) {
            $sanitized[$field] = sanitize_input($data[$field]);
        } else {
            $sanitized[$field] = $default;
        }
    }
    
    if (!empty($errors)) {
        send_error_response('Validation failed', 400, ['errors' => $errors]);
    }
    
    return $sanitized;
}

/**
 * Sanitize input string
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitize_input($input) {
    if (!SANITIZE_INPUT) {
        return $input;
    }
    
    // Remove null bytes
    $input = str_replace(chr(0), '', $input);
    
    // Trim whitespace
    $input = trim($input);
    
    // Convert special characters to HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Agent outbox / chat bodies — keep markdown intact.
 * htmlspecialchars on agent replies was an early Ask Q failure mode (&#039; etc.).
 * Escape at render time in the widget, not at storage.
 */
function sanitize_agent_message($input) {
    $input = str_replace(chr(0), '', (string) $input);
    return trim($input);
}

/**
 * Validate session ID format
 * 
 * @param string $session_id Session ID to validate
 * @return bool True if valid, false otherwise
 */
function validate_session_id($session_id) {
    if (!VALIDATE_SESSION_IDS) {
        return true;
    }
    
    // Session ID should be alphanumeric and 1-64 characters
    return preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $session_id);
}

/**
 * Validate message content
 * 
 * @param string $message Message to validate
 * @return bool True if valid, false otherwise
 */
function validate_message($message) {
    $length = strlen($message);
    return $length >= MIN_MESSAGE_LENGTH && $length <= MAX_MESSAGE_LENGTH;
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Log API request
 * 
 * @param string $endpoint API endpoint
 * @param string $method HTTP method
 * @param array $data Request data
 * @param int $status_code Response status code
 */
function log_api_request($endpoint, $method, $data = [], $status_code = 200) {
    $context = [
        'endpoint' => $endpoint,
        'method' => $method,
        'ip' => get_client_ip(),
        'status_code' => $status_code,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    if (DEBUG_MODE && !empty($data)) {
        $context['data'] = $data;
    }
    
    $level = $status_code >= 400 ? 'ERROR' : 'INFO';
    log_message($level, "API Request: {$method} {$endpoint}", $context);
} 
<?php
/**
 * Authentication and rate limiting functionality
 */

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/api_response.php';
require_once __DIR__ . '/rate_limit_config.php';

/**
 * Check if request is authenticated with API key
 * 
 * @return bool True if authenticated, false otherwise
 */
function is_authenticated() {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($auth_header)) {
        return false;
    }
    
    if (strpos($auth_header, API_KEY_PREFIX) !== 0) {
        return false;
    }
    
    $api_key = trim((string)substr($auth_header, strlen(API_KEY_PREFIX)));
    $expected = trim((string)get_api_key());
    if ($expected === '' || len_bridge_is_placeholder_secret($expected)) {
        log_message('ERROR', 'Len bridge poll key is not configured securely');
        return false;
    }
    return hash_equals($expected, $api_key);
}

/**
 * Check if request is authenticated with admin key
 * 
 * @return bool True if authenticated, false otherwise
 */
function is_admin_authenticated() {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($auth_header)) {
        return false;
    }
    
    if (strpos($auth_header, API_KEY_PREFIX) !== 0) {
        return false;
    }
    
    $admin_key = trim((string)substr($auth_header, strlen(API_KEY_PREFIX)));
    $expected = trim((string)get_admin_key());
    if ($expected === '' || len_bridge_is_placeholder_secret($expected)) {
        log_message('ERROR', 'Len bridge admin key is not configured securely');
        return false;
    }
    return hash_equals($expected, $admin_key);
}

/**
 * Require authentication for API key
 */
function require_auth() {
    if (!is_authenticated()) {
        log_message('WARNING', 'Unauthorized API access attempt', [
            'ip' => get_client_ip(),
            'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        send_unauthorized_response('Invalid or missing API key');
    }
}

/**
 * Require authentication for admin key
 */
function require_admin_auth() {
    if (!is_admin_authenticated()) {
        log_message('WARNING', 'Unauthorized admin access attempt', [
            'ip' => get_client_ip(),
            'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        send_unauthorized_response('Invalid or missing admin key');
    }
}

/**
 * Rate-limit subject key: per CRM user for widget session routes, else per IP.
 */
function len_bridge_rate_limit_key($endpoint, $crm_user_id = null) {
    $user_endpoints = defined('RATE_LIMIT_USER_ENDPOINTS') ? RATE_LIMIT_USER_ENDPOINTS : [];
    $uid = (int)($crm_user_id ?? 0);
    if ($uid > 0 && in_array($endpoint, $user_endpoints, true)) {
        return 'user:' . $uid;
    }
    return get_client_ip();
}

/**
 * Check rate limiting for current request
 *
 * @param string $endpoint API endpoint
 * @param int|null $crm_user_id Logged-in CRM user for per-user caps
 * @return bool True if within limits, false if rate limited
 */
function check_rate_limit($endpoint, $crm_user_id = null) {
    $rate_key = len_bridge_rate_limit_key($endpoint, $crm_user_id);
    $current_time = time();
    $window_start = $current_time - RATE_LIMIT_WINDOW;
    $current_time_str = date('Y-m-d H:i:s', $current_time);

    try {
        $pdo = get_db_connection();

        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE window_start < ?");
        $stmt->execute([date('Y-m-d H:i:s', $window_start)]);

        $stmt = $pdo->prepare("
            SELECT count, window_start
            FROM rate_limits
            WHERE ip_address = ? AND endpoint = ?
            LIMIT 1
        ");
        $stmt->execute([$rate_key, $endpoint]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_count = 0;
        if ($result) {
            $existing_window = strtotime((string)($result['window_start'] ?? '')) ?: 0;
            if ($existing_window >= $window_start) {
                $current_count = (int)($result['count'] ?? 0);
            }
        }

        $cfg = len_bridge_get_rate_limit_config();
        $is_user_key = str_starts_with($rate_key, 'user:');
        if ($is_user_key && isset($cfg['user_endpoints'][$endpoint])) {
            $endpoint_limit = (int)$cfg['user_endpoints'][$endpoint];
        } elseif (isset($cfg['ip_endpoints'][$endpoint])) {
            $endpoint_limit = (int)$cfg['ip_endpoints'][$endpoint];
        } else {
            $endpoint_limit = RATE_LIMIT_ENDPOINT_MAX;
        }

        if ($current_count >= $endpoint_limit) {
            log_message('WARNING', 'Rate limit exceeded', [
                'rate_key' => $rate_key,
                'endpoint' => $endpoint,
                'count' => $current_count,
                'limit' => $endpoint_limit
            ]);
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO rate_limits (ip_address, endpoint, count, window_start)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $rate_key,
            $endpoint,
            $current_count + 1,
            $current_time_str
        ]);

        return true;

    } catch (Exception $e) {
        log_message('ERROR', 'Rate limit check failed', [
            'error' => $e->getMessage(),
            'rate_key' => $rate_key,
            'endpoint' => $endpoint
        ]);
        return true;
    }
}

/**
 * Check overall rate limiting for IP
 * 
 * @return bool True if within limits, false if rate limited
 */
function check_overall_rate_limit($endpoint = '', $crm_user_id = null) {
    $rate_key = len_bridge_rate_limit_key($endpoint, $crm_user_id);
    $current_time = time();
    $window_start = $current_time - RATE_LIMIT_WINDOW;
    $cfg = len_bridge_get_rate_limit_config();
    $max_requests = RATE_LIMIT_MAX_REQUESTS;
    if (str_starts_with($rate_key, 'user:')) {
        $max_requests = (int)($cfg['user_max_requests'] ?? RATE_LIMIT_MAX_REQUESTS);
    } else {
        $max_requests = (int)($cfg['ip_max_requests'] ?? RATE_LIMIT_MAX_REQUESTS);
    }

    try {
        $pdo = get_db_connection();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(count), 0) as count
            FROM rate_limits
            WHERE ip_address = ? AND window_start >= ?
        ");
        $stmt->execute([$rate_key, date('Y-m-d H:i:s', $window_start)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_count = (int)($result['count'] ?? 0);

        if ($total_count >= $max_requests) {
            log_message('WARNING', 'Overall rate limit exceeded', [
                'rate_key' => $rate_key,
                'count' => $total_count,
                'limit' => $max_requests
            ]);
            return false;
        }

        return true;

    } catch (Exception $e) {
        log_message('ERROR', 'Overall rate limit check failed', [
            'error' => $e->getMessage(),
            'rate_key' => $rate_key
        ]);
        return true;
    }
}

/**
 * Apply rate limiting to current request
 * 
 * @param string $endpoint API endpoint
 */
function apply_rate_limiting($endpoint, $crm_user_id = null) {
    if (!check_overall_rate_limit($endpoint, $crm_user_id)) {
        send_rate_limit_response(RATE_LIMIT_WINDOW);
    }

    if (!check_rate_limit($endpoint, $crm_user_id)) {
        send_rate_limit_response(RATE_LIMIT_WINDOW);
    }
}

/**
 * Validate session and check if it's active
 * 
 * @param string $session_id Session ID to validate
 * @return bool True if session is active, false otherwise
 */
function is_session_active($session_id) {
    if (!validate_session_id($session_id)) {
        return false;
    }
    
    try {
        $pdo = get_db_connection();
        
        $stmt = $pdo->prepare("
            SELECT last_active 
            FROM web_chat_sessions 
            WHERE id = ?
        ");
        $stmt->execute([$session_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        $last_active = strtotime($result['last_active']);
        $current_time = time();
        
        // Check if session has timed out
        if (($current_time - $last_active) > SESSION_TIMEOUT) {
            // Remove expired session
            $stmt = $pdo->prepare("DELETE FROM web_chat_sessions WHERE id = ?");
            $stmt->execute([$session_id]);
            return false;
        }
        
        // Update last active time
        $stmt = $pdo->prepare("
            UPDATE web_chat_sessions 
            SET last_active = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$session_id]);
        
        return true;
        
    } catch (Exception $e) {
        log_message('ERROR', 'Session validation failed', [
            'error' => $e->getMessage(),
            'session_id' => $session_id
        ]);
        return false;
    }
}

/**
 * Create a new session
 * 
 * @param string $session_id Session ID
 * @param array $metadata Optional session metadata
 * @return bool True if created successfully, false otherwise
 */
function create_session($session_id, $metadata = []) {
    if (!validate_session_id($session_id)) {
        return false;
    }
    
    try {
        $pdo = get_db_connection();
        
        // Check if session already exists
        $stmt = $pdo->prepare("SELECT id FROM web_chat_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        if ($stmt->fetch()) {
            return true; // Session already exists
        }
        
        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO web_chat_sessions (id, metadata) 
            VALUES (?, ?)
        ");
        $stmt->execute([
            $session_id,
            json_encode($metadata)
        ]);
        
        log_message('INFO', 'Session created', [
            'session_id' => $session_id,
            'metadata' => $metadata
        ]);
        
        return true;
        
    } catch (Exception $e) {
        log_message('ERROR', 'Session creation failed', [
            'error' => $e->getMessage(),
            'session_id' => $session_id
        ]);
        return false;
    }
}

/**
 * Get session count for IP address
 * 
 * @param string $ip IP address
 * @return int Number of active sessions
 */
function get_session_count_for_ip($ip) {
    try {
        $pdo = get_db_connection();
        
        // This is a simplified check - in a real implementation,
        // you might want to track IP addresses in the sessions table
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM web_chat_sessions 
            WHERE last_active > datetime('now', '-1 day')
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
        
    } catch (Exception $e) {
        log_message('ERROR', 'Session count check failed', [
            'error' => $e->getMessage(),
            'ip' => $ip
        ]);
        return 0;
    }
}

/**
 * Check if IP has too many sessions
 * 
 * @param string $ip IP address
 * @return bool True if within limit, false if too many sessions
 */
function check_session_limit($ip) {
    $session_count = get_session_count_for_ip($ip);
    return $session_count < MAX_SESSIONS_PER_IP;
} 
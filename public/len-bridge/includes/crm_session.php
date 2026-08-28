<?php
/**
 * Bind Len bridge widget requests to logged-in CRM session.
 */
declare(strict_types=1);

if (!defined('CRM_LOADED')) {
    define('CRM_LOADED', true);
}
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once __DIR__ . '/api_response.php';

/**
 * @return int CRM user id from PHP session
 */
function require_crm_logged_in_user_id(): int
{
    $auth = new Auth();
    if (!$auth->isAuthenticated()) {
        send_unauthorized_response('CRM login required');
    }
    $uid = (int) $auth->getUserId();
    if ($uid <= 0) {
        send_unauthorized_response('CRM login required');
    }
    return $uid;
}

/**
 * Plaintext API key for SMCP injection (server-side Broca only).
 */
function crm_len_bridge_user_api_key(int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }
    $db = Database::getInstance();
    $user = $db->fetchOne('SELECT * FROM users WHERE id = ? AND is_active = 1', [$userId]);
    if (!$user) {
        return null;
    }
    $key = trim((string) ($user['api_key'] ?? ''));
    if ($key !== '') {
        return $key;
    }
    $key = bin2hex(random_bytes(API_KEY_LENGTH / 2));
    $db->query(
        'UPDATE users SET api_key = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
        [$key, $userId]
    );
    return $key;
}

/**
 * @return array{id:int,username:string,role:string}|null
 */
function crm_len_bridge_user_row(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $row = Database::getInstance()->fetchOne(
        'SELECT id, username, role FROM users WHERE id = ? AND is_active = 1',
        [$userId]
    );
    return is_array($row) ? $row : null;
}

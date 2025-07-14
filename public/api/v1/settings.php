<?php
/**
 * User Settings API Endpoint
 * FreeOpsDAO CRM - /api/v1/settings
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'code' => 401]);
    exit;
}

$user = $auth->getUser();
$db = Database::getInstance();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode([
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);
        break;
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON', 'code' => 400]);
            exit;
        }
        $fields = [];
        $params = [];
        if (!empty($input['first_name'])) {
            $fields['first_name'] = $input['first_name'];
        }
        if (!empty($input['last_name'])) {
            $fields['last_name'] = $input['last_name'];
        }
        if (!empty($input['email'])) {
            if (!validateEmail($input['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid email address', 'code' => 400]);
                exit;
            }
            $fields['email'] = $input['email'];
        }
        if (!empty($input['password'])) {
            if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
                http_response_code(400);
                echo json_encode(['error' => 'Password too short', 'code' => 400]);
                exit;
            }
            $fields['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update', 'code' => 400]);
            exit;
        }
        $db->update('users', $fields, 'id = ?', [$user['id']]);
        logActivity($user['id'], 'update_settings', 'User updated their settings');
        echo json_encode(['success' => true]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
        break;
} 
<?php
/**
 * API v1 resource handler — extracted from index.php (behavior-compatible).
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

function handleUsers($method, $id, $input, $auth) {
    // Check if user is admin without using requireAdmin() to avoid exit()
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Authentication required',
            'code' => 401
        ]);
        return;
    }
    
    if (!$auth->isAdmin()) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Admin access required',
            'code' => 403
        ]);
        return;
    }
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $user = $auth->getUserById($id);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode([
                        'error' => 'User not found',
                        'code' => 404
                    ]);
                    return;
                }
                echo json_encode($user);
            } else {
                try {
                    $users = $auth->getAllUsers();
                    echo json_encode([
                        'users' => $users,
                        'count' => count($users)
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Failed to load users',
                        'code' => 500
                    ]);
                }
            }
            break;
            
        case 'POST':
            try {
                $user = $auth->createUser($input);
                http_response_code(201);
                echo json_encode($user);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'error' => $e->getMessage(),
                    'code' => 400
                ]);
            }
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'User ID required for update',
                    'code' => 400
                ]);
                return;
            }
            
            try {
                // Handle API key regeneration
                if (isset($input['regenerate_api_key']) && $input['regenerate_api_key']) {
                    $newApiKey = $auth->regenerateApiKey($id);
                    $user = $auth->getUserById($id);
                    $user['api_key'] = $newApiKey; // Include the new API key in response
                    echo json_encode($user);
                } else {
                    $auth->updateUser($id, $input);
                    $user = $auth->getUserById($id);
                    echo json_encode($user);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'error' => $e->getMessage(),
                    'code' => 400
                ]);
            }
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'User ID required for deletion',
                    'code' => 400
                ]);
                return;
            }
            
            try {
                $auth->deleteUser($id);
                http_response_code(204);
                exit; // Ensure no content is sent for 204 response
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'error' => $e->getMessage(),
                    'code' => 400
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'error' => 'Method not allowed',
                'code' => 405
            ]);
    }
}

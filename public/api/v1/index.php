<?php
/**
 * API v1 Endpoint
 * FreeOpsDAO CRM - MCP-Ready API
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/auth.php';

// Set JSON content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize authentication
$auth = new Auth();

// Parse the request
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Extract resource and ID from path
$resource = null;
$resourceId = null;

// Handle different URL patterns
if (count($pathParts) >= 4 && $pathParts[0] === 'api' && $pathParts[1] === 'v1') {
    $resource = $pathParts[2];
    $resourceId = isset($pathParts[3]) ? $pathParts[3] : null;
    
    // Handle special actions like /convert
    if (isset($pathParts[4])) {
        $action = $pathParts[4];
    }
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body for POST/PUT requests
$input = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid JSON in request body',
            'code' => 400
        ]);
        exit;
    }
}

// Rate limiting (basic implementation)
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Authentication required',
        'code' => 401
    ]);
    exit;
}

// Route the request
try {
    switch ($resource) {
        case 'contacts':
            handleContacts($method, $resourceId, $input, $auth);
            break;
            
        case 'deals':
            handleDeals($method, $resourceId, $input, $auth);
            break;
            
        case 'users':
            handleUsers($method, $resourceId, $input, $auth);
            break;
            
        case 'webhooks':
            handleWebhooks($method, $resourceId, $input, $auth);
            break;
            
        case 'commands':
            handleCommands($method, $resourceId, $input, $auth);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Resource not found',
                'code' => 404,
                'available_resources' => ['contacts', 'deals', 'users', 'webhooks', 'commands']
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'code' => 500,
        'details' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}

/**
 * Handle contacts endpoints
 */
function handleContacts($method, $id, $input, $auth) {
    $db = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get specific contact
                $sql = "SELECT * FROM contacts WHERE id = ?";
                $contact = $db->fetchOne($sql, [$id]);
                
                if (!$contact) {
                    http_response_code(404);
                    echo json_encode([
                        'error' => 'Contact not found',
                        'code' => 404
                    ]);
                    return;
                }
                
                echo json_encode($contact);
            } else {
                // List contacts with optional filtering
                $where = "1=1";
                $params = [];
                
                if (isset($_GET['type'])) {
                    $where .= " AND contact_type = ?";
                    $params[] = $_GET['type'];
                }
                
                if (isset($_GET['status'])) {
                    $where .= " AND contact_status = ?";
                    $params[] = $_GET['status'];
                }
                
                $sql = "SELECT * FROM contacts WHERE $where ORDER BY created_at DESC";
                $contacts = $db->fetchAll($sql, $params);
                
                echo json_encode([
                    'contacts' => $contacts,
                    'count' => count($contacts)
                ]);
            }
            break;
            
        case 'POST':
            // Create new contact
            $required = ['first_name', 'last_name', 'email'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'error' => "Missing required field: $field",
                        'code' => 400
                    ]);
                    return;
                }
            }
            
            // Validate email
            if (!validateEmail($input['email'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid email address',
                    'code' => 400
                ]);
                return;
            }
            
            // Check if email already exists
            $existing = $db->fetchOne("SELECT id FROM contacts WHERE email = ?", [$input['email']]);
            if ($existing) {
                http_response_code(409);
                echo json_encode([
                    'error' => 'Contact with this email already exists',
                    'code' => 409
                ]);
                return;
            }
            
            // Prepare contact data
            $contactData = [
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? null,
                'company' => $input['company'] ?? null,
                'address' => $input['address'] ?? null,
                'city' => $input['city'] ?? null,
                'state' => $input['state'] ?? null,
                'zip_code' => $input['zip_code'] ?? null,
                'country' => $input['country'] ?? null,
                'evm_address' => $input['evm_address'] ?? null,
                'twitter_handle' => $input['twitter_handle'] ?? null,
                'linkedin_profile' => $input['linkedin_profile'] ?? null,
                'telegram_username' => $input['telegram_username'] ?? null,
                'discord_username' => $input['discord_username'] ?? null,
                'github_username' => $input['github_username'] ?? null,
                'website' => $input['website'] ?? null,
                'contact_type' => $input['contact_type'] ?? 'lead',
                'contact_status' => $input['contact_status'] ?? 'new',
                'source' => $input['source'] ?? null,
                'assigned_to' => $input['assigned_to'] ?? null,
                'notes' => $input['notes'] ?? null
            ];
            
            $contactId = $db->insert('contacts', $contactData);
            
            // Get the created contact
            $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
            
            http_response_code(201);
            echo json_encode($contact);
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Contact ID required for update',
                    'code' => 400
                ]);
                return;
            }
            
            // Check if contact exists
            $existing = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Contact not found',
                    'code' => 404
                ]);
                return;
            }
            
            // Handle special convert action
            if (isset($action) && $action === 'convert') {
                $updateData = [
                    'contact_type' => 'customer',
                    'contact_status' => 'active',
                    'first_purchase_date' => date('Y-m-d'),
                    'updated_at' => getCurrentTimestamp()
                ];
                
                $db->update('contacts', $updateData, 'id = ?', [$id]);
                
                $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
                echo json_encode($contact);
                return;
            }
            
            // Regular update
            $updateData = array_intersect_key($input, array_flip([
                'first_name', 'last_name', 'email', 'phone', 'company', 'address',
                'city', 'state', 'zip_code', 'country', 'evm_address', 'twitter_handle',
                'linkedin_profile', 'telegram_username', 'discord_username', 'github_username',
                'website', 'contact_type', 'contact_status', 'source', 'assigned_to', 'notes'
            ]));
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No valid data to update',
                    'code' => 400
                ]);
                return;
            }
            
            $updateData['updated_at'] = getCurrentTimestamp();
            
            $db->update('contacts', $updateData, 'id = ?', [$id]);
            
            $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
            echo json_encode($contact);
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Contact ID required for deletion',
                    'code' => 400
                ]);
                return;
            }
            
            $deleted = $db->delete('contacts', 'id = ?', [$id]);
            
            if ($deleted) {
                http_response_code(204);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Contact not found',
                    'code' => 404
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

/**
 * Handle deals endpoints
 */
function handleDeals($method, $id, $input, $auth) {
    $db = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $sql = "SELECT * FROM deals WHERE id = ?";
                $deal = $db->fetchOne($sql, [$id]);
                
                if (!$deal) {
                    http_response_code(404);
                    echo json_encode([
                        'error' => 'Deal not found',
                        'code' => 404
                    ]);
                    return;
                }
                
                echo json_encode($deal);
            } else {
                $sql = "SELECT * FROM deals ORDER BY created_at DESC";
                $deals = $db->fetchAll($sql);
                
                echo json_encode([
                    'deals' => $deals,
                    'count' => count($deals)
                ]);
            }
            break;
            
        case 'POST':
            if (empty($input['title']) || empty($input['contact_id'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Title and contact_id are required',
                    'code' => 400
                ]);
                return;
            }
            
            $dealData = [
                'title' => $input['title'],
                'contact_id' => $input['contact_id'],
                'amount' => $input['amount'] ?? null,
                'stage' => $input['stage'] ?? 'prospecting',
                'probability' => $input['probability'] ?? 0,
                'expected_close_date' => $input['expected_close_date'] ?? null,
                'assigned_to' => $input['assigned_to'] ?? null,
                'description' => $input['description'] ?? null
            ];
            
            $dealId = $db->insert('deals', $dealData);
            $deal = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
            
            http_response_code(201);
            echo json_encode($deal);
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Deal ID required for update',
                    'code' => 400
                ]);
                return;
            }
            
            $updateData = array_intersect_key($input, array_flip([
                'title', 'contact_id', 'amount', 'stage', 'probability',
                'expected_close_date', 'assigned_to', 'description'
            ]));
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No valid data to update',
                    'code' => 400
                ]);
                return;
            }
            
            $updateData['updated_at'] = getCurrentTimestamp();
            
            $db->update('deals', $updateData, 'id = ?', [$id]);
            $deal = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$id]);
            
            echo json_encode($deal);
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Deal ID required for deletion',
                    'code' => 400
                ]);
                return;
            }
            
            $deleted = $db->delete('deals', 'id = ?', [$id]);
            
            if ($deleted) {
                http_response_code(204);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Deal not found',
                    'code' => 404
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

/**
 * Handle users endpoints (admin only)
 */
function handleUsers($method, $id, $input, $auth) {
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
                $users = $auth->getAllUsers();
                echo json_encode([
                    'users' => $users,
                    'count' => count($users)
                ]);
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
                $auth->updateUser($id, $input);
                $user = $auth->getUserById($id);
                echo json_encode($user);
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

/**
 * Handle webhooks endpoints (future implementation)
 */
function handleWebhooks($method, $id, $input, $auth) {
    http_response_code(501);
    echo json_encode([
        'error' => 'Webhooks not implemented yet',
        'code' => 501
    ]);
}

/**
 * Handle commands endpoints (future implementation)
 */
function handleCommands($method, $id, $input, $auth) {
    http_response_code(501);
    echo json_encode([
        'error' => 'Commands not implemented yet',
        'code' => 501
    ]);
} 
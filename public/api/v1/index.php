<?php
// Debug logging wrapper function
function debugLog($message) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        file_put_contents(__DIR__ . '/debug.log', date('c') . ' ' . $message . "\n", FILE_APPEND);
    }
}

debugLog('METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? ''));
/**
 * API v1 Endpoint
 * Sanctum CRM - MCP-Ready API
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
// Include both services
require_once __DIR__ . '/../../includes/LeadEnrichmentService.php';
require_once __DIR__ . '/../../includes/MockLeadEnrichmentService.php';
require_once __DIR__ . '/../../includes/ContactTagService.php';
require_once __DIR__ . '/../../includes/ReportsAnalyticsService.php';
require_once __DIR__ . '/../../includes/ApiRequestContext.php';
require_once __DIR__ . '/../../includes/WebhookDispatcher.php';
require_once __DIR__ . '/handlers/contacts.php';
require_once __DIR__ . '/handlers/deals.php';
require_once __DIR__ . '/handlers/users.php';
require_once __DIR__ . '/handlers/webhooks.php';
require_once __DIR__ . '/handlers/reports.php';
require_once __DIR__ . '/handlers/merges.php';

// Resolve EnrichmentService: real LeadEnrichmentService when the *active* provider has a key
require_once __DIR__ . '/../../includes/enrichment/EnrichmentProviders.php';
$db = Database::getInstance();
$settings = $db->fetchOne(
    "SELECT rocketreach_api_key, apollo_api_key, enrichment_provider FROM settings WHERE id = 1"
) ?: [];
$provider = EnrichmentProviders::normalize($settings['enrichment_provider'] ?? null);
$useRealEnrichment = false;

if ($provider === EnrichmentProviders::APOLLO) {
    $useRealEnrichment = trim((string) ($settings['apollo_api_key'] ?? '')) !== '';
} else {
    $rrKey = trim((string) ($settings['rocketreach_api_key'] ?? ''));
    $hasRocketReachClient = false;
    if ($rrKey !== '' && class_exists('RocketReach\SDK\RocketReachClient')) {
        try {
            new RocketReach\SDK\RocketReachClient('test');
            $hasRocketReachClient = true;
        } catch (Exception $e) {
            $hasRocketReachClient = false;
        }
    }
    $useRealEnrichment = $rrKey !== '' && $hasRocketReachClient;
}

if ($useRealEnrichment) {
    class_alias('LeadEnrichmentService', 'EnrichmentService');
} else {
    class_alias('MockLeadEnrichmentService', 'EnrichmentService');
}

// Set JSON content type
if (!defined('CRM_TESTING')) header('Content-Type: application/json');
if (!defined('CRM_TESTING')) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed_origins = [
        'https://localhost',
        'https://www.localhost',
    ];
    
    // Add localhost origins only in development (Windows)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $allowed_origins = array_merge($allowed_origins, [
            'http://localhost:8080', 
            'http://127.0.0.1:8080',
            'http://localhost:3000',
            'http://127.0.0.1:3000'
        ]);
    }
    
    // Handle null origin (file:// protocol) for local testing
    if (empty($origin) || $origin === 'null') {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Access-Control-Allow-Origin: https://localhost');
        }
    } elseif (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: https://localhost');
    }
}
if (!defined('CRM_TESTING')) header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (!defined('CRM_TESTING')) header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if (!defined('CRM_TESTING')) header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize authentication
$auth = new Auth();
if (!defined('CRM_TESTING') && !headers_sent()) {
    header('X-Request-Id: ' . ApiRequestContext::requestId());
}

// Parse the request (supports pretty URLs, PHP dev router, and stock nginx via ?path=/resource/...)
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$pathParam = isset($_GET['path']) ? (string) $_GET['path'] : '';

if ($pathParam !== '') {
    if (strpos($pathParam, '..') !== false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid path', 'code' => 400]);
        exit;
    }
    $logical = trim($pathParam);
    if ($logical !== '' && $logical[0] !== '/') {
        $logical = '/' . $logical;
    }
    if (preg_match('#^/api/v1(/|$)#', $logical)) {
        $logical = preg_replace('#^/api/v1#', '', $logical);
        if ($logical === '' || $logical[0] !== '/') {
            $logical = '/' . ltrim($logical, '/');
        }
    }
    $tail = trim($logical, '/');
    $segments = $tail === '' ? [] : explode('/', $tail);
    $pathParts = array_merge(['api', 'v1'], $segments);
    $path = '/' . implode('/', $pathParts);
} else {
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $pathParts = explode('/', trim($path, '/'));
}

// Extract resource and ID from path
$resource = null;
$resourceId = null;
$action = null;

// Handle different URL patterns
if (count($pathParts) >= 3 && $pathParts[0] === 'api' && $pathParts[1] === 'v1') {
    $resource = $pathParts[2];
    debugLog("URL parsing: pathParts=" . json_encode($pathParts));
    // Special handling for endpoints like /api/v1/reports/analytics, /api/v1/reports/export
    if ($resource === 'reports' && isset($pathParts[3]) && in_array($pathParts[3], ['analytics', 'export'])) {
        $action = $pathParts[3];
        debugLog("URL parsing: reports action=" . $action);
    } elseif (isset($pathParts[3]) && is_numeric($pathParts[3])) {
        $resourceId = $pathParts[3];
        if (isset($pathParts[4])) {
            $action = $pathParts[4];
        } elseif (isset($_GET['action'])) {
            $action = $_GET['action'];
        }
        debugLog("URL parsing: numeric ID=" . $resourceId . " action=" . $action);
    } elseif (isset($pathParts[3])) {
        // For endpoints like /api/v1/webhooks/{id}/test where {id} is not numeric
        $action = $pathParts[3];
        debugLog("URL parsing: non-numeric action=" . $action);
    } elseif (isset($_GET['action'])) {
        $action = $_GET['action'];
        debugLog("URL parsing: GET action=" . $action);
    }
}
debugLog("parsed resource=$resource id=$resourceId action=$action");

// Get request method (move this up before special case checks)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Fallback: try to get method from headers if not set
if (empty($method) && isset($_SERVER['HTTP_X_HTTP_METHOD'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD'];
} elseif (empty($method) && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// Always log special case checks immediately after parsing
debugLog("[DEBUG] CHECKING convert: resource=$resource action=$action");
debugLog("[DEBUG] CHECKING test: resource=$resource action=$action");

// Add reports and OpenAPI endpoints
if ($resource === 'reports') {
    handleReports($method, $action, $auth);
    exit;
}

// Handle contacts export endpoint
if ($resource === 'contacts' && $action === 'export') {
    debugLog("[DEBUG] contacts/export endpoint hit");
    
    // Check authentication
    if (!$auth->isAuthenticated()) {
        ApiRequestContext::errorResponse(401, 'Authentication required');
        exit;
    }

    // Get format parameter
    $format = $_GET['format'] ?? 'csv';
    
    if ($format !== 'csv') {
        http_response_code(400);
        echo json_encode([
            'error' => 'Only CSV format is supported',
            'code' => 400
        ]);
        exit;
    }
    
    // Build query with filters (same logic as contacts listing)
    $where = "1=1";
    $params = [];
    
    if (isset($_GET['type']) && (string) $_GET['type'] !== '') {
        $where .= " AND contact_type = ?";
        $params[] = $_GET['type'];
    }
    
    if (isset($_GET['status']) && (string) $_GET['status'] !== '') {
        $where .= " AND contact_status = ?";
        $params[] = $_GET['status'];
    }
    
    // Empty enrichment_status= in query string must not bind '' (no pending rows match that)
    if (isset($_GET['enrichment_status']) && (string) $_GET['enrichment_status'] !== '') {
        if ($_GET['enrichment_status'] === 'null') {
            // "Not Enriched" UI: rows not successfully enriched (pending/failed/etc.), not only NULL/blank
            $where .= " AND COALESCE(NULLIF(TRIM(enrichment_status), ''), '') != 'enriched'";
        } else {
            $where .= " AND enrichment_status = ?";
            $params[] = $_GET['enrichment_status'];
        }
    }
    
    if (isset($_GET['source']) && (string) $_GET['source'] !== '') {
        if ($_GET['source'] === 'null') {
            $where .= " AND (source IS NULL OR source = '')";
        } else {
            $where .= " AND source = ?";
            $params[] = $_GET['source'];
        }
    }

    if (!empty($_GET['tag'])) {
        $tagService = new ContactTagService($db);
        $tagFilter = $tagService->normalizeTag((string) $_GET['tag']);
        if ($tagFilter !== '') {
            $where .= " AND contacts.id IN (SELECT contact_id FROM contact_tags WHERE tag = ?)";
            $params[] = $tagFilter;
        }
    }
    
    // Get contacts with all fields
    $sql = "SELECT * FROM contacts WHERE $where ORDER BY created_at DESC";
    $contacts = $db->fetchAll($sql, $params);
    
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="contacts_export_' . date('Y-m-d') . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // CSV headers
    $headers = [
        'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Address', 
        'City', 'State', 'Zip Code', 'Country', 'EVM Address', 'Twitter Handle',
        'LinkedIn Profile', 'Telegram Username', 'Discord Username', 'GitHub Username',
        'Website', 'Contact Type', 'Contact Status', 'Source', 'Assigned To', 
        'Enrichment Status', 'Notes', 'First Purchase Date', 'Created At', 'Updated At'
    ];
    
    fputcsv($output, $headers);
    
    // Add contact data
    foreach ($contacts as $contact) {
        $row = [
            $contact['id'],
            $contact['first_name'] ?? '',
            $contact['last_name'] ?? '',
            $contact['email'] ?? '',
            $contact['phone'] ?? '',
            $contact['company'] ?? '',
            $contact['address'] ?? '',
            $contact['city'] ?? '',
            $contact['state'] ?? '',
            $contact['zip_code'] ?? '',
            $contact['country'] ?? '',
            $contact['evm_address'] ?? '',
            $contact['twitter_handle'] ?? '',
            $contact['linkedin_profile'] ?? '',
            $contact['telegram_username'] ?? '',
            $contact['discord_username'] ?? '',
            $contact['github_username'] ?? '',
            $contact['website'] ?? '',
            $contact['contact_type'] ?? '',
            $contact['contact_status'] ?? '',
            $contact['source'] ?? '',
            $contact['assigned_to'] ?? '',
            $contact['enrichment_status'] ?? '',
            $contact['notes'] ?? '',
            $contact['first_purchase_date'] ?? '',
            $contact['created_at'] ?? '',
            $contact['updated_at'] ?? ''
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
if ($resource === 'openapi.json') {
    // Stub OpenAPI endpoint
    echo json_encode([
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'Sanctum CRM API',
            'version' => '1.0.0'
        ],
        'paths' => new stdClass()
    ]);
    exit;
}

// Get request body for POST/PUT requests (move this up before special case checks)
$input = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    // Check request size
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
    if ($contentLength > API_MAX_PAYLOAD_SIZE) {
        http_response_code(413);
        echo json_encode([
            'error' => 'Request too large',
            'code' => 413
        ]);
        exit;
    }
    
    $rawInput = file_get_contents('php://input');
    if (trim($rawInput) === '') {
        $input = [];
    } else {
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid JSON in request body',
                'code' => 400
            ]);
            exit;
        }
    }
}

// Debug log for special case variables (AFTER parsing)
debugLog("[DEBUG] SPECIAL CASE VARS: resource=" . var_export($resource, true) . " (" . gettype($resource) . ") action=" . var_export($action, true) . " (" . gettype($action) . ")");
// Special case: handle contact convert action directly
if (isset($resource) && $resource === 'contacts' && isset($action) && $action === 'convert') {
    debugLog("[DEBUG] ROUTER convert: method=$method resource=$resource resourceId=$resourceId action=$action input=" . json_encode($input));
    handleContacts($method, $resourceId, $input, $auth, $action);
    exit;
}
// Special case: handle webhook test action directly
if (isset($resource) && $resource === 'webhooks' && isset($action) && $action === 'test') {
    debugLog("[DEBUG] ROUTER test: method=$method resource=$resource resourceId=$resourceId action=$action input=" . json_encode($input));
    handleWebhooks($method, $resourceId, $input, $auth, $action);
    exit;
}

// Rate limiting implementation
function checkRateLimit($auth) {
    $userId = $auth->getUserId();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "rate_limit:$userId:$ip";
    
    // Simple in-memory rate limiting (consider Redis for production)
    $currentTime = time();
    $window = 3600; // 1 hour
    $maxRequests = API_RATE_LIMIT;
    
    // Check if we have rate limit data
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'window_start' => $currentTime];
    }
    
    $rateData = &$_SESSION['rate_limit'][$key];
    
    // Reset if window has passed
    if ($currentTime - $rateData['window_start'] > $window) {
        $rateData = ['count' => 0, 'window_start' => $currentTime];
    }
    
    // Check if limit exceeded
    if ($rateData['count'] >= $maxRequests) {
        http_response_code(429);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'code' => 429,
            'retry_after' => $window - ($currentTime - $rateData['window_start'])
        ]);
        exit;
    }
    
    // Increment counter
    $rateData['count']++;
}

// Apply rate limiting
if ($auth->isAuthenticated()) {
    checkRateLimit($auth);
}

// Authentication check
if (!$auth->isAuthenticated()) {
    ApiRequestContext::errorResponse(401, 'Authentication required');
    exit;
}


// Route the request
try {
    debugLog("ROUTER: method=$method resource=$resource id=$resourceId action=$action input=" . json_encode($input));
    debugLog("[DEBUG] BEFORE SWITCH: resource=$resource action=$action");
    switch ($resource) {
        case 'contacts':
            // /contacts/{id}/data-runs[/{runId}]
            if ($resourceId && (($action === 'data-runs') || (($pathParts[4] ?? '') === 'data-runs'))) {
                $runId = $pathParts[5] ?? null;
                handleContactDataRuns($method, $resourceId, $runId, $auth);
                break;
            }
            handleContacts($method, $resourceId, $input, $auth, $action);
            break;

        case 'merges':
            // /merges, /merges/stats, /merges/candidates, /merges/candidates/{id|accept|reject}
            $mergeAction = $pathParts[3] ?? null;
            $mergeSub = $pathParts[4] ?? null;
            handleMerges($method, $mergeSub, $input, $auth, $mergeAction);
            break;
            
        case 'deals':
            handleDeals($method, $resourceId, $input, $auth);
            break;
            
        case 'users':
            handleUsers($method, $resourceId, $input, $auth);
            break;
            
        case 'webhooks':
            handleWebhooks($method, $resourceId, $input, $auth, $action);
            break;
            
        case 'commands':
            handleCommands($method, $resourceId, $input, $auth);
            break;
            
        case 'enrichment':
            handleEnrichment($method, $resourceId, $input, $auth, $action);
            break;
            
        case 'import':
            handleImport($method, $resourceId, $input, $auth, $action);
            break;
            
        default:
            ApiRequestContext::errorResponse(404, 'Resource not found');
    }
} catch (Exception $e) {
    ApiRequestContext::errorResponse(500, 'Internal server error', DEBUG_MODE ? $e->getMessage() : null);
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

/**
 * Handle enrichment endpoints
 */
function handleEnrichment($method, $id, $input, $auth, $action = null) {
    debugLog("[DEBUG] handleEnrichment ENTRY: method=$method id=$id action=$action input=" . json_encode($input));
    
    try {
        if ($action === 'cron') {
            require_once __DIR__ . '/../../includes/EnrichmentCronService.php';
            $cron = new EnrichmentCronService();
            if ($method === 'GET') {
                http_response_code(200);
                echo json_encode([
                    'config' => $cron->getConfig(),
                    'last_run' => $cron->getLastRun(),
                ]);
                return;
            }
            if ($method === 'PUT' || $method === 'PATCH' || $method === 'POST') {
                if (!$auth->isAdmin()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Admin required', 'code' => 403]);
                    return;
                }
                $updated = $cron->updateConfig(is_array($input) ? $input : []);
                http_response_code(200);
                echo json_encode(['success' => true, 'config' => $updated]);
                return;
            }
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
            return;
        }

        $enrichmentService = new EnrichmentService();
        
        switch ($method) {
            case 'GET':
                if ($action === 'stats') {
                    // Get enrichment statistics
                    $stats = $enrichmentService->getEnrichmentStats();
                    http_response_code(200);
                    echo json_encode($stats);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'Invalid action for enrichment endpoint',
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
    } catch (Exception $e) {
        debugLog("[ERROR] handleEnrichment: failed error=" . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'code' => 500
        ]);
    }
}

/**
 * Handle import operations
 */
function handleImport($method, $id, $input, $auth, $action = null) {
    global $db;
    
    try {
        if ($method === 'POST') {
            // Handle CSV upload
            if (isset($_FILES['csvFile'])) {
                $file = $_FILES['csvFile'];
                
                // Validate file
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'File upload failed',
                        'code' => 400
                    ]);
                    return;
                }
                
                // Check file type using file extension (more reliable than mime_content_type)
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($extension !== 'csv') {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'Invalid file type. Please upload a CSV file.',
                        'code' => 400
                    ]);
                    return;
                }
                
                // Parse CSV
                $csvData = [];
                try {
                    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
                        $headers = fgetcsv($handle);
                        while (($data = fgetcsv($handle)) !== FALSE) {
                            $row = [];
                            foreach ($headers as $index => $header) {
                                $row[trim($header)] = isset($data[$index]) ? trim($data[$index]) : '';
                            }
                            $csvData[] = $row;
                        }
                        fclose($handle);
                    } else {
                        throw new Exception("Failed to open CSV file");
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Failed to parse CSV file: ' . $e->getMessage(),
                        'code' => 500
                    ]);
                    return;
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $csvData,
                    'rowCount' => count($csvData)
                ]);
                return;
            }
        }
        
        // Handle import processing
        if (isset($input['csvData']) && isset($input['fieldMapping'])) {
            $csvData = $input['csvData'];
            $fieldMapping = $input['fieldMapping'];
            $source = $input['source'] ?? 'CSV Import';
            $notes = $input['notes'] ?? '';
            $nameSplitConfig = $input['nameSplitConfig'] ?? null;
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            foreach ($csvData as $index => $row) {
                try {
                    $contactData = [];
                    
                    // Map CSV columns to contact fields with sanitization
                    foreach ($fieldMapping as $field => $column) {
                        // Skip name split fields - they'll be handled separately
                        if (strpos($column, '_split_') !== false) {
                            continue;
                        }
                        
                        if (isset($row[$column]) && !empty($row[$column])) {
                            // Sanitize input based on field type
                            if ($field === 'email') {
                                $contactData[$field] = $row[$column]; // Email validation handled separately
                            } elseif ($field === 'evm_address') {
                                $contactData[$field] = validateEVMAddress($row[$column]) ? $row[$column] : null;
                            } else {
                                $contactData[$field] = sanitizeInput($row[$column]);
                            }
                        }
                    }
                    
                    // Handle name splitting if configured
                    if ($nameSplitConfig && isset($row[$nameSplitConfig['column']])) {
                        $fullName = $row[$nameSplitConfig['column']];
                        $parts = explode($nameSplitConfig['delimiter'], $fullName);
                        
                        if (count($parts) >= 2) {
                            $firstPart = trim($parts[$nameSplitConfig['firstPart']]);
                            $lastPart = trim($parts[$nameSplitConfig['lastPart']]);
                            
                            // Set first_name and last_name with split values (sanitized)
                            $contactData['first_name'] = sanitizeInput($firstPart);
                            $contactData['last_name'] = sanitizeInput($lastPart);
                        }
                    }
                    
                    // Validate email if provided
                    if (!empty($contactData['email']) && !validateEmail($contactData['email'])) {
                        $errors[] = [
                            'row' => $index + 1,
                            'message' => 'Invalid email address: ' . $contactData['email']
                        ];
                        $errorCount++;
                        continue;
                    }
                    
                    // Add source and notes
                    $contactData['source'] = $source;
                    $contactData['notes'] = $notes;
                    $contactData['contact_type'] = 'lead';
                    $contactData['contact_status'] = 'new';
                    $contactData['created_at'] = getCurrentTimestamp();
                    $contactData['updated_at'] = getCurrentTimestamp();
                    
                    // Validate required fields - only require first_name and last_name if no name splitting is configured
                    if (!$nameSplitConfig && (empty($contactData['first_name']) || empty($contactData['last_name']))) {
                        $missingFields = [];
                        if (empty($contactData['first_name'])) $missingFields[] = 'first_name';
                        if (empty($contactData['last_name'])) $missingFields[] = 'last_name';
                        
                        $errors[] = [
                            'row' => $index + 1,
                            'message' => 'Missing required fields: ' . implode(', ', $missingFields) . ' (Data: ' . json_encode($contactData) . ')'
                        ];
                        $errorCount++;
                        continue;
                    }
                    
                    // If name splitting is configured but didn't produce valid names, skip this row
                    if ($nameSplitConfig && (empty($contactData['first_name']) || empty($contactData['last_name']))) {
                        $errors[] = [
                            'row' => $index + 1,
                            'message' => 'Name splitting failed - could not split name: ' . ($row[$nameSplitConfig['column']] ?? 'N/A')
                        ];
                        $errorCount++;
                        continue;
                    }
                    
                    // Check for duplicate email (only if email is provided)
                    if (!empty($contactData['email'])) {
                        $existing = $db->fetchOne("SELECT id FROM contacts WHERE email = ?", [$contactData['email']]);
                        if ($existing) {
                            $errors[] = [
                                'row' => $index + 1,
                                'message' => 'Contact with this email already exists'
                            ];
                            $errorCount++;
                            continue;
                        }
                    }
                    
                    // Insert contact
                    $db->insert('contacts', $contactData);
                    $successCount++;
                    
                } catch (Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'message' => 'Database error: ' . $e->getMessage()
                    ];
                    $errorCount++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'totalProcessed' => count($csvData),
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'errors' => $errors
            ]);
            return;
        }
        
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid import request',
            'code' => 400
        ]);
        return;
        
    } catch (Exception $e) {
        debugLog("[ERROR] handleImport: failed error=" . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'code' => 500
        ]);
    }
} 
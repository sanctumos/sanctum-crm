<?php
/**
 * Public inbound endpoint for Wix Automations / hosted site forms.
 * POST JSON or form fields → CRM lead contact.
 */

define('CRM_LOADED', true);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/ConfigManager.php';
require_once __DIR__ . '/includes/WebhookDispatcher.php';
require_once __DIR__ . '/includes/WixLeadIngest.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
    exit;
}

$ingest = new WixLeadIngest();
$secret = trim((string) ($_SERVER['HTTP_X_WIX_LEAD_SECRET'] ?? ''));
if ($secret === '') {
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Bearer ') === 0) {
        $secret = trim(substr($auth, 7));
    }
}
if ($secret === '') {
    $secret = trim((string) ($_GET['secret'] ?? ''));
}

if (!$ingest->verifyRequestSecret($secret)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'code' => 401]);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$payload = [];
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = $decoded;
    }
}
if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
}

$source = 'wix';
if (isset($payload['source']) && is_string($payload['source'])) {
    $source = trim($payload['source']);
} elseif (isset($_POST['source']) && is_string($_POST['source'])) {
    $source = trim($_POST['source']);
}

$result = $ingest->handlePayload($payload, $source !== '' ? $source : 'wix');
http_response_code($result['status']);
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

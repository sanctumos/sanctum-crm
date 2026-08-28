<?php
/**
 * Ask Len — page context for Broca (what screen the chatter is on).
 *
 * Sends IDs + short labels only — never dossier/enrichment body text.
 * Len loads full content via SMCP get-contact / get-contact-enrichment when needed.
 */
declare(strict_types=1);

require_once __DIR__ . '/utils.php';

/**
 * Canonical site origin for Docket UI links (no trailing slash).
 */
function len_bridge_admin_origin(): string
{
    if (defined('APP_URL') && is_string(APP_URL) && APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }
    return rtrim(get_base_url(), '/');
}

function len_bridge_request_query_int(string $key): int
{
    if (isset($_GET[$key])) {
        return max(0, (int) $_GET[$key]);
    }
    $qs = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    if (is_string($qs) && $qs !== '') {
        $params = [];
        parse_str($qs, $params);
        return max(0, (int) ($params[$key] ?? 0));
    }
    return 0;
}

function len_bridge_request_query_string(string $key, int $maxLen = 64): string
{
    $val = '';
    if (isset($_GET[$key]) && is_string($_GET[$key])) {
        $val = $_GET[$key];
    } else {
        $qs = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        if (is_string($qs) && $qs !== '') {
            $params = [];
            parse_str($qs, $params);
            $val = (string) ($params[$key] ?? '');
        }
    }
    return substr(trim($val), 0, $maxLen);
}

/**
 * @return array<string, mixed>
 */
function len_bridge_detect_page_context(): array
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    $page = len_bridge_request_query_string('page', 48);
    if ($page === '' && (str_ends_with($path, '/index.php') || $path === '/' || $path === '')) {
        $page = 'dashboard';
    }

    $ctx = [
        'surface' => $page !== '' ? $page : 'unknown',
        'path' => $path,
    ];

    $id = len_bridge_request_query_int('id');
    if ($page === 'view_contact' && $id > 0) {
        $ctx['surface'] = 'contact';
        $ctx['contact_id'] = $id;
    }
    if ($page === 'edit_contact' && $id > 0) {
        $ctx['surface'] = 'contact_edit';
        $ctx['contact_id'] = $id;
    }
    if ($page === 'contacts') {
        $ctx['surface'] = 'contacts';
        foreach (['q', 'tag', 'enrichment', 'license_status', 'board_state', 'address_state'] as $k) {
            $v = len_bridge_request_query_string($k, 80);
            if ($v !== '') {
                $ctx[$k] = $v;
            }
        }
    }
    if ($page === 'enrichment') {
        $ctx['surface'] = 'enrichment';
        $st = len_bridge_request_query_string('status', 32);
        if ($st !== '') {
            $ctx['queue_status'] = $st;
        }
    }
    if ($page === 'tags') {
        $ctx['surface'] = 'tags';
    }
    if ($page === 'settings') {
        $ctx['surface'] = 'settings';
    }
    if ($page === 'users') {
        $ctx['surface'] = 'users';
    }
    if ($page === 'dashboard' || $page === '') {
        $ctx['surface'] = 'dashboard';
    }
    if ($page === 'deals') {
        $ctx['surface'] = 'deals';
        foreach (['q', 'stage'] as $k) {
            $v = len_bridge_request_query_string($k, 80);
            if ($v !== '') {
                $ctx[$k] = $v;
            }
        }
    }
    if ($page === 'edit_contact' && $id > 0) {
        $ctx['surface'] = 'contact_edit';
        $ctx['contact_id'] = $id;
    }
    if ($page === 'merges') {
        $ctx['surface'] = 'merges';
    }
    if ($page === 'import_contacts') {
        $ctx['surface'] = 'import_contacts';
    }
    if ($page === 'webhooks') {
        $ctx['surface'] = 'webhooks';
    }
    if ($page === 'reports') {
        $ctx['surface'] = 'reports';
    }
    if ($page === 'profile') {
        $ctx['surface'] = 'profile';
    }

    return $ctx;
}

/**
 * @param array<string, mixed> $raw
 * @param array<string, mixed>|null $user
 * @return array<string, mixed>
 */
function len_bridge_enrich_page_context(array $raw, ?array $user = null): array
{
    $origin = len_bridge_admin_origin();
    $out = $raw;
    $out['admin_origin'] = $origin;
    $out['product'] = 'sanctum_crm';
    if ($user) {
        $out['username'] = (string) ($user['username'] ?? '');
        $out['user_id'] = (int) ($user['id'] ?? 0);
        $out['role'] = (string) ($user['role'] ?? '');
    }

    // Short human label for Layer B preamble
    $surface = (string) ($out['surface'] ?? 'unknown');
    $label = match ($surface) {
        'contact' => 'Contact dossier #' . (int) ($out['contact_id'] ?? 0),
        'contact_edit' => 'Edit contact #' . (int) ($out['contact_id'] ?? 0),
        'contacts' => 'Contacts list',
        'deals' => 'Deals pipeline',
        'merges' => 'Merge review',
        'import_contacts' => 'Import contacts',
        'webhooks' => 'Webhooks',
        'reports' => 'Reports',
        'profile' => 'Profile',
        'enrichment' => 'Enrichment queue',
        'tags' => 'Tags catalog',
        'settings' => 'Settings',
        'users' => 'Users',
        'dashboard' => 'Dashboard',
        default => ucfirst(str_replace('_', ' ', $surface)),
    };
    $out['screen_label'] = $label;

    return $out;
}

/**
 * Sanitize page_context from the widget (IDs + short labels only).
 *
 * @param array<string, mixed>|null $raw
 * @return array<string, mixed>
 */
function len_bridge_normalize_page_context(?array $raw): array
{
    if (!is_array($raw) || $raw === []) {
        return len_bridge_detect_page_context();
    }
    $out = [];
    $allowStr = [
        'surface', 'path', 'screen_label', 'admin_origin', 'product',
        'username', 'role', 'tag', 'enrichment', 'license_status',
        'board_state', 'address_state', 'queue_status', 'q',
    ];
    foreach ($allowStr as $k) {
        if (!isset($raw[$k])) {
            continue;
        }
        $v = trim((string) $raw[$k]);
        if ($v !== '') {
            $out[$k] = substr($v, 0, $k === 'q' ? 120 : 80);
        }
    }
    foreach (['contact_id', 'user_id'] as $k) {
        if (isset($raw[$k]) && (int) $raw[$k] > 0) {
            $out[$k] = (int) $raw[$k];
        }
    }
    if (empty($out['surface'])) {
        $out['surface'] = 'unknown';
    }
    return $out;
}

/**
 * Format context block prepended for Len (not shown in widget).
 *
 * @param array<string, mixed> $ctx
 */
function len_bridge_format_chat_context_block(array $ctx): string
{
    $origin = (string) ($ctx['admin_origin'] ?? len_bridge_admin_origin());
    $lines = [
        '[Chat context — Sanctum CRM]',
        'Admin origin (use for links): ' . $origin,
        'Screen: ' . (string) ($ctx['screen_label'] ?? $ctx['surface'] ?? 'unknown'),
    ];
    if (!empty($ctx['contact_id'])) {
        $lines[] = 'Contact id: ' . (int) $ctx['contact_id'];
    }
    foreach (['tag', 'enrichment', 'license_status', 'board_state', 'queue_status', 'q'] as $k) {
        if (!empty($ctx[$k])) {
            $lines[] = $k . ': ' . (string) $ctx[$k];
        }
    }
    if (!empty($ctx['username'])) {
        $lines[] = 'Chatter username: ' . (string) $ctx['username'];
    }
    return implode("\n", $lines);
}

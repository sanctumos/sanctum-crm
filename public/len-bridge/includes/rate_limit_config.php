<?php
/**
 * Ask Len / q-bridge rate limits — defaults with optional admin overrides (app_settings).
 *
 * Defaults target logged-in CRM users (not anonymous internet). Caps are high enough
 * that normal all-day admin + open chat panel never trips 429; still bounded for abuse.
 */
declare(strict_types=1);

const Q_BRIDGE_RATE_LIMITS_SETTING_KEY = 'q_bridge.rate_limits';

/**
 * @return array<string, mixed>
 */
function len_bridge_rate_limit_defaults(): array
{
    return [
        'user_endpoints' => [
            '/api/messages' => 300,       // ~5/min sustained chat
            '/api/responses' => 7200,   // 3s active poll ≈1200/h; 6× headroom
            '/api/history' => 600,      // open panel / reload thread
            '/api/user_session' => 3000, // persistSession on admin navigation
        ],
        'user_max_requests' => 20000,
        'ip_endpoints' => [
            '/api/messages' => 500,
            '/api/responses' => 7200,
            '/api/history' => 600,
            '/api/user_session' => 600,
            '/api/inbox' => 10000,        // Broca ~720/h at 5s poll
            '/api/outbox' => 5000,
            '/api/sessions' => 200,
        ],
        'ip_max_requests' => 25000,
    ];
}

function len_bridge_ensure_tasks_settings_loaded(): void
{
    // Docket has no Tasks app_settings — rate limits use file defaults only.
}

/** @var array<string, mixed>|null */
$GLOBALS['len_bridge_rate_limit_config_cache'] = null;

function len_bridge_clear_rate_limit_config_cache(): void
{
    $GLOBALS['len_bridge_rate_limit_config_cache'] = null;
}

/**
 * @return array<string, mixed>
 */
function len_bridge_get_rate_limit_config(): array
{
    if (is_array($GLOBALS['len_bridge_rate_limit_config_cache'] ?? null)) {
        return $GLOBALS['len_bridge_rate_limit_config_cache'];
    }
    $merged = len_bridge_rate_limit_defaults();
    $GLOBALS['len_bridge_rate_limit_config_cache'] = $merged;
    return $merged;
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function len_bridge_merge_rate_limit_config(array $base, array $overrides): array
{
    foreach (['user_endpoints', 'ip_endpoints'] as $bucket) {
        if (!isset($overrides[$bucket]) || !is_array($overrides[$bucket])) {
            continue;
        }
        foreach ($overrides[$bucket] as $endpoint => $limit) {
            $endpoint = (string)$endpoint;
            $limit = (int)$limit;
            if ($endpoint !== '' && $limit > 0) {
                $base[$bucket][$endpoint] = $limit;
            }
        }
    }
    if (isset($overrides['user_max_requests'])) {
        $v = (int)$overrides['user_max_requests'];
        if ($v > 0) {
            $base['user_max_requests'] = $v;
        }
    }
    if (isset($overrides['ip_max_requests'])) {
        $v = (int)$overrides['ip_max_requests'];
        if ($v > 0) {
            $base['ip_max_requests'] = $v;
        }
    }
    return $base;
}

/**
 * @param array<string, mixed> $input
 * @return array{success: bool, error?: string}
 */
function len_bridge_validate_rate_limit_input(array $input): array
{
    $check = static function (mixed $value, string $label) {
        if (!is_numeric($value)) {
            return ['success' => false, 'error' => $label . ' must be a number'];
        }
        $n = (int)$value;
        if ($n < 1 || $n > 100000) {
            return ['success' => false, 'error' => $label . ' must be between 1 and 100000'];
        }
        return ['success' => true, 'value' => $n];
    };

    $fields = [
        'messages' => '/api/messages',
        'responses' => '/api/responses',
        'history' => '/api/history',
        'user_session' => '/api/user_session',
    ];
    $userEndpoints = [];
    foreach ($fields as $field => $endpoint) {
        if (!array_key_exists($field, $input)) {
            return ['success' => false, 'error' => 'Missing ' . $field];
        }
        $r = $check($input[$field], $field);
        if (!$r['success']) {
            return $r;
        }
        $userEndpoints[$endpoint] = $r['value'];
    }

    $rMax = $check($input['user_max_requests'] ?? null, 'user_max_requests');
    if (!$rMax['success']) {
        return $rMax;
    }

    $rIp = $check($input['ip_max_requests'] ?? null, 'ip_max_requests');
    if (!$rIp['success']) {
        return $rIp;
    }

    return [
        'success' => true,
        'config' => [
            'user_endpoints' => $userEndpoints,
            'user_max_requests' => $rMax['value'],
            'ip_max_requests' => $rIp['value'],
        ],
    ];
}

/**
 * @param array<string, mixed> $config Partial config from admin form
 */
function len_bridge_save_rate_limit_config(array $config, ?int $actorUserId = null): array
{
    return [
        'success' => false,
        'error' => 'Ask Len rate-limit admin UI is not wired on Docket yet — file defaults apply.',
    ];
}

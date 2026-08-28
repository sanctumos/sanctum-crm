<?php
/**
 * Ask Len connection — enable/disable + Sanctum target (URL + agent).
 * Stored in system_config (category len_bridge); UI gated by len_bridge_is_ui_enabled().
 */
declare(strict_types=1);

const LEN_BRIDGE_CONNECTION_SETTING_CATEGORY = 'len_bridge';
const LEN_BRIDGE_CONNECTION_SETTING_KEY = 'connection';

/**
 * @return array{enabled: bool, sanctum_url: string, agent_id: string, agent_label: string}
 */
function len_bridge_connection_defaults(): array
{
    return [
        'enabled' => defined('CRM_LEN_BRIDGE_ENABLED') ? (bool) CRM_LEN_BRIDGE_ENABLED : false,
        'sanctum_url' => '',
        'agent_id' => '',
        'agent_label' => 'Len Vernal',
    ];
}

function len_bridge_ensure_crm_core_loaded(): void
{
    if (class_exists('ConfigManager')) {
        return;
    }
    $publicRoot = dirname(__DIR__, 2);
    if (!defined('CRM_LOADED')) {
        define('CRM_LOADED', true);
    }
    require_once $publicRoot . '/includes/config.php';
    require_once $publicRoot . '/includes/database.php';
    require_once $publicRoot . '/includes/ConfigManager.php';
}

/** @var array<string, mixed>|null */
$GLOBALS['len_bridge_connection_config_cache'] = null;

function len_bridge_clear_connection_config_cache(): void
{
    $GLOBALS['len_bridge_connection_config_cache'] = null;
}

/**
 * @return array{enabled: bool, sanctum_url: string, agent_id: string, agent_label: string}
 */
function len_bridge_get_connection_config(): array
{
    if (is_array($GLOBALS['len_bridge_connection_config_cache'] ?? null)) {
        /** @var array{enabled: bool, sanctum_url: string, agent_id: string, agent_label: string} */
        return $GLOBALS['len_bridge_connection_config_cache'];
    }

    $cfg = len_bridge_connection_defaults();
    try {
        len_bridge_ensure_crm_core_loaded();
        $raw = ConfigManager::getInstance()->get(
            LEN_BRIDGE_CONNECTION_SETTING_CATEGORY,
            LEN_BRIDGE_CONNECTION_SETTING_KEY
        );
        if ($raw !== null && $raw !== '') {
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (is_array($decoded)) {
                if (array_key_exists('enabled', $decoded)) {
                    $parsed = filter_var($decoded['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($parsed !== null) {
                        $cfg['enabled'] = $parsed;
                    }
                }
                if (isset($decoded['sanctum_url']) && is_string($decoded['sanctum_url'])) {
                    $cfg['sanctum_url'] = trim($decoded['sanctum_url']);
                }
                if (isset($decoded['agent_id']) && is_string($decoded['agent_id'])) {
                    $cfg['agent_id'] = trim($decoded['agent_id']);
                }
                if (isset($decoded['agent_label']) && is_string($decoded['agent_label'])) {
                    $label = trim($decoded['agent_label']);
                    if ($label !== '') {
                        $cfg['agent_label'] = $label;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Fall back to defaults if DB unavailable.
    }

    $GLOBALS['len_bridge_connection_config_cache'] = $cfg;
    return $cfg;
}

function len_bridge_is_ui_enabled(): bool
{
    if (defined('CRM_LEN_BRIDGE_ENABLED') && !CRM_LEN_BRIDGE_ENABLED) {
        return false;
    }
    $cfg = len_bridge_get_connection_config();
    return !empty($cfg['enabled']);
}

/**
 * @param array<string, mixed> $input
 * @return array{success: bool, error?: string, config?: array{enabled: bool, sanctum_url: string, agent_id: string, agent_label: string}}
 */
function len_bridge_save_connection_config(array $input, ?int $actorUserId = null): array
{
    len_bridge_ensure_crm_core_loaded();

    $enabled = !empty($input['enabled']);
    $sanctumUrl = trim((string) ($input['sanctum_url'] ?? ''));
    $agentId = trim((string) ($input['agent_id'] ?? ''));
    $agentLabel = trim((string) ($input['agent_label'] ?? ''));
    if ($agentLabel === '') {
        $agentLabel = 'Len Vernal';
    }

    if ($sanctumUrl !== '') {
        if (!preg_match('#^https?://#i', $sanctumUrl)) {
            return ['success' => false, 'error' => 'Sanctum URL must start with http:// or https://'];
        }
        if (strlen($sanctumUrl) > 500) {
            return ['success' => false, 'error' => 'Sanctum URL is too long'];
        }
    }
    if ($agentId !== '' && !preg_match('/^[a-zA-Z0-9._:-]{1,120}$/', $agentId)) {
        return ['success' => false, 'error' => 'Agent id has invalid characters'];
    }
    if (strlen($agentLabel) > 80) {
        return ['success' => false, 'error' => 'Agent display name is too long'];
    }

    $cfg = [
        'enabled' => $enabled,
        'sanctum_url' => $sanctumUrl,
        'agent_id' => $agentId,
        'agent_label' => $agentLabel,
    ];

    ConfigManager::getInstance()->set(
        LEN_BRIDGE_CONNECTION_SETTING_CATEGORY,
        LEN_BRIDGE_CONNECTION_SETTING_KEY,
        $cfg,
        false
    );

    len_bridge_clear_connection_config_cache();
    return ['success' => true, 'config' => $cfg];
}

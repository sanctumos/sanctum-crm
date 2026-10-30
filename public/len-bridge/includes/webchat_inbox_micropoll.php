<?php
/**
 * Broca inbox micro-polls must not hold a PHP-FPM worker for DB_TIMEOUT (30s).
 * Another poll is a few seconds away — wait ~2s, then 503 Inbox busy.
 */
if (!function_exists('webchat_bridge_inbox_busy_timeout_ms')) {

function webchat_bridge_inbox_busy_timeout_ms(): int
{
    $env = getenv('WEBCHAT_INBOX_BUSY_TIMEOUT_MS');
    if ($env !== false && trim((string) $env) !== '') {
        return max(50, min(5000, (int) $env));
    }

    return 2000;
}

function webchat_bridge_begin_inbox_micropoll(): int
{
    $ms = webchat_bridge_inbox_busy_timeout_ms();
    $GLOBALS['_webchat_bridge_inbox_busy_timeout_ms'] = $ms;
    $skipLimit = getenv('KITCHEN_POS_TEST') === '1'
        || getenv('TASKS_TEST') === '1'
        || defined('PHPUNIT_COMPOSER_INSTALL');
    if (!$skipLimit) {
        @set_time_limit(max(2, (int) ceil($ms / 1000) + 1));
    }

    return $ms;
}

function webchat_bridge_inbox_micropoll_active(): bool
{
    return isset($GLOBALS['_webchat_bridge_inbox_busy_timeout_ms']);
}

function webchat_bridge_apply_pdo_timeout(PDO $pdo, int $defaultTimeoutSeconds): void
{
    $ms = $defaultTimeoutSeconds * 1000;
    $seconds = $defaultTimeoutSeconds;
    if (webchat_bridge_inbox_micropoll_active()) {
        $ms = (int) $GLOBALS['_webchat_bridge_inbox_busy_timeout_ms'];
        $seconds = max(1, (int) ceil($ms / 1000));
    }
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, $seconds);
    try {
        $pdo->exec('PRAGMA busy_timeout = ' . $ms);
    } catch (Throwable $e) {
        error_log('webchat bridge busy_timeout: ' . $e->getMessage());
    }
}

}

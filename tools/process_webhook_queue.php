#!/usr/bin/env php
<?php
/**
 * Drain due webhook_delivery_queue rows (retries + dead-letter).
 *
 * Usage:
 *   php tools/process_webhook_queue.php
 *   php tools/process_webhook_queue.php --limit=50
 *   CRM_DB_PATH=/path/to/crm.db php tools/process_webhook_queue.php
 *
 * Cron (every minute): php /root/repos/crm.decisionsciencecorp.com/tools/process_webhook_queue.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('CRM_LOADED')) {
    define('CRM_LOADED', true);
}
if (!defined('CRM_TESTING')) {
    define('CRM_TESTING', false);
}
if (getenv('CRM_DB_PATH') && !defined('DB_PATH')) {
    define('DB_PATH', getenv('CRM_DB_PATH'));
}

require_once $root . '/public/includes/config.php';
require_once $root . '/public/includes/database.php';
require_once $root . '/public/includes/MigrationRunner.php';
require_once $root . '/public/includes/WebhookDispatcher.php';
require_once $root . '/public/includes/WebhookQueue.php';

$limit = 25;
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int) $m[1];
    }
}

putenv('CRM_AUTO_MIGRATE=0');
$db = Database::getInstanceWithoutAutoMigrate();
// Ensure queue table exists if migrate was applied
$runner = new MigrationRunner($db);
$runner->migrate(false);

$queue = new WebhookQueue($db);
$before = $queue->counts();
$stats = $queue->processDue($limit);
$after = $queue->counts();

echo json_encode([
    'ok' => true,
    'processed' => $stats,
    'counts_before' => $before,
    'counts_after' => $after,
], JSON_PRETTY_PRINT) . "\n";

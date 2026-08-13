#!/usr/bin/env php
<?php
/**
 * Apply pending CRM schema migrations (explicit; not on the request path).
 *
 * Usage:
 *   php tools/migrate.php status
 *   php tools/migrate.php --dry-run
 *   php tools/migrate.php
 *   CRM_DB_PATH=/path/to/crm.db php tools/migrate.php
 *
 * On multihost after sync: php /path/to/webroot/../tools/migrate.php
 * (or from the git clone SRC_DIR). Prefer Ada handoff naming this outcome.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('CRM_LOADED')) {
    define('CRM_LOADED', true);
}
if (!defined('CRM_TESTING')) {
    define('CRM_TESTING', false);
}

// Allow override before config loads
if (getenv('CRM_DB_PATH') && !defined('DB_PATH')) {
    define('DB_PATH', getenv('CRM_DB_PATH'));
}

require_once $root . '/public/includes/config.php';
require_once $root . '/public/includes/database.php';
require_once $root . '/public/includes/MigrationRunner.php';

$argv = $_SERVER['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$statusOnly = in_array('status', $argv, true);

// Force migrate path even if CRM_AUTO_MIGRATE is off — this CLI is the explicit runner.
putenv('CRM_AUTO_MIGRATE=0');

$db = Database::getInstanceWithoutAutoMigrate();
$runner = new MigrationRunner($db);

if ($statusOnly) {
    $st = $runner->status();
    echo "Applied (" . count($st['applied']) . "):\n";
    foreach ($st['applied'] as $row) {
        echo "  ✓ {$row['version']} — {$row['description']}\n";
    }
    echo "Pending (" . count($st['pending']) . "):\n";
    foreach ($st['pending'] as $row) {
        echo "  • {$row['version']} — {$row['description']}\n";
    }
    exit(count($st['pending']) > 0 ? 1 : 0);
}

$result = $runner->migrate($dryRun);
$label = $dryRun ? 'Would apply' : 'Applied';
echo "{$label}: " . (count($result['applied']) ? implode(', ', $result['applied']) : '(none)') . "\n";
echo "Skipped (already applied): " . count($result['skipped']) . "\n";
exit(0);

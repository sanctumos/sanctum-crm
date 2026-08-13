#!/usr/bin/env php
<?php
/**
 * Fresh-install smoke for Sanctum CRM (CLI).
 *
 *   php tools/smoke_install.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$smokeDb = sys_get_temp_dir() . '/sanctum-crm-install-smoke-' . getmypid() . '.db';
@unlink($smokeDb);

define('CRM_LOADED', true);
define('CRM_TESTING', true);
define('DB_PATH', $smokeDb);

require_once $root . '/public/includes/config.php';
require_once $root . '/public/includes/database.php';
require_once $root . '/public/includes/ConfigManager.php';
require_once $root . '/public/includes/InstallationManager.php';
require_once $root . '/public/includes/auth.php';
require_once $root . '/public/includes/ContactDataStore.php';
require_once $root . '/public/includes/ContactTagService.php';
require_once $root . '/public/includes/ContactMergeService.php';
require_once $root . '/public/includes/MigrationRunner.php';

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "PASS: $msg\n";
}

$im = new InstallationManager();
assertTrue($im->isFirstBoot(), 'starts in first-boot state');

$env = $im->validateEnvironment();
if (empty($env['valid'])) {
    $onlyMb = ($env['errors'] ?? []) === ["Required PHP extension 'mbstring' is not loaded"];
    if ($onlyMb) {
        echo "WARN: mbstring missing in this CLI image; continuing smoke past environment gate\n";
    } else {
        fwrite(STDERR, 'FAIL: environment validates: ' . implode('; ', $env['errors'] ?? []) . "\n");
        exit(1);
    }
} else {
    echo "PASS: environment validates\n";
}
$im->completeStep('environment');

assertTrue($im->initializeDatabase(), 'database initializes');
$im->setupDefaultConfig();
$im->completeStep('database');

assertTrue($im->setupCompany('Smoke Co', 'UTC') === true, 'company setup');
$im->completeStep('company');

assertTrue(
    $im->createAdminUser('admin', 'admin@example.com', 'SmokePass123!', 'Smoke', 'Admin') === true,
    'admin user created'
);
$im->completeStep('admin');

assertTrue($im->completeInstallation() === true, 'installation marked complete');
assertTrue(!$im->isFirstBoot(), 'first-boot cleared');

$db = Database::getInstance();
$db->ensureContactDataSidecar();
$db->ensureSkinLabColumns();
(new MigrationRunner($db))->migrate(false);

$store = new ContactDataStore($db);
$store->ensureSchema();
(new ContactTagService($db))->ensureSchema();
new ContactMergeService($db);

$user = $db->fetchOne("SELECT id, api_key, role FROM users WHERE username = 'admin'");
assertTrue(!empty($user['id']), 'admin row exists');
assertTrue(($user['role'] ?? '') === 'admin', 'admin role');
assertTrue(!empty($user['api_key']), 'admin has api_key');

$tables = array_column(
    $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"),
    'name'
);
foreach (['contacts', 'deals', 'webhooks', 'users', 'settings', 'company_info', 'installation_state',
          'enrichment_cron_runs', 'contact_data_facts', 'contact_merge_candidates'] as $need) {
    assertTrue(in_array($need, $tables, true), "table present: $need");
}

// Allowed pages / feature files exist on disk
foreach ([
    'public/pages/merges.php',
    'public/pages/profile.php',
    'public/cron/enrichment.php',
    'public/cron/merge_candidates.php',
    'public/includes/EnrichmentCronService.php',
    'public/includes/WebhookQueue.php',
    'public/assets/css/skins/hey.css',
] as $rel) {
    assertTrue(is_file($root . '/' . $rel), "feature file: $rel");
}

@unlink($smokeDb);
echo "\nInstall smoke OK.\n";
exit(0);

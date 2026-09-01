<?php
/**
 * Deposit Wix lead ingest shared secret into CRM system_config (integrations.wix_lead_ingest_secret).
 *
 * Usage on multihost (after backup):
 *   php tools/deposit-wix-lead-secret.php /var/www/crm.soletigre.com/db/crm.db
 *
 * Or pass secret via env WIX_LEAD_INGEST_SECRET.
 */

declare(strict_types=1);

$dbPath = $argv[1] ?? getenv('CRM_DB_PATH') ?: '';
if ($dbPath === '' || !is_file($dbPath)) {
    fwrite(STDERR, "Usage: php deposit-wix-lead-secret.php /path/to/crm.db\n");
    exit(1);
}

$secret = trim((string) (getenv('WIX_LEAD_INGEST_SECRET') ?: ''));
if ($secret === '') {
    $secret = bin2hex(random_bytes(32));
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $db->query(
    "SELECT id FROM system_config WHERE category = 'integrations' AND config_key = 'wix_lead_ingest_secret'"
)->fetchColumn();

$now = date('Y-m-d H:i:s');
if ($exists) {
    $stmt = $db->prepare(
        'UPDATE system_config SET config_value = ?, data_type = ?, is_encrypted = 0, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$secret, 'string', $now, $exists]);
} else {
    $stmt = $db->prepare(
        'INSERT INTO system_config (category, config_key, config_value, data_type, is_encrypted, created_at, updated_at)
         VALUES (?, ?, ?, ?, 0, ?, ?)'
    );
    $stmt->execute(['integrations', 'wix_lead_ingest_secret', $secret, 'string', $now, $now]);
}

echo "OK secret_set=1\n";
echo "WIX_LEAD_INGEST_SECRET=" . $secret . "\n";

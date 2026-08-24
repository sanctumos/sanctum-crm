<?php
/**
 * Scheduled lead enrichment cron (active provider: RocketReach or Apollo).
 *
 * Run from the deployed web root:
 *   php /var/www/localhost/html/cron/enrichment.php
 */

define('CRM_LOADED', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/LeadEnrichmentService.php';
require_once __DIR__ . '/../includes/MockLeadEnrichmentService.php';
require_once __DIR__ . '/../includes/EnrichmentCronService.php';
require_once __DIR__ . '/../includes/enrichment/EnrichmentProviders.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line.');
}

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

if (!class_exists('EnrichmentService', false)) {
    if ($useRealEnrichment) {
        class_alias('LeadEnrichmentService', 'EnrichmentService');
    } else {
        class_alias('MockLeadEnrichmentService', 'EnrichmentService');
    }
}

try {
    $force = in_array('--force', $argv ?? [], true);
    echo "Starting enrichment cron at " . date('Y-m-d H:i:s') . "\n";

    $service = new EnrichmentCronService();
    $config = $service->getConfig();
    if (!$force && empty($config['enabled'])) {
        echo "Scheduled enrichment is disabled.\n";
        exit(0);
    }

    $result = $service->run($force);

    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(($result['status'] ?? '') === 'failed' ? 1 : 0);
} catch (Exception $e) {
    error_log("Enrichment cron error: " . $e->getMessage());
    echo "Enrichment cron failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

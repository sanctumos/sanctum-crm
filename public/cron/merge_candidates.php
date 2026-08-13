<?php
/**
 * Propose contact merge candidates (Doc #919). Never merges — humans accept in UI/API.
 *
 *   php /var/www/localhost/html/cron/merge_candidates.php
 *   php cron/merge_candidates.php --max=800
 */

define('CRM_LOADED', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/ContactMergeService.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line.');
}

$maxPairs = 500;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--max=(\d+)$/', $arg, $m)) {
        $maxPairs = max(1, min(5000, (int) $m[1]));
    }
}

try {
    echo 'Starting merge candidate cron at ' . date('Y-m-d H:i:s') . "\n";
    $service = new ContactMergeService();
    $stats = $service->generateCandidates($maxPairs);
    echo json_encode([
        'status' => 'ok',
        'max_pairs' => $maxPairs,
        'stats' => $stats,
        'pending_high' => $service->pendingHighCount(),
    ], JSON_PRETTY_PRINT) . "\n";
    exit(0);
} catch (Exception $e) {
    error_log('Merge candidate cron error: ' . $e->getMessage());
    echo 'Merge candidate cron failed: ' . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

#!/usr/bin/env php
<?php
/**
 * Unit: twitter_handle is a first-class enrichment lookup (RR People Search path).
 */
define('CRM_LOADED', true);
define('CRM_TESTING', true);

require_once __DIR__ . '/../../public/includes/config.php';
require_once __DIR__ . '/../../public/includes/database.php';
require_once __DIR__ . '/../../public/includes/LeadEnrichmentService.php';

$passed = 0;
$failed = 0;
$assert = function (bool $ok, string $msg) use (&$passed, &$failed) {
    if ($ok) {
        echo "PASS - $msg\n";
        $passed++;
    } else {
        echo "FAIL - $msg\n";
        $failed++;
    }
};

$svc = new LeadEnrichmentService();
$thin = [
    'id' => 0,
    'first_name' => 'Anon',
    'last_name' => '@somehandle',
    'email' => null,
    'linkedin_profile' => null,
    'company' => null,
    'twitter_handle' => '@SomeHandle',
];
$assert($svc->canEnrich($thin) === true, 'canEnrich true with twitter_handle alone');

$lookups = $svc->collectEnrichmentLookups(0, $thin);
$tw = array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'twitter'));
$assert(count($tw) === 1, 'collectEnrichmentLookups includes twitter');
$assert(($tw[0]['value'] ?? '') === 'SomeHandle', 'twitter handle normalized without @');

$none = [
    'id' => 0,
    'first_name' => 'X',
    'last_name' => 'Y',
    'email' => null,
    'linkedin_profile' => null,
    'company' => null,
    'twitter_handle' => '',
];
$assert($svc->canEnrich($none) === false, 'canEnrich false without identifiers');

echo "RESULT passed=$passed failed=$failed\n";
exit($failed > 0 ? 1 : 0);

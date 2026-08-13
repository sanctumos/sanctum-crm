<?php
/**
 * Enrichment lookups include sidecar email alts.
 */

define('CRM_LOADED', true);
define('CRM_TESTING', true);

require_once __DIR__ . '/../../public/includes/config.php';
require_once __DIR__ . '/../../public/includes/database.php';
require_once __DIR__ . '/../../public/includes/ContactDataStore.php';
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

$db = Database::getInstance();
$store = new ContactDataStore($db);
$store->ensureSchema();
$svc = new LeadEnrichmentService();
$ts = getCurrentTimestamp();

$db->insert('contacts', [
    'first_name' => 'Lookup',
    'last_name' => 'Test',
    'email' => 'primary-' . uniqid() . '@example.com',
    'contact_type' => 'lead',
    'contact_status' => 'new',
    'created_at' => $ts,
    'updated_at' => $ts,
]);
$id = (int) $db->getLastInsertId();
$contact = $db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$id]);
$alt = 'sidecar-' . uniqid() . '@example.com';
$store->recordRun($id, [
    'source' => 'merge',
    'outcome' => 'merged',
    'label' => 'test',
    'raw_payload' => ['test' => true],
    'facts' => [
        ['fact_type' => 'email', 'value' => $alt, 'label' => 'absorbed_primary'],
        ['fact_type' => 'social', 'value' => 'https://www.linkedin.com/in/lookup-test', 'label' => 'linkedin'],
    ],
]);

$lookups = $svc->collectEnrichmentLookups($id, $contact);
$emails = array_values(array_map(static fn($l) => $l['value'], array_filter($lookups, static fn($l) => $l['type'] === 'email')));
$lis = array_values(array_filter($lookups, static fn($l) => $l['type'] === 'linkedin'));

$assert(count($emails) >= 2, 'collects primary + sidecar emails');
$assert(in_array(strtolower($contact['email']), $emails, true), 'includes primary email');
$assert(in_array(strtolower($alt), $emails, true), 'includes sidecar email');
$assert($emails[0] === strtolower($contact['email']), 'primary email is first');
$assert(count($lis) >= 1, 'includes sidecar linkedin');
$assert($svc->canEnrich(['id' => $id, 'first_name' => 'X', 'last_name' => 'Y']), 'canEnrich via sidecar alone');

// Thin card with only sidecar email
$thin = ['id' => $id, 'first_name' => '', 'last_name' => '', 'email' => '', 'linkedin_profile' => '', 'company' => ''];
// Re-fetch facts still on contact id
$assert($svc->canEnrich($thin) === true, 'canEnrich true when only sidecar facts exist');

$db->delete('contacts', 'id = ?', [$id]);

echo "\nEnrichmentSidecarLookupTest: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);

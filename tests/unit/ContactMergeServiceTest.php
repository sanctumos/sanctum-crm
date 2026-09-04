<?php
/**
 * ContactMergeService — scoring + merge smoke.
 */

define('CRM_LOADED', true);
define('CRM_TESTING', true);

require_once __DIR__ . '/../../public/includes/config.php';
require_once __DIR__ . '/../../public/includes/database.php';
require_once __DIR__ . '/../../public/includes/ContactMergeService.php';

class ContactMergeServiceTest
{
    private int $passed = 0;
    private int $failed = 0;
    private ContactMergeService $svc;

    private function assertTrue(bool $cond, string $msg): void
    {
        if ($cond) {
            echo "PASS - $msg\n";
            $this->passed++;
        } else {
            echo "FAIL - $msg\n";
            $this->failed++;
        }
    }

    public function runAllTests(): void
    {
        $this->run();
    }

    public function run(): int
    {
        $this->svc = new ContactMergeService();

        $this->assertTrue(
            ContactMergeService::fingerprint(10, 3) === '3:10',
            'fingerprint orders ids'
        );
        $this->assertTrue(ContactMergeService::tierForScore(0.90) === 'high', 'tier high');
        $this->assertTrue(ContactMergeService::tierForScore(0.70) === 'medium', 'tier medium');
        $this->assertTrue(ContactMergeService::tierForScore(0.45) === 'low', 'tier low');
        $this->assertTrue(
            ContactMergeService::tierMeetsFloor('high', 'high'),
            'high meets mass floor'
        );
        $this->assertTrue(
            !ContactMergeService::tierMeetsFloor('medium', 'high'),
            'medium fails mass floor'
        );

        $this->assertScoring();

        $db = Database::getInstance();
        $ts = getCurrentTimestamp();
        $emailA = 'merge-test-a-' . uniqid() . '@example.com';
        $emailB = 'merge-test-b-' . uniqid() . '@example.com';
        $db->insert('contacts', [
            'first_name' => 'Ada',
            'last_name' => 'MergeTest',
            'email' => $emailA,
            'phone' => '2145550199',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
        $idA = (int) $db->getLastInsertId();
        $db->insert('contacts', [
            'first_name' => 'Ada',
            'last_name' => 'MergeTest',
            'email' => $emailB,
            'phone' => '2145550199',
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
        $idB = (int) $db->getLastInsertId();

        $stats = $this->svc->generateCandidates(50);
        $this->assertTrue(($stats['created'] + $stats['updated']) >= 1, 'cron creates phone/name candidate');

        $list = $this->svc->listCandidates(['status' => 'pending', 'tier' => 'high', 'limit' => 50]);
        $hit = null;
        foreach ($list['candidates'] as $c) {
            $ids = [(int) $c['survivor_id'], (int) $c['merge_id']];
            if (in_array($idA, $ids, true) && in_array($idB, $ids, true)) {
                $hit = $c;
                break;
            }
        }
        $this->assertTrue($hit !== null, 'candidate lists Ada pair in high');
        if ($hit) {
            $this->assertTrue((float) $hit['confidence'] >= 0.95, 'phone+full name is top high');
            $this->assertTrue(
                in_array('phone_and_full_name', $hit['reason_codes'], true),
                'reason includes phone_and_full_name'
            );

            // Lineage notes: no truncation; old contact identity on survivor notes + sidecar
            $longNotes = str_repeat('Lineage note body. ', 80);
            $db->update('contacts', ['notes' => $longNotes], 'id = ?', [(int) $hit['merge_id']]);
            $dry = $this->svc->merge((int) $hit['survivor_id'], [(int) $hit['merge_id']], [], true);
            $this->assertTrue(!empty($dry['success']) && !empty($dry['dry_run']), 'dry-run merge ok');
            $patchedNotes = (string) ($dry['plan']['card_patch']['notes'] ?? '');
            $this->assertTrue(str_contains($patchedNotes, 'Merged contact #' . (int) $hit['merge_id']), 'card notes carry lineage header');
            $this->assertTrue(str_contains($patchedNotes, $longNotes), 'card notes keep full absorbed notes (no 500 cap)');
            $sideNote = (string) ($dry['plan']['sidecar'][0]['raw_payload']['lineage_note'] ?? '');
            $this->assertTrue(str_contains($sideNote, $longNotes), 'sidecar lineage_note is full length');

            $acc = $this->svc->acceptCandidates([(int) $hit['id']], null, true);
            $this->assertTrue(($acc['accepted'] ?? 0) === 1, 'accept high candidate merges');
            $gone = $db->fetchOne('SELECT id FROM contacts WHERE id = ?', [(int) $hit['merge_id']]);
            $this->assertTrue($gone === null || $gone === false, 'absorbed contact deleted');
            $surv = $db->fetchOne('SELECT id, notes FROM contacts WHERE id = ?', [(int) $hit['survivor_id']]);
            $this->assertTrue(!empty($surv), 'survivor remains');
            $this->assertTrue(str_contains((string) ($surv['notes'] ?? ''), $longNotes), 'survivor notes retained full lineage after accept');
        }

        foreach ([$idA, $idB] as $cid) {
            $db->delete('contacts', 'id = ?', [$cid]);
        }

        echo "\nContactMergeServiceTest: {$this->passed} passed, {$this->failed} failed\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function assertScoring(): void
    {
        $phoneFull = $this->svc->scoreContactPair(
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '2145550100', 'email' => 'a@x.com'],
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '214-555-0100', 'email' => 'b@x.com']
        );
        $this->assertTrue($phoneFull['confidence'] >= 0.95, 'phone+fn+ln scores >= 0.95');
        $this->assertTrue(
            ContactMergeService::tierForScore($phoneFull['confidence']) === 'high',
            'phone+fn+ln is high'
        );

        $phoneOnly = $this->svc->scoreContactPair(
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '2145550100', 'email' => ''],
            ['first_name' => 'Unknown', 'last_name' => 'Unknown', 'phone' => '2145550100', 'email' => '']
        );
        $this->assertTrue($phoneOnly['confidence'] < 0.60, 'phone-only without name agreement < 0.60');
        $this->assertTrue(
            ContactMergeService::tierForScore($phoneOnly['confidence']) === 'low',
            'phone-only is low (hand-review; not mass-accept)'
        );
        $this->assertTrue($phoneFull['confidence'] > $phoneOnly['confidence'], 'full match outranks phone-only');

        $phoneConflict = $this->svc->scoreContactPair(
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '2145550100', 'email' => '', 'company' => ''],
            ['first_name' => 'John', 'last_name' => 'Smith', 'phone' => '2145550100', 'email' => '', 'company' => '']
        );
        $this->assertTrue($phoneConflict['confidence'] < 0.85, 'phone + name conflict not high');
        $this->assertTrue(
            in_array('name_conflict', $phoneConflict['reason_codes'], true),
            'name_conflict reason set'
        );
        $this->assertTrue(
            $phoneConflict['confidence'] < $phoneOnly['confidence'],
            'name conflict below weak phone-only'
        );

        $dealerLine = $this->svc->scoreContactPair(
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '2145550100', 'company' => 'Acme Golf Cars'],
            ['first_name' => 'John', 'last_name' => 'Smith', 'phone' => '2145550100', 'company' => 'Acme Golf Cars']
        );
        $this->assertTrue($dealerLine['confidence'] < 0.50, 'shared dealer office line stays low');
        $this->assertTrue(
            in_array('shared_dealer_line', $dealerLine['reason_codes'], true),
            'shared_dealer_line reason set'
        );

        // Andrew Smith vs Andrew Lowe — both fully named, last disagrees
        $andrewMismatch = $this->svc->scoreContactPair(
            ['first_name' => 'Andrew', 'last_name' => 'Smith', 'phone' => '', 'email' => 'a@x.com'],
            ['first_name' => 'Andrew', 'last_name' => 'Lowe', 'phone' => '', 'email' => 'b@x.com']
        );
        $this->assertTrue($andrewMismatch['confidence'] < 0.40, 'Andrew Smith vs Lowe is not a candidate');
        $this->assertTrue(
            in_array('full_name_mismatch', $andrewMismatch['reason_codes'], true),
            'full_name_mismatch reason set'
        );

        $andrewPhone = $this->svc->scoreContactPair(
            ['first_name' => 'Andrew', 'last_name' => 'Smith', 'phone' => '2145550100', 'email' => ''],
            ['first_name' => 'Andrew', 'last_name' => 'Lowe', 'phone' => '2145550100', 'email' => '']
        );
        $this->assertTrue($andrewPhone['confidence'] < 0.60, 'Andrew mismatch + phone stays low');
        $this->assertTrue(
            ContactMergeService::tierForScore($andrewPhone['confidence']) === 'low',
            'Andrew mismatch + phone is low tier'
        );

        $gerwilAndrew = $this->svc->scoreContactPair(
            ['first_name' => 'Andrew', 'last_name' => 'Unknown', 'phone' => '', 'email' => 'andrew@ro.gerwil.co'],
            ['first_name' => 'Andrew', 'last_name' => 'Lowe', 'phone' => '', 'email' => 'andrew.lowe@gmail.com']
        );
        $this->assertTrue($gerwilAndrew['confidence'] < 0.85, 'gerwil first-only vs Andrew Lowe not high');

        $nameOnly = $this->svc->scoreContactPair(
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '', 'email' => 'a@x.com'],
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '', 'email' => 'b@x.com']
        );
        $this->assertTrue($nameOnly['confidence'] >= 0.85, 'exact name alone can be high');
        $this->assertTrue(
            $phoneFull['confidence'] > $nameOnly['confidence'],
            'phone+name outranks name-only'
        );

        // Flip keep/absorb
        $rich = ['id' => 1, 'email' => 'rich@example.com', 'phone' => '2145550111', 'last_name' => 'Rich', 'company' => 'Co', 'contact_type' => 'customer'];
        $poor = ['id' => 2, 'email' => '', 'phone' => '', 'last_name' => 'Unknown', 'company' => '', 'contact_type' => 'lead'];
        $br = $this->svc->survivorRichnessBreakdown($rich);
        $bp = $this->svc->survivorRichnessBreakdown($poor);
        $this->assertTrue($br['score'] > $bp['score'], 'richer card scores higher for keep pick');
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    exit((new ContactMergeServiceTest())->run());
}

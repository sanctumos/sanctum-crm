<?php
/**
 * Enrichment cron scheduler tests.
 */

require_once __DIR__ . '/../bootstrap.php';

class FakeCronEnrichmentService {
    public $processed = [];

    public function canEnrich($contact) {
        return !empty($contact['email']) ||
            !empty($contact['linkedin_profile']) ||
            (!empty($contact['first_name']) && !empty($contact['last_name']) && !empty($contact['company']));
    }

    public function enrichContact($contactId, $strategy = 'auto') {
        $this->processed[] = ['id' => (int) $contactId, 'strategy' => $strategy];
        return [
            'success' => true,
            'outcome' => 'enriched',
            'contact' => ['id' => (int) $contactId, 'enriched_at' => getCurrentTimestamp()],
        ];
    }
}

class EnrichmentCronTest {
    private $db;

    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
    }

    public function runAllTests() {
        echo "Running Enrichment Cron Tests...\n";
        $this->testDisabledCronSkips();
        $this->testFiltersAndRunCap();
        $this->testDailyCapPreventsRun();
        $this->testIntervalPreventsRun();
        $this->testMaxAttemptsAndCanEnrichGate();
        echo "All Enrichment Cron tests completed!\n";
    }

    public function testDisabledCronSkips() {
        echo "  Testing disabled cron skips... ";
        $fixture = $this->newServiceWithConfig(['enabled' => false]);
        $result = $fixture['service']->run(false);

        if ($result['status'] === 'skipped' && $result['skipped_reason'] === 'disabled' && count($fixture['fake']->processed) === 0) {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Disabled cron did not skip correctly\n";
    }

    public function testFiltersAndRunCap() {
        echo "  Testing filters and per-run cap... ";
        $fixture = $this->newServiceWithConfig([
            'enabled' => true,
            'max_per_run' => 2,
            'max_per_day' => 10,
            'sources' => ['website'],
            'contact_types' => ['lead'],
            'contact_statuses' => ['new'],
            'eligible_enrichment_statuses' => ['pending'],
        ]);

        TestUtils::createTestContact(['email' => 'cron1@example.com', 'source' => 'website']);
        TestUtils::createTestContact(['email' => 'cron2@example.com', 'source' => 'website']);
        TestUtils::createTestContact(['email' => 'cron3@example.com', 'source' => 'website']);
        TestUtils::createTestContact(['email' => 'skip@example.com', 'source' => 'referral']);
        $result = $fixture['service']->run(false);

        if ($result['processed'] === 2 && count($fixture['fake']->processed) === 2 && $result['status'] === 'completed') {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Expected exactly 2 filtered contacts, got {$result['processed']}\n";
    }

    public function testDailyCapPreventsRun() {
        echo "  Testing daily cap prevents run... ";
        $fixture = $this->newServiceWithConfig(['enabled' => true, 'max_per_run' => 10, 'max_per_day' => 1]);
        $this->db->insert('enrichment_cron_runs', [
            'started_at' => getCurrentTimestamp(),
            'completed_at' => getCurrentTimestamp(),
            'status' => 'completed',
            'processed_count' => 1,
            'enriched_count' => 1,
            'created_at' => getCurrentTimestamp(),
        ]);
        TestUtils::createTestContact(['email' => 'cap@example.com']);
        $result = $fixture['service']->run(true);

        if ($result['status'] === 'skipped' && $result['skipped_reason'] === 'daily_cap_reached' && count($fixture['fake']->processed) === 0) {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Daily cap did not prevent run\n";
    }

    public function testIntervalPreventsRun() {
        echo "  Testing interval prevents early run... ";
        $fixture = $this->newServiceWithConfig(['enabled' => true, 'interval_minutes' => 60]);
        $this->db->insert('enrichment_cron_runs', [
            'started_at' => getCurrentTimestamp(),
            'completed_at' => getCurrentTimestamp(),
            'status' => 'completed',
            'processed_count' => 1,
            'enriched_count' => 1,
            'created_at' => getCurrentTimestamp(),
        ]);
        TestUtils::createTestContact(['email' => 'interval@example.com']);
        $result = $fixture['service']->run(false);

        if ($result['status'] === 'skipped' && $result['skipped_reason'] === 'not_due' && count($fixture['fake']->processed) === 0) {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Interval did not prevent early run\n";
    }

    public function testMaxAttemptsAndCanEnrichGate() {
        echo "  Testing max attempts excludes exhausted contacts... ";
        $fixture = $this->newServiceWithConfig([
            'enabled' => true,
            'max_per_run' => 10,
            'max_attempts_per_contact' => 3,
            'contact_types' => ['lead'],
            'contact_statuses' => ['new'],
            'eligible_enrichment_statuses' => ['pending'],
        ]);

        TestUtils::createTestContact(['email' => 'attempts@example.com', 'enrichment_attempts' => 3]);
        $ineligibleId = TestUtils::createTestContact(['email' => null, 'company' => null, 'linkedin_profile' => null]);
        $eligibleId = TestUtils::createTestContact(['email' => 'eligible@example.com', 'enrichment_attempts' => 2]);
        $result = $fixture['service']->run(false);
        $processedIds = array_column($fixture['fake']->processed, 'id');

        // Ineligible rows are still attempted (service records failed); max-attempts rows are not selected.
        if ($result['processed'] === 2 && in_array($eligibleId, $processedIds, true) && in_array($ineligibleId, $processedIds, true)) {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Expected ineligible + eligible contacts to process (not max-attempts row)\n";
    }

    private function newServiceWithConfig($overrides = []) {
        $this->resetData();
        $fake = new FakeCronEnrichmentService();
        $service = new EnrichmentCronService($fake);
        $defaults = [
            'enabled' => true,
            'interval_minutes' => 1,
            'strategy' => 'auto',
            'max_per_run' => 10,
            'max_per_day' => 100,
            'max_attempts_per_contact' => 3,
            'retry_failed' => false,
            'eligible_enrichment_statuses' => ['pending', 'empty'],
            'contact_types' => ['lead'],
            'contact_statuses' => ['new', 'qualified'],
            'sources' => [],
            'assigned_to' => '',
            'min_contact_age_days' => 0,
        ];
        $service->updateConfig(array_merge($defaults, $overrides));
        return ['service' => $service, 'fake' => $fake];
    }

    private function resetData() {
        $this->db->delete('enrichment_cron_runs', '1=1');
        TestUtils::cleanupTestDatabase();
    }
}

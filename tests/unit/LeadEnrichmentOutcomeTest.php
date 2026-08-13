<?php
/**
 * Lead enrichment attempt / status persistence tests.
 */

require_once __DIR__ . '/../bootstrap.php';

class LeadEnrichmentOutcomeTest {
    private $db;

    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
    }

    public function runAllTests() {
        echo "Running Lead Enrichment Outcome Tests...\n";
        $this->testInsufficientDataMarksFailed();
        $this->testDisabledServiceMarksFailed();
        echo "All Lead Enrichment Outcome tests completed!\n";
    }

    public function testInsufficientDataMarksFailed() {
        echo "  Testing insufficient data records failed status... ";
        $this->enableRocketReachForTests();

        $contactId = TestUtils::createTestContact([
            'first_name' => 'No',
            'last_name' => 'Data',
            'email' => null,
            'company' => null,
            'linkedin_profile' => null,
            'enrichment_status' => 'pending',
            'enrichment_attempts' => 0,
        ]);

        $service = new LeadEnrichmentService();
        $result = $service->enrichContact($contactId, 'auto');
        $row = $this->db->fetchOne('SELECT enrichment_status, enrichment_attempts, enrichment_error FROM contacts WHERE id = ?', [$contactId]);

        if (($result['outcome'] ?? '') === 'failed'
            && ($row['enrichment_status'] ?? '') === 'failed'
            && (int) ($row['enrichment_attempts'] ?? 0) === 1
            && !empty($row['enrichment_error'])) {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Expected failed status with attempt recorded\n";
    }

    public function testDisabledServiceMarksFailed() {
        echo "  Testing disabled enrichment records failed status... ";
        $this->db->update('settings', ['rocketreach_api_key' => '', 'updated_at' => getCurrentTimestamp()], 'id = 1');

        $contactId = TestUtils::createTestContact([
            'email' => 'disabled-test@example.com',
            'enrichment_status' => 'pending',
            'enrichment_attempts' => 0,
        ]);

        $service = new LeadEnrichmentService();
        $result = $service->enrichContact($contactId, 'auto');
        $row = $this->db->fetchOne('SELECT enrichment_status, enrichment_attempts FROM contacts WHERE id = ?', [$contactId]);

        if (($result['outcome'] ?? '') === 'failed'
            && ($row['enrichment_status'] ?? '') === 'failed'
            && (int) ($row['enrichment_attempts'] ?? 0) === 1) {
            echo "PASS\n";
            return;
        }
        echo "FAIL - Expected failed status when enrichment is disabled\n";
    }

    private function enableRocketReachForTests(): void {
        $this->db->update('settings', [
            'rocketreach_api_key' => 'test_key_for_unit_tests',
            'updated_at' => getCurrentTimestamp(),
        ], 'id = 1');
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new LeadEnrichmentOutcomeTest();
    $test->runAllTests();
}

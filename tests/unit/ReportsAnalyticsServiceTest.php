<?php
/**
 * Unit tests for ReportsAnalyticsService
 */

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/public/includes/ReportsAnalyticsService.php';

class ReportsAnalyticsServiceTest
{
    private $db;
    private ReportsAnalyticsService $svc;

    public function __construct()
    {
        $this->db = TestUtils::getTestDatabase();
        $this->svc = new ReportsAnalyticsService($this->db);
    }

    public function runAllTests()
    {
        echo "Running ReportsAnalyticsService Tests...\n";
        $this->testEmptyRange();
        $this->testSmallPipeline();
        $this->testLargerContactSources();
        $this->testNormalizeRangeSwap();
        $this->testReportTypeDealsOnly();
        echo "All ReportsAnalyticsService tests completed!\n";
    }

    public function testEmptyRange()
    {
        echo "  Testing empty analytics range... ";
        $payload = $this->svc->build('2099-01-01', '2099-01-31', 'all');
        if (
            $payload['metrics']['total_deals'] === 0
            && $payload['empty'] === true
            && isset($payload['analytics'][0]['metric'])
            && count($payload['charts']['deals_by_stage']['values']) === 6
        ) {
            echo "PASS\n";
        } else {
            echo "FAIL\n";
        }
    }

    public function testSmallPipeline()
    {
        echo "  Testing small pipeline aggregation... ";
        $contactId = TestUtils::createTestContact(['source' => 'website']);
        $stamp = date('Y-m-d H:i:s');
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'title' => 'Analytics Small A',
            'stage' => 'negotiation',
            'amount' => 30000,
            'probability' => 90,
        ]);
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'title' => 'Analytics Small B',
            'stage' => 'closed_won',
            'amount' => 6000,
            'probability' => 100,
        ]);
        // Ensure created_at is today for range filter
        $this->db->query("UPDATE deals SET created_at = ? WHERE title LIKE 'Analytics Small%'", [$stamp]);

        $start = date('Y-m-d', strtotime('-1 day'));
        $end = date('Y-m-d', strtotime('+1 day'));
        $payload = $this->svc->build($start, $end, 'all');

        $ok = $payload['metrics']['total_deals'] >= 2
            && $payload['metrics']['total_pipeline_value'] >= 36000
            && $payload['metrics']['win_rate'] > 0
            && $payload['empty'] === false
            && in_array('Deal Created', array_column($payload['activity'], 'action'), true);

        echo $ok ? "PASS\n" : "FAIL\n";
    }

    public function testLargerContactSources()
    {
        echo "  Testing contact source aggregation... ";
        for ($i = 0; $i < 12; $i++) {
            TestUtils::createTestContact([
                'email' => 'analytics-src-' . uniqid('', true) . '@t.com',
                'source' => $i % 2 === 0 ? 'referral' : 'website',
            ]);
        }
        $start = date('Y-m-d', strtotime('-1 day'));
        $end = date('Y-m-d', strtotime('+1 day'));
        $payload = $this->svc->build($start, $end, 'contacts');
        $sum = array_sum($payload['charts']['contact_sources']['values']);
        $ok = $sum >= 12
            && $payload['metrics']['total_deals'] === 0
            && count($payload['charts']['contact_sources']['labels']) >= 1;
        echo $ok ? "PASS\n" : "FAIL\n";
    }

    public function testNormalizeRangeSwap()
    {
        echo "  Testing normalizeRange swap... ";
        [$start, $end] = $this->svc->normalizeRange('2026-07-20', '2026-07-01');
        $ok = $start === '2026-07-01' && str_starts_with($end, '2026-07-20');
        echo $ok ? "PASS\n" : "FAIL\n";
    }

    public function testReportTypeDealsOnly()
    {
        echo "  Testing deals-only report type... ";
        $payload = $this->svc->build(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), 'deals');
        $ok = $payload['range']['report_type'] === 'deals'
            && $payload['charts']['contact_sources']['labels'] === [];
        echo $ok ? "PASS\n" : "FAIL\n";
    }
}

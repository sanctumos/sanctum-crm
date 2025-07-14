<?php
/**
 * Reports Unit Tests
 * FreeOpsDAO CRM - Reports and Analytics Testing
 */

require_once __DIR__ . '/../bootstrap.php';

class ReportsTest {
    private $db;
    
    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
    }
    
    public function runAllTests() {
        echo "Running Reports Unit Tests...\n";
        
        $this->testDealStatistics();
        $this->testContactStatistics();
        $this->testPipelineAnalysis();
        $this->testRevenueTracking();
        $this->testUserActivityTracking();
        $this->testDataFiltering();
        $this->testDataAggregation();
        $this->testExportFunctionality();
        $this->testReportGeneration();
        $this->testChartDataPreparation();
        $this->testDateRangeFiltering();
        $this->testPerformanceMetrics();
        
        echo "All reports tests completed!\n";
    }
    
    public function testDealStatistics() {
        echo "  Testing deal statistics... ";
        
        // Create test deals with different stages and amounts
        $deals = [
            ['stage' => 'prospecting', 'amount' => 1000, 'probability' => 25],
            ['stage' => 'qualification', 'amount' => 2500, 'probability' => 50],
            ['stage' => 'proposal', 'amount' => 5000, 'probability' => 75],
            ['stage' => 'negotiation', 'amount' => 7500, 'probability' => 90],
            ['stage' => 'closed_won', 'amount' => 10000, 'probability' => 100],
            ['stage' => 'closed_lost', 'amount' => 2000, 'probability' => 0]
        ];
        
        $contactId = TestUtils::createTestContact();
        $totalValue = 0;
        $wonValue = 0;
        
        foreach ($deals as $dealData) {
            $dealData['contact_id'] = $contactId;
            TestUtils::createTestDeal($dealData);
            $totalValue += $dealData['amount'];
            if ($dealData['stage'] === 'closed_won') {
                $wonValue += $dealData['amount'];
            }
        }
        
        // Test statistics calculation
        $stats = $this->calculateDealStatistics();
        
        if ($stats['total_deals'] >= 6 && $stats['total_value'] >= $totalValue && $stats['won_value'] >= $wonValue) {
            echo "PASS\n";
        } else {
            echo "FAIL - Deal statistics not calculated correctly\n";
        }
    }
    
    public function testContactStatistics() {
        echo "  Testing contact statistics... ";
        
        // Create test contacts with different types and sources
        $contacts = [
            ['contact_type' => 'lead', 'source' => 'website'],
            ['contact_type' => 'lead', 'source' => 'referral'],
            ['contact_type' => 'customer', 'source' => 'website'],
            ['contact_type' => 'customer', 'source' => 'social_media'],
            ['contact_type' => 'lead', 'source' => 'email_campaign']
        ];
        
        foreach ($contacts as $contactData) {
            TestUtils::createTestContact($contactData);
        }
        
        // Test contact statistics
        $stats = $this->calculateContactStatistics();
        
        if ($stats['total_contacts'] >= 5 && $stats['leads'] >= 3 && $stats['customers'] >= 2) {
            echo "PASS\n";
        } else {
            echo "FAIL - Contact statistics not calculated correctly\n";
        }
    }
    
    public function testPipelineAnalysis() {
        echo "  Testing pipeline analysis... ";
        
        // Create deals in different stages
        $contactId = TestUtils::createTestContact();
        $stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        
        foreach ($stages as $stage) {
            TestUtils::createTestDeal([
                'contact_id' => $contactId,
                'stage' => $stage,
                'amount' => 1000,
                'probability' => $this->getProbabilityForStage($stage)
            ]);
        }
        
        // Test pipeline analysis
        $pipeline = $this->analyzePipeline();
        
        if (count($pipeline) >= 6 && isset($pipeline['prospecting']) && isset($pipeline['closed_won'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - Pipeline analysis not working correctly\n";
        }
    }
    
    public function testRevenueTracking() {
        echo "  Testing revenue tracking... ";
        
        // Create deals with different close dates
        $contactId = TestUtils::createTestContact();
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));
        
        // Current month deals
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'stage' => 'closed_won',
            'amount' => 5000,
            'expected_close_date' => date('Y-m-d')
        ]);
        
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'stage' => 'closed_won',
            'amount' => 3000,
            'expected_close_date' => date('Y-m-d')
        ]);
        
        // Test revenue tracking
        $revenue = $this->trackRevenue();
        
        if ($revenue['current_month'] >= 8000) {
            echo "PASS\n";
        } else {
            echo "FAIL - Revenue tracking not working correctly\n";
        }
    }
    
    public function testUserActivityTracking() {
        echo "  Testing user activity tracking... ";
        
        // Create API requests for different users
        $userId = TestUtils::createTestUser();
        
        for ($i = 0; $i < 5; $i++) {
            TestUtils::createTestApiRequest([
                'user_id' => $userId,
                'endpoint' => '/api/v1/contacts',
                'method' => 'GET',
                'response_code' => 200
            ]);
        }
        
        // Test activity tracking
        $activity = $this->trackUserActivity();
        
        if ($activity['total_requests'] >= 5) {
            echo "PASS\n";
        } else {
            echo "FAIL - User activity tracking not working correctly\n";
        }
    }
    
    public function testDataFiltering() {
        echo "  Testing data filtering... ";
        
        // Create test data with different dates
        $contactId = TestUtils::createTestContact();
        
        // Recent deals
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Old deals
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'created_at' => date('Y-m-d H:i:s', strtotime('-60 days'))
        ]);
        
        // Test date filtering
        $recentDeals = $this->filterDealsByDateRange(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
        $oldDeals = $this->filterDealsByDateRange(date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('-30 days')));
        
        if (count($recentDeals) >= 1 && count($oldDeals) >= 1) {
            echo "PASS\n";
        } else {
            echo "FAIL - Data filtering not working correctly\n";
        }
    }
    
    public function testDataAggregation() {
        echo "  Testing data aggregation... ";
        
        // Create deals with different amounts
        $contactId = TestUtils::createTestContact();
        $amounts = [1000, 2000, 3000, 4000, 5000];
        
        foreach ($amounts as $amount) {
            TestUtils::createTestDeal([
                'contact_id' => $contactId,
                'amount' => $amount
            ]);
        }
        
        // Test aggregation
        $aggregated = $this->aggregateDealData();
        
        if ($aggregated['total_amount'] >= 15000 && $aggregated['average_amount'] >= 3000) {
            echo "PASS\n";
        } else {
            echo "FAIL - Data aggregation not working correctly\n";
        }
    }
    
    public function testExportFunctionality() {
        echo "  Testing export functionality... ";
        
        // Create test data
        $contactId = TestUtils::createTestContact();
        TestUtils::createTestDeal(['contact_id' => $contactId]);
        
        // Test CSV export
        $csvData = $this->exportToCSV('deals');
        
        if (strpos($csvData, 'ID,Title,Contact ID,Amount,Stage') !== false) {
            // Test JSON export
            $jsonData = $this->exportToJSON('deals');
            $decoded = json_decode($jsonData, true);
            
            if ($decoded && isset($decoded['deals'])) {
                echo "PASS\n";
            } else {
                echo "FAIL - JSON export not working correctly\n";
            }
        } else {
            echo "FAIL - CSV export not working correctly\n";
        }
    }
    
    public function testReportGeneration() {
        echo "  Testing report generation... ";
        
        // Create test data
        $contactId = TestUtils::createTestContact();
        TestUtils::createTestDeal(['contact_id' => $contactId]);
        
        // Generate report
        $report = $this->generateReport('all', date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
        
        if ($report && isset($report['summary']) && isset($report['data'])) {
            echo "PASS\n";
        } else {
            echo "FAIL - Report generation not working correctly\n";
        }
    }
    
    public function testChartDataPreparation() {
        echo "  Testing chart data preparation... ";
        
        // Create test data
        $contactId = TestUtils::createTestContact();
        $stages = ['prospecting', 'qualification', 'proposal'];
        
        foreach ($stages as $stage) {
            TestUtils::createTestDeal([
                'contact_id' => $contactId,
                'stage' => $stage
            ]);
        }
        
        // Prepare chart data
        $chartData = $this->prepareChartData();
        
        if (isset($chartData['deals_by_stage']) && count($chartData['deals_by_stage']) >= 3) {
            echo "PASS\n";
        } else {
            echo "FAIL - Chart data preparation not working correctly\n";
        }
    }
    
    public function testDateRangeFiltering() {
        echo "  Testing date range filtering... ";
        
        // Create test data with specific dates
        $contactId = TestUtils::createTestContact();
        
        // Today
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Last week
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'created_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
        ]);
        
        // Last month
        TestUtils::createTestDeal([
            'contact_id' => $contactId,
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
        ]);
        
        // Test different date ranges
        $today = $this->filterByDateRange(date('Y-m-d'), date('Y-m-d'));
        $thisWeek = $this->filterByDateRange(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
        $thisMonth = $this->filterByDateRange(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
        
        if (count($today) >= 1 && count($thisWeek) >= 2 && count($thisMonth) >= 3) {
            echo "PASS\n";
        } else {
            echo "FAIL - Date range filtering not working correctly\n";
        }
    }
    
    public function testPerformanceMetrics() {
        echo "  Testing performance metrics... ";
        
        // Create API requests with different response times
        $userId = TestUtils::createTestUser();
        
        for ($i = 0; $i < 10; $i++) {
            TestUtils::createTestApiRequest([
                'user_id' => $userId,
                'response_time' => rand(10, 100) / 1000, // 10-100ms
                'response_code' => 200
            ]);
        }
        
        // Calculate performance metrics
        $metrics = $this->calculatePerformanceMetrics();
        
        if (isset($metrics['average_response_time']) && $metrics['average_response_time'] > 0) {
            echo "PASS\n";
        } else {
            echo "FAIL - Performance metrics not calculated correctly\n";
        }
    }
    
    // Helper methods
    private function calculateDealStatistics() {
        $stats = [];
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM deals");
        $stats['total_deals'] = $result['count'];
        
        $result = $this->db->fetchOne("SELECT SUM(amount) as total FROM deals WHERE amount IS NOT NULL");
        $stats['total_value'] = $result['total'] ?? 0;
        
        $result = $this->db->fetchOne("SELECT SUM(amount) as total FROM deals WHERE stage = 'closed_won' AND amount IS NOT NULL");
        $stats['won_value'] = $result['total'] ?? 0;
        
        return $stats;
    }
    
    private function calculateContactStatistics() {
        $stats = [];
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts");
        $stats['total_contacts'] = $result['count'];
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'lead'");
        $stats['leads'] = $result['count'];
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'customer'");
        $stats['customers'] = $result['count'];
        
        return $stats;
    }
    
    private function analyzePipeline() {
        $pipeline = [];
        
        $stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        
        foreach ($stages as $stage) {
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) as count, SUM(amount) as value FROM deals WHERE stage = ?",
                [$stage]
            );
            
            $pipeline[$stage] = [
                'count' => $result['count'],
                'value' => $result['value'] ?? 0
            ];
        }
        
        return $pipeline;
    }
    
    private function trackRevenue() {
        $revenue = [];
        
        $currentMonth = date('Y-m');
        $result = $this->db->fetchOne(
            "SELECT SUM(amount) as total FROM deals WHERE stage = 'closed_won' AND strftime('%Y-%m', expected_close_date) = ?",
            [$currentMonth]
        );
        
        $revenue['current_month'] = $result['total'] ?? 0;
        
        return $revenue;
    }
    
    private function trackUserActivity() {
        $activity = [];
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM api_requests");
        $activity['total_requests'] = $result['count'];
        
        return $activity;
    }
    
    private function filterDealsByDateRange($startDate, $endDate) {
        return $this->db->fetchAll(
            "SELECT * FROM deals WHERE created_at BETWEEN ? AND ?",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
    }
    
    private function aggregateDealData() {
        $result = $this->db->fetchOne(
            "SELECT SUM(amount) as total, AVG(amount) as average FROM deals WHERE amount IS NOT NULL"
        );
        
        return [
            'total_amount' => $result['total'] ?? 0,
            'average_amount' => $result['average'] ?? 0
        ];
    }
    
    private function exportToCSV($type) {
        if ($type === 'deals') {
            $deals = $this->db->fetchAll("SELECT * FROM deals");
            
            $csv = "ID,Title,Contact ID,Amount,Stage,Probability,Expected Close Date,Created At\n";
            foreach ($deals as $deal) {
                $csv .= implode(',', [
                    $deal['id'],
                    $deal['title'],
                    $deal['contact_id'],
                    $deal['amount'] ?? '',
                    $deal['stage'],
                    $deal['probability'],
                    $deal['expected_close_date'] ?? '',
                    $deal['created_at']
                ]) . "\n";
            }
            
            return $csv;
        }
        
        return '';
    }
    
    private function exportToJSON($type) {
        if ($type === 'deals') {
            $deals = $this->db->fetchAll("SELECT * FROM deals");
            return json_encode(['deals' => $deals]);
        }
        
        return '{}';
    }
    
    private function generateReport($type, $startDate, $endDate) {
        return [
            'summary' => [
                'type' => $type,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => $this->calculateDealStatistics()
        ];
    }
    
    private function prepareChartData() {
        $chartData = [];
        
        // Deals by stage
        $stages = $this->db->fetchAll(
            "SELECT stage, COUNT(*) as count FROM deals GROUP BY stage"
        );
        
        $chartData['deals_by_stage'] = $stages;
        
        return $chartData;
    }
    
    private function filterByDateRange($startDate, $endDate) {
        return $this->db->fetchAll(
            "SELECT * FROM deals WHERE created_at BETWEEN ? AND ?",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
    }
    
    private function calculatePerformanceMetrics() {
        $result = $this->db->fetchOne(
            "SELECT AVG(response_time) as avg_time, COUNT(*) as total_requests FROM api_requests"
        );
        
        return [
            'average_response_time' => $result['avg_time'] ?? 0,
            'total_requests' => $result['total_requests'] ?? 0
        ];
    }
    
    private function getProbabilityForStage($stage) {
        $probabilities = [
            'prospecting' => 25,
            'qualification' => 50,
            'proposal' => 75,
            'negotiation' => 90,
            'closed_won' => 100,
            'closed_lost' => 0
        ];
        
        return $probabilities[$stage] ?? 0;
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ReportsTest();
    $test->runAllTests();
} 
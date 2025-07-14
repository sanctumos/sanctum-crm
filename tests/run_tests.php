<?php
/**
 * Test Runner
 * FreeOpsDAO CRM - Complete Test Suite Runner
 */

require_once __DIR__ . '/bootstrap.php';

class TestRunner {
    private $results = [];
    private $startTime;
    
    public function runAllTests() {
        $this->startTime = microtime(true);
        
        echo "==========================================\n";
        echo "FreeOpsDAO CRM - Complete Test Suite\n";
        echo "==========================================\n\n";
        
        // Run unit tests
        $this->runUnitTests();
        
        // Run API tests
        $this->runApiTests();
        
        // Run integration tests
        $this->runIntegrationTests();
        
        // Generate report
        $this->generateReport();
    }
    
    private function runUnitTests() {
        echo "UNIT TESTS\n";
        echo "==========\n";
        
        // Database tests
        echo "\nDatabase Tests:\n";
        ob_start();
        include __DIR__ . '/unit/DatabaseTest.php';
        $output = ob_get_clean();
        $this->results['database'] = $this->parseTestOutput($output);
        echo $output;
        
        // Authentication tests
        echo "\nAuthentication Tests:\n";
        ob_start();
        include __DIR__ . '/unit/AuthTest.php';
        $output = ob_get_clean();
        $this->results['auth'] = $this->parseTestOutput($output);
        echo $output;
    }
    
    private function runApiTests() {
        echo "\nAPI INTEGRATION TESTS\n";
        echo "====================\n";
        
        // Check if server is running
        if (!$this->isServerRunning()) {
            echo "\nWARNING: Server not running. Start with: php -S localhost:8000\n";
            echo "Skipping API tests...\n";
            $this->results['api'] = ['status' => 'skipped', 'message' => 'Server not running'];
            return;
        }
        
        echo "\nAPI Tests:\n";
        ob_start();
        include __DIR__ . '/api/ApiTest.php';
        $output = ob_get_clean();
        $this->results['api'] = $this->parseTestOutput($output);
        echo $output;
    }
    
    private function runIntegrationTests() {
        echo "\nINTEGRATION TESTS\n";
        echo "================\n";
        
        echo "\nSystem Integration Tests:\n";
        $this->testSystemIntegration();
    }
    
    private function testSystemIntegration() {
        echo "  Testing complete workflow... ";
        
        try {
            $db = TestUtils::getTestDatabase();
            $auth = new Auth();
            
            // Test complete user -> contact -> deal workflow
            $user = TestUtils::createTestUser();
            $contact = TestUtils::createTestContact();
            $deal = TestUtils::createTestDeal(['contact_id' => $contact]);
            
            // Verify relationships
            $dealData = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$deal]);
            $contactData = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$dealData['contact_id']]);
            
            if ($dealData && $contactData && $dealData['contact_id'] == $contact) {
                echo "PASS\n";
                $this->results['integration'] = ['status' => 'pass', 'tests' => 1, 'passed' => 1];
            } else {
                echo "FAIL - Relationship integrity issue\n";
                $this->results['integration'] = ['status' => 'fail', 'tests' => 1, 'passed' => 0];
            }
            
            // Clean up
            $db->delete('deals', 'id = ?', [$deal]);
            $db->delete('contacts', 'id = ?', [$contact]);
            $db->delete('users', 'id = ?', [$user['id']]);
            
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
            $this->results['integration'] = ['status' => 'fail', 'tests' => 1, 'passed' => 0];
        }
    }
    
    private function isServerRunning() {
        // Check if curl is available
        if (!function_exists('curl_init')) {
            return false;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode > 0;
    }
    
    private function parseTestOutput($output) {
        $lines = explode("\n", $output);
        $tests = 0;
        $passed = 0;
        $failed = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'PASS') !== false) {
                $tests++;
                $passed++;
            } elseif (strpos($line, 'FAIL') !== false) {
                $tests++;
                $failed++;
            }
        }
        
        return [
            'status' => $failed > 0 ? 'fail' : 'pass',
            'tests' => $tests,
            'passed' => $passed,
            'failed' => $failed
        ];
    }
    
    private function generateReport() {
        $endTime = microtime(true);
        $duration = round($endTime - $this->startTime, 2);
        
        echo "\n==========================================\n";
        echo "TEST SUMMARY\n";
        echo "==========================================\n";
        
        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        
        foreach ($this->results as $suite => $result) {
            if ($result['status'] === 'skipped') {
                echo "\n{$suite}: SKIPPED - {$result['message']}\n";
                continue;
            }
            
            $tests = $result['tests'];
            $passed = $result['passed'];
            $failed = $result['failed'];
            $status = $result['status'] === 'pass' ? 'PASS' : 'FAIL';
            
            echo "\n{$suite}: {$status} ({$passed}/{$tests} passed)\n";
            
            $totalTests += $tests;
            $totalPassed += $passed;
            $totalFailed += $failed;
        }
        
        echo "\n==========================================\n";
        echo "OVERALL RESULTS\n";
        echo "==========================================\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Passed: {$totalPassed}\n";
        echo "Failed: {$totalFailed}\n";
        echo "Success Rate: " . ($totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 1) : 0) . "%\n";
        echo "Duration: {$duration}s\n";
        
        if ($totalFailed === 0) {
            echo "\n🎉 ALL TESTS PASSED! 🎉\n";
        } else {
            echo "\n❌ {$totalFailed} TEST(S) FAILED ❌\n";
        }
        
        echo "\n==========================================\n";
        
        // Save results to file
        $this->saveResults();
    }
    
    private function saveResults() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'duration' => round(microtime(true) - $this->startTime, 2),
            'results' => $this->results
        ];
        
        $resultsFile = __DIR__ . '/test_results.json';
        file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT));
        
        echo "Results saved to: {$resultsFile}\n";
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $runner = new TestRunner();
    $runner->runAllTests();
} else {
    // Web interface
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CRM Test Suite</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container mt-4">
            <h1>FreeOpsDAO CRM - Test Suite</h1>
            <p>Click the button below to run all tests:</p>
            <a href="?run=1" class="btn btn-primary">Run All Tests</a>
            
            <?php if (isset($_GET['run'])): ?>
            <div class="mt-4">
                <h3>Test Results:</h3>
                <pre><?php
                    $runner = new TestRunner();
                    $runner->runAllTests();
                ?></pre>
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
} 
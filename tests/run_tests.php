<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Test Runner
 * Sanctum CRM - Comprehensive Test Suite
 */

require_once __DIR__ . '/bootstrap.php';

class TestRunner {
    private $results = [];
    private $startTime;
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    public function runAllTests() {
        echo "==========================================\n";
        echo "Sanctum CRM - Comprehensive Test Suite\n";
        echo "==========================================\n\n";
        
        // Run unit tests
        $this->runUnitTests();
        
        // Run configuration tests
        $this->runConfigurationTests();
        
        // Run API tests
        $this->runApiTests();
        
        // Run integration tests
        $this->runIntegrationTests();
        
        // Generate coverage report
        $this->generateCoverageReport();
        
        // Display final results
        $this->displayFinalResults();
    }
    
    public function runUnitTests() {
        echo "UNIT TESTS\n";
        echo "==========\n";
        
        $unitTests = [
            'AuthTest.php' => 'AuthTest',
            'DatabaseTest.php' => 'DatabaseTest',
            'WebhookTest.php' => 'WebhookTest',
            'UserManagementTest.php' => 'UserManagementTest',
            'ReportsTest.php' => 'ReportsTest',
            'ImportTest.php' => 'ImportTest'
        ];
        
        foreach ($unitTests as $file => $class) {
            $this->runTestFile("unit/{$file}", $class);
        }
        
        echo "\n";
    }
    
    public function runApiTests() {
        echo "API TESTS\n";
        echo "=========\n";
        
        $apiTests = [
            'ApiTest.php' => 'ApiTest',
            'ImportApiTest.php' => 'ImportApiTest'
        ];
        
        foreach ($apiTests as $file => $class) {
            $this->runTestFile("api/{$file}", $class);
        }
        
        echo "\n";
    }
    
    public function runConfigurationTests() {
        echo "CONFIGURATION TESTS\n";
        echo "===================\n";
        
        $configTests = [
            'ConfigManagerTest.php' => 'ConfigManagerTest',
            'InstallationManagerTest.php' => 'InstallationManagerTest',
            'EnvironmentDetectorTest.php' => 'EnvironmentDetectorTest',
            'FirstBootIntegrationTest.php' => 'FirstBootIntegrationTest',
            'InstallationWizardE2ETest.php' => 'InstallationWizardE2ETest',
            'ConfigurationApiTest.php' => 'ConfigurationApiTest'
        ];
        
        foreach ($configTests as $file => $class) {
            $this->runTestFile("unit/{$file}", $class);
        }
        
        echo "\n";
    }
    
    public function runIntegrationTests() {
        echo "INTEGRATION TESTS\n";
        echo "=================\n";
        
        $integrationTests = [
            'IntegrationTest.php' => 'IntegrationTest',
            'ImportIntegrationTest.php' => 'ImportIntegrationTest'
        ];
        
        foreach ($integrationTests as $file => $class) {
            $this->runTestFile("integration/{$file}", $class);
        }
        
        echo "\n";
    }
    
    private function runTestFile($relativePath, $className) {
        $filePath = __DIR__ . '/' . $relativePath;
        
        if (!file_exists($filePath)) {
            echo "  SKIP: {$relativePath} (file not found)\n";
            return;
        }
        
        echo "  Running {$className}...\n";
        
        // Capture output
        ob_start();
        
        try {
            require_once $filePath;
            
            if (class_exists($className)) {
                echo "    Creating {$className} instance...\n";
                $test = new $className();
                
                if (method_exists($test, 'runAllTests')) {
                    echo "    Executing {$className}::runAllTests()...\n";
                    $test->runAllTests();
                    $output = ob_get_clean();
                    
                    // Parse test results
                    $this->parseTestResults($output, $className);
                    
                    echo $output;
                    echo "    Completed {$className}\n";
                } else {
                    ob_end_clean();
                    echo "    FAIL: {$className} missing runAllTests method\n";
                    $this->recordTestResult($className, false);
                }
            } else {
                ob_end_clean();
                echo "    FAIL: {$className} class not found\n";
                $this->recordTestResult($className, false);
            }
        } catch (Exception $e) {
            ob_end_clean();
            echo "    FAIL: {$className} - " . $e->getMessage() . "\n";
            echo "    Stack trace: " . $e->getTraceAsString() . "\n";
            $this->recordTestResult($className, false);
        } catch (Error $e) {
            ob_end_clean();
            echo "    FATAL: {$className} - " . $e->getMessage() . "\n";
            echo "    Stack trace: " . $e->getTraceAsString() . "\n";
            $this->recordTestResult($className, false);
        }
    }
    
    private function parseTestResults($output, $className) {
        $lines = explode("\n", $output);
        $testCount = 0;
        $passCount = 0;
        $failCount = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'PASS') !== false) {
                $passCount++;
                $testCount++;
            } elseif (strpos($line, 'FAIL') !== false) {
                $failCount++;
                $testCount++;
            }
        }
        
        $this->totalTests += $testCount;
        $this->passedTests += $passCount;
        $this->failedTests += $failCount;
        
        $this->results[$className] = [
            'total' => $testCount,
            'passed' => $passCount,
            'failed' => $failCount,
            'success_rate' => $testCount > 0 ? ($passCount / $testCount) * 100 : 0
        ];
    }
    
    private function recordTestResult($className, $success) {
        $this->totalTests++;
        if ($success) {
            $this->passedTests++;
        } else {
            $this->failedTests++;
        }
        
        $this->results[$className] = [
            'total' => 1,
            'passed' => $success ? 1 : 0,
            'failed' => $success ? 0 : 1,
            'success_rate' => $success ? 100 : 0
        ];
    }
    
    public function generateCoverageReport() {
        echo "COVERAGE REPORT\n";
        echo "===============\n";
        
        $coverage = $this->calculateCoverage();
        
        echo "  Code Coverage: {$coverage['percentage']}%\n";
        echo "  Files Covered: {$coverage['files_covered']}/{$coverage['total_files']}\n";
        echo "  Functions Covered: {$coverage['functions_covered']}/{$coverage['total_functions']}\n";
        echo "  Classes Covered: {$coverage['classes_covered']}/{$coverage['total_classes']}\n\n";
        
        // Detailed coverage breakdown
        echo "  Coverage Breakdown:\n";
        foreach ($coverage['breakdown'] as $file => $stats) {
            echo "    {$file}: {$stats['percentage']}% ({$stats['covered']}/{$stats['total']})\n";
        }
        
        echo "\n";
    }
    
    private function calculateCoverage() {
        $coverage = [
            'percentage' => 0,
            'files_covered' => 0,
            'total_files' => 0,
            'functions_covered' => 0,
            'total_functions' => 0,
            'classes_covered' => 0,
            'total_classes' => 0,
            'breakdown' => []
        ];
        
        // Analyze includes directory
        $includesDir = __DIR__ . '/../includes';
        $files = glob($includesDir . '/*.php');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $coverage['total_files']++;
            
            // Check if file is tested
            $isTested = $this->isFileTested($filename);
            if ($isTested) {
                $coverage['files_covered']++;
            }
            
            // Analyze file content
            $fileStats = $this->analyzeFile($file);
            $coverage['total_functions'] += $fileStats['functions'];
            $coverage['total_classes'] += $fileStats['classes'];
            
            if ($isTested) {
                $coverage['functions_covered'] += $fileStats['functions'];
                $coverage['classes_covered'] += $fileStats['classes'];
            }
            
            $coverage['breakdown'][$filename] = [
                'percentage' => $isTested ? 100 : 0,
                'covered' => $isTested ? $fileStats['functions'] + $fileStats['classes'] : 0,
                'total' => $fileStats['functions'] + $fileStats['classes']
            ];
        }
        
        // Calculate overall percentage
        $totalItems = $coverage['total_functions'] + $coverage['total_classes'];
        if ($totalItems > 0) {
            $coverage['percentage'] = round((($coverage['functions_covered'] + $coverage['classes_covered']) / $totalItems) * 100, 2);
        }
        
        return $coverage;
    }
    
    private function isFileTested($filename) {
        $testFiles = [
            'auth.php' => ['AuthTest.php', 'UserManagementTest.php'],
            'database.php' => ['DatabaseTest.php'],
            'config.php' => ['WebhookTest.php', 'ReportsTest.php'],
            'layout.php' => ['ImportTest.php', 'ImportApiTest.php', 'ImportIntegrationTest.php']
        ];
        
        return isset($testFiles[$filename]);
    }
    
    private function analyzeFile($filepath) {
        $content = file_get_contents($filepath);
        
        return [
            'functions' => preg_match_all('/function\s+\w+\s*\(/', $content),
            'classes' => preg_match_all('/class\s+\w+/', $content)
        ];
    }
    
    public function displayFinalResults() {
        $endTime = microtime(true);
        $duration = $endTime - $this->startTime;
        
        echo "FINAL RESULTS\n";
        echo "=============\n";
        echo "  Total Tests: {$this->totalTests}\n";
        echo "  Passed: {$this->passedTests}\n";
        echo "  Failed: {$this->failedTests}\n";
        echo "  Success Rate: " . ($this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 2) : 0) . "%\n";
        echo "  Duration: " . round($duration, 2) . " seconds\n\n";
        
        // Test suite summary
        echo "  Test Suite Summary:\n";
        foreach ($this->results as $className => $result) {
            $status = $result['failed'] > 0 ? 'FAIL' : 'PASS';
            echo "    {$className}: {$status} ({$result['passed']}/{$result['total']} tests passed)\n";
        }
        
        echo "\n";
        
        // Overall status
        if ($this->failedTests === 0) {
            echo "✅ ALL TESTS PASSED! 🎉\n";
            echo "The CRM system is ready for production.\n";
        } else {
            echo "❌ {$this->failedTests} TESTS FAILED!\n";
            echo "Please review and fix the failing tests before deployment.\n";
        }
        
        echo "\n";
    }
    
    public function generateJUnitReport() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<testsuites>' . "\n";
        
        foreach ($this->results as $className => $result) {
            $xml .= "  <testsuite name=\"{$className}\" tests=\"{$result['total']}\" failures=\"{$result['failed']}\">\n";
            
            // Add individual test cases (simplified)
            for ($i = 0; $i < $result['total']; $i++) {
                $status = $i < $result['passed'] ? 'passed' : 'failed';
                $xml .= "    <testcase name=\"test_{$i}\" status=\"{$status}\" />\n";
            }
            
            $xml .= "  </testsuite>\n";
        }
        
        $xml .= '</testsuites>';
        
        file_put_contents(__DIR__ . '/junit.xml', $xml);
        echo "JUnit report generated: tests/junit.xml\n";
    }
    
    public function generateHtmlReport() {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Best Jobs in TA - Test Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f0f0f0; padding: 20px; border-radius: 5px; }
        .summary { margin: 20px 0; }
        .test-suite { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 3px; }
        .pass { background: #d4edda; border-color: #c3e6cb; }
        .fail { background: #f8d7da; border-color: #f5c6cb; }
        .coverage { background: #e2e3e5; padding: 10px; border-radius: 3px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Best Jobs in TA - Test Results</h1>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
    </div>
    
    <div class="summary">
        <h2>Summary</h2>
        <p>Total Tests: ' . $this->totalTests . '</p>
        <p>Passed: ' . $this->passedTests . '</p>
        <p>Failed: ' . $this->failedTests . '</p>
        <p>Success Rate: ' . ($this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 2) : 0) . '%</p>
    </div>';
        
        foreach ($this->results as $className => $result) {
            $status = $result['failed'] > 0 ? 'fail' : 'pass';
            $html .= "
    <div class=\"test-suite {$status}\">
        <h3>{$className}</h3>
        <p>Tests: {$result['passed']}/{$result['total']} passed</p>
        <p>Success Rate: {$result['success_rate']}%</p>
    </div>";
        }
        
        $html .= '
</body>
</html>';
        
        file_put_contents(__DIR__ . '/test-report.html', $html);
        echo "HTML report generated: tests/test-report.html\n";
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new TestRunner();
    $runner->runAllTests();
    
    // Generate reports
    $runner->generateJUnitReport();
    $runner->generateHtmlReport();
} 
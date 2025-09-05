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
 * Configuration Tests Runner
 * Runs all tests for the first boot configuration system
 */

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\TestRunner;

class ConfigurationTestRunner {
    private $testResults = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    
    public function runAllTests() {
        echo "🧪 Running Sanctum CRM Configuration Tests\n";
        echo "==========================================\n\n";
        
        $this->runUnitTests();
        $this->runIntegrationTests();
        $this->runE2ETests();
        $this->runApiTests();
        
        $this->printSummary();
    }
    
    private function runUnitTests() {
        echo "📋 Unit Tests\n";
        echo "-------------\n";
        
        $unitTests = [
            'ConfigManagerTest' => 'tests/unit/ConfigManagerTest.php',
            'InstallationManagerTest' => 'tests/unit/InstallationManagerTest.php',
            'EnvironmentDetectorTest' => 'tests/unit/EnvironmentDetectorTest.php'
        ];
        
        foreach ($unitTests as $testName => $testFile) {
            $this->runTestFile($testName, $testFile, 'unit');
        }
        
        echo "\n";
    }
    
    private function runIntegrationTests() {
        echo "🔗 Integration Tests\n";
        echo "--------------------\n";
        
        $integrationTests = [
            'FirstBootIntegrationTest' => 'tests/integration/FirstBootIntegrationTest.php'
        ];
        
        foreach ($integrationTests as $testName => $testFile) {
            $this->runTestFile($testName, $testFile, 'integration');
        }
        
        echo "\n";
    }
    
    private function runE2ETests() {
        echo "🌐 End-to-End Tests\n";
        echo "-------------------\n";
        
        $e2eTests = [
            'InstallationWizardE2ETest' => 'tests/e2e/InstallationWizardE2ETest.php'
        ];
        
        foreach ($e2eTests as $testName => $testFile) {
            $this->runTestFile($testName, $testFile, 'e2e');
        }
        
        echo "\n";
    }
    
    private function runApiTests() {
        echo "🔌 API Tests\n";
        echo "------------\n";
        
        $apiTests = [
            'ConfigurationApiTest' => 'tests/api/ConfigurationApiTest.php'
        ];
        
        foreach ($apiTests as $testName => $testFile) {
            $this->runTestFile($testName, $testFile, 'api');
        }
        
        echo "\n";
    }
    
    private function runTestFile($testName, $testFile, $category) {
        if (!file_exists($testFile)) {
            echo "❌ $testName: File not found ($testFile)\n";
            $this->failedTests++;
            $this->totalTests++;
            return;
        }
        
        try {
            // Use PHPUnit to run the test
            $command = "php vendor/bin/phpunit $testFile --no-coverage --colors=never";
            $output = [];
            $returnCode = 0;
            
            exec($command, $output, $returnCode);
            
            $this->totalTests++;
            
            if ($returnCode === 0) {
                echo "✅ $testName: PASSED\n";
                $this->passedTests++;
                $this->testResults[$category][] = [
                    'name' => $testName,
                    'status' => 'PASSED',
                    'output' => implode("\n", $output)
                ];
            } else {
                echo "❌ $testName: FAILED\n";
                $this->failedTests++;
                $this->testResults[$category][] = [
                    'name' => $testName,
                    'status' => 'FAILED',
                    'output' => implode("\n", $output)
                ];
                
                // Print first few lines of output for debugging
                $outputLines = array_slice($output, 0, 5);
                foreach ($outputLines as $line) {
                    if (trim($line)) {
                        echo "   $line\n";
                    }
                }
                if (count($output) > 5) {
                    echo "   ... (truncated)\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ $testName: ERROR - " . $e->getMessage() . "\n";
            $this->failedTests++;
            $this->totalTests++;
            $this->testResults[$category][] = [
                'name' => $testName,
                'status' => 'ERROR',
                'output' => $e->getMessage()
            ];
        }
    }
    
    private function printSummary() {
        echo "📊 Test Summary\n";
        echo "===============\n";
        echo "Total Tests: $this->totalTests\n";
        echo "Passed: $this->passedTests\n";
        echo "Failed: $this->failedTests\n";
        echo "Success Rate: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n\n";
        
        if ($this->failedTests > 0) {
            echo "❌ Failed Tests:\n";
            foreach ($this->testResults as $category => $tests) {
                foreach ($tests as $test) {
                    if ($test['status'] !== 'PASSED') {
                        echo "   $category/{$test['name']}: {$test['status']}\n";
                    }
                }
            }
        } else {
            echo "🎉 All tests passed!\n";
        }
        
        echo "\n";
        
        // Print detailed results by category
        $this->printDetailedResults();
    }
    
    private function printDetailedResults() {
        echo "📋 Detailed Results\n";
        echo "==================\n";
        
        foreach ($this->testResults as $category => $tests) {
            echo "\n$category Tests:\n";
            foreach ($tests as $test) {
                $status = $test['status'] === 'PASSED' ? '✅' : '❌';
                echo "  $status {$test['name']}\n";
            }
        }
    }
    
    public function generateTestReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_tests' => $this->totalTests,
            'passed_tests' => $this->passedTests,
            'failed_tests' => $this->failedTests,
            'success_rate' => round(($this->passedTests / $this->totalTests) * 100, 2),
            'test_results' => $this->testResults
        ];
        
        $reportFile = __DIR__ . '/test-report-configuration.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "📄 Test report saved to: $reportFile\n";
    }
}

// Run the tests
$runner = new ConfigurationTestRunner();
$runner->runAllTests();
$runner->generateTestReport();

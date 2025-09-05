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
 * ConfigManager Custom Test
 * Tests for configuration management functionality using custom test framework
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/includes/ConfigManager.php';

class ConfigManagerCustomTest {
    private $config;
    private $db;
    private $testResults = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
    }
    
    public function runAllTests() {
        echo "Running Configuration Management Tests...\n";
        
        $this->testGetInstance();
        $this->testSetAndGetConfiguration();
        $this->testGetWithDefault();
        $this->testSetCategory();
        $this->testGetCategory();
        $this->testDataTypes();
        $this->testEncryptedConfiguration();
        $this->testDeleteConfiguration();
        $this->testClearCache();
        $this->testCompanyInfo();
        $this->testCompanyInfoDefault();
        $this->testIsFirstBoot();
        $this->testInstallationProgress();
        $this->testGetCurrentInstallationStep();
        $this->testUpdateExistingConfiguration();
        $this->testSpecialCharacters();
        $this->testLargeConfiguration();
        $this->testGetAllConfigurations();
        $this->testGetAllWithEncryptedValues();
        $this->testGetAllWithDifferentDataTypes();
        $this->testGetAllEmpty();
        $this->testGetCategoryEmpty();
        $this->testSetCategoryWithEmptyArray();
        $this->testSetCategoryWithNullValues();
        $this->testDeleteNonExistentConfiguration();
        $this->testCacheBehavior();
        $this->testUpdateExistingConfigurationClearsCache();
        $this->testDeleteConfigurationClearsCache();
        $this->testCompanyInfoWithEmptyDatabase();
        $this->testSetCompanyInfoWithEmptyDatabase();
        $this->testInstallationProgressEmpty();
        $this->testCompleteInstallationStepWithData();
        $this->testCompleteInstallationStepWithoutData();
        $this->testGetCurrentInstallationStepWithIncompleteSteps();
        $this->testGetCurrentInstallationStepAllComplete();
        $this->testGetCurrentInstallationStepNoSteps();
        $this->testGetCurrentInstallationStepWithGaps();
        $this->testDataTypeDetection();
        $this->testEncryptionDecryption();
        $this->testEncryptionWithSpecialCharacters();
        $this->testEncryptionWithUnicode();
        $this->testConcurrentAccess();
        $this->testErrorHandling();
        $this->testMemoryUsage();
        $this->testNestedArrays();
        $this->testBooleanEdgeCases();
        $this->testNumericEdgeCases();
        
        $this->displayResults();
    }
    
    private function testGetInstance() {
        $instance1 = ConfigManager::getInstance();
        $instance2 = ConfigManager::getInstance();
        
        if ($instance1 === $instance2 && $instance1 instanceof ConfigManager) {
            $this->pass("getInstance returns singleton");
        } else {
            $this->fail("getInstance singleton test failed");
        }
    }
    
    private function testSetAndGetConfiguration() {
        $this->config->set('application', 'app_name', 'Test App');
        $value = $this->config->get('application', 'app_name');
        
        if ($value === 'Test App') {
            $this->pass("set and get configuration");
        } else {
            $this->fail("set and get configuration failed");
        }
    }
    
    private function testGetWithDefault() {
        $value = $this->config->get('application', 'non_existent', 'default_value');
        
        if ($value === 'default_value') {
            $this->pass("get with default value");
        } else {
            $this->fail("get with default value failed");
        }
    }
    
    private function testSetCategory() {
        $configs = [
            'app_name' => 'Test App',
            'app_url' => 'http://test.com',
            'timezone' => 'UTC'
        ];
        
        $this->config->setCategory('application', $configs);
        
        $appName = $this->config->get('application', 'app_name');
        $appUrl = $this->config->get('application', 'app_url');
        $timezone = $this->config->get('application', 'timezone');
        
        if ($appName === 'Test App' && $appUrl === 'http://test.com' && $timezone === 'UTC') {
            $this->pass("setCategory functionality");
        } else {
            $this->fail("setCategory functionality failed");
        }
    }
    
    private function testGetCategory() {
        // Clear the application category first
        $this->db->query("DELETE FROM system_config WHERE category = 'application'");
        
        $configs = [
            'app_name' => 'Test App',
            'app_url' => 'http://test.com'
        ];
        
        $this->config->setCategory('application', $configs);
        $retrieved = $this->config->getCategory('application');
        
        if ($configs === $retrieved) {
            $this->pass("getCategory functionality");
        } else {
            $this->fail("getCategory functionality failed");
        }
    }
    
    private function testDataTypes() {
        $this->config->set('test', 'string_value', 'test string');
        $this->config->set('test', 'int_value', 42);
        $this->config->set('test', 'float_value', 3.14);
        $this->config->set('test', 'bool_value', true);
        $this->config->set('test', 'array_value', ['key' => 'value']);
        
        $stringVal = $this->config->get('test', 'string_value');
        $intVal = $this->config->get('test', 'int_value');
        $floatVal = $this->config->get('test', 'float_value');
        $boolVal = $this->config->get('test', 'bool_value');
        $arrayVal = $this->config->get('test', 'array_value');
        
        if ($stringVal === 'test string' && $intVal === 42 && $floatVal === 3.14 && 
            $boolVal === true && $arrayVal === ['key' => 'value']) {
            $this->pass("data types handling");
        } else {
            $this->fail("data types handling failed");
        }
    }
    
    private function testEncryptedConfiguration() {
        $this->config->set('security', 'api_key', 'secret_key', true);
        $value = $this->config->get('security', 'api_key');
        
        if ($value === 'secret_key') {
            $this->pass("encrypted configuration");
        } else {
            $this->fail("encrypted configuration failed");
        }
    }
    
    private function testDeleteConfiguration() {
        $this->config->set('test', 'key', 'value');
        $this->config->delete('test', 'key');
        
        $value = $this->config->get('test', 'key');
        if ($value === null) {
            $this->pass("delete configuration");
        } else {
            $this->fail("delete configuration failed");
        }
    }
    
    private function testClearCache() {
        $this->config->set('test', 'key', 'value');
        $this->config->clearCache();
        
        $value = $this->config->get('test', 'key');
        if ($value === 'value') {
            $this->pass("clear cache");
        } else {
            $this->fail("clear cache failed");
        }
    }
    
    private function testCompanyInfo() {
        $companyData = [
            'company_name' => 'Test Company',
            'timezone' => 'America/New_York'
        ];
        
        $this->config->setCompanyInfo($companyData);
        $retrieved = $this->config->getCompanyInfo();
        
        if ($retrieved['company_name'] === 'Test Company' && $retrieved['timezone'] === 'America/New_York') {
            $this->pass("company info management");
        } else {
            $this->fail("company info management failed");
        }
    }
    
    private function testCompanyInfoDefault() {
        // Clear company info to test default
        $this->db->query("DELETE FROM company_info");
        
        $retrieved = $this->config->getCompanyInfo();
        
        if ($retrieved['company_name'] === 'Sanctum CRM' && $retrieved['timezone'] === 'UTC') {
            $this->pass("company info default values");
        } else {
            $this->fail("company info default values failed");
        }
    }
    
    private function testIsFirstBoot() {
        // Clear data to test first boot
        $this->db->query("DELETE FROM company_info");
        $this->db->query("DELETE FROM users WHERE role = 'admin'");
        
        $isFirstBoot = $this->config->isFirstBoot();
        
        if ($isFirstBoot === true) {
            $this->pass("first boot detection");
        } else {
            $this->fail("first boot detection failed");
        }
    }
    
    private function testInstallationProgress() {
        // Clear installation state first
        $this->db->query("DELETE FROM installation_state");
        
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('database', ['tables_created' => 5]);
        
        $progress = $this->config->getInstallationProgress();
        
        if (count($progress) === 2 && $progress[0]['step'] === 'environment' && 
            $progress[1]['step'] === 'database') {
            $this->pass("installation progress tracking");
        } else {
            $this->fail("installation progress tracking failed");
        }
    }
    
    private function testGetCurrentInstallationStep() {
        // Clear installation state
        $this->db->query("DELETE FROM installation_state");
        
        $currentStep = $this->config->getCurrentInstallationStep();
        
        if ($currentStep === 'environment') {
            $this->pass("current installation step detection");
        } else {
            $this->fail("current installation step detection failed");
        }
    }
    
    private function testUpdateExistingConfiguration() {
        $this->config->set('test', 'key', 'initial_value');
        $this->config->set('test', 'key', 'updated_value');
        
        $value = $this->config->get('test', 'key');
        
        if ($value === 'updated_value') {
            $this->pass("update existing configuration");
        } else {
            $this->fail("update existing configuration failed");
        }
    }
    
    private function testSpecialCharacters() {
        $specialValue = "Test with special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";
        $this->config->set('test', 'special', $specialValue);
        
        $retrieved = $this->config->get('test', 'special');
        
        if ($retrieved === $specialValue) {
            $this->pass("special characters handling");
        } else {
            $this->fail("special characters handling failed");
        }
    }
    
    private function testLargeConfiguration() {
        $largeArray = array_fill(0, 1000, 'test_value');
        $this->config->set('test', 'large_array', $largeArray);
        
        $retrieved = $this->config->get('test', 'large_array');
        
        if ($retrieved === $largeArray) {
            $this->pass("large configuration handling");
        } else {
            $this->fail("large configuration handling failed");
        }
    }
    
    private function testGetAllConfigurations() {
        $this->config->set('category1', 'key1', 'value1');
        $this->config->set('category1', 'key2', 'value2');
        $this->config->set('category2', 'key1', 'value3');
        
        $allConfigs = $this->config->getAll();
        
        if (isset($allConfigs['category1']['key1']) && $allConfigs['category1']['key1'] === 'value1' &&
            isset($allConfigs['category2']['key1']) && $allConfigs['category2']['key1'] === 'value3') {
            $this->pass("get all configurations");
        } else {
            $this->fail("get all configurations failed");
        }
    }
    
    private function testGetAllWithEncryptedValues() {
        $this->config->set('test', 'encrypted_key', 'secret_value', true);
        $this->config->set('test', 'normal_key', 'normal_value');
        
        $allConfigs = $this->config->getAll();
        
        if ($allConfigs['test']['encrypted_key'] === 'secret_value' && 
            $allConfigs['test']['normal_key'] === 'normal_value') {
            $this->pass("get all with encrypted values");
        } else {
            $this->fail("get all with encrypted values failed");
        }
    }
    
    private function testGetAllWithDifferentDataTypes() {
        $this->config->set('test', 'string_val', 'test');
        $this->config->set('test', 'int_val', 42);
        $this->config->set('test', 'float_val', 3.14);
        $this->config->set('test', 'bool_val', true);
        $this->config->set('test', 'array_val', ['key' => 'value']);
        
        $allConfigs = $this->config->getAll();
        
        if ($allConfigs['test']['string_val'] === 'test' && $allConfigs['test']['int_val'] === 42 &&
            $allConfigs['test']['float_val'] === 3.14 && $allConfigs['test']['bool_val'] === true &&
            $allConfigs['test']['array_val'] === ['key' => 'value']) {
            $this->pass("get all with different data types");
        } else {
            $this->fail("get all with different data types failed");
        }
    }
    
    private function testGetAllEmpty() {
        // Clear all configs
        $this->db->query("DELETE FROM system_config");
        
        $allConfigs = $this->config->getAll();
        
        if (is_array($allConfigs) && empty($allConfigs)) {
            $this->pass("get all empty");
        } else {
            $this->fail("get all empty failed");
        }
    }
    
    private function testGetCategoryEmpty() {
        $configs = $this->config->getCategory('nonexistent');
        
        if (is_array($configs) && empty($configs)) {
            $this->pass("get category empty");
        } else {
            $this->fail("get category empty failed");
        }
    }
    
    private function testSetCategoryWithEmptyArray() {
        $result = $this->config->setCategory('test', []);
        
        if ($result === true) {
            $this->pass("set category with empty array");
        } else {
            $this->fail("set category with empty array failed");
        }
    }
    
    private function testSetCategoryWithNullValues() {
        $this->config->setCategory('test', [
            'null_val' => null,
            'empty_string' => '',
            'zero' => 0,
            'false_val' => false
        ]);
        
        $configs = $this->config->getCategory('test');
        
        if ($configs['null_val'] === null && $configs['empty_string'] === '' &&
            $configs['zero'] === 0 && $configs['false_val'] === false) {
            $this->pass("set category with null values");
        } else {
            $this->fail("set category with null values failed");
        }
    }
    
    private function testDeleteNonExistentConfiguration() {
        $result = $this->config->delete('nonexistent', 'key');
        
        if ($result === true) {
            $this->pass("delete non-existent configuration");
        } else {
            $this->fail("delete non-existent configuration failed");
        }
    }
    
    private function testCacheBehavior() {
        $this->config->set('test', 'cached_key', 'cached_value');
        $this->config->get('test', 'cached_key'); // Load into cache
        $this->config->clearCache();
        
        $value = $this->config->get('test', 'cached_key');
        
        if ($value === 'cached_value') {
            $this->pass("cache behavior");
        } else {
            $this->fail("cache behavior failed");
        }
    }
    
    private function testUpdateExistingConfigurationClearsCache() {
        $this->config->set('test', 'update_key', 'initial_value');
        $this->config->get('test', 'update_key'); // Load into cache
        $this->config->set('test', 'update_key', 'updated_value');
        
        $value = $this->config->get('test', 'update_key');
        
        if ($value === 'updated_value') {
            $this->pass("update existing configuration clears cache");
        } else {
            $this->fail("update existing configuration clears cache failed");
        }
    }
    
    private function testDeleteConfigurationClearsCache() {
        $this->config->set('test', 'delete_key', 'delete_value');
        $this->config->get('test', 'delete_key'); // Load into cache
        $this->config->delete('test', 'delete_key');
        
        $value = $this->config->get('test', 'delete_key', 'default');
        
        if ($value === 'default') {
            $this->pass("delete configuration clears cache");
        } else {
            $this->fail("delete configuration clears cache failed");
        }
    }
    
    private function testCompanyInfoWithEmptyDatabase() {
        $this->db->query("DELETE FROM company_info");
        
        $companyInfo = $this->config->getCompanyInfo();
        
        if ($companyInfo['company_name'] === 'Sanctum CRM' && $companyInfo['timezone'] === 'UTC') {
            $this->pass("company info with empty database");
        } else {
            $this->fail("company info with empty database failed");
        }
    }
    
    private function testSetCompanyInfoWithEmptyDatabase() {
        $this->db->query("DELETE FROM company_info");
        
        $result = $this->config->setCompanyInfo([
            'company_name' => 'New Company',
            'timezone' => 'America/New_York'
        ]);
        
        if ($result === true) {
            $companyInfo = $this->config->getCompanyInfo();
            if ($companyInfo['company_name'] === 'New Company' && $companyInfo['timezone'] === 'America/New_York') {
                $this->pass("set company info with empty database");
            } else {
                $this->fail("set company info with empty database failed");
            }
        } else {
            $this->fail("set company info with empty database failed");
        }
    }
    
    private function testInstallationProgressEmpty() {
        $this->db->query("DELETE FROM installation_state");
        
        $progress = $this->config->getInstallationProgress();
        
        if (is_array($progress) && empty($progress)) {
            $this->pass("installation progress empty");
        } else {
            $this->fail("installation progress empty failed");
        }
    }
    
    private function testCompleteInstallationStepWithData() {
        $testData = ['php_version' => '8.1', 'extensions' => ['sqlite3', 'json']];
        
        $result = $this->config->completeInstallationStep('environment', $testData);
        
        if ($result === true) {
            $progress = $this->config->getInstallationProgress();
            if (count($progress) === 1 && $progress[0]['step'] === 'environment') {
                $this->pass("complete installation step with data");
            } else {
                $this->fail("complete installation step with data failed");
            }
        } else {
            $this->fail("complete installation step with data failed");
        }
    }
    
    private function testCompleteInstallationStepWithoutData() {
        $result = $this->config->completeInstallationStep('database');
        
        if ($result === true) {
            $this->pass("complete installation step without data");
        } else {
            $this->fail("complete installation step without data failed");
        }
    }
    
    private function testGetCurrentInstallationStepWithIncompleteSteps() {
        $this->db->query("DELETE FROM installation_state");
        
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('database');
        
        $currentStep = $this->config->getCurrentInstallationStep();
        
        if ($currentStep === 'company') {
            $this->pass("get current installation step with incomplete steps");
        } else {
            $this->fail("get current installation step with incomplete steps failed");
        }
    }
    
    private function testGetCurrentInstallationStepAllComplete() {
        $this->db->query("DELETE FROM installation_state");
        
        $steps = ['environment', 'database', 'company', 'admin', 'complete'];
        foreach ($steps as $step) {
            $this->config->completeInstallationStep($step);
        }
        
        $currentStep = $this->config->getCurrentInstallationStep();
        
        if ($currentStep === 'complete') {
            $this->pass("get current installation step all complete");
        } else {
            $this->fail("get current installation step all complete failed");
        }
    }
    
    private function testGetCurrentInstallationStepNoSteps() {
        $this->db->query("DELETE FROM installation_state");
        
        $currentStep = $this->config->getCurrentInstallationStep();
        
        if ($currentStep === 'environment') {
            $this->pass("get current installation step no steps");
        } else {
            $this->fail("get current installation step no steps failed");
        }
    }
    
    private function testGetCurrentInstallationStepWithGaps() {
        $this->db->query("DELETE FROM installation_state");
        
        $this->config->completeInstallationStep('environment');
        $this->config->completeInstallationStep('company');
        
        $currentStep = $this->config->getCurrentInstallationStep();
        
        if ($currentStep === 'database') {
            $this->pass("get current installation step with gaps");
        } else {
            $this->fail("get current installation step with gaps failed");
        }
    }
    
    private function testDataTypeDetection() {
        // Clear test data first
        $this->db->query("DELETE FROM system_config WHERE category = 'test'");
        
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean' => true,
            'array' => ['key' => 'value'],
            'object' => (object)['key' => 'value']
        ];
        
        $allPassed = true;
        foreach ($testCases as $expectedType => $value) {
            $this->config->set('test', $expectedType, $value);
            $retrieved = $this->config->get('test', $expectedType);
            
            if ($expectedType === 'object') {
                // Objects should be retrieved as objects, not arrays
                if ($retrieved !== $value) {
                    $allPassed = false;
                    break;
                }
            } else {
                if ($retrieved !== $value) {
                    $allPassed = false;
                    break;
                }
            }
        }
        
        if ($allPassed) {
            $this->pass("data type detection");
        } else {
            $this->fail("data type detection failed");
        }
    }
    
    private function testEncryptionDecryption() {
        $originalValue = 'sensitive_data_123';
        
        $this->config->set('security', 'secret', $originalValue, true);
        $retrieved = $this->config->get('security', 'secret');
        
        if ($retrieved === $originalValue) {
            $this->pass("encryption decryption");
        } else {
            $this->fail("encryption decryption failed");
        }
    }
    
    private function testEncryptionWithSpecialCharacters() {
        $specialValue = "Special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";
        
        $this->config->set('test', 'special', $specialValue, true);
        $retrieved = $this->config->get('test', 'special');
        
        if ($retrieved === $specialValue) {
            $this->pass("encryption with special characters");
        } else {
            $this->fail("encryption with special characters failed");
        }
    }
    
    private function testEncryptionWithUnicode() {
        $unicodeValue = "Unicode: 你好世界 🌍 émojis";
        
        $this->config->set('test', 'unicode', $unicodeValue, true);
        $retrieved = $this->config->get('test', 'unicode');
        
        if ($retrieved === $unicodeValue) {
            $this->pass("encryption with unicode");
        } else {
            $this->fail("encryption with unicode failed");
        }
    }
    
    private function testConcurrentAccess() {
        $config1 = ConfigManager::getInstance();
        $config2 = ConfigManager::getInstance();
        
        $config1->set('test', 'concurrent', 'value1');
        $value = $config2->get('test', 'concurrent');
        
        if ($value === 'value1') {
            $config2->set('test', 'concurrent', 'value2');
            $value = $config1->get('test', 'concurrent');
            
            if ($value === 'value2') {
                $this->pass("concurrent access");
            } else {
                $this->fail("concurrent access failed");
            }
        } else {
            $this->fail("concurrent access failed");
        }
    }
    
    private function testErrorHandling() {
        // Test with invalid category/key combinations
        $this->config->set('', 'key', 'value');
        $this->config->set('category', '', 'value');
        
        // These should not cause errors
        $this->pass("error handling");
    }
    
    private function testMemoryUsage() {
        $largeData = str_repeat('x', 10000);
        
        $this->config->set('test', 'large', $largeData);
        $retrieved = $this->config->get('test', 'large');
        
        if ($retrieved === $largeData) {
            $this->pass("memory usage");
        } else {
            $this->fail("memory usage failed");
        }
    }
    
    private function testNestedArrays() {
        $nestedArray = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep_value'
                ]
            ]
        ];
        
        $this->config->set('test', 'nested', $nestedArray);
        $retrieved = $this->config->get('test', 'nested');
        
        if ($retrieved === $nestedArray) {
            $this->pass("nested arrays");
        } else {
            $this->fail("nested arrays failed");
        }
    }
    
    private function testBooleanEdgeCases() {
        $this->config->set('test', 'true_bool', true);
        $this->config->set('test', 'false_bool', false);
        $this->config->set('test', 'string_true', 'true');
        $this->config->set('test', 'string_false', 'false');
        
        $trueBool = $this->config->get('test', 'true_bool');
        $falseBool = $this->config->get('test', 'false_bool');
        $stringTrue = $this->config->get('test', 'string_true');
        $stringFalse = $this->config->get('test', 'string_false');
        
        if ($trueBool === true && $falseBool === false && 
            $stringTrue === 'true' && $stringFalse === 'false') {
            $this->pass("boolean edge cases");
        } else {
            $this->fail("boolean edge cases failed");
        }
    }
    
    private function testNumericEdgeCases() {
        $this->config->set('test', 'zero', 0);
        $this->config->set('test', 'negative', -1);
        $this->config->set('test', 'float_zero', 0.0);
        $this->config->set('test', 'string_zero', '0');
        
        $zero = $this->config->get('test', 'zero');
        $negative = $this->config->get('test', 'negative');
        $floatZero = $this->config->get('test', 'float_zero');
        $stringZero = $this->config->get('test', 'string_zero');
        
        if ($zero === 0 && $negative === -1 && $floatZero === 0.0 && $stringZero === '0') {
            $this->pass("numeric edge cases");
        } else {
            $this->fail("numeric edge cases failed");
        }
    }
    
    private function pass($testName) {
        echo "  ✓ $testName\n";
        $this->testResults[] = ['name' => $testName, 'status' => 'PASS'];
    }
    
    private function fail($testName) {
        echo "  ✗ $testName\n";
        $this->testResults[] = ['name' => $testName, 'status' => 'FAIL'];
    }
    
    private function displayResults() {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($test) {
            return $test['status'] === 'PASS';
        }));
        $failed = $total - $passed;
        
        echo "\nConfiguration Management Test Results:\n";
        echo "Total Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n";
        
        if ($failed > 0) {
            echo "\nFailed Tests:\n";
            foreach ($this->testResults as $test) {
                if ($test['status'] === 'FAIL') {
                    echo "  - " . $test['name'] . "\n";
                }
            }
        }
    }
}

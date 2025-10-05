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
 * ConfigManager Unit Tests
 * Tests for configuration management functionality
 */

require_once __DIR__ . '/../bootstrap.php';

class ConfigManagerTest {
    private $config;
    private $db;

    public function __construct() {
        $this->db = TestUtils::getTestDatabase();
        $this->config = ConfigManager::getInstance();

        // Clear test data
        try {
            $this->db->query("DELETE FROM system_config");
            $this->db->query("DELETE FROM company_info");
            $this->db->query("DELETE FROM installation_state");
        } catch (Exception $e) {
            // Tables might not exist yet, continue
        }
    }

    public function runAllTests() {
        echo "Running ConfigManager Tests...\n";

        $this->testBasicFunctionality();
        $this->testDataTypes();
        $this->testCategoryOperations();
        $this->testEncryptionFeatures();
        $this->testValidationFeatures();

        echo "All ConfigManager tests completed!\n";
    }

    public function testBasicFunctionality() {
        echo "  Testing basic functionality... ";

        try {
            // Test singleton pattern
            $instance1 = ConfigManager::getInstance();
            $instance2 = ConfigManager::getInstance();

            if ($instance1 === $instance2 && $instance1 instanceof ConfigManager) {
                echo "PASS (singleton)\n";
            } else {
                echo "FAIL (singleton)\n";
                return;
            }

            // Test set and get
            $this->config->set('test', 'simple_value', 'test_data');
            $value = $this->config->get('test', 'simple_value');

            if ($value === 'test_data') {
                echo "PASS (set/get)\n";
            } else {
                echo "FAIL (set/get)\n";
            }

        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }

    public function testDataTypes() {
        echo "  Testing data types... ";

        try {
            // Test different data types
            $this->config->set('test', 'string_value', 'test string');
            $this->config->set('test', 'int_value', 42);
            $this->config->set('test', 'bool_value', true);

            if ($this->config->get('test', 'string_value') === 'test string' &&
                $this->config->get('test', 'int_value') === 42 &&
                $this->config->get('test', 'bool_value') === true) {
                echo "PASS\n";
            } else {
                echo "FAIL\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }

    public function testCategoryOperations() {
        echo "  Testing category operations... ";

        try {
            // Test category setting and retrieval
            $configs = [
                'app_name' => 'Test App',
                'app_url' => 'http://test.com'
            ];

            $this->config->setCategory('application', $configs);
            $retrieved = $this->config->getCategory('application');

            if (is_array($retrieved) &&
                $retrieved['app_name'] === 'Test App' &&
                $retrieved['app_url'] === 'http://test.com') {
                echo "PASS\n";
            } else {
                echo "FAIL\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }

    public function testEncryptionFeatures() {
        echo "  Testing encryption features... ";

        try {
            // Test if encryption is available
            if (method_exists($this->config, 'encryptConfig')) {
                $encrypted = $this->config->encryptConfig('test_value');
                if ($encrypted && $encrypted !== 'test_value') {
                    echo "PASS\n";
                } else {
                    echo "FAIL\n";
                }
            } else {
                echo "SKIP - Encryption not available\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }

    public function testValidationFeatures() {
        echo "  Testing validation features... ";

        try {
            // Test if validation is available
            if (method_exists($this->config, 'validateConfig')) {
                $isValid = $this->config->validateConfig('application', 'app_name', 'Test App');
                if ($isValid) {
                    echo "PASS\n";
                } else {
                    echo "FAIL\n";
                }
            } else {
                echo "SKIP - Validation not available\n";
            }
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ConfigManagerTest();
    $test->runAllTests();
}
?>
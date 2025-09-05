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
 * Page Coverage Test
 * Tests for pages that need coverage: edit_contact.php, import_contacts.php, view_contact.php
 */

require_once __DIR__ . '/../bootstrap.php';

class PageCoverageTest {
    private $testResults = [];
    
    public function runAllTests() {
        echo "Running Page Coverage Tests...\n";
        
        $this->testEditContactPage();
        $this->testImportContactsPage();
        $this->testViewContactPage();
        
        $this->displayResults();
    }
    
    private function testEditContactPage() {
        // Test that edit_contact.php exists and is accessible
        $filePath = __DIR__ . '/../../public/pages/edit_contact.php';
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            
            // Check for essential elements
            $hasForm = strpos($content, '<form') !== false;
            $hasInput = strpos($content, '<input') !== false;
            $hasSubmit = strpos($content, 'submit') !== false;
            $hasValidation = strpos($content, 'validation') !== false || strpos($content, 'required') !== false;
            
            if ($hasForm && $hasInput && $hasSubmit) {
                $this->pass("edit_contact.php page structure");
            } else {
                $this->fail("edit_contact.php page structure");
            }
            
            // Test file size and complexity
            $fileSize = filesize($filePath);
            if ($fileSize > 1000) { // Reasonable size for a contact edit page
                $this->pass("edit_contact.php file size");
            } else {
                $this->fail("edit_contact.php file size");
            }
            
        } else {
            $this->fail("edit_contact.php file exists");
        }
    }
    
    private function testImportContactsPage() {
        // Test that import_contacts.php exists and is accessible
        $filePath = __DIR__ . '/../../public/pages/import_contacts.php';
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            
            // Check for essential elements
            $hasForm = strpos($content, '<form') !== false;
            $hasFileInput = strpos($content, 'type="file"') !== false;
            $hasUpload = strpos($content, 'upload') !== false || strpos($content, 'import') !== false;
            $hasCSV = strpos($content, 'csv') !== false || strpos($content, 'CSV') !== false;
            
            if ($hasForm && $hasFileInput && $hasUpload) {
                $this->pass("import_contacts.php page structure");
            } else {
                $this->fail("import_contacts.php page structure");
            }
            
            // Test file size and complexity
            $fileSize = filesize($filePath);
            if ($fileSize > 500) { // Reasonable size for an import page
                $this->pass("import_contacts.php file size");
            } else {
                $this->fail("import_contacts.php file size");
            }
            
            // Test for CSV handling
            if ($hasCSV) {
                $this->pass("import_contacts.php CSV support");
            } else {
                $this->fail("import_contacts.php CSV support");
            }
            
        } else {
            $this->fail("import_contacts.php file exists");
        }
    }
    
    private function testViewContactPage() {
        // Test that view_contact.php exists and is accessible
        $filePath = __DIR__ . '/../../public/pages/view_contact.php';
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            
            // Check for essential elements
            $hasDisplay = strpos($content, 'display') !== false || strpos($content, 'show') !== false;
            $hasContact = strpos($content, 'contact') !== false;
            $hasDetails = strpos($content, 'details') !== false || strpos($content, 'information') !== false;
            $hasEdit = strpos($content, 'edit') !== false;
            
            if ($hasContact && $hasDetails) {
                $this->pass("view_contact.php page structure");
            } else {
                $this->fail("view_contact.php page structure");
            }
            
            // Test file size and complexity
            $fileSize = filesize($filePath);
            if ($fileSize > 500) { // Reasonable size for a view page
                $this->pass("view_contact.php file size");
            } else {
                $this->fail("view_contact.php file size");
            }
            
            // Test for contact data display
            if ($hasDisplay || $hasContact) {
                $this->pass("view_contact.php contact display");
            } else {
                $this->fail("view_contact.php contact display");
            }
            
        } else {
            $this->fail("view_contact.php file exists");
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
        
        echo "\nPage Coverage Test Results:\n";
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

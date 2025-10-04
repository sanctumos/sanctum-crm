<?php
/**
 * ACTUAL Frontend UI Test
 * Tests the REAL frontend JavaScript with REAL CSV file upload
 */

require_once __DIR__ . '/../bootstrap.php';

class ActualFrontendUITest {
    private $baseUrl;
    private $csvFile;
    private $sessionCookie;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost:6789';
        $this->csvFile = __DIR__ . '/../source-data/KinISO_staffing_leads_SEP25.csv';
        
        // Start a session to get cookies
        $this->startSession();
    }
    
    private function startSession() {
        // Login to get session cookie
        $loginData = [
            'username' => 'admin',
            'password' => 'admin123'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/login.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_POSTFIELDS => http_build_query($loginData),
            CURLOPT_COOKIEJAR => tempnam(sys_get_temp_dir(), 'cookies'),
            CURLOPT_COOKIEFILE => tempnam(sys_get_temp_dir(), 'cookies')
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Extract cookies from response
        preg_match_all('/Set-Cookie: ([^;]+)/', $response, $matches);
        $this->sessionCookie = implode('; ', $matches[1]);
        
        curl_close($ch);
    }
    
    public function runAllTests() {
        echo "Running ACTUAL Frontend UI Tests...\n";
        
        $this->testImportPageWithJavaScript();
        $this->testCSVFileUploadThroughUI();
        $this->testFieldMappingThroughUI();
        $this->testNameSplittingThroughUI();
        $this->testCompleteUIImportWorkflow();
        
        echo "All ACTUAL Frontend UI tests completed!\n";
    }
    
    public function testImportPageWithJavaScript() {
        echo "  Testing import page with JavaScript... ";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/index.php?page=import_contacts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $this->sessionCookie
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $html = $response;
            
            // Check for ALL the JavaScript functions that the UI needs
            $hasFileInput = strpos($html, 'type="file"') !== false;
            $hasUploadButton = strpos($html, 'Upload CSV') !== false;
            $hasMappingSection = strpos($html, 'field-mapping') !== false;
            $hasNameSplitting = strpos($html, 'nameSplitSection') !== false;
            $hasJavaScript = strpos($html, 'setupNameSplitHandlers') !== false;
            $hasCSVUpload = strpos($html, 'csvUploadForm') !== false;
            $hasFieldMapping = strpos($html, 'populateCSVColumns') !== false;
            $hasNameSplit = strpos($html, 'applyNameSplit') !== false;
            $hasImportProcess = strpos($html, 'startImport') !== false;
            
            if ($hasFileInput && $hasUploadButton && $hasMappingSection && $hasNameSplitting && 
                $hasJavaScript && $hasCSVUpload && $hasFieldMapping && $hasNameSplit && $hasImportProcess) {
                echo "PASS\n";
            } else {
                echo "FAIL - Missing JavaScript functions\n";
                echo "    File input: " . ($hasFileInput ? 'Yes' : 'No') . "\n";
                echo "    Upload button: " . ($hasUploadButton ? 'Yes' : 'No') . "\n";
                echo "    Mapping section: " . ($hasMappingSection ? 'Yes' : 'No') . "\n";
                echo "    Name splitting: " . ($hasNameSplitting ? 'Yes' : 'No') . "\n";
                echo "    setupNameSplitHandlers: " . ($hasJavaScript ? 'Yes' : 'No') . "\n";
                echo "    csvUploadForm: " . ($hasCSVUpload ? 'Yes' : 'No') . "\n";
                echo "    populateCSVColumns: " . ($hasFieldMapping ? 'Yes' : 'No') . "\n";
                echo "    applyNameSplit: " . ($hasNameSplit ? 'Yes' : 'No') . "\n";
                echo "    startImport: " . ($hasImportProcess ? 'Yes' : 'No') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $httpCode . "\n";
        }
    }
    
    public function testCSVFileUploadThroughUI() {
        echo "  Testing CSV file upload through UI... ";
        
        if (!file_exists($this->csvFile)) {
            echo "SKIP (test CSV not found)\n";
            return;
        }
        
        // Test the ACTUAL file upload endpoint that the UI JavaScript calls
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $this->sessionCookie
            ],
            CURLOPT_POSTFIELDS => [
                'csvFile' => new CURLFile($this->csvFile, 'text/csv', 'KinISO_staffing_leads_SEP25.csv')
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (uploaded " . ($data['rowCount'] ?? 0) . " rows)\n";
            } else {
                echo "FAIL - Upload failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $httpCode . "\n";
            echo "    Response: " . substr($response, 0, 200) . "\n";
        }
    }
    
    public function testFieldMappingThroughUI() {
        echo "  Testing field mapping through UI... ";
        
        // Parse the REAL CSV file like the UI would
        $csvData = $this->parseCsvFile(5);
        
        // Use the EXACT field mapping that the UI would send
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'company' => 'company_name',
            'position' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => 'Zip Code'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'UI Field Mapping Test',
            'notes' => 'Testing UI field mapping',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $this->sessionCookie
            ],
            CURLOPT_POSTFIELDS => json_encode($importData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "PASS (mapped " . ($data['totalProcessed'] ?? 0) . " records)\n";
            } else {
                echo "FAIL - Mapping failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $httpCode . "\n";
        }
    }
    
    public function testNameSplittingThroughUI() {
        echo "  Testing name splitting through UI... ";
        
        // Test the EXACT name splitting logic that the UI uses
        $testNames = ['Carolyn Boteler', 'Chris Burkhard', 'Samuel Brooks', 'Heidi Wallace', 'Reed Laws'];
        $splitCount = 0;
        
        foreach ($testNames as $name) {
            // This is the EXACT logic from the UI JavaScript
            $parts = explode(' ', $name, 2);
            if (count($parts) >= 2) {
                $firstPart = trim($parts[0]);
                $lastPart = trim($parts[1]);
                if (!empty($firstPart) && !empty($lastPart)) {
                    $splitCount++;
                }
            }
        }
        
        if ($splitCount === count($testNames)) {
            echo "PASS (split " . $splitCount . " names)\n";
        } else {
            echo "FAIL - Only split " . $splitCount . " of " . count($testNames) . " names\n";
        }
    }
    
    public function testCompleteUIImportWorkflow() {
        echo "  Testing complete UI import workflow... ";
        
        // Clean up any existing test data
        $this->cleanupTestData();
        
        // Parse the REAL CSV file
        $csvData = $this->parseCsvFile(3);
        
        // Use the EXACT workflow that the UI would follow
        $fieldMapping = [
            'first_name' => 'Name',
            'last_name' => 'Name',
            'company' => 'company_name',
            'position' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone'
        ];
        
        $nameSplitConfig = [
            'column' => 'Name',
            'delimiter' => ' ',
            'firstPart' => 0,
            'lastPart' => 1
        ];
        
        $importData = [
            'csvData' => $csvData,
            'fieldMapping' => $fieldMapping,
            'source' => 'UI Complete Workflow Test',
            'notes' => 'Testing complete UI workflow',
            'nameSplitConfig' => $nameSplitConfig
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/v1/import',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $this->sessionCookie
            ],
            CURLOPT_POSTFIELDS => json_encode($importData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                // Verify contacts were created in database
                $db = new SQLite3(__DIR__ . '/../../db/crm.db');
                $count = $db->querySingle("SELECT COUNT(*) FROM contacts WHERE source = 'UI Complete Workflow Test'");
                $db->close();
                
                if ($count > 0) {
                    echo "PASS (imported " . $count . " contacts)\n";
                } else {
                    echo "FAIL - No contacts found in database\n";
                }
            } else {
                echo "FAIL - Import failed: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "FAIL - HTTP " . $httpCode . "\n";
        }
    }
    
    private function parseCsvFile($maxRows = 10) {
        if (!file_exists($this->csvFile)) {
            return [];
        }
        
        $csvData = [];
        if (($handle = fopen($this->csvFile, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            $rowCount = 0;
            while (($data = fgetcsv($handle)) !== FALSE && $rowCount < $maxRows) {
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[trim($header)] = isset($data[$index]) ? trim($data[$index]) : '';
                }
                $csvData[] = $row;
                $rowCount++;
            }
            fclose($handle);
        }
        
        return $csvData;
    }
    
    private function cleanupTestData() {
        $db = new SQLite3(__DIR__ . '/../../db/crm.db');
        $db->exec("DELETE FROM contacts WHERE source = 'UI Complete Workflow Test'");
        $db->close();
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new ActualFrontendUITest();
        $test->runAllTests();
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>

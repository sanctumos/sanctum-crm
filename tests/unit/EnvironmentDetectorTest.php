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
 * EnvironmentDetector Unit Tests
 * Tests for environment detection functionality
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class EnvironmentDetectorTest extends TestCase {
    private $detector;
    
    protected function setUp(): void {
        $this->detector = new EnvironmentDetector();
    }
    
    public function testDetectWebServer() {
        $server = $this->detector->detectWebServer();
        
        $this->assertIsString($server);
        $this->assertContains($server, ['Apache', 'Nginx', 'IIS', 'Lighttpd', 'Unknown']);
    }
    
    public function testDetectPHPVersion() {
        $version = $this->detector->detectPHPVersion();
        
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version);
    }
    
    public function testDetectExtensions() {
        $extensions = $this->detector->detectExtensions();
        
        $this->assertIsArray($extensions);
        $this->assertNotEmpty($extensions);
        
        // Should contain some common extensions
        $this->assertContains('json', $extensions);
        $this->assertContains('session', $extensions);
    }
    
    public function testDetectOS() {
        $os = $this->detector->detectOS();
        
        $this->assertIsString($os);
        $this->assertNotEmpty($os);
    }
    
    public function testIsWindows() {
        $isWindows = $this->detector->isWindows();
        
        $this->assertIsBool($isWindows);
        
        // Should match PHP_OS
        $expected = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->assertEquals($expected, $isWindows);
    }
    
    public function testIsLinux() {
        $isLinux = $this->detector->isLinux();
        
        $this->assertIsBool($isLinux);
        
        // Should match PHP_OS
        $expected = strtoupper(substr(PHP_OS, 0, 5)) === 'LINUX';
        $this->assertEquals($expected, $isLinux);
    }
    
    public function testIsMacOS() {
        $isMacOS = $this->detector->isMacOS();
        
        $this->assertIsBool($isMacOS);
        
        // Should match PHP_OS
        $expected = strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN';
        $this->assertEquals($expected, $isMacOS);
    }
    
    public function testGetEnvironmentInfo() {
        $info = $this->detector->getEnvironmentInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('web_server', $info);
        $this->assertArrayHasKey('php_version', $info);
        $this->assertArrayHasKey('operating_system', $info);
        $this->assertArrayHasKey('is_windows', $info);
        $this->assertArrayHasKey('is_linux', $info);
        $this->assertArrayHasKey('is_macos', $info);
        $this->assertArrayHasKey('extensions', $info);
        $this->assertArrayHasKey('memory_limit', $info);
        $this->assertArrayHasKey('max_execution_time', $info);
        $this->assertArrayHasKey('upload_max_filesize', $info);
        $this->assertArrayHasKey('post_max_size', $info);
        $this->assertArrayHasKey('timezone', $info);
        $this->assertArrayHasKey('document_root', $info);
        $this->assertArrayHasKey('server_name', $info);
        $this->assertArrayHasKey('server_port', $info);
        $this->assertArrayHasKey('https', $info);
        
        // Verify data types
        $this->assertIsString($info['web_server']);
        $this->assertIsString($info['php_version']);
        $this->assertIsString($info['operating_system']);
        $this->assertIsBool($info['is_windows']);
        $this->assertIsBool($info['is_linux']);
        $this->assertIsBool($info['is_macos']);
        $this->assertIsArray($info['extensions']);
        $this->assertIsString($info['memory_limit']);
        $this->assertIsString($info['max_execution_time']);
        $this->assertIsString($info['upload_max_filesize']);
        $this->assertIsString($info['post_max_size']);
        $this->assertIsString($info['timezone']);
        $this->assertIsBool($info['https']);
    }
    
    public function testGetRecommendedConfig() {
        $recommendations = $this->detector->getRecommendedConfig();
        
        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('web_server', $recommendations);
        $this->assertArrayHasKey('php_version', $recommendations);
        $this->assertArrayHasKey('memory_limit', $recommendations);
        $this->assertArrayHasKey('extensions', $recommendations);
        $this->assertArrayHasKey('security', $recommendations);
        
        // Test web server recommendations
        $webServer = $recommendations['web_server'];
        $this->assertArrayHasKey('type', $webServer);
        $this->assertArrayHasKey('config_file', $webServer);
        $this->assertArrayHasKey('recommendations', $webServer);
        $this->assertIsArray($webServer['recommendations']);
        
        // Test PHP version recommendations
        $phpVersion = $recommendations['php_version'];
        $this->assertArrayHasKey('current', $phpVersion);
        $this->assertArrayHasKey('recommended', $phpVersion);
        $this->assertArrayHasKey('status', $phpVersion);
        $this->assertArrayHasKey('message', $phpVersion);
        $this->assertContains($phpVersion['status'], ['success', 'warning', 'error']);
        
        // Test memory limit recommendations
        $memoryLimit = $recommendations['memory_limit'];
        $this->assertArrayHasKey('current', $memoryLimit);
        $this->assertArrayHasKey('recommended', $memoryLimit);
        $this->assertArrayHasKey('status', $memoryLimit);
        $this->assertArrayHasKey('message', $memoryLimit);
        
        // Test extensions recommendations
        $extensions = $recommendations['extensions'];
        $this->assertArrayHasKey('missing', $extensions);
        $this->assertArrayHasKey('status', $extensions);
        $this->assertArrayHasKey('message', $extensions);
        $this->assertIsArray($extensions['missing']);
        
        // Test security recommendations
        $security = $recommendations['security'];
        $this->assertArrayHasKey('recommendations', $security);
        $this->assertArrayHasKey('https_enabled', $security);
        $this->assertArrayHasKey('status', $security);
        $this->assertIsArray($security['recommendations']);
        $this->assertIsBool($security['https_enabled']);
    }
    
    public function testGetDeploymentGuide() {
        $guide = $this->detector->getDeploymentGuide();
        
        $this->assertIsArray($guide);
        $this->assertArrayHasKey('platform', $guide);
        
        $platform = $guide['platform'];
        $this->assertContains($platform, ['Linux', 'Windows', 'macOS']);
        
        if ($platform === 'Linux') {
            $this->assertArrayHasKey('commands', $guide);
            $this->assertIsArray($guide['commands']);
            
            if (isset($guide['web_server'])) {
                $this->assertArrayHasKey('additional_commands', $guide);
                $this->assertIsArray($guide['additional_commands']);
            }
        } elseif ($platform === 'Windows') {
            $this->assertArrayHasKey('recommendations', $guide);
            $this->assertIsArray($guide['recommendations']);
        } elseif ($platform === 'macOS') {
            $this->assertArrayHasKey('commands', $guide);
            $this->assertIsArray($guide['commands']);
        }
    }
    
    public function testIsProductionReady() {
        $readiness = $this->detector->isProductionReady();
        
        $this->assertIsArray($readiness);
        $this->assertArrayHasKey('ready', $readiness);
        $this->assertArrayHasKey('issues', $readiness);
        $this->assertArrayHasKey('recommendations', $readiness);
        
        $this->assertIsBool($readiness['ready']);
        $this->assertIsArray($readiness['issues']);
        $this->assertIsArray($readiness['recommendations']);
    }
    
    public function testParseMemoryLimit() {
        // Test with reflection to access private method
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('parseMemoryLimit');
        $method->setAccessible(true);
        
        // Test different memory limit formats
        $this->assertEquals(128, $method->invoke($this->detector, '128M'));
        $this->assertEquals(256, $method->invoke($this->detector, '256M'));
        $this->assertEquals(1, $method->invoke($this->detector, '1024K'));
        $this->assertEquals(1024, $method->invoke($this->detector, '1G'));
        $this->assertEquals(0.5, $method->invoke($this->detector, '512K'));
        
        // Test unlimited memory
        $unlimited = $method->invoke($this->detector, '-1');
        $this->assertEquals(PHP_INT_MAX, $unlimited);
    }
    
    public function testWebServerDetection() {
        // Test different server software strings
        $testCases = [
            'Apache/2.4.41' => 'Apache',
            'nginx/1.18.0' => 'Nginx',
            'Microsoft-IIS/10.0' => 'IIS',
            'lighttpd/1.4.55' => 'Lighttpd',
            'Unknown Server' => 'Unknown'
        ];
        
        foreach ($testCases as $serverSoftware => $expected) {
            // Mock $_SERVER for testing
            $originalServer = $_SERVER['SERVER_SOFTWARE'] ?? null;
            $_SERVER['SERVER_SOFTWARE'] = $serverSoftware;
            
            $detector = new EnvironmentDetector();
            $result = $detector->detectWebServer();
            
            $this->assertEquals($expected, $result, "Failed for server software: $serverSoftware");
            
            // Restore original value
            if ($originalServer !== null) {
                $_SERVER['SERVER_SOFTWARE'] = $originalServer;
            } else {
                unset($_SERVER['SERVER_SOFTWARE']);
            }
        }
    }
    
    public function testRequiredExtensions() {
        $recommendations = $this->detector->getRecommendedConfig();
        $extensions = $recommendations['extensions'];
        
        $requiredExtensions = ['sqlite3', 'json', 'curl', 'mbstring', 'openssl', 'session', 'pdo', 'pdo_sqlite'];
        
        if (empty($extensions['missing'])) {
            // All required extensions should be present
            $loadedExtensions = $this->detector->detectExtensions();
            foreach ($requiredExtensions as $ext) {
                $this->assertContains($ext, $loadedExtensions, "Required extension $ext should be loaded");
            }
        } else {
            // Missing extensions should be in the required list
            foreach ($extensions['missing'] as $missing) {
                $this->assertContains($missing, $requiredExtensions, "Missing extension $missing should be required");
            }
        }
    }
    
    public function testEnvironmentInfoConsistency() {
        $info = $this->detector->getEnvironmentInfo();
        
        // Verify OS detection consistency
        $this->assertEquals($info['is_windows'], $this->detector->isWindows());
        $this->assertEquals($info['is_linux'], $this->detector->isLinux());
        $this->assertEquals($info['is_macos'], $this->detector->isMacOS());
        
        // Verify only one OS flag is true
        $osFlags = [$info['is_windows'], $info['is_linux'], $info['is_macos']];
        $trueCount = array_sum($osFlags);
        $this->assertLessThanOrEqual(1, $trueCount, "Only one OS flag should be true");
        
        // Verify PHP version consistency
        $this->assertEquals($info['php_version'], $this->detector->detectPHPVersion());
        
        // Verify extensions consistency
        $this->assertEquals($info['extensions'], $this->detector->detectExtensions());
    }
}

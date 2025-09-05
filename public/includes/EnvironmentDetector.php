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
 * Environment Detector
 * Detects server environment and provides recommendations
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class EnvironmentDetector {
    
    /**
     * Detect web server type
     */
    public function detectWebServer() {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        
        if (strpos($serverSoftware, 'Apache') !== false) {
            return 'Apache';
        } elseif (strpos($serverSoftware, 'nginx') !== false) {
            return 'Nginx';
        } elseif (strpos($serverSoftware, 'IIS') !== false) {
            return 'IIS';
        } elseif (strpos($serverSoftware, 'lighttpd') !== false) {
            return 'Lighttpd';
        } else {
            return 'Unknown';
        }
    }
    
    /**
     * Detect PHP version
     */
    public function detectPHPVersion() {
        return PHP_VERSION;
    }
    
    /**
     * Detect loaded PHP extensions
     */
    public function detectExtensions() {
        return get_loaded_extensions();
    }
    
    /**
     * Detect operating system
     */
    public function detectOS() {
        return PHP_OS;
    }
    
    /**
     * Check if running on Windows
     */
    public function isWindows() {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
    
    /**
     * Check if running on Linux
     */
    public function isLinux() {
        return strtoupper(substr(PHP_OS, 0, 5)) === 'LINUX';
    }
    
    /**
     * Check if running on macOS
     */
    public function isMacOS() {
        return strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN';
    }
    
    /**
     * Get server environment information
     */
    public function getEnvironmentInfo() {
        return [
            'web_server' => $this->detectWebServer(),
            'php_version' => $this->detectPHPVersion(),
            'operating_system' => $this->detectOS(),
            'is_windows' => $this->isWindows(),
            'is_linux' => $this->isLinux(),
            'is_macos' => $this->isMacOS(),
            'extensions' => $this->detectExtensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'server_port' => $_SERVER['SERVER_PORT'] ?? '',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
        ];
    }
    
    /**
     * Get recommended configuration for current environment
     */
    public function getRecommendedConfig() {
        $env = $this->getEnvironmentInfo();
        $recommendations = [];
        
        // Web server specific recommendations
        switch ($env['web_server']) {
            case 'Apache':
                $recommendations['web_server'] = [
                    'type' => 'Apache',
                    'config_file' => '.htaccess',
                    'recommendations' => [
                        'Enable mod_rewrite for clean URLs',
                        'Set proper file permissions (755 for directories, 644 for files)',
                        'Configure virtual host for production',
                        'Enable HTTPS with SSL certificate'
                    ]
                ];
                break;
                
            case 'Nginx':
                $recommendations['web_server'] = [
                    'type' => 'Nginx',
                    'config_file' => 'nginx.conf',
                    'recommendations' => [
                        'Configure location block for PHP processing',
                        'Set proper file permissions (755 for directories, 644 for files)',
                        'Configure server block for production',
                        'Enable HTTPS with SSL certificate'
                    ]
                ];
                break;
                
            case 'IIS':
                $recommendations['web_server'] = [
                    'type' => 'IIS',
                    'config_file' => 'web.config',
                    'recommendations' => [
                        'Install URL Rewrite module',
                        'Configure application pool settings',
                        'Set proper file permissions',
                        'Enable HTTPS with SSL certificate'
                    ]
                ];
                break;
        }
        
        // PHP version recommendations
        if (version_compare($env['php_version'], '8.0.0', '<')) {
            $recommendations['php_version'] = [
                'current' => $env['php_version'],
                'recommended' => '8.0+',
                'status' => 'warning',
                'message' => 'PHP 8.0 or higher is recommended for better performance and security'
            ];
        } else {
            $recommendations['php_version'] = [
                'current' => $env['php_version'],
                'recommended' => '8.0+',
                'status' => 'success',
                'message' => 'PHP version is up to date'
            ];
        }
        
        // Memory limit recommendations
        $memoryLimit = $this->parseMemoryLimit($env['memory_limit']);
        if ($memoryLimit < 128) {
            $recommendations['memory_limit'] = [
                'current' => $env['memory_limit'],
                'recommended' => '128M',
                'status' => 'warning',
                'message' => 'Consider increasing memory limit to 128M or higher for better performance'
            ];
        } else {
            $recommendations['memory_limit'] = [
                'current' => $env['memory_limit'],
                'recommended' => '128M',
                'status' => 'success',
                'message' => 'Memory limit is adequate'
            ];
        }
        
        // Extension recommendations
        $requiredExtensions = ['sqlite3', 'json', 'curl', 'mbstring', 'openssl', 'session', 'pdo', 'pdo_sqlite'];
        $missingExtensions = array_diff($requiredExtensions, $env['extensions']);
        
        if (!empty($missingExtensions)) {
            $recommendations['extensions'] = [
                'missing' => $missingExtensions,
                'status' => 'error',
                'message' => 'Required PHP extensions are missing'
            ];
        } else {
            $recommendations['extensions'] = [
                'missing' => [],
                'status' => 'success',
                'message' => 'All required PHP extensions are installed'
            ];
        }
        
        // Security recommendations
        $securityRecommendations = [];
        
        if (!$env['https']) {
            $securityRecommendations[] = 'Enable HTTPS for production deployment';
        }
        
        if ($env['max_execution_time'] > 300) {
            $securityRecommendations[] = 'Consider reducing max_execution_time for security';
        }
        
        if (empty($securityRecommendations)) {
            $securityRecommendations[] = 'Security configuration looks good';
        }
        
        $recommendations['security'] = [
            'recommendations' => $securityRecommendations,
            'https_enabled' => $env['https'],
            'status' => $env['https'] ? 'success' : 'warning'
        ];
        
        return $recommendations;
    }
    
    /**
     * Get deployment guide for current environment
     */
    public function getDeploymentGuide() {
        $env = $this->getEnvironmentInfo();
        $guide = [];
        
        if ($env['is_linux']) {
            $guide['platform'] = 'Linux';
            $guide['commands'] = [
                'Update system' => 'sudo apt update && sudo apt upgrade -y',
                'Install PHP and extensions' => 'sudo apt install php8.1 php8.1-sqlite3 php8.1-curl php8.1-mbstring php8.1-openssl php8.1-json php8.1-zip php8.1-xml php8.1-gd',
                'Set permissions' => 'chmod 755 -R public/ && chmod 644 db/crm.db',
                'Create backup directory' => 'mkdir -p db/backup && chmod 755 db/backup'
            ];
            
            if ($env['web_server'] === 'Apache') {
                $guide['web_server'] = 'Apache';
                $guide['additional_commands'] = [
                    'Install Apache' => 'sudo apt install apache2',
                    'Enable mod_rewrite' => 'sudo a2enmod rewrite',
                    'Restart Apache' => 'sudo systemctl restart apache2'
                ];
            } elseif ($env['web_server'] === 'Nginx') {
                $guide['web_server'] = 'Nginx';
                $guide['additional_commands'] = [
                    'Install Nginx' => 'sudo apt install nginx',
                    'Install PHP-FPM' => 'sudo apt install php8.1-fpm',
                    'Restart services' => 'sudo systemctl restart nginx php8.1-fpm'
                ];
            }
        } elseif ($env['is_windows']) {
            $guide['platform'] = 'Windows';
            $guide['recommendations'] = [
                'Use XAMPP or WAMP for easy setup',
                'Ensure PHP extensions are enabled in php.ini',
                'Set proper file permissions',
                'Configure virtual host for production'
            ];
        } elseif ($env['is_macos']) {
            $guide['platform'] = 'macOS';
            $guide['commands'] = [
                'Install Homebrew' => '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"',
                'Install PHP' => 'brew install php',
                'Install SQLite' => 'brew install sqlite',
                'Set permissions' => 'chmod 755 -R public/ && chmod 644 db/crm.db'
            ];
        }
        
        return $guide;
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($memoryLimit) {
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024;
            case 'm':
                return $value;
            case 'k':
                return $value / 1024;
            default:
                return $value / (1024 * 1024); // Assume bytes
        }
    }
    
    /**
     * Check if environment is production ready
     */
    public function isProductionReady() {
        $env = $this->getEnvironmentInfo();
        $recommendations = $this->getRecommendedConfig();
        
        $issues = [];
        
        // Check PHP version
        if ($recommendations['php_version']['status'] !== 'success') {
            $issues[] = 'PHP version issue';
        }
        
        // Check extensions
        if ($recommendations['extensions']['status'] !== 'success') {
            $issues[] = 'Missing PHP extensions';
        }
        
        // Check memory limit
        if ($recommendations['memory_limit']['status'] !== 'success') {
            $issues[] = 'Memory limit too low';
        }
        
        // Check HTTPS
        if (!$env['https']) {
            $issues[] = 'HTTPS not enabled';
        }
        
        return [
            'ready' => empty($issues),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }
}

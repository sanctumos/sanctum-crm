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
 * Configuration Manager
 * Centralized configuration management for Sanctum CRM
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ConfigManager {
    private $db;
    private $cache = [];
    private static $instance = null;
    
    private function __construct() {
        $this->db = Database::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get a configuration value
     */
    public function get($category, $key, $default = null) {
        $cacheKey = $category . '.' . $key;
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $result = $this->db->fetchOne(
            "SELECT config_value, data_type, is_encrypted FROM system_config WHERE category = ? AND config_key = ?",
            [$category, $key]
        );
        
        if ($result) {
            $value = $result['config_value'];
            
            // Decrypt if needed
            if ($result['is_encrypted']) {
                $value = $this->decrypt($value);
            }
            
            // Convert data type
            $value = $this->convertDataType($value, $result['data_type']);
            
            $this->cache[$cacheKey] = $value;
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Set a configuration value
     */
    public function set($category, $key, $value, $encrypt = false) {
        $dataType = $this->getDataType($value);
        
        // Convert to string for storage first
        if (is_array($value) || is_object($value)) {
            $configValue = json_encode($value);
        } else {
            $configValue = (string) $value;
        }
        
        // Encrypt if needed (after string conversion)
        if ($encrypt) {
            $configValue = $this->encrypt($configValue);
        }
        
        $existing = $this->db->fetchOne(
            "SELECT id FROM system_config WHERE category = ? AND config_key = ?",
            [$category, $key]
        );
        
        if ($existing) {
            $this->db->update('system_config', [
                'config_value' => $configValue,
                'data_type' => $dataType,
                'is_encrypted' => $encrypt ? 1 : 0,
                'updated_at' => getCurrentTimestamp()
            ], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('system_config', [
                'category' => $category,
                'config_key' => $key,
                'config_value' => $configValue,
                'data_type' => $dataType,
                'is_encrypted' => $encrypt ? 1 : 0,
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp()
            ]);
        }
        
        // Update cache
        $cacheKey = $category . '.' . $key;
        $this->cache[$cacheKey] = $value;
        
        return true;
    }
    
    /**
     * Get all configurations for a category
     */
    public function getCategory($category) {
        $results = $this->db->fetchAll(
            "SELECT config_key, config_value, data_type, is_encrypted FROM system_config WHERE category = ?",
            [$category]
        );
        
        $configs = [];
        foreach ($results as $row) {
            $value = $row['config_value'];
            
            // Decrypt if needed
            if ($row['is_encrypted']) {
                $value = $this->decrypt($value);
            }
            
            // Convert data type
            $value = $this->convertDataType($value, $row['data_type']);
            
            $configs[$row['config_key']] = $value;
        }
        
        return $configs;
    }
    
    /**
     * Set multiple configurations for a category
     */
    public function setCategory($category, $configs) {
        foreach ($configs as $key => $value) {
            $this->set($category, $key, $value);
        }
        return true;
    }
    
    /**
     * Delete a configuration
     */
    public function delete($category, $key) {
        $this->db->delete('system_config', 'category = ? AND config_key = ?', [$category, $key]);
        
        // Remove from cache
        $cacheKey = $category . '.' . $key;
        unset($this->cache[$cacheKey]);
        
        return true;
    }
    
    /**
     * Get all configurations
     */
    public function getAll() {
        $results = $this->db->fetchAll(
            "SELECT category, config_key, config_value, data_type, is_encrypted FROM system_config ORDER BY category, config_key"
        );
        
        $configs = [];
        foreach ($results as $row) {
            $value = $row['config_value'];
            
            // Decrypt if needed
            if ($row['is_encrypted']) {
                $value = $this->decrypt($value);
            }
            
            // Convert data type
            $value = $this->convertDataType($value, $row['data_type']);
            
            $configs[$row['category']][$row['config_key']] = $value;
        }
        
        return $configs;
    }
    
    /**
     * Clear configuration cache
     */
    public function clearCache() {
        $this->cache = [];
        return true;
    }
    
    /**
     * Get company information
     */
    public function getCompanyInfo() {
        $result = $this->db->fetchOne("SELECT * FROM company_info ORDER BY id LIMIT 1");
        return $result ?: [
            'company_name' => 'Sanctum CRM',
            'timezone' => 'UTC'
        ];
    }
    
    /**
     * Set company information
     */
    public function setCompanyInfo($data) {
        $existing = $this->db->fetchOne("SELECT id FROM company_info ORDER BY id LIMIT 1");
        
        if ($existing) {
            $this->db->update('company_info', array_merge($data, [
                'updated_at' => getCurrentTimestamp()
            ]), 'id = ' . $existing['id']);
        } else {
            $this->db->insert('company_info', array_merge($data, [
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp()
            ]));
        }
        
        return true;
    }
    
    /**
     * Check if first boot is needed
     */
    public function isFirstBoot() {
        // Check if company info exists
        $companyInfo = $this->db->fetchOne("SELECT id FROM company_info WHERE id = 1");
        if (!$companyInfo) {
            return true;
        }
        
        // Check if admin user exists
        $adminUser = $this->db->fetchOne("SELECT id FROM users WHERE role = 'admin'");
        if (!$adminUser) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get installation progress
     */
    public function getInstallationProgress() {
        $steps = $this->db->fetchAll("SELECT step, is_completed, completed_at FROM installation_state ORDER BY id");
        return $steps;
    }
    
    /**
     * Mark installation step as completed
     */
    public function completeInstallationStep($step, $data = null) {
        $existing = $this->db->fetchOne("SELECT id FROM installation_state WHERE step = ?", [$step]);
        
        if ($existing) {
            $this->db->update('installation_state', [
                'is_completed' => 1,
                'completed_at' => getCurrentTimestamp(),
                'data' => $data ? json_encode($data) : null
            ], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('installation_state', [
                'step' => $step,
                'is_completed' => 1,
                'completed_at' => getCurrentTimestamp(),
                'data' => $data ? json_encode($data) : null,
                'created_at' => getCurrentTimestamp()
            ]);
        }
        
        return true;
    }
    
    /**
     * Get current installation step
     */
    public function getCurrentInstallationStep() {
        $incomplete = $this->db->fetchOne(
            "SELECT step FROM installation_state WHERE is_completed = 0 ORDER BY id LIMIT 1"
        );
        
        if ($incomplete) {
            return $incomplete['step'];
        }
        
        // Check if all steps are completed
        $allSteps = ['environment', 'database', 'company', 'admin', 'complete'];
        $completedSteps = $this->db->fetchAll(
            "SELECT step FROM installation_state WHERE is_completed = 1"
        );
        
        $completedStepNames = array_column($completedSteps, 'step');
        
        foreach ($allSteps as $step) {
            if (!in_array($step, $completedStepNames)) {
                return $step;
            }
        }
        
        return 'complete';
    }
    
    /**
     * Convert data type for storage
     */
    private function getDataType($value) {
        if (is_null($value)) {
            return 'null';
        } elseif (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value) || is_object($value)) {
            return 'json';
        } else {
            return 'string';
        }
    }
    
    /**
     * Convert data type from storage
     */
    private function convertDataType($value, $dataType) {
        switch ($dataType) {
            case 'null':
                return null;
            case 'boolean':
                return (bool) $value;
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Encrypt value
     */
    private function encrypt($value) {
        // Simple encryption - in production, use proper encryption
        return base64_encode($value);
    }
    
    /**
     * Decrypt value
     */
    private function decrypt($value) {
        // Simple decryption - in production, use proper decryption
        return base64_decode($value);
    }
}

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
 * Database Management Class
 * Best Jobs in TA - SQLite Database Handler (sqlite3 extension)
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class Database {
    private $db;
    private static $instance = null;
    
    private function __construct() {
        $this->connect();
        $this->initializeTables();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        // Ensure db folder exists
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $this->db = new SQLite3(DB_PATH);
        // Enable foreign key constraints
        $this->db->exec('PRAGMA foreign_keys = ON');
    }
    
    private function initializeTables() {
        $this->createTables();
    }
    
    private function createTables() {
        $tables = [
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    first_name VARCHAR(50),
                    last_name VARCHAR(50),
                    role VARCHAR(20) DEFAULT 'user',
                    api_key VARCHAR(255) UNIQUE,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'contacts' => "
                CREATE TABLE IF NOT EXISTS contacts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    first_name VARCHAR(50) NOT NULL,
                    last_name VARCHAR(50) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    phone VARCHAR(20),
                    company VARCHAR(100),
                    position VARCHAR(100),
                    address TEXT,
                    city VARCHAR(50),
                    state VARCHAR(50),
                    zip_code VARCHAR(20),
                    country VARCHAR(50),
                    twitter_handle VARCHAR(50),
                    linkedin_profile VARCHAR(255),
                    telegram_username VARCHAR(50),
                    discord_username VARCHAR(50),
                    github_username VARCHAR(50),
                    website VARCHAR(255),
                    contact_type VARCHAR(10) DEFAULT 'lead',
                    contact_status VARCHAR(20) DEFAULT 'new',
                    source VARCHAR(50),
                    assigned_to INTEGER,
                    notes TEXT,
                    first_purchase_date DATE,
                    total_purchases DECIMAL(10,2) DEFAULT 0.00,
                    last_purchase_date DATE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'deals' => "
                CREATE TABLE IF NOT EXISTS deals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(200) NOT NULL,
                    contact_id INTEGER NOT NULL,
                    amount DECIMAL(10,2),
                    stage VARCHAR(50) DEFAULT 'prospecting',
                    probability INTEGER DEFAULT 0,
                    expected_close_date DATE,
                    assigned_to INTEGER,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (contact_id) REFERENCES contacts(id)
                )
            ",
            'webhooks' => "
                CREATE TABLE IF NOT EXISTS webhooks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    url VARCHAR(255) NOT NULL,
                    events TEXT NOT NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ",
            'api_requests' => "
                CREATE TABLE IF NOT EXISTS api_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_id VARCHAR(64) UNIQUE,
                    user_id INTEGER,
                    endpoint VARCHAR(100),
                    method VARCHAR(10),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    response_code INTEGER,
                    response_time DECIMAL(10,3),
                    status VARCHAR(20) DEFAULT 'pending',
                    result TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    completed_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ",
            'settings' => "
                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    show_default_credentials BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            "
        ];
        foreach ($tables as $table => $sql) {
            $this->db->exec($sql);
        }
        // MIGRATION: Add updated_at to users if missing
        $columns = $this->getTableInfo('users');
        $hasUpdatedAt = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'updated_at') {
                $hasUpdatedAt = true;
                break;
            }
        }
        if (!$hasUpdatedAt) {
            $this->db->exec("ALTER TABLE users ADD COLUMN updated_at DATETIME");
            $this->db->exec("UPDATE users SET updated_at = COALESCE(created_at, datetime('now'))");
        }
        
        // MIGRATION: Make email nullable in contacts table
        $this->migrateContactsEmailNullable();
        
        $this->createDefaultAdmin();
        $this->createDefaultSettings();
    }
    private function createDefaultAdmin() {
        $result = $this->db->querySingle("SELECT COUNT(*) as count FROM users");
        if ($result == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $apiKey = generateApiKey();
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, api_key) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, 'admin', SQLITE3_TEXT);
            $stmt->bindValue(2, 'admin@bestjobsinta.com', SQLITE3_TEXT);
            $stmt->bindValue(3, $adminPassword, SQLITE3_TEXT);
            $stmt->bindValue(4, 'Admin', SQLITE3_TEXT);
            $stmt->bindValue(5, 'User', SQLITE3_TEXT);
            $stmt->bindValue(6, 'admin', SQLITE3_TEXT);
            $stmt->bindValue(7, $apiKey, SQLITE3_TEXT);
            $stmt->execute();
            if (DEBUG_MODE) {
                error_log("Default admin user created with API key: $apiKey");
            }
        }
    }
    
    private function createDefaultSettings() {
        $result = $this->db->querySingle("SELECT COUNT(*) as count FROM settings");
        if ($result == 0) {
            $sql = "INSERT INTO settings (show_default_credentials, created_at, updated_at) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, 1, SQLITE3_INTEGER);
            $stmt->bindValue(2, getCurrentTimestamp(), SQLITE3_TEXT);
            $stmt->bindValue(3, getCurrentTimestamp(), SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    
    private function migrateContactsEmailNullable() {
        // Check if email is still NOT NULL in contacts table
        $columns = $this->getTableInfo('contacts');
        $emailColumn = null;
        foreach ($columns as $col) {
            if ($col['name'] === 'email') {
                $emailColumn = $col;
                break;
            }
        }
        
        if ($emailColumn && $emailColumn['notnull'] == 1) {
            // Email is still NOT NULL, we need to recreate the table
            try {
                // Create a backup of existing data
                $this->db->exec("CREATE TABLE contacts_backup AS SELECT * FROM contacts");
                
                // Drop the old table
                $this->db->exec("DROP TABLE contacts");
                
                // Create new table with nullable email
                $this->db->exec("
                    CREATE TABLE contacts (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        first_name VARCHAR(50) NOT NULL,
                        last_name VARCHAR(50) NOT NULL,
                        email VARCHAR(100) UNIQUE,
                        phone VARCHAR(20),
                        company VARCHAR(100),
                        position VARCHAR(100),
                        address TEXT,
                        city VARCHAR(50),
                        state VARCHAR(50),
                        zip_code VARCHAR(20),
                        country VARCHAR(50),
                        twitter_handle VARCHAR(50),
                        linkedin_profile VARCHAR(255),
                        telegram_username VARCHAR(50),
                        discord_username VARCHAR(50),
                        github_username VARCHAR(50),
                        website VARCHAR(255),
                        contact_type VARCHAR(10) DEFAULT 'lead',
                        contact_status VARCHAR(20) DEFAULT 'new',
                        source VARCHAR(50),
                        assigned_to INTEGER,
                        notes TEXT,
                        first_purchase_date DATE,
                        total_purchases DECIMAL(10,2) DEFAULT 0.00,
                        last_purchase_date DATE,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                // Copy data back
                $this->db->exec("INSERT INTO contacts SELECT * FROM contacts_backup");
                
                // Drop backup table
                $this->db->exec("DROP TABLE contacts_backup");
                
                if (DEBUG_MODE) {
                    error_log("Migrated contacts table to make email nullable");
                }
            } catch (Exception $e) {
                if (DEBUG_MODE) {
                    error_log("Migration failed: " . $e->getMessage());
                }
                // If migration fails, try to restore from backup
                try {
                    $this->db->exec("DROP TABLE IF EXISTS contacts");
                    $this->db->exec("ALTER TABLE contacts_backup RENAME TO contacts");
                } catch (Exception $e2) {
                    if (DEBUG_MODE) {
                        error_log("Backup restoration failed: " . $e2->getMessage());
                    }
                }
            }
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue(is_int($k) ? $k+1 : ':' . $k, $v, $type);
        }
        $result = $stmt->execute();
        return $result;
    }
    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }
    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }
    public function insert($table, $data) {
        $cleanData = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $cleanData[$key] = json_encode($value);
            } else {
                $cleanData[$key] = $value;
            }
        }
        $columns = implode(', ', array_keys($cleanData));
        $placeholders = implode(', ', array_fill(0, count($cleanData), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare insert statement: ' . $this->db->lastErrorMsg());
        }
        $i = 1;
        foreach ($cleanData as $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($i, $value, $type);
            $i++;
        }
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception('Insert failed: ' . $this->db->lastErrorMsg());
        }
        return $this->db->lastInsertRowID();
    }
    public function update($table, $data, $where, $whereParams = []) {
        $cleanData = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $cleanData[$key] = json_encode($value);
            } else {
                $cleanData[$key] = $value;
            }
        }
        if (empty($cleanData)) {
            throw new Exception('No valid data to update');
        }
        $setClause = implode(', ', array_map(function($k) { return "$k = ?"; }, array_keys($cleanData)));
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare update statement: ' . $this->db->lastErrorMsg());
        }
        $i = 1;
        foreach ($cleanData as $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($i, $value, $type);
            $i++;
        }
        foreach ($whereParams as $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($i, $value, $type);
            $i++;
        }
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception('Update failed: ' . $this->db->lastErrorMsg());
        }
        return true;
    }
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare delete statement: ' . $this->db->lastErrorMsg());
        }
        $i = 1;
        foreach ($params as $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($i, $value, $type);
            $i++;
        }
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception('Delete failed: ' . $this->db->lastErrorMsg());
        }
        return true;
    }
    public function beginTransaction() {
        $this->db->exec('BEGIN TRANSACTION');
    }
    public function commit() {
        $this->db->exec('COMMIT');
    }
    public function rollback() {
        $this->db->exec('ROLLBACK');
    }
    public function backup() {
        // Not implemented for sqlite3
    }
    public function getTableInfo($table) {
        $result = $this->db->query("PRAGMA table_info($table)");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row;
        }
        return $columns;
    }
    public function getLastInsertId() {
        return $this->db->lastInsertRowID();
    }
} 
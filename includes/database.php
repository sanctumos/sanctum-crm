<?php
/**
 * Database Management Class
 * FreeOpsDAO CRM - SQLite Database Handler
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class Database {
    private $pdo;
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
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign key constraints
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    private function initializeTables() {
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
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
                    address TEXT,
                    city VARCHAR(50),
                    state VARCHAR(50),
                    zip_code VARCHAR(20),
                    country VARCHAR(50),
                    evm_address VARCHAR(42),
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
                    user_id INTEGER NOT NULL,
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
                    status VARCHAR(20) DEFAULT 'pending',
                    result TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    completed_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            "
        ];
        
        foreach ($tables as $table => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                error_log("Failed to create table $table: " . $e->getMessage());
                throw new Exception("Database initialization failed");
            }
        }
        
        // Create default admin user if no users exist
        $this->createDefaultAdmin();
    }
    
    private function createDefaultAdmin() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $apiKey = generateApiKey();
            
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role, api_key) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'admin',
                'admin@freeopsdao.com',
                $adminPassword,
                'Admin',
                'User',
                'admin',
                $apiKey
            ]);
            
            if (DEBUG_MODE) {
                error_log("Default admin user created with API key: $apiKey");
            }
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "$column = :$column";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));
        
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function backup() {
        $backupDir = DB_BACKUP_PATH;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . 'crm_backup_' . date('Y-m-d_H-i-s') . '.db';
        
        if (copy(DB_PATH, $backupFile)) {
            return $backupFile;
        } else {
            throw new Exception("Database backup failed");
        }
    }
    
    public function getTableInfo($table) {
        $sql = "PRAGMA table_info($table)";
        return $this->fetchAll($sql);
    }
    
    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
} 
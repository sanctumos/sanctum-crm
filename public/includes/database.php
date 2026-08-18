<?php
/**
 * Database Management Class
 * Sanctum CRM - SQLite Database Handler (sqlite3 extension)
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class Database {
    private $db;
    private static $instance = null;
    /** When true, constructor skips MigrationRunner (used by migrate CLI). */
    private static $skipAutoMigrate = false;
    private static $skinLabEnsured = false;
    private static $contactDataSidecarEnsured = false;
    
    private function __construct() {
        $this->connect();
        $this->initializeTables();
        $this->ensureSkinLabColumns();
        $this->ensureMustChangePasswordColumn();
        $this->ensureContactDataSidecar();
        if (!self::$skipAutoMigrate && self::autoMigrateEnabled()) {
            require_once __DIR__ . '/MigrationRunner.php';
            (new MigrationRunner($this))->migrate(false);
        }
    }

    public static function autoMigrateEnabled(): bool
    {
        if (defined('CRM_TESTING') && CRM_TESTING) {
            return true;
        }
        $env = getenv('CRM_AUTO_MIGRATE');
        if ($env === false || $env === '') {
            $env = $_ENV['CRM_AUTO_MIGRATE'] ?? '';
        }
        return $env === '1' || strtolower((string) $env) === 'true';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function getInstanceWithoutAutoMigrate(): Database
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$skipAutoMigrate = true;
        try {
            self::$instance = new self();
        } finally {
            self::$skipAutoMigrate = false;
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
        $this->migrateContactsEmailNullable();
        $this->ensureEnrichmentColumns();
        $this->ensureSettingsColumns();
        $this->ensureConfigTables();
        $this->ensureEnrichmentCronTables();
        $this->ensureContactTagsTable();
        // First-boot wizard owns admin creation — do not seed default admin.
        $this->createDefaultSettings();
    }

    public function applyBaselineSchema(): void
    {
        $this->createTables();
        $this->migrateContactsEmailNullable();
        $this->ensureEnrichmentColumns();
        $this->ensureSettingsColumns();
        $this->ensureConfigTables();
        $this->ensureSkinLabColumns();
        $this->ensureMustChangePasswordColumn();
        $this->ensureContactDataSidecar();
        $this->ensureEnrichmentCronTables();
        $this->ensureContactTagsTable();
        $this->createDefaultSettings();
    }

    public function ensureContactDataSidecar(): void
    {
        if (self::$contactDataSidecarEnsured) {
            return;
        }
        self::$contactDataSidecarEnsured = true;
        try {
            require_once __DIR__ . '/ContactDataStore.php';
            (new ContactDataStore($this))->ensureSchema();
        } catch (Exception $e) {
            self::$contactDataSidecarEnsured = false;
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('ensureContactDataSidecar: ' . $e->getMessage());
            }
        }
    }

    public function ensureSkinLabColumns(): void
    {
        if (self::$skinLabEnsured) {
            return;
        }
        self::$skinLabEnsured = true;
        try {
            $userNames = array_column($this->getTableInfo('users'), 'name');
            if (!in_array('skin_slug', $userNames, true)) {
                $this->db->exec('ALTER TABLE users ADD COLUMN skin_slug TEXT DEFAULT NULL');
            }
            $settingsNames = array_column($this->getTableInfo('settings'), 'name');
            if (!in_array('default_skin_slug', $settingsNames, true)) {
                $this->db->exec("ALTER TABLE settings ADD COLUMN default_skin_slug TEXT DEFAULT 'hey'");
            }
            $this->db->exec(
                "UPDATE settings SET default_skin_slug = 'hey'
                 WHERE id = 1 AND (default_skin_slug IS NULL OR default_skin_slug = '')"
            );
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('ensureSkinLabColumns: ' . $e->getMessage());
            }
            self::$skinLabEnsured = false;
        }
    }

    public function ensureMustChangePasswordColumn(): void
    {
        try {
            $userNames = array_column($this->getTableInfo('users'), 'name');
            if (!in_array('must_change_password', $userNames, true)) {
                $this->db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER DEFAULT 0');
            }
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('ensureMustChangePasswordColumn: ' . $e->getMessage());
            }
        }
    }

    private function ensureContactTagsTable(): void
    {
        try {
            require_once __DIR__ . '/ContactTagService.php';
            (new ContactTagService($this))->ensureSchema();
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('contact_tags schema: ' . $e->getMessage());
            }
        }
    }

    private function ensureEnrichmentCronTables() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS enrichment_cron_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME,
                status VARCHAR(20) NOT NULL DEFAULT 'running',
                selected_count INTEGER DEFAULT 0,
                processed_count INTEGER DEFAULT 0,
                enriched_count INTEGER DEFAULT 0,
                failed_count INTEGER DEFAULT 0,
                skipped_count INTEGER DEFAULT 0,
                skipped_reason VARCHAR(255),
                error_summary TEXT,
                config_snapshot TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
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
                    must_change_password INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'contacts' => "
                CREATE TABLE IF NOT EXISTS contacts (
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
            ",
            'company_info' => "
                CREATE TABLE IF NOT EXISTS company_info (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    company_name VARCHAR(255) NOT NULL,
                    timezone VARCHAR(50) DEFAULT 'UTC',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'system_config' => "
                CREATE TABLE IF NOT EXISTS system_config (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    category VARCHAR(50) NOT NULL,
                    config_key VARCHAR(100) NOT NULL,
                    config_value TEXT,
                    data_type VARCHAR(20) DEFAULT 'string',
                    is_encrypted BOOLEAN DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(category, config_key)
                )
            ",
            'installation_state' => "
                CREATE TABLE IF NOT EXISTS installation_state (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    step VARCHAR(50) NOT NULL,
                    is_completed BOOLEAN DEFAULT 0,
                    completed_at DATETIME,
                    data TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    
    private function ensureSettingsColumns() {
        // Check if RocketReach columns exist in settings table, add them if they don't
        $columns = $this->getTableInfo('settings');
        $existingColumns = array_column($columns, 'name');

        $settingsColumns = [
            'rocketreach_api_key' => 'VARCHAR(255)',
            'default_skin_slug' => "TEXT DEFAULT 'hey'",
            'enrichment_cron_enabled' => 'INTEGER DEFAULT 0',
            'enrichment_cron_interval_minutes' => 'INTEGER DEFAULT 60',
            'enrichment_cron_strategy' => 'VARCHAR(50) DEFAULT "auto"',
            'enrichment_cron_max_per_run' => 'INTEGER DEFAULT 10',
            'enrichment_cron_max_per_day' => 'INTEGER DEFAULT 400',
            'enrichment_cron_max_attempts_per_contact' => 'INTEGER DEFAULT 3',
            'enrichment_cron_retry_failed' => 'INTEGER DEFAULT 0',
            'enrichment_cron_statuses' => 'TEXT DEFAULT \'["pending","empty"]\'',
            'enrichment_cron_contact_types' => 'TEXT DEFAULT \'["lead"]\'',
            'enrichment_cron_contact_statuses' => 'TEXT DEFAULT \'["new","qualified"]\'',
            'enrichment_cron_sources' => 'TEXT DEFAULT \'[]\'',
            'enrichment_cron_assigned_to' => 'VARCHAR(50) DEFAULT ""',
            'enrichment_cron_min_contact_age_days' => 'INTEGER DEFAULT 0'
        ];

        foreach ($settingsColumns as $columnName => $columnDef) {
            if (!in_array($columnName, $existingColumns)) {
                try {
                    $this->db->exec("ALTER TABLE settings ADD COLUMN {$columnName} {$columnDef}");
                } catch (Exception $e) {
                    // Column might already exist, ignore error
                }
            }
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
        if ($stmt === false) {
            $error = $this->db->lastErrorMsg();
            error_log("Database prepare error: " . $error . " | SQL: " . $sql);
            throw new Exception("Database query preparation failed: " . $error);
        }
        foreach ($params as $k => $v) {
            $type = is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue(is_int($k) ? $k+1 : ':' . $k, $v, $type);
        }
        $result = $stmt->execute();
        if ($result === false) {
            $error = $this->db->lastErrorMsg();
            error_log("Database execute error: " . $error . " | SQL: " . $sql);
            throw new Exception("Database query execution failed: " . $error);
        }
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
            if ($value === null) {
                $stmt->bindValue($i, null, SQLITE3_NULL);
            } else {
                $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
                $stmt->bindValue($i, $value, $type);
            }
            $i++;
        }
        foreach ($whereParams as $value) {
            if ($value === null) {
                $stmt->bindValue($i, null, SQLITE3_NULL);
                $i++;
                continue;
            }
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
    
    private function ensureEnrichmentColumns() {
        // Check if enrichment columns exist, add them if they don't
        $columns = $this->getTableInfo('contacts');
        $existingColumns = array_column($columns, 'name');
        
        $enrichmentColumns = [
            'enriched_at' => 'DATETIME',
            'enrichment_source' => 'VARCHAR(50)',
            'enrichment_data' => 'TEXT',
            'enrichment_raw' => 'TEXT',
            'rocketreach_profile_id' => 'INTEGER',
            'enrichment_status' => 'VARCHAR(20) DEFAULT "pending"',
            'enrichment_attempts' => 'INTEGER DEFAULT 0',
            'enrichment_error' => 'TEXT'
        ];
        
        foreach ($enrichmentColumns as $columnName => $columnDef) {
            if (!in_array($columnName, $existingColumns)) {
                try {
                    $this->db->exec("ALTER TABLE contacts ADD COLUMN {$columnName} {$columnDef}");
                } catch (Exception $e) {
                    // Column might already exist, ignore error
                }
            }
        }
    }
    
    private function ensureConfigTables() {
        // Check if config tables exist, create them if they don't
        $tables = ['company_info', 'system_config', 'installation_state'];
        
        foreach ($tables as $table) {
            $result = $this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            if (!$result) {
                // Table doesn't exist, create it
                switch ($table) {
                    case 'company_info':
                        $this->db->exec("
                            CREATE TABLE IF NOT EXISTS company_info (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                company_name VARCHAR(255) NOT NULL,
                                timezone VARCHAR(50) DEFAULT 'UTC',
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                            )
                        ");
                        break;
                    case 'system_config':
                        $this->db->exec("
                            CREATE TABLE IF NOT EXISTS system_config (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                category VARCHAR(50) NOT NULL,
                                config_key VARCHAR(100) NOT NULL,
                                config_value TEXT,
                                data_type VARCHAR(20) DEFAULT 'string',
                                is_encrypted BOOLEAN DEFAULT 0,
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                UNIQUE(category, config_key)
                            )
                        ");
                        break;
                    case 'installation_state':
                        $this->db->exec("
                            CREATE TABLE IF NOT EXISTS installation_state (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                step VARCHAR(50) NOT NULL,
                                is_completed BOOLEAN DEFAULT 0,
                                completed_at DATETIME,
                                data TEXT,
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                            )
                        ");
                        break;
                }
            }
        }
    }
    
} 
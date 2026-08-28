<?php
/**
 * Database configuration for Len webchat bridge (separate SQLite from docket.db).
 */

if (!defined('CRM_LOADED')) {
    define('CRM_LOADED', true);
}
require_once dirname(__DIR__, 2) . '/includes/config.php';

if (!defined('LEN_BRIDGE_DB_PATH')) {
    define(
        'LEN_BRIDGE_DB_PATH',
        crm_env_or_default('CRM_LEN_BRIDGE_DB_PATH', dirname(DB_PATH) . '/len_bridge_webchat.db')
    );
}
if (!defined('LEN_BRIDGE_DB_TIMEOUT')) {
    define('LEN_BRIDGE_DB_TIMEOUT', 30);
}

function get_db_connection() {
    try {
        $pdo = new PDO('sqlite:' . LEN_BRIDGE_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, LEN_BRIDGE_DB_TIMEOUT);
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA synchronous=NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new Exception('Database connection failed');
    }
}

function init_database() {
    $pdo = get_db_connection();
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS web_chat_sessions (
            id VARCHAR(64) PRIMARY KEY,
            uid VARCHAR(16) UNIQUE,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_active TEXT DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            metadata TEXT
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS web_chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(64),
            message TEXT,
            timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
            processed INTEGER DEFAULT 0,
            broca_message_id INTEGER NULL,
            FOREIGN KEY (session_id) REFERENCES web_chat_sessions(id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS web_chat_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(64),
            response TEXT,
            timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
            message_id INTEGER NULL,
            FOREIGN KEY (session_id) REFERENCES web_chat_sessions(id),
            FOREIGN KEY (message_id) REFERENCES web_chat_messages(id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            ip_address VARCHAR(45),
            endpoint VARCHAR(50),
            count INTEGER DEFAULT 1,
            window_start TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ip_address, endpoint)
        )
    ");
    
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_session ON web_chat_messages(session_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_processed ON web_chat_messages(processed)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_responses_session ON web_chat_responses(session_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_window ON rate_limits(window_start)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_uid ON web_chat_sessions(uid)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_ip ON web_chat_sessions(ip_address)');

    len_bridge_ensure_message_metadata_column($pdo);
}

/**
 * Per-message JSON (page context at send time) for Broca inbox.
 */
function len_bridge_ensure_message_metadata_column(PDO $pdo): void {
    $stmt = $pdo->query('PRAGMA table_info(web_chat_messages)');
    $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($cols as $col) {
        if (($col['name'] ?? '') === 'metadata') {
            return;
        }
    }
    $pdo->exec('ALTER TABLE web_chat_messages ADD COLUMN metadata TEXT');
}

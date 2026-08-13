<?php
/**
 * Webhook delivery queue table + indexes.
 */

return [
    'version' => '20260729_002_webhook_delivery_queue',
    'description' => 'Async webhook_delivery_queue with retry/dead-letter columns',
    'up' => static function (Database $db): void {
        $sqlite = $db->getConnection();
        $sqlite->exec(
            "CREATE TABLE IF NOT EXISTS webhook_delivery_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                webhook_id INTEGER,
                url VARCHAR(255) NOT NULL,
                event VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                next_attempt_at DATETIME NOT NULL,
                last_http_code INTEGER,
                last_error TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME
            )"
        );
        $sqlite->exec(
            "CREATE INDEX IF NOT EXISTS idx_webhook_queue_due
             ON webhook_delivery_queue(status, next_attempt_at)"
        );
        $sqlite->exec(
            "CREATE INDEX IF NOT EXISTS idx_webhook_queue_webhook_id
             ON webhook_delivery_queue(webhook_id)"
        );
    },
];

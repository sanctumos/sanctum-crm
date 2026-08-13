<?php
/**
 * Baseline schema — formerly Database::initializeTables() hot-path bootstrap.
 * Idempotent: safe on existing production DBs.
 */

return [
    'version' => '20260729_001_baseline',
    'description' => 'Core tables, Sanctum install tables, enrichment/settings, contact_tags',
    'up' => static function (Database $db): void {
        $db->applyBaselineSchema();
    },
];

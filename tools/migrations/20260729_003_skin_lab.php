<?php
/**
 * Skin Lab columns — historical registry row.
 *
 * Prefer Database::ensureSkinLabColumns() (run-once-on-load). This migration
 * remains so MigrationRunner status stays honest on hosts that already applied it;
 * up() is idempotent and delegates to the same ensure.
 */

return [
    'version' => '20260729_003_skin_lab',
    'description' => 'Skin Lab columns (delegates to ensureSkinLabColumns on load)',
    'up' => static function (Database $db): void {
        $db->ensureSkinLabColumns();
    },
];

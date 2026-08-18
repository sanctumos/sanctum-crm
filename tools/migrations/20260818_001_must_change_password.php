<?php
/**
 * Force password change on first login — historical registry row.
 *
 * Prefer Database::ensureMustChangePasswordColumn() (run on load). This
 * migration stays so MigrationRunner status is honest; up() is idempotent.
 */

return [
    'version' => '20260818_001_must_change_password',
    'description' => 'users.must_change_password (delegates to ensureMustChangePasswordColumn)',
    'up' => static function (Database $db): void {
        $db->ensureMustChangePasswordColumn();
    },
];

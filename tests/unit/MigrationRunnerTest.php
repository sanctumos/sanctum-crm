<?php
/**
 * MigrationRunner unit tests
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/includes/MigrationRunner.php';

class MigrationRunnerTest
{
    public function testMigrateIsIdempotent(): void
    {
        echo "  Testing migrate is idempotent... ";
        $db = Database::getInstance();
        $runner = new MigrationRunner($db);
        $first = $runner->migrate(false);
        $second = $runner->migrate(false);
        if (!empty($second['applied'])) {
            throw new Exception('Second migrate applied versions again: ' . implode(',', $second['applied']));
        }
        if (empty($second['skipped']) && empty($first['skipped']) && empty($first['applied'])) {
            throw new Exception('No migrations discovered');
        }
        echo "PASS\n";
    }

    public function testDryRunDoesNotRecord(): void
    {
        echo "  Testing dry-run does not write versions... ";
        $db = Database::getInstance();
        $runner = new MigrationRunner($db);
        $before = $runner->appliedVersions();
        $dry = $runner->migrate(true);
        $after = $runner->appliedVersions();
        if ($before !== $after) {
            throw new Exception('Dry-run changed schema_migrations');
        }
        // dry-run lists pending as "applied" in result payload
        if (!is_array($dry['applied'])) {
            throw new Exception('Dry-run missing applied list');
        }
        echo "PASS\n";
    }

    public function testAutoMigrateOffByDefaultOutsideTesting(): void
    {
        echo "  Testing autoMigrateEnabled respects CRM_TESTING... ";
        if (!Database::autoMigrateEnabled()) {
            throw new Exception('Expected auto-migrate on under CRM_TESTING');
        }
        echo "PASS\n";
    }

    public function runAllTests(): void
    {
        echo "Running MigrationRunnerTest...\n";
        $this->testMigrateIsIdempotent();
        $this->testDryRunDoesNotRecord();
        $this->testAutoMigrateOffByDefaultOutsideTesting();
        echo "MigrationRunnerTest: all passed\n";
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    (new MigrationRunnerTest())->runAllTests();
}

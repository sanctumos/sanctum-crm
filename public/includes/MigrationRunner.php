<?php
/**
 * Explicit, versioned, idempotent schema migrations (off the hot request path).
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class MigrationRunner
{
    private Database $db;
    private string $migrationsDir;

    public function __construct(Database $db, ?string $migrationsDir = null)
    {
        $this->db = $db;
        $this->migrationsDir = $migrationsDir
            ?? dirname(__DIR__, 2) . '/tools/migrations';
    }

    public function ensureRegistry(): void
    {
        $this->db->getConnection()->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                description TEXT,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    /** @return list<string> */
    public function appliedVersions(): array
    {
        $this->ensureRegistry();
        $rows = $this->db->fetchAll(
            'SELECT version FROM schema_migrations ORDER BY version ASC'
        );
        return array_map(static fn($r) => (string) $r['version'], $rows);
    }

    /**
     * Discover migration PHP files. Each file returns:
     * ['version' => string, 'description' => string, 'up' => callable(Database): void]
     *
     * @return list<array{version:string,description:string,up:callable,file:string}>
     */
    public function discover(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }
        $files = glob($this->migrationsDir . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $out = [];
        foreach ($files = array_values($files) as $file) {
            $base = basename($file);
            if ($base === 'README.md' || str_starts_with($base, '_')) {
                continue;
            }
            $spec = require $file;
            if (!is_array($spec) || empty($spec['version']) || !isset($spec['up']) || !is_callable($spec['up'])) {
                throw new RuntimeException("Invalid migration file: {$file}");
            }
            $out[] = [
                'version' => (string) $spec['version'],
                'description' => (string) ($spec['description'] ?? ''),
                'up' => $spec['up'],
                'file' => $file,
            ];
        }
        return $out;
    }

    /**
     * @return array{applied:list<string>,skipped:list<string>,dry_run:bool}
     */
    public function migrate(bool $dryRun = false): array
    {
        $this->ensureRegistry();
        $applied = $this->appliedVersions();
        $appliedSet = array_fill_keys($applied, true);
        $result = ['applied' => [], 'skipped' => [], 'dry_run' => $dryRun];

        foreach ($this->discover() as $mig) {
            $version = $mig['version'];
            if (isset($appliedSet[$version])) {
                $result['skipped'][] = $version;
                continue;
            }
            if ($dryRun) {
                $result['applied'][] = $version;
                continue;
            }
            $sqlite = $this->db->getConnection();
            $sqlite->exec('BEGIN');
            try {
                ($mig['up'])($this->db);
                $this->db->insert('schema_migrations', [
                    'version' => $version,
                    'description' => $mig['description'],
                    'applied_at' => date('Y-m-d H:i:s'),
                ]);
                $sqlite->exec('COMMIT');
                $result['applied'][] = $version;
            } catch (Throwable $e) {
                $sqlite->exec('ROLLBACK');
                throw new RuntimeException(
                    "Migration {$version} failed: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $result;
    }

    public function status(): array
    {
        $applied = array_fill_keys($this->appliedVersions(), true);
        $pending = [];
        $done = [];
        foreach ($this->discover() as $mig) {
            $row = [
                'version' => $mig['version'],
                'description' => $mig['description'],
                'file' => basename($mig['file']),
            ];
            if (isset($applied[$mig['version']])) {
                $done[] = $row;
            } else {
                $pending[] = $row;
            }
        }
        return ['applied' => $done, 'pending' => $pending];
    }
}

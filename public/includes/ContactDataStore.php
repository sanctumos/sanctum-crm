<?php
/**
 * Contact data sidecar — Docket-shaped runs + typed facts.
 * Writers: merge, rocketreach, future enrichers. Primary card stays conforming;
 * raw blobs and overflow live here (Doc #919 v0.3).
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ContactDataStore
{
    public const SOURCES = ['merge', 'rocketreach', 'linkedin', 'import', 'other'];

    public const FACT_TYPES = [
        'email', 'phone', 'name', 'address', 'social', 'employer', 'username', 'other',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $pdo = $this->db->getConnection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_data_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact_id INTEGER NOT NULL,
                source VARCHAR(40) NOT NULL,
                outcome VARCHAR(40),
                label VARCHAR(120),
                actor_user_id INTEGER,
                raw_payload TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
            )
        ");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_data_runs_contact ON contact_data_runs(contact_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_data_runs_source ON contact_data_runs(source)');

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_data_facts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact_id INTEGER NOT NULL,
                run_id INTEGER,
                source VARCHAR(40) NOT NULL,
                fact_type VARCHAR(40) NOT NULL,
                value TEXT NOT NULL,
                label VARCHAR(120),
                confidence REAL,
                meta_json TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
                FOREIGN KEY (run_id) REFERENCES contact_data_runs(id) ON DELETE CASCADE
            )
        ");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_data_facts_contact ON contact_data_facts(contact_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_data_facts_type ON contact_data_facts(fact_type)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_data_facts_value ON contact_data_facts(value)');

        // No FKs: accepted merges delete the absorbed contact; CASCADE would wipe the audit row.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_merge_candidates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                survivor_id INTEGER NOT NULL,
                merge_id INTEGER NOT NULL,
                confidence REAL NOT NULL,
                confidence_tier VARCHAR(20) NOT NULL,
                reason_codes TEXT NOT NULL,
                reason_summary TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                inspected_at DATETIME,
                resolved_at DATETIME,
                resolved_by_user_id INTEGER,
                merge_run_id INTEGER,
                fingerprint TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(fingerprint)
            )
        ");
        $this->rebuildMergeCandidatesWithoutFk($pdo);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_merge_candidates_status ON contact_merge_candidates(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_merge_candidates_tier ON contact_merge_candidates(confidence_tier)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_merge_candidates_survivor ON contact_merge_candidates(survivor_id)');
    }

    /** One-time rebuild if an earlier schema linked candidates to contacts with CASCADE. */
    private function rebuildMergeCandidatesWithoutFk(\SQLite3 $pdo): void
    {
        $res = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='contact_merge_candidates'");
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
        if (!$row || empty($row['sql']) || stripos($row['sql'], 'REFERENCES') === false) {
            return;
        }
        $pdo->exec('BEGIN');
        try {
            $pdo->exec("
                CREATE TABLE contact_merge_candidates__nofk (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    survivor_id INTEGER NOT NULL,
                    merge_id INTEGER NOT NULL,
                    confidence REAL NOT NULL,
                    confidence_tier VARCHAR(20) NOT NULL,
                    reason_codes TEXT NOT NULL,
                    reason_summary TEXT,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    inspected_at DATETIME,
                    resolved_at DATETIME,
                    resolved_by_user_id INTEGER,
                    merge_run_id INTEGER,
                    fingerprint TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(fingerprint)
                )
            ");
            $pdo->exec('INSERT OR IGNORE INTO contact_merge_candidates__nofk
                SELECT id, survivor_id, merge_id, confidence, confidence_tier, reason_codes, reason_summary,
                       status, inspected_at, resolved_at, resolved_by_user_id, merge_run_id, fingerprint,
                       created_at, updated_at
                FROM contact_merge_candidates');
            $pdo->exec('DROP TABLE contact_merge_candidates');
            $pdo->exec('ALTER TABLE contact_merge_candidates__nofk RENAME TO contact_merge_candidates');
            $pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /**
     * @param array{source?:string,outcome?:?string,label?:?string,actor_user_id?:?int,raw_payload?:mixed,facts?:list<array>} $run
     * @return array{run_id:int,fact_count:int}
     */
    public function recordRun(int $contactId, array $run): array
    {
        $source = strtolower(trim((string) ($run['source'] ?? 'other')));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'other';
        }

        $raw = $run['raw_payload'] ?? $run['raw'] ?? null;
        if (is_array($raw) || is_object($raw)) {
            $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            $rawJson = (string) ($raw ?? '{}');
        }
        if ($rawJson === false || $rawJson === '') {
            $rawJson = '{}';
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->db->insert('contact_data_runs', [
            'contact_id' => $contactId,
            'source' => $source,
            'outcome' => $this->nullableTrim($run['outcome'] ?? null),
            'label' => $this->nullableTrim($run['label'] ?? null, 120),
            'actor_user_id' => isset($run['actor_user_id']) ? (int) $run['actor_user_id'] : null,
            'raw_payload' => $rawJson,
            'created_at' => $now,
        ]);
        $runId = (int) $this->db->getLastInsertId();

        $factsIn = $run['facts'] ?? [];
        if (!is_array($factsIn)) {
            $factsIn = [];
        }
        $factCount = 0;
        foreach ($factsIn as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            if ($this->insertFact($contactId, $runId, $source, $fact, $now)) {
                $factCount++;
            }
        }

        return ['run_id' => $runId, 'fact_count' => $factCount];
    }

    /**
     * @param array{fact_type?:string,value?:mixed,label?:?string,confidence?:?float,meta?:mixed,meta_json?:mixed} $fact
     */
    private function insertFact(int $contactId, int $runId, string $source, array $fact, string $now): bool
    {
        $type = strtolower(trim((string) ($fact['fact_type'] ?? $fact['type'] ?? 'other')));
        if (!in_array($type, self::FACT_TYPES, true)) {
            $type = 'other';
        }
        $value = $fact['value'] ?? '';
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $meta = $fact['meta'] ?? $fact['meta_json'] ?? null;
        $metaJson = null;
        if ($meta !== null) {
            if (is_array($meta) || is_object($meta)) {
                $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $metaJson = (string) $meta;
            }
        }

        $this->db->insert('contact_data_facts', [
            'contact_id' => $contactId,
            'run_id' => $runId,
            'source' => $source,
            'fact_type' => $type,
            'value' => $value,
            'label' => $this->nullableTrim($fact['label'] ?? null, 120),
            'confidence' => isset($fact['confidence']) ? (float) $fact['confidence'] : null,
            'meta_json' => $metaJson,
            'created_at' => $now,
        ]);
        return true;
    }

    /**
     * @return list<array>
     */
    public function listRuns(int $contactId, ?string $source = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if ($source !== null && $source !== '') {
            $rows = $this->db->fetchAll(
                'SELECT id, contact_id, source, outcome, label, actor_user_id, created_at,
                        LENGTH(raw_payload) AS raw_bytes
                 FROM contact_data_runs
                 WHERE contact_id = ? AND source = ?
                 ORDER BY id DESC LIMIT ?',
                [$contactId, strtolower($source), $limit]
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT id, contact_id, source, outcome, label, actor_user_id, created_at,
                        LENGTH(raw_payload) AS raw_bytes
                 FROM contact_data_runs
                 WHERE contact_id = ?
                 ORDER BY id DESC LIMIT ?',
                [$contactId, $limit]
            );
        }
        return is_array($rows) ? $rows : [];
    }

    public function getRun(int $contactId, int $runId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM contact_data_runs WHERE id = ? AND contact_id = ?',
            [$runId, $contactId]
        );
        if (!$row) {
            return null;
        }
        $decoded = json_decode((string) ($row['raw_payload'] ?? ''), true);
        $row['raw'] = $decoded ?? $row['raw_payload'];
        $row['facts'] = $this->listFactsForRun($contactId, $runId);
        return $row;
    }

    /**
     * @return list<array>
     */
    public function listFactsForRun(int $contactId, int $runId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM contact_data_facts WHERE contact_id = ? AND run_id = ? ORDER BY id ASC',
            [$contactId, $runId]
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array>
     */
    public function listFacts(int $contactId, ?string $factType = null): array
    {
        if ($factType !== null && $factType !== '') {
            $rows = $this->db->fetchAll(
                'SELECT * FROM contact_data_facts WHERE contact_id = ? AND fact_type = ? ORDER BY id DESC',
                [$contactId, strtolower($factType)]
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT * FROM contact_data_facts WHERE contact_id = ? ORDER BY id DESC',
                [$contactId]
            );
        }
        return is_array($rows) ? $rows : [];
    }

    /** Contact ids that have this email as a typed fact (case-insensitive). */
    public function findContactIdsByEmailFact(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT contact_id FROM contact_data_facts
             WHERE fact_type = 'email' AND LOWER(value) = LOWER(?)",
            [$email]
        );
        if (!is_array($rows)) {
            return [];
        }
        return array_values(array_map(static fn($r) => (int) $r['contact_id'], $rows));
    }

    /**
     * Extract RocketReach facts from raw person + optional normalized payload.
     *
     * @return list<array>
     */
    public function factsFromRocketReach(array $raw, ?array $normalized = null): array
    {
        $facts = [];
        $seen = [];

        $add = function (string $type, $value, ?string $label = null, $meta = null) use (&$facts, &$seen) {
            $v = is_string($value) ? trim($value) : $value;
            if ($v === null || $v === '') {
                return;
            }
            if (is_array($v) || is_object($v)) {
                return;
            }
            $v = trim((string) $v);
            if ($v === '') {
                return;
            }
            $key = $type . '|' . strtolower($v);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $fact = ['fact_type' => $type, 'value' => $v];
            if ($label !== null) {
                $fact['label'] = $label;
            }
            if ($meta !== null) {
                $fact['meta'] = $meta;
            }
            $facts[] = $fact;
        };

        foreach ([
            'recommended_professional_email' => 'recommended_professional',
            'recommended_email' => 'recommended',
            'current_work_email' => 'current_work',
            'recommended_personal_email' => 'recommended_personal',
            'current_personal_email' => 'current_personal',
        ] as $k => $label) {
            if (!empty($raw[$k])) {
                $add('email', $raw[$k], $label);
            }
        }
        foreach ($raw['emails'] ?? [] as $item) {
            $addr = is_array($item) ? ($item['email'] ?? '') : (string) $item;
            $meta = is_array($item) ? $item : null;
            $add('email', $addr, is_array($item) ? ($item['type'] ?? 'email') : 'email', $meta);
        }

        foreach ($raw['phones'] ?? [] as $item) {
            $num = is_array($item) ? ($item['number'] ?? '') : (string) $item;
            $add('phone', $num, is_array($item) && !empty($item['recommended']) ? 'recommended' : 'phone', is_array($item) ? $item : null);
        }

        if (!empty($raw['linkedin_url'])) {
            $add('social', $raw['linkedin_url'], 'linkedin');
        }
        $links = $raw['links'] ?? [];
        if (is_array($links)) {
            foreach ($links as $platform => $url) {
                if (is_string($url) && $url !== '') {
                    $add('social', $url, (string) $platform);
                }
            }
        }

        $employer = $raw['current_employer'] ?? ($normalized['company']['name'] ?? null);
        if ($employer) {
            $add('employer', $employer, 'current_employer');
        }
        if (!empty($raw['name'])) {
            $add('name', $raw['name'], 'display_name');
        }
        if (!empty($raw['location'])) {
            $add('address', $raw['location'], 'location');
        }

        return $facts;
    }

    /**
     * Lazy backfill: one rocketreach run from legacy contacts.enrichment_raw if missing.
     */
    public function backfillLegacyRocketReach(int $contactId, array $contact): ?int
    {
        $rawJson = $contact['enrichment_raw'] ?? null;
        if ($rawJson === null || trim((string) $rawJson) === '') {
            return null;
        }
        $existing = $this->db->fetchOne(
            "SELECT id FROM contact_data_runs WHERE contact_id = ? AND source = 'rocketreach' LIMIT 1",
            [$contactId]
        );
        if ($existing) {
            return (int) $existing['id'];
        }

        $raw = json_decode((string) $rawJson, true);
        if (!is_array($raw)) {
            $raw = ['_legacy_raw' => $rawJson];
        }
        $normalized = null;
        if (!empty($contact['enrichment_data'])) {
            $normalized = json_decode((string) $contact['enrichment_data'], true);
            if (!is_array($normalized)) {
                $normalized = null;
            }
        }
        $payload = [
            'legacy_backfill' => true,
            'raw' => $raw,
            'normalized' => $normalized,
        ];
        $result = $this->recordRun($contactId, [
            'source' => 'rocketreach',
            'outcome' => 'enriched',
            'label' => 'Legacy enrichment_raw backfill',
            'raw_payload' => $payload,
            'facts' => $this->factsFromRocketReach($raw, $normalized),
        ]);
        return $result['run_id'];
    }

    private function nullableTrim($v, ?int $max = null): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if ($max !== null && strlen($s) > $max) {
            $s = substr($s, 0, $max);
        }
        return $s;
    }
}

<?php
/**
 * Scheduled RocketReach enrichment orchestration.
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class EnrichmentCronService
{
    private Database $db;
    private $enrichmentService;

    public function __construct($enrichmentService = null)
    {
        $this->db = Database::getInstance();
        $this->enrichmentService = $enrichmentService;
    }

    public function getConfig(): array
    {
        $settings = $this->getSettings();

        return [
            'enabled' => (bool) ($settings['enrichment_cron_enabled'] ?? 0),
            'interval_minutes' => max(1, (int) ($settings['enrichment_cron_interval_minutes'] ?? 60)),
            'strategy' => $this->normalizeStrategy($settings['enrichment_cron_strategy'] ?? 'auto'),
            'max_per_run' => max(1, (int) ($settings['enrichment_cron_max_per_run'] ?? 10)),
            'max_per_day' => max(1, (int) ($settings['enrichment_cron_max_per_day'] ?? 400)),
            'max_attempts_per_contact' => max(1, (int) ($settings['enrichment_cron_max_attempts_per_contact'] ?? 3)),
            'retry_failed' => (bool) ($settings['enrichment_cron_retry_failed'] ?? 0),
            'eligible_enrichment_statuses' => $this->normalizeArray($settings['enrichment_cron_statuses'] ?? ['pending', 'empty']),
            'contact_types' => $this->normalizeArray($settings['enrichment_cron_contact_types'] ?? ['lead']),
            'contact_statuses' => $this->normalizeArray($settings['enrichment_cron_contact_statuses'] ?? ['new', 'qualified']),
            'sources' => $this->normalizeArray($settings['enrichment_cron_sources'] ?? []),
            'assigned_to' => trim((string) ($settings['enrichment_cron_assigned_to'] ?? '')),
            'min_contact_age_days' => max(0, (int) ($settings['enrichment_cron_min_contact_age_days'] ?? 0)),
        ];
    }

    public function updateConfig(array $input): array
    {
        $current = $this->getConfig();
        $data = [
            'enrichment_cron_enabled' => $this->readBoolean($input, 'enabled', $current['enabled']) ? 1 : 0,
            'enrichment_cron_interval_minutes' => max(1, (int) ($input['interval_minutes'] ?? $current['interval_minutes'])),
            'enrichment_cron_strategy' => $this->normalizeStrategy($input['strategy'] ?? $current['strategy']),
            'enrichment_cron_max_per_run' => max(1, (int) ($input['max_per_run'] ?? $current['max_per_run'])),
            'enrichment_cron_max_per_day' => max(1, (int) ($input['max_per_day'] ?? $current['max_per_day'])),
            'enrichment_cron_max_attempts_per_contact' => max(1, (int) ($input['max_attempts_per_contact'] ?? $current['max_attempts_per_contact'])),
            'enrichment_cron_retry_failed' => $this->readBoolean($input, 'retry_failed', $current['retry_failed']) ? 1 : 0,
            'enrichment_cron_statuses' => json_encode($this->normalizeArray($input['eligible_enrichment_statuses'] ?? $current['eligible_enrichment_statuses'])),
            'enrichment_cron_contact_types' => json_encode($this->normalizeArray($input['contact_types'] ?? $current['contact_types'])),
            'enrichment_cron_contact_statuses' => json_encode($this->normalizeArray($input['contact_statuses'] ?? $current['contact_statuses'])),
            'enrichment_cron_sources' => json_encode($this->normalizeArray($input['sources'] ?? $current['sources'])),
            'enrichment_cron_assigned_to' => trim((string) ($input['assigned_to'] ?? $current['assigned_to'])),
            'enrichment_cron_min_contact_age_days' => max(0, (int) ($input['min_contact_age_days'] ?? $current['min_contact_age_days'])),
            'updated_at' => getCurrentTimestamp(),
        ];

        $this->db->update('settings', $data, 'id = 1');
        return $this->getConfig();
    }

    public function getLastRun(): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM enrichment_cron_runs ORDER BY started_at DESC, id DESC LIMIT 1"
        );
    }

    public function run(bool $force = false): array
    {
        $config = $this->getConfig();

        if (!$config['enabled'] && !$force) {
            return $this->recordSkippedRun('disabled', $config);
        }
        if (!$force && !$this->isDue($config)) {
            return $this->recordSkippedRun('not_due', $config);
        }

        $remainingToday = $this->getRemainingDailyCapacity($config);
        if ($remainingToday <= 0) {
            return $this->recordSkippedRun('daily_cap_reached', $config);
        }

        $limit = min($config['max_per_run'], $remainingToday);
        $contacts = $this->getEligibleContacts($config, $limit);
        if (empty($contacts)) {
            return $this->recordSkippedRun('no_eligible_contacts', $config);
        }

        $runId = $this->startRun($config, count($contacts));
        $service = $this->getEnrichmentService();
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($contacts as $contact) {
            $processed++;
            try {
                $result = $service->enrichContact((int) $contact['id'], $config['strategy']);
                $outcome = $result['outcome'] ?? (empty($result['success']) ? 'failed' : 'enriched');
                if ($outcome === 'enriched') {
                    $successful++;
                } elseif ($outcome === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                    $errors[] = [
                        'contact_id' => (int) $contact['id'],
                        'error' => $result['message'] ?? 'Enrichment did not complete',
                    ];
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = [
                    'contact_id' => (int) $contact['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $status = $failed === 0 ? 'completed' : ($successful > 0 || $skipped > 0 ? 'partial' : 'failed');
        $this->finishRun($runId, [
            'completed_at' => getCurrentTimestamp(),
            'status' => $status,
            'processed_count' => $processed,
            'enriched_count' => $successful,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'error_summary' => empty($errors) ? null : json_encode($errors),
        ]);

        return [
            'success' => $status !== 'failed',
            'status' => $status,
            'run_id' => $runId,
            'selected' => count($contacts),
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function getSettings(): array
    {
        $settings = $this->db->fetchOne("SELECT * FROM settings WHERE id = 1");
        if (!$settings) {
            $this->db->insert('settings', [
                'show_default_credentials' => 1,
                'created_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp(),
            ]);
            $settings = $this->db->fetchOne("SELECT * FROM settings WHERE id = 1");
        }
        return $settings ?: [];
    }

    private function getEnrichmentService()
    {
        if ($this->enrichmentService) {
            return $this->enrichmentService;
        }
        if (class_exists('EnrichmentService')) {
            $this->enrichmentService = new EnrichmentService();
        } else {
            $settings = $this->getSettings();
            require_once __DIR__ . '/enrichment/EnrichmentProviders.php';
            $provider = EnrichmentProviders::normalize($settings['enrichment_provider'] ?? null);
            $hasKey = $provider === EnrichmentProviders::APOLLO
                ? !empty($settings['apollo_api_key'])
                : !empty($settings['rocketreach_api_key']);
            $this->enrichmentService = (!$hasKey && class_exists('MockLeadEnrichmentService'))
                ? new MockLeadEnrichmentService()
                : new LeadEnrichmentService();
        }
        return $this->enrichmentService;
    }

    private function isDue(array $config): bool
    {
        $lastRun = $this->db->fetchOne(
            "SELECT started_at FROM enrichment_cron_runs
             WHERE status IN ('completed', 'partial', 'failed')
             ORDER BY started_at DESC, id DESC LIMIT 1"
        );
        if (!$lastRun || empty($lastRun['started_at'])) {
            return true;
        }
        return time() >= strtotime($lastRun['started_at']) + ($config['interval_minutes'] * 60);
    }

    private function getRemainingDailyCapacity(array $config): int
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(processed_count), 0) as used
             FROM enrichment_cron_runs
             WHERE started_at >= date('now')"
        );
        return max(0, $config['max_per_day'] - (int) ($row['used'] ?? 0));
    }

    private function getEligibleContacts(array $config, int $limit): array
    {
        $where = [];
        $params = [];
        $this->appendInFilter($where, $params, 'contact_type', $config['contact_types']);
        $this->appendInFilter($where, $params, 'contact_status', $config['contact_statuses']);
        $this->appendInFilter($where, $params, 'source', $config['sources']);

        if ($config['assigned_to'] !== '') {
            $where[] = 'assigned_to = ?';
            $params[] = (int) $config['assigned_to'];
        }
        if ($config['min_contact_age_days'] > 0) {
            $where[] = "created_at <= datetime('now', ?)";
            $params[] = '-' . $config['min_contact_age_days'] . ' days';
        }

        $where[] = '(enrichment_attempts IS NULL OR enrichment_attempts < ?)';
        $params[] = $config['max_attempts_per_contact'];

        $statuses = $config['eligible_enrichment_statuses'];
        if ($config['retry_failed'] && !in_array('failed', $statuses, true)) {
            $statuses[] = 'failed';
        }
        $statusClause = $this->buildStatusFilter($statuses, $params);
        if ($statusClause) {
            $where[] = $statusClause;
        }

        $sql = 'SELECT * FROM contacts';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at ASC, id ASC LIMIT ?';
        $params[] = max($limit * 5, $limit);

        $eligible = [];
        foreach ($this->db->fetchAll($sql, $params) as $contact) {
            $eligible[] = $contact;
            if (count($eligible) >= $limit) {
                break;
            }
        }
        return $eligible;
    }

    private function appendInFilter(array &$where, array &$params, string $column, array $values): void
    {
        if (empty($values)) {
            return;
        }
        $where[] = $column . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
        foreach ($values as $value) {
            $params[] = $value;
        }
    }

    private function buildStatusFilter(array $statuses, array &$params): ?string
    {
        if (empty($statuses)) {
            return null;
        }
        $parts = [];
        $normal = [];
        foreach ($statuses as $status) {
            if ($status === 'empty') {
                $parts[] = "(enrichment_status IS NULL OR enrichment_status = '')";
            } else {
                $normal[] = $status;
            }
        }
        if (!empty($normal)) {
            $parts[] = 'enrichment_status IN (' . implode(',', array_fill(0, count($normal), '?')) . ')';
            foreach ($normal as $status) {
                $params[] = $status;
            }
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    private function recordSkippedRun(string $reason, array $config): array
    {
        $runId = $this->db->insert('enrichment_cron_runs', [
            'started_at' => getCurrentTimestamp(),
            'completed_at' => getCurrentTimestamp(),
            'status' => 'skipped',
            'skipped_reason' => $reason,
            'config_snapshot' => json_encode($config),
            'created_at' => getCurrentTimestamp(),
        ]);
        return [
            'success' => true,
            'status' => 'skipped',
            'run_id' => $runId,
            'skipped_reason' => $reason,
            'selected' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }

    private function startRun(array $config, int $selectedCount): int
    {
        return $this->db->insert('enrichment_cron_runs', [
            'started_at' => getCurrentTimestamp(),
            'status' => 'running',
            'selected_count' => $selectedCount,
            'config_snapshot' => json_encode($config),
            'created_at' => getCurrentTimestamp(),
        ]);
    }

    private function finishRun(int $runId, array $data): void
    {
        $this->db->update('enrichment_cron_runs', $data, 'id = ?', [$runId]);
    }

    private function normalizeStrategy(string $strategy): string
    {
        return in_array($strategy, ['auto', 'email', 'linkedin', 'name_company'], true) ? $strategy : 'auto';
    }

    private function normalizeArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : ($value === '' ? [] : explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private function readBoolean(array $input, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $input)) {
            return $default;
        }
        if (is_bool($input[$key])) {
            return $input[$key];
        }
        return in_array((string) $input[$key], ['1', 'true', 'on', 'yes'], true);
    }
}

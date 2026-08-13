<?php
/**
 * Server-side report aggregation for the CRM reports page.
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ReportsAnalyticsService
{
    public const STAGES = [
        'prospecting',
        'qualification',
        'proposal',
        'negotiation',
        'closed_won',
        'closed_lost',
    ];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * @return array{
     *   metrics: array,
     *   charts: array,
     *   activity: list<array>,
     *   analytics: list<array{metric:string,value:float|int}>,
     *   range: array{start_date:string,end_date:string,report_type:string},
     *   empty: bool
     * }
     */
    public function build(string $startDate, string $endDate, string $reportType = 'all'): array
    {
        $reportType = $this->normalizeReportType($reportType);
        [$start, $end] = $this->normalizeRange($startDate, $endDate);

        $includeDeals = in_array($reportType, ['all', 'deals', 'users'], true);
        $includeContacts = in_array($reportType, ['all', 'contacts', 'users'], true);

        $dealStats = $includeDeals
            ? $this->dealMetrics($start, $end)
            : ['total_deals' => 0, 'total_pipeline_value' => 0.0, 'win_rate' => 0.0, 'avg_deal_size' => 0.0, 'won_deals' => 0, 'closed_deals' => 0];

        $dealsByStage = $includeDeals ? $this->dealsByStage($start, $end) : $this->emptyStageCounts();
        $pipelineByStage = $includeDeals ? $this->pipelineValueByStage($start, $end) : $this->emptyStageValues();
        $dealsOverTime = $includeDeals ? $this->dealsOverTime($start, $end) : ['labels' => [], 'values' => []];
        $contactSources = $includeContacts ? $this->contactSources($start, $end) : ['labels' => [], 'values' => []];
        $activity = $includeDeals ? $this->recentDealActivity($start, $end, 10) : [];

        $metrics = [
            'total_deals' => (int) $dealStats['total_deals'],
            'total_pipeline_value' => (float) $dealStats['total_pipeline_value'],
            'win_rate' => (float) $dealStats['win_rate'],
            'avg_deal_size' => (float) $dealStats['avg_deal_size'],
        ];

        $charts = [
            'deals_by_stage' => [
                'labels' => array_map([$this, 'stageLabel'], self::STAGES),
                'values' => array_map(static fn(string $s) => (int) ($dealsByStage[$s] ?? 0), self::STAGES),
            ],
            'pipeline_value_by_stage' => [
                'labels' => array_map([$this, 'stageLabel'], self::STAGES),
                'values' => array_map(static fn(string $s) => (float) ($pipelineByStage[$s] ?? 0), self::STAGES),
            ],
            'contact_sources' => $contactSources,
            'deals_over_time' => $dealsOverTime,
        ];

        $empty = $metrics['total_deals'] === 0
            && ($contactSources['values'] === [] || array_sum($contactSources['values']) === 0);

        $analytics = [
            ['metric' => 'total_deals', 'value' => $metrics['total_deals']],
            ['metric' => 'total_pipeline_value', 'value' => $metrics['total_pipeline_value']],
            ['metric' => 'win_rate', 'value' => $metrics['win_rate']],
            ['metric' => 'avg_deal_size', 'value' => $metrics['avg_deal_size']],
            ['metric' => 'deals', 'value' => $metrics['total_deals']],
            ['metric' => 'contacts', 'value' => (int) array_sum($contactSources['values'])],
        ];

        return [
            'metrics' => $metrics,
            'charts' => $charts,
            'activity' => $activity,
            'analytics' => $analytics,
            'range' => [
                'start_date' => $start,
                'end_date' => substr($end, 0, 10),
                'report_type' => $reportType,
            ],
            'empty' => $empty,
        ];
    }

    public function normalizeReportType(string $reportType): string
    {
        $allowed = ['all', 'deals', 'contacts', 'users'];
        $reportType = strtolower(trim($reportType));
        return in_array($reportType, $allowed, true) ? $reportType : 'all';
    }

    /** @return array{0:string,1:string} start YYYY-MM-DD, end YYYY-MM-DD 23:59:59 */
    public function normalizeRange(string $startDate, string $endDate): array
    {
        $start = $this->parseDate($startDate) ?? date('Y-m-d', strtotime('-30 days'));
        $endDay = $this->parseDate($endDate) ?? date('Y-m-d');
        if ($start > $endDay) {
            [$start, $endDay] = [$endDay, $start];
        }
        return [$start, $endDay . ' 23:59:59'];
    }

    public function stageLabel(string $stage): string
    {
        return strtoupper(str_replace('_', ' ', $stage));
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    /** @return array{total_deals:int,total_pipeline_value:float,win_rate:float,avg_deal_size:float,won_deals:int,closed_deals:int} */
    private function dealMetrics(string $start, string $end): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total_deals,
                COALESCE(SUM(COALESCE(amount, 0)), 0) AS total_pipeline_value,
                SUM(CASE WHEN stage = 'closed_won' THEN 1 ELSE 0 END) AS won_deals,
                SUM(CASE WHEN stage IN ('closed_won', 'closed_lost') THEN 1 ELSE 0 END) AS closed_deals
             FROM deals
             WHERE datetime(created_at) >= datetime(?)
               AND datetime(created_at) <= datetime(?)",
            [$start, $end]
        ) ?: [];

        $total = (int) ($row['total_deals'] ?? 0);
        $value = (float) ($row['total_pipeline_value'] ?? 0);
        $won = (int) ($row['won_deals'] ?? 0);
        $closed = (int) ($row['closed_deals'] ?? 0);
        $winRate = $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0;
        $avg = $total > 0 ? round($value / $total, 2) : 0.0;

        return [
            'total_deals' => $total,
            'total_pipeline_value' => $value,
            'win_rate' => $winRate,
            'avg_deal_size' => $avg,
            'won_deals' => $won,
            'closed_deals' => $closed,
        ];
    }

    /** @return array<string,int> */
    private function dealsByStage(string $start, string $end): array
    {
        $rows = $this->db->fetchAll(
            "SELECT stage, COUNT(*) AS cnt
             FROM deals
             WHERE datetime(created_at) >= datetime(?)
               AND datetime(created_at) <= datetime(?)
             GROUP BY stage",
            [$start, $end]
        ) ?: [];
        $out = $this->emptyStageCounts();
        foreach ($rows as $row) {
            $stage = (string) ($row['stage'] ?? '');
            if (isset($out[$stage])) {
                $out[$stage] = (int) $row['cnt'];
            }
        }
        return $out;
    }

    /** @return array<string,float> */
    private function pipelineValueByStage(string $start, string $end): array
    {
        $rows = $this->db->fetchAll(
            "SELECT stage, COALESCE(SUM(COALESCE(amount, 0)), 0) AS total
             FROM deals
             WHERE datetime(created_at) >= datetime(?)
               AND datetime(created_at) <= datetime(?)
             GROUP BY stage",
            [$start, $end]
        ) ?: [];
        $out = $this->emptyStageValues();
        foreach ($rows as $row) {
            $stage = (string) ($row['stage'] ?? '');
            if (isset($out[$stage])) {
                $out[$stage] = (float) $row['total'];
            }
        }
        return $out;
    }

    /** @return array{labels:list<string>,values:list<int>} */
    private function dealsOverTime(string $start, string $end): array
    {
        $rows = $this->db->fetchAll(
            "SELECT strftime('%Y-%m', created_at) AS month_key, COUNT(*) AS cnt
             FROM deals
             WHERE datetime(created_at) >= datetime(?)
               AND datetime(created_at) <= datetime(?)
             GROUP BY month_key
             ORDER BY month_key ASC",
            [$start, $end]
        ) ?: [];
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            if (empty($row['month_key'])) {
                continue;
            }
            $labels[] = (string) $row['month_key'];
            $values[] = (int) $row['cnt'];
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    private function contactSources(string $start, string $end): array
    {
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(NULLIF(TRIM(source), ''), 'Unknown') AS source_label, COUNT(*) AS cnt
             FROM contacts
             WHERE datetime(created_at) >= datetime(?)
               AND datetime(created_at) <= datetime(?)
             GROUP BY source_label
             ORDER BY cnt DESC
             LIMIT 20",
            [$start, $end]
        ) ?: [];
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = (string) $row['source_label'];
            $values[] = (int) $row['cnt'];
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** @return list<array{user:string,action:string,details:string,date:string}> */
    private function recentDealActivity(string $start, string $end, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $rows = $this->db->fetchAll(
            "SELECT title, created_at
             FROM deals
             WHERE datetime(created_at) >= datetime(?)
               AND datetime(created_at) <= datetime(?)
             ORDER BY datetime(created_at) DESC
             LIMIT {$limit}",
            [$start, $end]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'user' => 'System',
                'action' => 'Deal Created',
                'details' => (string) ($row['title'] ?? ''),
                'date' => (string) ($row['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    /** @return array<string,int> */
    private function emptyStageCounts(): array
    {
        return array_fill_keys(self::STAGES, 0);
    }

    /** @return array<string,float> */
    private function emptyStageValues(): array
    {
        return array_fill_keys(self::STAGES, 0.0);
    }
}

<?php
/**
 * Contact merge executor + cron candidate generation (Doc #919 v0.4).
 * Cron proposes scored pairs; humans accept/reject (mass accept = high tier by default).
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/ContactDataStore.php';

class ContactMergeService
{
    public const TIER_HIGH = 'high';
    public const TIER_MEDIUM = 'medium';
    public const TIER_LOW = 'low';

    public const MASS_ACCEPT_FLOOR = self::TIER_HIGH;

    private Database $db;
    private ContactDataStore $store;

    /** Card columns that may be filled from an absorbed contact. */
    private const FILL_FIELDS = [
        'first_name', 'last_name', 'email', 'phone', 'company', 'position', 'address',
        'city', 'state', 'zip_code', 'country', 'evm_address', 'twitter_handle',
        'linkedin_profile', 'telegram_username', 'discord_username', 'github_username',
        'website', 'source', 'assigned_to',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->store = new ContactDataStore($this->db);
        $this->store->ensureSchema();
    }

    public static function fingerprint(int $a, int $b): string
    {
        $lo = min($a, $b);
        $hi = max($a, $b);
        return $lo . ':' . $hi;
    }

    public static function tierForScore(float $score): string
    {
        if ($score >= 0.85) {
            return self::TIER_HIGH;
        }
        if ($score >= 0.60) {
            return self::TIER_MEDIUM;
        }
        return self::TIER_LOW;
    }

    public static function tierMeetsFloor(string $tier, string $floor = self::MASS_ACCEPT_FLOOR): bool
    {
        $rank = [self::TIER_LOW => 1, self::TIER_MEDIUM => 2, self::TIER_HIGH => 3];
        return ($rank[$tier] ?? 0) >= ($rank[$floor] ?? 3);
    }

    // -------------------------------------------------------------------------
    // Merge execution
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $fieldOverrides
     * @return array{success:bool,dry_run:bool,survivor?:array,merged_ids?:list<int>,run_ids?:list<int>,facts_written?:int,deals_remapped?:int,tags_moved?:int,plan?:array,error?:string}
     */
    public function merge(int $survivorId, array $mergeIds, array $fieldOverrides = [], bool $dryRun = false, ?int $actorUserId = null, bool $expireRelated = true): array
    {
        $mergeIds = array_values(array_unique(array_map('intval', $mergeIds)));
        $mergeIds = array_values(array_filter($mergeIds, static fn($id) => $id > 0 && $id !== $survivorId));
        if ($mergeIds === []) {
            return ['success' => false, 'dry_run' => $dryRun, 'error' => 'merge_ids required'];
        }

        $survivor = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$survivorId]);
        if (!$survivor) {
            return ['success' => false, 'dry_run' => $dryRun, 'error' => 'Survivor contact not found'];
        }

        $absorbed = [];
        foreach ($mergeIds as $mid) {
            $row = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$mid]);
            if (!$row) {
                return ['success' => false, 'dry_run' => $dryRun, 'error' => "Merge contact #$mid not found"];
            }
            $absorbed[] = $row;
        }

        $cardPatch = $this->buildCardPatch($survivor, $absorbed, $fieldOverrides);
        $notesPatch = $this->buildNotesLineagePatch($survivor, $absorbed);
        if ($notesPatch !== null) {
            $cardPatch['notes'] = $notesPatch;
        }
        $plans = [];
        foreach ($absorbed as $row) {
            $plans[] = $this->buildSidecarPlan($survivor, $row, $cardPatch);
        }

        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'survivor' => $survivor,
                'merged_ids' => $mergeIds,
                'plan' => [
                    'card_patch' => $cardPatch,
                    'sidecar' => $plans,
                ],
            ];
        }

        $runIds = [];
        $factsWritten = 0;
        $dealsRemapped = 0;
        $tagsMoved = 0;

        try {
            $this->db->beginTransaction();

            if ($cardPatch !== []) {
                $cardPatch['updated_at'] = getCurrentTimestamp();
                $this->db->update('contacts', $cardPatch, 'id = ?', [$survivorId]);
            }

            foreach ($plans as $plan) {
                $result = $this->store->recordRun($survivorId, [
                    'source' => 'merge',
                    'outcome' => 'merged',
                    'label' => $plan['label'],
                    'actor_user_id' => $actorUserId,
                    'raw_payload' => $plan['raw_payload'],
                    'facts' => $plan['facts'],
                ]);
                $runIds[] = $result['run_id'];
                $factsWritten += $result['fact_count'];
            }

            foreach ($mergeIds as $mid) {
                $dealCount = $this->db->fetchOne(
                    'SELECT COUNT(*) AS c FROM deals WHERE contact_id = ?',
                    [$mid]
                );
                $this->db->query('UPDATE deals SET contact_id = ? WHERE contact_id = ?', [$survivorId, $mid]);
                $dealsRemapped += (int) ($dealCount['c'] ?? 0);

                $tags = $this->db->fetchAll('SELECT tag FROM contact_tags WHERE contact_id = ?', [$mid]);
                foreach ($tags ?: [] as $t) {
                    try {
                        $this->db->insert('contact_tags', [
                            'contact_id' => $survivorId,
                            'tag' => $t['tag'],
                        ]);
                        $tagsMoved++;
                    } catch (Exception $e) {
                        // UNIQUE(contact_id, tag) — already present
                    }
                }
                $this->db->delete('contact_tags', 'contact_id = ?', [$mid]);
                $this->db->delete('contacts', 'id = ?', [$mid]);
            }

            // Expire other pending pairs that involved any absorbed id (or the survivor as merge side).
            if ($expireRelated) {
                $involved = array_merge([$survivorId], $mergeIds);
                $placeholders = implode(',', array_fill(0, count($involved), '?'));
                $expireParams = array_merge([getCurrentTimestamp()], $involved, $involved);
                $this->db->query(
                    "UPDATE contact_merge_candidates
                     SET status = 'expired', updated_at = ?
                     WHERE status = 'pending'
                       AND (survivor_id IN ($placeholders) OR merge_id IN ($placeholders))",
                    $expireParams
                );
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'dry_run' => false, 'error' => $e->getMessage()];
        }

        $updated = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$survivorId]);
        if (function_exists('crm_dispatch_webhook')) {
            crm_dispatch_webhook('contact.merged', [
                'survivor' => $updated,
                'merged_ids' => $mergeIds,
                'run_ids' => $runIds,
            ]);
            foreach ($mergeIds as $mid) {
                crm_dispatch_webhook('contact.deleted', ['contact_id' => $mid]);
            }
        }

        return [
            'success' => true,
            'dry_run' => false,
            'survivor' => $updated,
            'merged_ids' => $mergeIds,
            'run_ids' => $runIds,
            'facts_written' => $factsWritten,
            'deals_remapped' => $dealsRemapped,
            'tags_moved' => $tagsMoved,
        ];
    }

    /**
     * @param list<array> $absorbed
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function buildCardPatch(array $survivor, array $absorbed, array $overrides): array
    {
        $patch = [];
        foreach (self::FILL_FIELDS as $field) {
            if (array_key_exists($field, $overrides) && $overrides[$field] !== null && $overrides[$field] !== '') {
                $patch[$field] = $overrides[$field];
                continue;
            }
            $cur = trim((string) ($survivor[$field] ?? ''));
            if ($cur !== '' && !($field === 'last_name' && strcasecmp($cur, 'Unknown') === 0 && $this->isUnknownName($survivor))) {
                // keep survivor; still allow Unknown first/last to be upgraded
                if (!($this->isPlaceholderName($survivor, $field))) {
                    continue;
                }
            }
            foreach ($absorbed as $row) {
                $v = trim((string) ($row[$field] ?? ''));
                if ($v === '' || strcasecmp($v, 'Unknown') === 0) {
                    continue;
                }
                $patch[$field] = $v;
                break;
            }
        }

        // Type / status prefer richer
        $types = array_merge([$survivor['contact_type'] ?? ''], array_column($absorbed, 'contact_type'));
        if (in_array('customer', $types, true) && ($survivor['contact_type'] ?? '') !== 'customer') {
            $patch['contact_type'] = 'customer';
        }
        $statusRank = ['new' => 1, 'qualified' => 2, 'contacted' => 3, 'engaged' => 4, 'active' => 5];
        $bestStatus = $survivor['contact_status'] ?? 'new';
        $bestRank = $statusRank[strtolower((string) $bestStatus)] ?? 0;
        foreach ($absorbed as $row) {
            $s = strtolower((string) ($row['contact_status'] ?? ''));
            $r = $statusRank[$s] ?? 0;
            if ($r > $bestRank) {
                $bestRank = $r;
                $bestStatus = $row['contact_status'];
            }
        }
        if ($bestStatus && $bestStatus !== ($survivor['contact_status'] ?? null)) {
            $patch['contact_status'] = $bestStatus;
        }

        return $patch;
    }

    private function isUnknownName(array $c): bool
    {
        $fn = strtolower(trim((string) ($c['first_name'] ?? '')));
        $ln = strtolower(trim((string) ($c['last_name'] ?? '')));
        return ($fn === '' || $fn === 'unknown') && ($ln === '' || $ln === 'unknown');
    }

    private function isPlaceholderName(array $c, string $field): bool
    {
        if ($field !== 'first_name' && $field !== 'last_name') {
            return false;
        }
        $v = strtolower(trim((string) ($c[$field] ?? '')));
        return $v === '' || $v === 'unknown';
    }

    /**
     * @param array<string,mixed> $cardPatch
     * @return array{label:string,raw_payload:array,facts:list<array>}
     */
    private function buildSidecarPlan(array $survivor, array $absorbed, array $cardPatch): array
    {
        $conflicts = [];
        $notCopied = [];
        foreach (self::FILL_FIELDS as $field) {
            $sVal = $survivor[$field] ?? null;
            $aVal = $absorbed[$field] ?? null;
            $sEmpty = $sVal === null || trim((string) $sVal) === '';
            $aEmpty = $aVal === null || trim((string) $aVal) === '';
            if (!$aEmpty && !$sEmpty && (string) $sVal !== (string) $aVal) {
                $conflicts[$field] = ['survivor' => $sVal, 'absorbed' => $aVal];
            }
            if (!$aEmpty && !array_key_exists($field, $cardPatch) && (string) $sVal !== (string) $aVal) {
                $notCopied[] = $field;
            }
        }
        if (!empty($absorbed['notes']) && trim((string) $absorbed['notes']) !== '') {
            // Notes are merged onto the survivor card with lineage; still flagged for sidecar audit.
            $notCopied[] = 'notes';
        }

        $email = trim((string) ($absorbed['email'] ?? ''));
        $label = 'merge from #' . $absorbed['id'] . ($email !== '' ? " $email" : '');

        $facts = [];
        if ($email !== '') {
            $facts[] = [
                'fact_type' => 'email',
                'value' => $email,
                'label' => $this->isFormerEmployerEmail($email) ? 'former_employer' : 'absorbed_primary',
                'meta' => ['source_contact_id' => (int) $absorbed['id']],
            ];
        }
        $phone = trim((string) ($absorbed['phone'] ?? ''));
        if ($phone !== '') {
            $facts[] = [
                'fact_type' => 'phone',
                'value' => $phone,
                'label' => 'absorbed',
                'meta' => ['source_contact_id' => (int) $absorbed['id']],
            ];
        }
        foreach (['twitter_handle' => 'twitter', 'linkedin_profile' => 'linkedin', 'telegram_username' => 'telegram', 'discord_username' => 'discord', 'github_username' => 'github'] as $col => $net) {
            $v = trim((string) ($absorbed[$col] ?? ''));
            if ($v !== '') {
                $facts[] = [
                    'fact_type' => 'social',
                    'value' => $v,
                    'label' => $net,
                    'meta' => ['network' => $net, 'source_contact_id' => (int) $absorbed['id']],
                ];
            }
        }
        $co = trim((string) ($absorbed['company'] ?? ''));
        if ($co !== '' && empty($cardPatch['company'])) {
            $facts[] = [
                'fact_type' => 'employer',
                'value' => $co,
                'label' => 'absorbed_company',
                'meta' => ['source_contact_id' => (int) $absorbed['id']],
            ];
        }

        // Full absorbed notes + card snapshot — no truncation (storage is cheap; lineage matters).
        $lineageNote = $this->formatAbsorbedLineageNote($absorbed);
        $facts[] = [
            'fact_type' => 'other',
            'value' => $lineageNote,
            'label' => 'merged_contact_lineage',
            'meta' => [
                'key' => 'merged_contact_lineage',
                'source_contact_id' => (int) $absorbed['id'],
                'source_email' => $email !== '' ? $email : null,
                'source_phone' => $phone !== '' ? $phone : null,
            ],
        ];

        return [
            'label' => $label,
            'raw_payload' => [
                'merged_contact_id' => (int) $absorbed['id'],
                'merged_at' => gmdate('c'),
                'absorbed_row' => $absorbed,
                'lineage_note' => $lineageNote,
                'field_conflicts' => $conflicts,
                'not_copied_to_card' => array_values(array_unique($notCopied)),
            ],
            'facts' => $facts,
        ];
    }

    /**
     * Append absorbed contact lineage (+ full notes) onto the survivor notes field.
     *
     * @param list<array> $absorbed
     */
    private function buildNotesLineagePatch(array $survivor, array $absorbedRows): ?string
    {
        $blocks = [];
        foreach ($absorbedRows as $row) {
            $blocks[] = $this->formatAbsorbedLineageNote($row);
        }
        if ($blocks === []) {
            return null;
        }
        $addition = implode("\n\n", $blocks);
        $existing = trim((string) ($survivor['notes'] ?? ''));
        if ($existing === '') {
            return $addition;
        }
        return $existing . "\n\n" . $addition;
    }

    /** Full unlimited lineage block for an absorbed contact (identity + notes). */
    private function formatAbsorbedLineageNote(array $absorbed): string
    {
        $id = (int) ($absorbed['id'] ?? 0);
        $name = trim(trim((string) ($absorbed['first_name'] ?? '')) . ' ' . trim((string) ($absorbed['last_name'] ?? '')));
        $lines = [
            '--- Merged contact #' . $id . ' (' . gmdate('Y-m-d\\TH:i:s\\Z') . ') ---',
        ];
        $fields = [
            'Name' => $name !== '' ? $name : null,
            'Email' => trim((string) ($absorbed['email'] ?? '')) ?: null,
            'Phone' => trim((string) ($absorbed['phone'] ?? '')) ?: null,
            'Company' => trim((string) ($absorbed['company'] ?? '')) ?: null,
            'Position' => trim((string) ($absorbed['position'] ?? '')) ?: null,
            'Source' => trim((string) ($absorbed['source'] ?? '')) ?: null,
            'Type' => trim((string) ($absorbed['contact_type'] ?? '')) ?: null,
            'Status' => trim((string) ($absorbed['contact_status'] ?? '')) ?: null,
            'Address' => trim(implode(', ', array_filter([
                trim((string) ($absorbed['address'] ?? '')),
                trim((string) ($absorbed['city'] ?? '')),
                trim((string) ($absorbed['state'] ?? '')),
                trim((string) ($absorbed['zip_code'] ?? '')),
                trim((string) ($absorbed['country'] ?? '')),
            ]))) ?: null,
            'LinkedIn' => trim((string) ($absorbed['linkedin_profile'] ?? '')) ?: null,
            'Website' => trim((string) ($absorbed['website'] ?? '')) ?: null,
        ];
        foreach ($fields as $label => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }
        $notes = (string) ($absorbed['notes'] ?? '');
        if (trim($notes) !== '') {
            $lines[] = '';
            $lines[] = 'Notes:';
            $lines[] = $notes;
        }
        return implode("\n", $lines);
    }

    private function isFormerEmployerEmail(string $email): bool
    {
        return false; // personal employer heuristics not shipped in Sanctum
    }

    /** Owner self-addresses — never seed merge candidates (false-positive magnet). */
    private function isOwnerSelfEmail(string $email): bool
    {
        return false; // personal owner-address skip not shipped in Sanctum
    }

    // -------------------------------------------------------------------------
    // Candidate generation (cron)
    // -------------------------------------------------------------------------

    /**
     * @return array{created:int,updated:int,skipped:int,expired:int,scanned:int}
     */
    public function generateCandidates(int $maxPairs = 500): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'expired' => 0, 'scanned' => 0];

        // Expire orphans
        $this->db->query(
            "UPDATE contact_merge_candidates SET status = 'expired', updated_at = ?
             WHERE status = 'pending'
               AND (
                 survivor_id NOT IN (SELECT id FROM contacts)
                 OR merge_id NOT IN (SELECT id FROM contacts)
               )",
            [getCurrentTimestamp()]
        );

        $contacts = $this->db->fetchAll(
            'SELECT id, first_name, last_name, email, phone, company, contact_type, contact_status
             FROM contacts ORDER BY id ASC'
        );
        if (!is_array($contacts) || $contacts === []) {
            return $stats;
        }
        $stats['scanned'] = count($contacts);

        $byId = [];
        $byPhone = [];
        $byName = [];
        $byFirst = [];
        $gerwil = [];

        foreach ($contacts as $c) {
            $id = (int) $c['id'];
            $email = strtolower(trim((string) ($c['email'] ?? '')));
            // Skip Mark's own Gerwil addresses — they match every "Mark *" card.
            if ($this->isOwnerSelfEmail($email)) {
                continue;
            }
            $byId[$id] = $c;
            $phone = $this->normalizePhone($c['phone'] ?? '');
            if ($phone !== null && strlen($phone) >= 10) {
                $byPhone[$phone][] = $id;
            }
            $fn = $this->normName($c['first_name'] ?? '');
            $ln = $this->normName($c['last_name'] ?? '');
            if ($fn !== '' && $ln !== '' && $fn !== 'unknown' && $ln !== 'unknown') {
                $byName[$fn . '|' . $ln][] = $id;
            }
            if ($fn !== '' && $fn !== 'unknown') {
                $byFirst[$fn][] = $id;
            }
            if ($this->isFormerEmployerEmail($email)) {
                $local = explode('@', $email)[0];
                $gerwil[] = ['id' => $id, 'local' => $local, 'contact' => $c];
            }
        }

        // Collect unique pair fingerprints from each signal bucket, then score once.
        // Phone-alone must not equal phone+fn+ln — see scoreContactPair().
        $pairIds = []; // fingerprint => [idA, idB]

        $notePair = static function (int $a, int $b) use (&$pairIds): void {
            if ($a === $b) {
                return;
            }
            $fp = ContactMergeService::fingerprint($a, $b);
            $pairIds[$fp] = [min($a, $b), max($a, $b)];
        };

        foreach ($byPhone as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $ids = array_values(array_unique($ids));
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $notePair($ids[$i], $ids[$j]);
                }
            }
        }

        foreach ($byName as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $ids = array_values(array_unique($ids));
            if (count($ids) > 25) {
                continue;
            }
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $notePair($ids[$i], $ids[$j]);
                }
            }
        }

        foreach ($gerwil as $g) {
            $local = $this->normName($g['local']);
            if ($local === '' || $local === 'unknown') {
                continue;
            }
            foreach ($byFirst[$local] ?? [] as $oid) {
                if ($oid === $g['id']) {
                    continue;
                }
                $notePair($g['id'], $oid);
            }
        }

        $proposals = [];
        foreach ($pairIds as $fp => [$idA, $idB]) {
            $ca = $byId[$idA] ?? null;
            $cb = $byId[$idB] ?? null;
            if (!$ca || !$cb) {
                continue;
            }
            $scored = $this->scoreContactPair($ca, $cb);
            if ($scored['confidence'] < 0.40) {
                continue;
            }
            [$survivorId, $mergeId] = $this->pickSurvivor($ca, $cb);
            $proposals[$fp] = [
                'fingerprint' => $fp,
                'survivor_id' => $survivorId,
                'merge_id' => $mergeId,
                'confidence' => $scored['confidence'],
                'confidence_tier' => self::tierForScore($scored['confidence']),
                'reason_codes' => $scored['reason_codes'],
            ];
        }

        // Cap
        if (count($proposals) > $maxPairs) {
            uasort($proposals, static fn($a, $b) => $b['confidence'] <=> $a['confidence']);
            $proposals = array_slice($proposals, 0, $maxPairs, true);
        }

        $now = getCurrentTimestamp();
        foreach ($proposals as $p) {
            $fp = $p['fingerprint'];
            $existing = $this->db->fetchOne(
                'SELECT * FROM contact_merge_candidates WHERE fingerprint = ?',
                [$fp]
            );
            $summary = $this->reasonSummary($p['reason_codes']);
            $codesJson = json_encode(array_values($p['reason_codes']));

            if ($existing) {
                if (in_array($existing['status'], ['accepted', 'rejected'], true)) {
                    $stats['skipped']++;
                    continue;
                }
                $this->db->update('contact_merge_candidates', [
                    'survivor_id' => $p['survivor_id'],
                    'merge_id' => $p['merge_id'],
                    'confidence' => $p['confidence'],
                    'confidence_tier' => $p['confidence_tier'],
                    'reason_codes' => $codesJson,
                    'reason_summary' => $summary,
                    'status' => 'pending',
                    'updated_at' => $now,
                ], 'id = ?', [(int) $existing['id']]);
                $stats['updated']++;
            } else {
                try {
                    $this->db->insert('contact_merge_candidates', [
                        'survivor_id' => $p['survivor_id'],
                        'merge_id' => $p['merge_id'],
                        'confidence' => $p['confidence'],
                        'confidence_tier' => $p['confidence_tier'],
                        'reason_codes' => $codesJson,
                        'reason_summary' => $summary,
                        'status' => 'pending',
                        'fingerprint' => $fp,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $stats['created']++;
                } catch (Exception $e) {
                    $stats['skipped']++;
                }
            }
        }

        // Re-score other pending rows so scoring fixes demote/expire stale high matches
        // (e.g. Andrew Smith vs Andrew Lowe) instead of leaving them at old confidence.
        $pendingRows = $this->db->fetchAll(
            "SELECT * FROM contact_merge_candidates WHERE status = 'pending'"
        ) ?: [];
        foreach ($pendingRows as $row) {
            $fp = (string) ($row['fingerprint'] ?? '');
            if ($fp !== '' && isset($proposals[$fp])) {
                continue; // already refreshed above
            }
            if (in_array($row['status'] ?? '', ['accepted', 'rejected'], true)) {
                continue;
            }
            $ca = $byId[(int) $row['survivor_id']] ?? null;
            $cb = $byId[(int) $row['merge_id']] ?? null;
            if (!$ca || !$cb) {
                continue;
            }
            $scored = $this->scoreContactPair($ca, $cb);
            $summary = $this->reasonSummary($scored['reason_codes']);
            $codesJson = json_encode(array_values($scored['reason_codes']));
            if ($scored['confidence'] < 0.40) {
                $this->db->update('contact_merge_candidates', [
                    'confidence' => $scored['confidence'],
                    'confidence_tier' => 'low',
                    'reason_codes' => $codesJson,
                    'reason_summary' => $summary,
                    'status' => 'expired',
                    'updated_at' => $now,
                ], 'id = ?', [(int) $row['id']]);
                $stats['expired']++;
            } else {
                $this->db->update('contact_merge_candidates', [
                    'confidence' => $scored['confidence'],
                    'confidence_tier' => self::tierForScore($scored['confidence']),
                    'reason_codes' => $codesJson,
                    'reason_summary' => $summary,
                    'updated_at' => $now,
                ], 'id = ?', [(int) $row['id']]);
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * Composite pair score. Phone alone never equals phone+first+last.
     *
     * @return array{confidence:float,reason_codes:list<string>}
     */
    public function scoreContactPair(array $a, array $b): array
    {
        $reasons = [];
        $phoneA = $this->normalizePhone($a['phone'] ?? '');
        $phoneB = $this->normalizePhone($b['phone'] ?? '');
        $phoneMatch = $phoneA !== null && $phoneB !== null && strlen($phoneA) >= 10 && $phoneA === $phoneB;
        if ($phoneMatch) {
            $reasons[] = 'exact_phone';
        }

        $fnA = $this->normName($a['first_name'] ?? '');
        $fnB = $this->normName($b['first_name'] ?? '');
        $lnA = $this->normName($a['last_name'] ?? '');
        $lnB = $this->normName($b['last_name'] ?? '');

        $realFnA = $this->isRealNamePart($fnA);
        $realFnB = $this->isRealNamePart($fnB);
        $realLnA = $this->isRealNamePart($lnA);
        $realLnB = $this->isRealNamePart($lnB);

        $sameFirst = $realFnA && $realFnB && $fnA === $fnB;
        $sameLast = $realLnA && $realLnB && $lnA === $lnB;
        $firstConflict = $realFnA && $realFnB && $fnA !== $fnB;
        $lastConflict = $realLnA && $realLnB && $lnA !== $lnB;
        $nameConflict = $firstConflict || $lastConflict;
        $sameFullName = $sameFirst && $sameLast;
        // Both cards have a real first+last, but they are not the same person-name.
        // e.g. Andrew Smith vs Andrew Lowe — must NOT score high (no "every Andrew" merges).
        $bothHaveFullNames = $realFnA && $realFnB && $realLnA && $realLnB;
        $fullNameMismatch = $bothHaveFullNames && !$sameFullName;

        if ($sameFullName) {
            $reasons[] = 'exact_name';
        } elseif ($sameFirst) {
            $reasons[] = 'exact_first';
        } elseif ($sameLast) {
            $reasons[] = 'exact_last';
        }
        if ($nameConflict) {
            $reasons[] = 'name_conflict';
        }
        if ($fullNameMismatch) {
            $reasons[] = 'full_name_mismatch';
        }

        // Gerwil / former-employer localpart cues
        $emailA = strtolower(trim((string) ($a['email'] ?? '')));
        $emailB = strtolower(trim((string) ($b['email'] ?? '')));
        $gerwilA = $this->isFormerEmployerEmail($emailA);
        $gerwilB = $this->isFormerEmployerEmail($emailB);
        if ($gerwilA xor $gerwilB) {
            $gEmail = $gerwilA ? $emailA : $emailB;
            $gContact = $gerwilA ? $a : $b;
            $other = $gerwilA ? $b : $a;
            $local = $this->normName(explode('@', $gEmail)[0] ?? '');
            $oFn = $this->normName($other['first_name'] ?? '');
            $oLn = $this->normName($other['last_name'] ?? '');
            $gLn = $this->normName($gContact['last_name'] ?? '');
            $oEmail = strtolower(trim((string) ($other['email'] ?? '')));
            if ($local !== '' && $local !== 'unknown' && $this->isRealNamePart($oFn) && $local === $oFn) {
                // Both have real last names that disagree → not a Gerwil identity hit
                if ($this->isRealNamePart($gLn) && $this->isRealNamePart($oLn) && $gLn !== $oLn) {
                    $reasons[] = 'gerwil_last_mismatch';
                } elseif (!$this->isRealNamePart($oLn)) {
                    $reasons[] = 'gerwil_local_first_only';
                } elseif ($oEmail !== '' && !$this->isFormerEmployerEmail($oEmail)) {
                    // Named personal card matched only on first/local — weak unless last also aligns
                    if ($this->isRealNamePart($gLn) && $gLn === $oLn) {
                        $reasons[] = 'gerwil_local_to_personal';
                    } elseif (!$this->isRealNamePart($gLn)) {
                        $reasons[] = 'gerwil_first_only_named_other';
                    } else {
                        $reasons[] = 'gerwil_local_named';
                    }
                } else {
                    $reasons[] = 'gerwil_local_named';
                }
            }
        }

        $score = 0.0;

        // Strongest: same phone AND same first+last
        if ($phoneMatch && $sameFullName) {
            $score = 0.96;
            $reasons[] = 'phone_and_full_name';
        } elseif ($fullNameMismatch) {
            // Both fully named but disagree — significant decrement; never high/medium-high.
            // Shared phone still worth a low inspect row; name-only mismatch is not a candidate.
            $score = $phoneMatch ? 0.48 : 0.0;
            $reasons[] = 'full_name_mismatch_penalized';
        } elseif ($phoneMatch && $nameConflict) {
            // Shared line with conflicting name parts (one side incomplete names)
            $score = 0.55;
        } elseif ($phoneMatch && $sameFirst && !$lastConflict) {
            // Same phone + first; last missing/unknown on one side
            $score = 0.84;
            $reasons[] = 'phone_and_first';
        } elseif ($phoneMatch && $sameLast && !$firstConflict) {
            $score = 0.80;
            $reasons[] = 'phone_and_last';
        } elseif ($phoneMatch) {
            // Phone only (no usable name agreement) — medium
            $score = 0.70;
            $reasons[] = 'phone_only';
        } elseif ($sameFullName) {
            // Name match without confirming phone
            $score = 0.86;
        } elseif (in_array('gerwil_local_to_personal', $reasons, true)) {
            $score = 0.88;
        } elseif (in_array('gerwil_local_named', $reasons, true)) {
            $score = 0.74;
        } elseif (in_array('gerwil_first_only_named_other', $reasons, true)) {
            // andrew@gerwil → Andrew Lowe (no last on Gerwil side): weak, not mass-accept
            $score = 0.50;
        } elseif (in_array('gerwil_local_first_only', $reasons, true)) {
            $score = 0.52;
        } elseif (in_array('gerwil_last_mismatch', $reasons, true)) {
            $score = 0.0;
        } elseif ($sameFirst) {
            $score = 0.48;
        } elseif ($sameLast) {
            $score = 0.45;
        }

        // Small boost when personal emails differ but names+phone already strong (duplicate cards)
        if ($score >= 0.85 && !$fullNameMismatch && $emailA !== '' && $emailB !== '' && $emailA !== $emailB
            && !$this->isFormerEmployerEmail($emailA) && !$this->isFormerEmployerEmail($emailB)) {
            $score = min(0.99, $score + 0.01);
            $reasons[] = 'distinct_personal_emails';
        }

        $reasons = array_values(array_unique($reasons));
        return [
            'confidence' => round($score, 4),
            'reason_codes' => $reasons,
        ];
    }

    private function isRealNamePart(string $n): bool
    {
        return $n !== '' && $n !== 'unknown';
    }

    /**
     * Prefer contact with real personal email and fuller card as survivor.
     * Heuristic only — UI/API can flip sides before accept.
     *
     * Points: personal email +5, former-employer email +1, phone +2,
     * real last name +2, company +1, customer type +1. Tie → lower contact id.
     *
     * @return array{0:int,1:int} survivor_id, merge_id
     */
    private function pickSurvivor(array $a, array $b): array
    {
        $sa = $this->survivorRichnessScore($a);
        $sb = $this->survivorRichnessScore($b);
        if ($sa > $sb) {
            return [(int) $a['id'], (int) $b['id']];
        }
        if ($sb > $sa) {
            return [(int) $b['id'], (int) $a['id']];
        }
        // Tie: keep lower id as survivor (stable, predictable).
        if ((int) $a['id'] <= (int) $b['id']) {
            return [(int) $a['id'], (int) $b['id']];
        }
        return [(int) $b['id'], (int) $a['id']];
    }

    /** @return array{score:int,breakdown:list<string>} */
    public function survivorRichnessBreakdown(array $c): array
    {
        $score = 0;
        $parts = [];
        $email = strtolower(trim((string) ($c['email'] ?? '')));
        if ($email !== '' && !$this->isFormerEmployerEmail($email)) {
            $score += 5;
            $parts[] = '+5 personal email';
        } elseif ($email !== '') {
            $score += 1;
            $parts[] = '+1 former-employer email';
        }
        if (trim((string) ($c['phone'] ?? '')) !== '') {
            $score += 2;
            $parts[] = '+2 phone';
        }
        $ln = $this->normName($c['last_name'] ?? '');
        if ($ln !== '' && $ln !== 'unknown') {
            $score += 2;
            $parts[] = '+2 real last name';
        }
        if (trim((string) ($c['company'] ?? '')) !== '') {
            $score += 1;
            $parts[] = '+1 company';
        }
        if (($c['contact_type'] ?? '') === 'customer') {
            $score += 1;
            $parts[] = '+1 customer type';
        }
        if ($parts === []) {
            $parts[] = '0 (empty card)';
        }
        return ['score' => $score, 'breakdown' => $parts];
    }

    private function survivorRichnessScore(array $c): int
    {
        return $this->survivorRichnessBreakdown($c)['score'];
    }

    /**
     * Flip keep/absorb on a pending candidate (does not merge).
     *
     * @return array{success:bool,candidate?:array,error?:string}
     */
    public function swapCandidateSides(int $candidateId): array
    {
        $cand = $this->db->fetchOne('SELECT * FROM contact_merge_candidates WHERE id = ?', [$candidateId]);
        if (!$cand) {
            return ['success' => false, 'error' => 'Candidate not found'];
        }
        if (($cand['status'] ?? '') !== 'pending') {
            return ['success' => false, 'error' => 'Only pending candidates can be flipped'];
        }
        $survivorId = (int) $cand['survivor_id'];
        $mergeId = (int) $cand['merge_id'];
        if ($survivorId <= 0 || $mergeId <= 0) {
            return ['success' => false, 'error' => 'Invalid candidate sides'];
        }
        $this->db->update('contact_merge_candidates', [
            'survivor_id' => $mergeId,
            'merge_id' => $survivorId,
            'updated_at' => getCurrentTimestamp(),
        ], 'id = ?', [$candidateId]);
        $updated = $this->getCandidate($candidateId);
        return ['success' => true, 'candidate' => $updated];
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }
        // US 11-digit leading 1
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        return $digits;
    }

    private function normName(?string $n): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $n)));
    }

    private function reasonSummary(array $codes): string
    {
        $labels = [
            'exact_phone' => 'Same phone number',
            'exact_name' => 'Same first and last name',
            'exact_first' => 'Same first name',
            'exact_last' => 'Same last name',
            'name_conflict' => 'Names disagree',
            'full_name_mismatch' => 'Both have full names that do not match',
            'full_name_mismatch_penalized' => 'Full-name mismatch (heavy penalty)',
            'phone_and_full_name' => 'Phone + full name match',
            'phone_and_first' => 'Phone + first name match',
            'phone_and_last' => 'Phone + last name match',
            'phone_only' => 'Phone match only (names incomplete or weak)',
            'distinct_personal_emails' => 'Different personal emails on matching cards',
            'gerwil_local_to_personal' => 'Roger Wilco address matches personal contact',
            'gerwil_local_named' => 'Roger Wilco local matches named contact',
            'gerwil_local_first_only' => 'Roger Wilco local matches first name only',
            'gerwil_first_only_named_other' => 'Roger Wilco first-only vs fully named contact (weak)',
            'gerwil_last_mismatch' => 'Roger Wilco last name disagrees',
        ];
        $priority = [
            'phone_and_full_name', 'exact_name', 'full_name_mismatch_penalized', 'full_name_mismatch',
            'exact_phone', 'phone_and_first', 'phone_and_last',
            'phone_only', 'name_conflict', 'exact_first', 'exact_last',
            'gerwil_local_to_personal', 'gerwil_local_named', 'gerwil_first_only_named_other',
            'gerwil_local_first_only', 'gerwil_last_mismatch',
            'distinct_personal_emails',
        ];
        $ordered = [];
        foreach ($priority as $code) {
            if (in_array($code, $codes, true)) {
                $ordered[] = $labels[$code] ?? $code;
            }
        }
        foreach ($codes as $c) {
            if (!in_array($c, $priority, true)) {
                $ordered[] = $labels[$c] ?? $c;
            }
        }
        return implode('; ', $ordered);
    }

    // -------------------------------------------------------------------------
    // Candidate CRUD / accept / reject
    // -------------------------------------------------------------------------

    /**
     * @return array{candidates:list<array>,total:int}
     */
    public function listCandidates(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        $status = $filters['status'] ?? 'pending';
        if ($status !== 'all' && $status !== '') {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['tier'])) {
            $where[] = 'c.confidence_tier = ?';
            $params[] = $filters['tier'];
        }
        if (isset($filters['min_confidence'])) {
            $where[] = 'c.confidence >= ?';
            $params[] = (float) $filters['min_confidence'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $w = implode(' AND ', $where);

        $totalRow = $this->db->fetchOne("SELECT COUNT(*) AS total FROM contact_merge_candidates c WHERE $w", $params);
        $total = (int) ($totalRow['total'] ?? 0);

        $sql = "SELECT c.*,
                       s.first_name AS survivor_first_name, s.last_name AS survivor_last_name,
                       s.email AS survivor_email, s.phone AS survivor_phone, s.company AS survivor_company,
                       m.first_name AS merge_first_name, m.last_name AS merge_last_name,
                       m.email AS merge_email, m.phone AS merge_phone, m.company AS merge_company
                FROM contact_merge_candidates c
                LEFT JOIN contacts s ON s.id = c.survivor_id
                LEFT JOIN contacts m ON m.id = c.merge_id
                WHERE $w
                ORDER BY c.confidence DESC, c.id DESC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        foreach ($rows as &$row) {
            $row['reason_codes'] = json_decode((string) ($row['reason_codes'] ?? '[]'), true) ?: [];
            $row['mass_accept_eligible'] = $row['status'] === 'pending'
                && self::tierMeetsFloor($row['confidence_tier'] ?? '', self::MASS_ACCEPT_FLOOR);
        }
        unset($row);

        return ['candidates' => $rows, 'total' => $total];
    }

    public function getCandidate(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM contact_merge_candidates WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        $row['reason_codes'] = json_decode((string) ($row['reason_codes'] ?? '[]'), true) ?: [];
        $row['survivor'] = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [(int) $row['survivor_id']]);
        $row['merge'] = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [(int) $row['merge_id']]);
        $row['mass_accept_eligible'] = $row['status'] === 'pending'
            && self::tierMeetsFloor($row['confidence_tier'] ?? '', self::MASS_ACCEPT_FLOOR);
        if ($row['survivor']) {
            $row['survivor_pick'] = $this->survivorRichnessBreakdown($row['survivor']);
        }
        if ($row['merge']) {
            $row['merge_pick'] = $this->survivorRichnessBreakdown($row['merge']);
        }
        return $row;
    }

    /**
     * @param list<int> $ids
     * @return array{accepted:int,failed:list<array>,results:list<array>}
     */
    public function acceptCandidates(array $ids, ?int $actorUserId = null, bool $requireHighTier = true, array $fieldOverrides = []): array
    {
        $accepted = 0;
        $failed = [];
        $results = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $cand = $this->getCandidate($id);
            if (!$cand || $cand['status'] !== 'pending') {
                $failed[] = ['id' => $id, 'error' => 'Not a pending candidate'];
                continue;
            }
            if ($requireHighTier && !self::tierMeetsFloor($cand['confidence_tier'], self::MASS_ACCEPT_FLOOR)) {
                $failed[] = ['id' => $id, 'error' => 'Below mass-accept confidence floor (high required)'];
                continue;
            }
            if (!$cand['survivor'] || !$cand['merge']) {
                $failed[] = ['id' => $id, 'error' => 'Missing contact'];
                continue;
            }
            $mergeResult = $this->merge(
                (int) $cand['survivor_id'],
                [(int) $cand['merge_id']],
                $fieldOverrides,
                false,
                $actorUserId,
                false
            );
            if (empty($mergeResult['success'])) {
                $failed[] = ['id' => $id, 'error' => $mergeResult['error'] ?? 'Merge failed'];
                continue;
            }
            $runId = $mergeResult['run_ids'][0] ?? null;
            $this->db->update('contact_merge_candidates', [
                'status' => 'accepted',
                'resolved_at' => getCurrentTimestamp(),
                'resolved_by_user_id' => $actorUserId,
                'merge_run_id' => $runId,
                'inspected_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp(),
            ], 'id = ?', [$id]);
            $accepted++;
            $results[] = ['id' => $id, 'merge' => $mergeResult];
        }
        $this->expireOrphanCandidates();
        return ['accepted' => $accepted, 'failed' => $failed, 'results' => $results];
    }

    /** Mark pending candidates whose contacts no longer exist. */
    public function expireOrphanCandidates(): int
    {
        $this->db->query(
            "UPDATE contact_merge_candidates SET status = 'expired', updated_at = ?
             WHERE status = 'pending'
               AND (
                 survivor_id NOT IN (SELECT id FROM contacts)
                 OR merge_id NOT IN (SELECT id FROM contacts)
               )",
            [getCurrentTimestamp()]
        );
        return 0;
    }

    /**
     * @param list<int> $ids
     * @return array{rejected:int,failed:list<array>}
     */
    public function rejectCandidates(array $ids, ?int $actorUserId = null): array
    {
        $rejected = 0;
        $failed = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $cand = $this->db->fetchOne('SELECT * FROM contact_merge_candidates WHERE id = ?', [$id]);
            if (!$cand || $cand['status'] !== 'pending') {
                $failed[] = ['id' => $id, 'error' => 'Not pending'];
                continue;
            }
            $this->db->update('contact_merge_candidates', [
                'status' => 'rejected',
                'resolved_at' => getCurrentTimestamp(),
                'resolved_by_user_id' => $actorUserId,
                'inspected_at' => getCurrentTimestamp(),
                'updated_at' => getCurrentTimestamp(),
            ], 'id = ?', [$id]);
            $rejected++;
        }
        return ['rejected' => $rejected, 'failed' => $failed];
    }

    public function pendingHighCount(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM contact_merge_candidates WHERE status = 'pending' AND confidence_tier = 'high'"
        );
        return (int) ($row['c'] ?? 0);
    }
}

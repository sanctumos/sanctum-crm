<?php
/**
 * Merge candidates — inspect by confidence tier; "Accept all high pending" is high only.
 * Explicit "Accept selected" works for any pending tier (including multi-select low/medium).
 */

require_once __DIR__ . '/../includes/ContactMergeService.php';

$db = Database::getInstance();
$mergeSvc = new ContactMergeService();
$actorId = isset($user['id']) ? (int) $user['id'] : null;

$tier = $_GET['tier'] ?? 'high';
if (!in_array($tier, ['high', 'medium', 'low', 'all'], true)) {
    $tier = 'high';
}
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'accepted', 'rejected', 'expired', 'all'], true)) {
    $status = 'pending';
}
$inspectId = isset($_GET['inspect']) ? (int) $_GET['inspect'] : 0;
$pageNum = max(1, (int) ($_GET['page_num'] ?? 1));
$perPage = 40;
$offset = ($pageNum - 1) * $perPage;

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $ids = [];
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
    } elseif (!empty($_POST['id'])) {
        $ids = [(int) $_POST['id']];
    }

    if ($postAction === 'mass_accept') {
        $list = $mergeSvc->listCandidates([
            'status' => 'pending',
            'tier' => 'high',
            'limit' => 200,
            'offset' => 0,
        ]);
        $ids = array_map(static fn($r) => (int) $r['id'], $list['candidates']);
        $out = $mergeSvc->acceptCandidates($ids, $actorId, true);
        $flash = 'Mass accept: ' . (int) $out['accepted'] . ' merged'
            . (count($out['failed']) ? '; ' . count($out['failed']) . ' skipped' : '');
        $flashType = $out['accepted'] > 0 ? 'success' : 'warning';
    } elseif ($postAction === 'accept_selected' && $ids !== []) {
        // Explicit selection = user reviewed these rows; do not apply mass-accept high floor
        // (that floor is only for "Accept all high pending"). Multi-select of low/medium must work.
        $out = $mergeSvc->acceptCandidates($ids, $actorId, false);
        $failN = count($out['failed']);
        $flash = 'Accepted ' . (int) $out['accepted']
            . ($failN ? '; failed: ' . $failN : '');
        if ($failN && $out['accepted'] === 0 && !empty($out['failed'][0]['error'])) {
            $flash .= ' — ' . $out['failed'][0]['error'];
        }
        $flashType = $out['accepted'] > 0 ? 'success' : 'danger';
    } elseif ($postAction === 'accept_one' && $ids !== []) {
        $out = $mergeSvc->acceptCandidates($ids, $actorId, false);
        $flash = $out['accepted'] ? 'Merge accepted.' : ('Could not accept: ' . ($out['failed'][0]['error'] ?? 'unknown'));
        $flashType = $out['accepted'] ? 'success' : 'danger';
        $inspectId = 0;
    } elseif ($postAction === 'reject_selected' || $postAction === 'reject_one') {
        $out = $mergeSvc->rejectCandidates($ids, $actorId);
        $flash = 'Rejected ' . (int) $out['rejected'];
        $inspectId = 0;
    } elseif ($postAction === 'flip_sides' && $ids !== []) {
        $out = $mergeSvc->swapCandidateSides($ids[0]);
        if (!empty($out['success'])) {
            $flash = 'Flipped keep ↔ absorb.';
            $inspectId = $ids[0];
        } else {
            $flash = $out['error'] ?? 'Could not flip sides.';
            $flashType = 'danger';
            $inspectId = $ids[0];
        }
    } elseif ($postAction === 'run_cron') {
        if ($auth->isAdmin()) {
            $stats = $mergeSvc->generateCandidates(500);
            $flash = 'Candidate scan complete: created ' . $stats['created']
                . ', updated ' . $stats['updated']
                . ' (scanned ' . $stats['scanned'] . ' contacts)';
        } else {
            $flash = 'Admin only.';
            $flashType = 'danger';
        }
    }
}

$filters = [
    'status' => $status,
    'limit' => $perPage,
    'offset' => $offset,
];
if ($tier !== 'all') {
    $filters['tier'] = $tier;
}
$list = $mergeSvc->listCandidates($filters);
$candidates = $list['candidates'];
$total = $list['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$pendingHigh = $mergeSvc->pendingHighCount();

$counts = [];
foreach (['high', 'medium', 'low'] as $t) {
    $c = $mergeSvc->listCandidates(['status' => 'pending', 'tier' => $t, 'limit' => 1]);
    $counts[$t] = $c['total'];
}

$inspect = $inspectId > 0 ? $mergeSvc->getCandidate($inspectId) : null;

$fmtName = static function (?array $c, string $prefix = ''): string {
    if (!$c) {
        return '—';
    }
    $fn = trim((string) ($c[$prefix . 'first_name'] ?? $c['first_name'] ?? ''));
    $ln = trim((string) ($c[$prefix . 'last_name'] ?? $c['last_name'] ?? ''));
    $name = trim($fn . ' ' . $ln);
    return $name !== '' ? $name : 'Unknown';
};

renderHeader('Merge');
?>

<div class="container-fluid px-3 px-lg-4 py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Merge</h1>
            <p class="text-muted mb-0">Cron proposes candidates. You inspect and accept — mass accept is high confidence only.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($auth->isAdmin()): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="run_cron">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>Scan now
                </button>
            </form>
            <?php endif; ?>
            <?php if ($pendingHigh > 0 && $status === 'pending'): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Accept all <?php echo (int) $pendingHigh; ?> high-confidence candidates? This merges contacts permanently.');">
                <input type="hidden" name="action" value="mass_accept">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check2-all me-1"></i>Mass accept high (<?php echo (int) $pendingHigh; ?>)
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($flash); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $tierTabs = [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            'all' => 'All tiers',
        ];
        foreach ($tierTabs as $key => $label):
            $active = $tier === $key;
            $href = '/index.php?page=merges&tier=' . urlencode($key) . '&status=' . urlencode($status);
            $n = $counts[$key] ?? null;
        ?>
        <a class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo $href; ?>">
            <?php echo htmlspecialchars($label); ?>
            <?php if ($n !== null): ?><span class="badge text-bg-light text-dark ms-1"><?php echo (int) $n; ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
        <span class="vr mx-1 d-none d-md-inline"></span>
        <?php foreach (['pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected'] as $sk => $sl): ?>
        <a class="btn btn-sm <?php echo $status === $sk ? 'btn-secondary' : 'btn-outline-secondary'; ?>"
           href="/index.php?page=merges&tier=<?php echo urlencode($tier); ?>&status=<?php echo urlencode($sk); ?>">
            <?php echo htmlspecialchars($sl); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="<?php echo $inspect ? 'col-lg-7' : 'col-12'; ?>">
            <form method="post" id="mergeListForm">
                <?php if ($status === 'pending' && $candidates): ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="submit" name="action" value="accept_selected" class="btn btn-sm btn-success"
                            onclick="return confirm('Accept the selected merge(s)?');">
                        Accept selected
                    </button>
                    <button type="submit" name="action" value="reject_selected" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Reject selected candidates?');">
                        Reject selected
                    </button>
                    <span class="text-muted small align-self-center">High ≥0.85 needs phone+full name (or strong name/Gerwil). Phone-only is medium.</span>
                </div>
                <?php endif; ?>

                <div class="table-responsive surface">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <?php if ($status === 'pending'): ?>
                                <th style="width:2rem"><input type="checkbox" id="checkAll" aria-label="Select all"></th>
                                <?php endif; ?>
                                <th>Confidence</th>
                                <th>Keep</th>
                                <th>Absorb</th>
                                <th>Why</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$candidates): ?>
                            <tr><td colspan="6" class="text-muted p-4">No candidates in this view.<?php if ($auth->isAdmin() && $status === 'pending'): ?> Run <strong>Scan now</strong> or the nightly cron.<?php endif; ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($candidates as $row):
                            $tierClass = [
                                'high' => 'success',
                                'medium' => 'warning',
                                'low' => 'secondary',
                            ][$row['confidence_tier']] ?? 'secondary';
                            $survName = trim(($row['survivor_first_name'] ?? '') . ' ' . ($row['survivor_last_name'] ?? ''));
                            $mergeName = trim(($row['merge_first_name'] ?? '') . ' ' . ($row['merge_last_name'] ?? ''));
                        ?>
                            <tr class="<?php echo $inspectId === (int) $row['id'] ? 'table-active' : ''; ?>">
                                <?php if ($status === 'pending'): ?>
                                <td>
                                    <input type="checkbox" name="ids[]" value="<?php echo (int) $row['id']; ?>"
                                           <?php echo !empty($row['mass_accept_eligible']) ? '' : 'data-below-floor="1"'; ?>>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge text-bg-<?php echo $tierClass; ?>">
                                        <?php echo htmlspecialchars($row['confidence_tier']); ?>
                                        <?php echo number_format((float) $row['confidence'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($survName ?: 'Unknown'); ?></div>
                                    <div class="small text-muted">#<?php echo (int) $row['survivor_id']; ?>
                                        <?php echo htmlspecialchars((string) ($row['survivor_email'] ?? '')); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($mergeName ?: 'Unknown'); ?></div>
                                    <div class="small text-muted">#<?php echo (int) $row['merge_id']; ?>
                                        <?php echo htmlspecialchars((string) ($row['merge_email'] ?? '')); ?></div>
                                </td>
                                <td class="small"><?php echo htmlspecialchars((string) ($row['reason_summary'] ?? '')); ?></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="/index.php?page=merges&tier=<?php echo urlencode($tier); ?>&status=<?php echo urlencode($status); ?>&inspect=<?php echo (int) $row['id']; ?>&page_num=<?php echo (int) $pageNum; ?>">Inspect</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm">
                    <?php for ($p = 1; $p <= min($totalPages, 20); $p++): ?>
                    <li class="page-item <?php echo $p === $pageNum ? 'active' : ''; ?>">
                        <a class="page-link" href="/index.php?page=merges&tier=<?php echo urlencode($tier); ?>&status=<?php echo urlencode($status); ?>&page_num=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

        <?php if ($inspect): ?>
        <div class="col-lg-5">
            <div class="surface surface-pad">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Inspect #<?php echo (int) $inspect['id']; ?></h2>
                        <span class="badge text-bg-<?php echo $inspect['confidence_tier'] === 'high' ? 'success' : ($inspect['confidence_tier'] === 'medium' ? 'warning' : 'secondary'); ?>">
                            <?php echo htmlspecialchars($inspect['confidence_tier']); ?>
                            <?php echo number_format((float) $inspect['confidence'], 2); ?>
                        </span>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="/index.php?page=merges&tier=<?php echo urlencode($tier); ?>&status=<?php echo urlencode($status); ?>">Close</a>
                </div>
                <p class="small text-muted"><?php echo htmlspecialchars((string) ($inspect['reason_summary'] ?? '')); ?></p>

                <?php
                $s = $inspect['survivor'] ?? null;
                $m = $inspect['merge'] ?? null;
                $fields = ['first_name', 'last_name', 'email', 'phone', 'company', 'position', 'city', 'state'];
                $sPick = $inspect['survivor_pick'] ?? ['score' => 0, 'breakdown' => []];
                $mPick = $inspect['merge_pick'] ?? ['score' => 0, 'breakdown' => []];
                ?>
                <div class="alert alert-light border small mb-3">
                    <strong>How Keep was chosen (scan heuristic)</strong>
                    <div class="mt-1">Richer card wins: personal email +5, former-employer email +1, phone +2, real last name +2, company +1, customer +1. Tie → lower contact id. Flip anytime before accept.</div>
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <div class="fw-semibold">Keep score: <?php echo (int) ($sPick['score'] ?? 0); ?></div>
                            <div class="text-muted"><?php echo htmlspecialchars(implode(', ', $sPick['breakdown'] ?? [])); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="fw-semibold">Absorb score: <?php echo (int) ($mPick['score'] ?? 0); ?></div>
                            <div class="text-muted"><?php echo htmlspecialchars(implode(', ', $mPick['breakdown'] ?? [])); ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead><tr><th>Field</th><th>Keep (#<?php echo (int) $inspect['survivor_id']; ?>)</th><th>Absorb (#<?php echo (int) $inspect['merge_id']; ?>)</th></tr></thead>
                        <tbody>
                        <?php foreach ($fields as $f):
                            $sv = (string) ($s[$f] ?? '');
                            $mv = (string) ($m[$f] ?? '');
                            $diff = $sv !== '' && $mv !== '' && $sv !== $mv;
                        ?>
                            <tr class="<?php echo $diff ? 'table-warning' : ''; ?>">
                                <td class="text-muted"><?php echo htmlspecialchars($f); ?></td>
                                <td><?php echo htmlspecialchars($sv !== '' ? $sv : '—'); ?></td>
                                <td><?php echo htmlspecialchars($mv !== '' ? $mv : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-2">
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/index.php?page=view_contact&id=<?php echo (int) $inspect['survivor_id']; ?>">Open keep</a>
                    <?php if ($m): ?>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/index.php?page=view_contact&id=<?php echo (int) $inspect['merge_id']; ?>">Open absorb</a>
                    <?php endif; ?>
                    <?php if (($inspect['status'] ?? '') === 'pending'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="id" value="<?php echo (int) $inspect['id']; ?>">
                        <button type="submit" name="action" value="flip_sides" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-left-right me-1"></i>Flip keep ↔ absorb
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (($inspect['status'] ?? '') === 'pending'): ?>
                <form method="post" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="id" value="<?php echo (int) $inspect['id']; ?>">
                    <button type="submit" name="action" value="accept_one" class="btn btn-success"
                            onclick="return confirm('Merge absorb into keep? Absorbed contact is deleted; lineage goes into notes + sidecar.');">
                        Accept merge
                    </button>
                    <button type="submit" name="action" value="reject_one" class="btn btn-outline-danger">Reject</button>
                </form>
                <?php if ($inspect['confidence_tier'] !== 'high'): ?>
                <p class="small text-muted mt-2 mb-0">This pair is below the mass-accept floor. Individual accept is allowed after you review the fields above.</p>
                <?php endif; ?>
                <?php else: ?>
                <p class="mb-0"><span class="badge text-bg-secondary"><?php echo htmlspecialchars($inspect['status']); ?></span></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <p class="small text-muted mt-4 mb-0">
        Cron: <code>php …/cron/merge_candidates.php</code>
        · API: <code>GET /api/v1/merges/candidates</code>,
        <code>POST …/accept</code>, <code>POST …/reject</code>
    </p>
</div>

<script>
(function () {
    var all = document.getElementById('checkAll');
    if (!all) return;
    all.addEventListener('change', function () {
        document.querySelectorAll('#mergeListForm input[name="ids[]"]').forEach(function (cb) {
            cb.checked = all.checked;
        });
    });
})();
</script>

<?php
renderFooter();

<?php
/**
 * Layout Template System
 * Sanctum CRM - Consistent page layout
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/skin-lab-env.php';

// Get current user (if auth is available)
$user = null;
if (isset($auth) && $auth instanceof Auth) {
    $user = $auth->getUser();
}

// Get current page for navigation highlighting
$currentPage = $_GET['page'] ?? 'dashboard';

// Get database instance for stats
$db = Database::getInstance();

// Get statistics for dashboard
$stats = [];
$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts");
$stats['total_contacts'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'lead'");
$stats['total_leads'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE contact_type = 'customer'");
$stats['total_customers'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM deals");
$stats['total_deals'] = $result['count'];

$result = $db->fetchOne("SELECT SUM(amount) as total FROM deals WHERE amount IS NOT NULL");
$stats['total_deal_value'] = $result['total'] ?? 0;

// Enrichment statistics
$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'enriched'");
$stats['enriched_contacts'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'failed'");
$stats['failed_enrichments'] = $result['count'];

$result = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'pending'");
$stats['pending_enrichments'] = $result['count'];

// Calculate enrichment rate
$stats['enrichment_rate'] = $stats['total_contacts'] > 0 ? 
    round(($stats['enriched_contacts'] / $stats['total_contacts']) * 100, 2) : 0;

// Recent contacts
$recent_contacts = $db->fetchAll("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5");

// Recent deals
$recent_deals = $db->fetchAll("SELECT * FROM deals ORDER BY created_at DESC LIMIT 5");

/**
 * Map a contact_type ('lead', 'customer', 'prospect', ...) to a status-pill modifier.
 * Customers feel like a finished state; prospects are mid-flight; cold/disqualified are blocked.
 */
function crm_pill_for_contact_type(?string $type): string {
    return match (strtolower((string)$type)) {
        'lead'         => 'todo',
        'prospect'     => 'doing',
        'customer'     => 'done',
        'cold'         => 'blocked',
        'disqualified' => 'blocked',
        default        => 'default',
    };
}

/**
 * Map a contact lifecycle status (the `contact_status` column — new/qualified/contacted/etc.)
 * to a status-pill modifier. We try not to over-color this; most lifecycle stops sit at
 * `info` (intermediate) until they reach a terminal state.
 */
function crm_pill_for_contact_status(?string $status): string {
    return match (strtolower((string)$status)) {
        'new'         => 'info',
        'qualified'   => 'info',
        'contacted'   => 'doing',
        'engaged'     => 'doing',
        'converted'   => 'done',
        'won'         => 'done',
        'lost'        => 'blocked',
        'disqualified'=> 'blocked',
        default       => 'default',
    };
}

/**
 * Map an enrichment_status to a status-pill modifier.
 * `pending` (the most common) is doing; `enriched` is done; `failed` is blocked; empty/unknown is default.
 */
function crm_pill_for_enrichment(?string $status): string {
    return match (strtolower((string)$status)) {
        'enriched' => 'done',
        'pending'  => 'doing',
        'failed'   => 'blocked',
        'empty'    => 'default',
        ''         => 'default',
        default    => 'default',
    };
}

/**
 * Map a deal stage to a status-pill modifier.
 * Pipeline reads left to right: prospecting/qualification -> proposal/negotiation -> closed.
 */
function crm_pill_for_deal_stage(?string $stage): string {
    return match (strtolower((string)$stage)) {
        'prospecting'   => 'info',
        'qualification' => 'info',
        'proposal'      => 'doing',
        'negotiation'   => 'doing',
        'closed_won'    => 'done',
        'closed_lost'   => 'blocked',
        default         => 'default',
    };
}

/**
 * Render a status-pill <span>. Pass a label and a pre-mapped variant ('todo' | 'doing' |
 * 'done' | 'blocked' | 'info' | 'default'). $icon is an optional Bootstrap Icon name.
 */
function crm_status_pill(string $label, string $variant = 'default', ?string $icon = null): string {
    $variant = htmlspecialchars($variant, ENT_QUOTES, 'UTF-8');
    $iconHtml = $icon ? '<i class="bi bi-' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i>' : '';
    return '<span class="status-pill status-pill--' . $variant . '">'
        . $iconHtml
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

/** Human label for a normalized tag slug (dfw-metro → Dfw Metro). */
function crm_format_tag_label(string $tag): string {
    $label = str_replace(['-', '_'], ' ', strtolower(trim($tag)));
    return $label === '' ? '' : ucwords($label);
}

/** Contacts list URL filtered to a single tag. */
function crm_contacts_tag_url(string $tag): string {
    return '/index.php?' . http_build_query(['page' => 'contacts', 'tag' => $tag]);
}

/**
 * Render clickable tag chips. When $activeTag matches a chip, it gets the active style
 * so the user can see which filter is applied.
 */
function crm_render_tag_chips(array $tags, ?string $activeTag = null): string {
    if ($tags === []) {
        return '';
    }
    $html = '<div class="chip-row">';
    foreach ($tags as $tag) {
        $tag = (string) $tag;
        if ($tag === '') {
            continue;
        }
        $isActive = $activeTag !== null && $activeTag === $tag;
        $class = 'tag-chip text-decoration-none' . ($isActive ? ' tag-chip--active' : '');
        $html .= '<a href="' . htmlspecialchars(crm_contacts_tag_url($tag), ENT_QUOTES, 'UTF-8') . '" class="' . $class . '">'
            . '<i class="bi bi-tag"></i>'
            . htmlspecialchars(crm_format_tag_label($tag), ENT_QUOTES, 'UTF-8')
            . '</a>';
    }
    $html .= '</div>';
    return $html;
}

function renderPageHeader(string $title, string $subtitle = '', string $actionsHtml = ''): void
{
    ?>
            <div class="page-header">
                <div class="page-header__title">
                    <h1><?php echo htmlspecialchars($title); ?></h1>
                    <?php if ($subtitle !== ''): ?>
                        <div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($actionsHtml !== ''): ?>
                    <div class="page-header__actions"><?php echo $actionsHtml; ?></div>
                <?php endif; ?>
            </div>
    <?php
}

function renderHeader($title = null) {
    global $user, $auth, $currentPage;
    $appName = getAppName();
    $pageTitle = $title ? $title . ' - ' . $appName : $appName;
    $userName = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
    if ($userName === '' && $user) {
        $userName = $user['username'] ?? '';
    }
    $isAdmin = isset($auth) && $auth instanceof Auth ? $auth->isAdmin() : false;
    $skin = crmSkinEffectiveSlug(is_array($user) ? $user : null);
    $navLight = crmSkinUsesLightNav($skin);
    $navThemeClass = $navLight ? 'navbar-light' : 'navbar-dark';
    // First-class nav stays short so desktop chrome stays one line.
    $mergeBadge = 0;
    try {
        require_once __DIR__ . '/ContactMergeService.php';
        $mergeBadge = (new ContactMergeService())->pendingHighCount();
    } catch (Throwable $e) {
        $mergeBadge = 0;
    }
    $navItems = [
        ['key' => 'dashboard', 'href' => '/index.php',                'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
        ['key' => 'contacts',  'href' => '/index.php?page=contacts',  'icon' => 'bi-people',       'label' => 'Contacts'],
        ['key' => 'merges',    'href' => '/index.php?page=merges',    'icon' => 'bi-intersect',    'label' => 'Merge', 'badge' => $mergeBadge],
        ['key' => 'deals',     'href' => '/index.php?page=deals',     'icon' => 'bi-cash-coin',    'label' => 'Deals'],
        ['key' => 'reports',   'href' => '/index.php?page=reports',   'icon' => 'bi-bar-chart',    'label' => 'Reports'],
    ];
    $settingsActive = in_array($currentPage, ['settings', 'webhooks', 'users', 'help'], true);
    ?>
    <!DOCTYPE html>
    <html lang="en" data-skin-comp="<?php echo htmlspecialchars($skin); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="/assets/css/crm.css?v=14" rel="stylesheet">
        <link href="<?php echo htmlspecialchars(crmSkinStylesheetHref($skin)); ?>" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg <?php echo htmlspecialchars($navThemeClass); ?> admin-nav py-0<?php echo $navLight ? '' : ' bg-dark'; ?>">
            <div class="container-fluid px-3 px-lg-4">
                <a class="navbar-brand fw-semibold d-inline-flex align-items-center gap-2" href="/index.php" title="<?php echo htmlspecialchars($appName); ?>">
                    <i class="bi bi-people"></i>
                    <span class="crm-brand-short d-none d-xxl-inline"><?php echo htmlspecialchars($appName); ?></span>
                    <span class="crm-brand-compact d-inline d-xxl-none"><?php echo htmlspecialchars($appName); ?></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#crmNavbar" aria-controls="crmNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="crmNavbar">
                    <?php if ($user): ?>
                    <div class="crm-nav-cluster d-flex flex-column flex-lg-row flex-lg-nowrap ms-lg-auto align-items-stretch align-items-lg-center">
                        <?php foreach ($navItems as $item): ?>
                            <a class="btn btn-outline-light text-center text-lg-start <?php echo $currentPage === $item['key'] ? 'active' : ''; ?>" href="<?php echo $item['href']; ?>">
                                <i class="bi <?php echo $item['icon']; ?> me-1"></i><?php echo htmlspecialchars($item['label']); ?>
                                <?php if (!empty($item['badge'])): ?>
                                <span class="badge text-bg-warning ms-1"><?php echo (int) $item['badge']; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <div class="dropdown crm-nav-end-dropdown w-100 w-lg-auto flex-lg-grow-0">
                            <button class="btn btn-outline-light text-center text-lg-start dropdown-toggle w-100 w-lg-auto <?php echo $settingsActive ? 'active' : ''; ?>" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-haspopup="true">
                                <i class="bi bi-gear me-1"></i>Settings
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item <?php echo $currentPage === 'webhooks' ? 'active' : ''; ?>" href="/index.php?page=webhooks"><i class="bi bi-link-45deg me-2"></i>Webhooks</a></li>
                                <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="/index.php?page=users"><i class="bi bi-people-fill me-2"></i>Users</a></li>
                                <li><a class="dropdown-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="/index.php?page=settings"><i class="bi bi-sliders me-2"></i>System settings</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item <?php echo $currentPage === 'help' ? 'active' : ''; ?>" href="/index.php?page=help"><i class="bi bi-question-circle me-2"></i>Help</a></li>
                            </ul>
                        </div>
                        <hr class="d-lg-none border-secondary opacity-50 my-1 mx-0 w-100">
                        <div class="dropdown crm-nav-end-dropdown w-100 w-lg-auto flex-lg-grow-0">
                            <button class="btn btn-outline-light text-center text-lg-start dropdown-toggle d-inline-flex align-items-center gap-2 w-100 w-lg-auto" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-haspopup="true">
                                <i class="bi bi-person-circle"></i>
                                <span class="crm-nav-user"><?php echo htmlspecialchars($userName !== '' ? $userName : 'Account'); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/index.php?page=profile"><i class="bi bi-key me-2"></i>Change password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="ms-lg-auto py-2 py-lg-0">
                        <a class="btn btn-outline-light px-3" href="/login.php">Login</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <main class="crm-shell">
    <?php
}

function renderFooter() {
    ?>
        </main>
        <script src="/assets/js/crm-api.js"></script>
        <script src="/assets/js/crm-toast.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
        <div class="crm-toast-host" id="crmToastHost" aria-live="polite" aria-relevant="additions"></div>
    </body>
    </html>
    <?php
}

function renderDashboardStats() {
    global $stats;
    $primary = [
        ['icon' => 'people',      'tone' => 'accent',  'value' => number_format($stats['total_contacts']),            'label' => 'Contacts'],
        ['icon' => 'person-plus', 'tone' => 'accent',  'value' => number_format($stats['total_leads']),               'label' => 'Leads'],
        ['icon' => 'person-check','tone' => 'success', 'value' => number_format($stats['total_customers']),           'label' => 'Customers'],
        ['icon' => 'cash-coin',   'tone' => 'success', 'value' => '$' . number_format($stats['total_deal_value'], 2), 'label' => 'Deal Value'],
    ];
    $kpis = [
        ['value' => number_format($stats['enriched_contacts']),   'label' => 'Enriched'],
        ['value' => $stats['enrichment_rate'] . '%',              'label' => 'Enrichment rate'],
        ['value' => number_format($stats['pending_enrichments']), 'label' => 'Pending'],
        ['value' => number_format($stats['failed_enrichments']),  'label' => 'Failed'],
    ];
    ?>
    <div class="row g-3 mb-3">
        <?php foreach ($primary as $t): ?>
        <div class="col-6 col-lg-3">
            <div class="surface surface-pad h-100">
                <div class="d-flex align-items-center gap-3">
                    <span class="crm-glyph-tile crm-glyph-tile--circle crm-glyph-tile--<?php echo $t['tone']; ?>">
                        <i class="bi bi-<?php echo $t['icon']; ?>"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="h4 mb-0 fw-semibold text-truncate"><?php echo $t['value']; ?></div>
                        <div class="fine-print text-truncate"><?php echo $t['label']; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="section">
        <div class="section-title">Enrichment KPIs <span class="section-title__count">pipeline health</span></div>
        <div class="crm-kpi-row">
            <?php foreach ($kpis as $k): ?>
            <div class="crm-kpi">
                <div class="crm-kpi__value"><?php echo $k['value']; ?></div>
                <div class="crm-kpi__label"><?php echo $k['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function renderRecentActivity() {
    global $recent_contacts, $recent_deals;
    ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="surface h-100">
                <div class="surface__header">
                    <h5><i class="bi bi-clock me-2 text-muted"></i>Recent Contacts</h5>
                    <div class="surface__actions">
                        <a href="/index.php?page=contacts" class="btn btn-sm btn-outline-secondary">View all</a>
                    </div>
                </div>
                <div class="surface__body">
                    <?php if (empty($recent_contacts)): ?>
                        <div class="empty-hint">No recent contacts</div>
                    <?php else: ?>
                    <ul class="activity-feed">
                        <?php foreach ($recent_contacts as $contact): ?>
                        <li class="activity-feed__item">
                            <div class="min-w-0 flex-grow-1">
                                <strong><?php echo crm_h($contact['first_name'] . ' ' . $contact['last_name']); ?></strong>
                                <div class="fine-print"><?php echo !empty($contact['email']) ? crm_h($contact['email']) : 'No email'; ?></div>
                                <div class="chip-row mt-1">
                                    <?php echo crm_status_pill(ucfirst((string)$contact['contact_type']), crm_pill_for_contact_type($contact['contact_type'])); ?>
                                    <?php echo crm_status_pill(ucfirst((string)$contact['contact_status']), crm_pill_for_contact_status($contact['contact_status'])); ?>
                                </div>
                            </div>
                            <small class="text-muted text-nowrap"><?php echo date('M j', strtotime($contact['created_at'])); ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="surface h-100">
                <div class="surface__header">
                    <h5><i class="bi bi-graph-up me-2 text-muted"></i>Recent Deals</h5>
                    <div class="surface__actions">
                        <a href="/index.php?page=deals" class="btn btn-sm btn-outline-secondary">View all</a>
                    </div>
                </div>
                <div class="surface__body">
                    <?php if (empty($recent_deals)): ?>
                        <div class="empty-hint">No recent deals</div>
                    <?php else: ?>
                    <ul class="activity-feed">
                        <?php foreach ($recent_deals as $deal): ?>
                        <li class="activity-feed__item">
                            <div class="min-w-0 flex-grow-1">
                                <strong><?php echo crm_h($deal['title']); ?></strong>
                                <div class="fine-print">
                                    <?php echo !empty($deal['amount']) ? '$' . number_format((float)$deal['amount'], 2) : 'No amount'; ?>
                                </div>
                                <div class="chip-row mt-1">
                                    <?php echo crm_status_pill(ucwords(str_replace('_', ' ', (string)$deal['stage'])), crm_pill_for_deal_stage($deal['stage'] ?? '')); ?>
                                </div>
                            </div>
                            <small class="text-muted text-nowrap"><?php echo date('M j', strtotime($deal['created_at'])); ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

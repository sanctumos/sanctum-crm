<?php
/**
 * Contacts Page
 * Sanctum CRM - Contact Management
 */

// Get database instance
$db = Database::getInstance();
require_once __DIR__ . '/../includes/ContactTagService.php';
require_once __DIR__ . '/../includes/ContactsListPagination.php';
$tagService = new ContactTagService($db);

// Handle actions
$action = $_GET['action'] ?? 'list';
$contact_id = $_GET['id'] ?? null;

// Get filter parameters
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$enrichment_filter = $_GET['enrichment_status'] ?? '';
$source_filter = $_GET['source'] ?? '';
$tag_filter_raw = $_GET['tag'] ?? '';
$tag_filter = $tag_filter_raw !== '' ? $tagService->normalizeTag($tag_filter_raw) : '';

// Handle view mode with session persistence
if (isset($_GET['view'])) {
    $_SESSION['contacts_view_mode'] = $_GET['view'];
}
$view_mode = $_SESSION['contacts_view_mode'] ?? 'cards'; // Default to cards view

// Handle pagination with session persistence
if (isset($_GET['per_page'])) {
    $_SESSION['contacts_per_page'] = (int)$_GET['per_page'];
}
$per_page = $_SESSION['contacts_per_page'] ?? 100; // Default to 100

// page_num in the URL always wins when paginating with filters active (GitHub #13)
$page = ContactsListPagination::resolvePage($_GET);

// Build query
$where = "1=1";
$params = [];

if ($type_filter) {
    $where .= " AND contact_type = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where .= " AND contact_status = ?";
    $params[] = $status_filter;
}

if ($enrichment_filter) {
    if ($enrichment_filter === 'null') {
        $where .= " AND COALESCE(NULLIF(TRIM(enrichment_status), ''), '') != 'enriched'";
    } else {
        $where .= " AND enrichment_status = ?";
        $params[] = $enrichment_filter;
    }
}

if ($source_filter) {
    if ($source_filter === 'null') {
        $where .= " AND (source IS NULL OR source = '')";
    } else {
        $where .= " AND source = ?";
        $params[] = $source_filter;
    }
}

if ($tag_filter !== '') {
    $where .= " AND contacts.id IN (SELECT contact_id FROM contact_tags WHERE tag = ?)";
    $params[] = $tag_filter;
}

// Get total count for pagination (use copy of params)
$count_sql = "SELECT COUNT(*) as total FROM contacts WHERE $where";
$count_params = $params; // Copy params for count query
$total_result = $db->fetchOne($count_sql, $count_params);
$total_contacts = $total_result['total'] ?? 0;
$total_pages = (int) ceil($total_contacts / $per_page);
$page = ContactsListPagination::capPage($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Get contacts with pagination
$sql = "SELECT * FROM contacts WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$query_params = $params; // Copy params for main query
$query_params[] = $per_page;
$query_params[] = $offset;
$contacts = $db->fetchAll($sql, $query_params);

$tagMap = $tagService->listTagsForContactIds(array_column($contacts, 'id'));
foreach ($contacts as &$contact) {
    $contact['tags'] = $tagMap[(int) $contact['id']] ?? [];
}
unset($contact);

// Get unique sources for the dropdown
$sources_sql = "SELECT DISTINCT source FROM contacts WHERE source IS NOT NULL AND source != '' ORDER BY source";
$sources_result = $db->fetchAll($sources_sql);
$available_sources = array_column($sources_result, 'source');

$tags_result = $db->fetchAll("SELECT DISTINCT tag FROM contact_tags ORDER BY tag");
$available_tags = array_column($tags_result, 'tag');

// Render the page using the template system
renderHeader('Contacts');
ob_start();
?>
<a href="/?page=import_contacts" class="btn btn-success"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import CSV</a>
<button class="btn btn-info" type="button" onclick="exportContactsCSV()"><i class="bi bi-download me-1"></i>Export CSV</button>
<button class="btn btn-warning" type="button" onclick="bulkEnrichContacts()"><i class="bi bi-magic me-1"></i>Bulk Enrich</button>
<?php if ($tag_filter !== ''): ?>
<button class="btn btn-outline-danger" type="button" onclick="bulkDeleteByTag('<?php echo htmlspecialchars($tag_filter, ENT_QUOTES); ?>', <?php echo (int) $tagService->countContactsWithTag($tag_filter); ?>)"><i class="bi bi-trash me-1"></i>Delete tag “<?php echo htmlspecialchars(crm_format_tag_label($tag_filter)); ?>”</button>
<?php endif; ?>
<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addContactModal"><i class="bi bi-plus-lg me-1"></i>Add Contact</button>
<?php
renderPageHeader('Contacts', 'Leads and customers', ob_get_clean());
?>

<style>
    .table {
        border-radius: 10px;
        overflow: hidden;
    }
    .btn-action {
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 0.875rem;
    }
    .view-toggle .btn {
        border-radius: 6px;
    }
    .view-toggle .btn.active {
        background-color: var(--crm-accent, #0d6efd);
        border-color: var(--crm-accent, #0d6efd);
        color: white;
    }
    .table th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
</style>

<!-- Filters -->
<form class="filter-bar" method="GET" action="/index.php" role="search">
    <input type="hidden" name="page" value="contacts">
    <?php if (!empty($view_mode)): ?>
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
    <?php endif; ?>
    <div class="filter-bar__field">
        <select name="type" class="form-select" aria-label="Contact type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="lead" <?php echo $type_filter === 'lead' ? 'selected' : ''; ?>>Leads</option>
            <option value="customer" <?php echo $type_filter === 'customer' ? 'selected' : ''; ?>>Customers</option>
        </select>
    </div>
    <div class="filter-bar__field">
        <select name="status" class="form-select" aria-label="Contact status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
            <option value="qualified" <?php echo $status_filter === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
    </div>
    <div class="filter-bar__field">
        <select name="enrichment_status" class="form-select" aria-label="Enrichment status" onchange="this.form.submit()">
            <option value="">All Enrichment</option>
            <option value="enriched" <?php echo $enrichment_filter === 'enriched' ? 'selected' : ''; ?>>Enriched</option>
            <option value="not_found" <?php echo $enrichment_filter === 'not_found' ? 'selected' : ''; ?>>Not Found</option>
            <option value="failed" <?php echo $enrichment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
            <option value="pending" <?php echo $enrichment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="null" <?php echo $enrichment_filter === 'null' ? 'selected' : ''; ?>>Not Enriched</option>
        </select>
    </div>
    <div class="filter-bar__field">
        <select name="source" class="form-select" aria-label="Source" onchange="this.form.submit()">
            <option value="">All Sources</option>
            <option value="null" <?php echo $source_filter === 'null' ? 'selected' : ''; ?>>No Source</option>
            <?php foreach ($available_sources as $source): ?>
                <option value="<?php echo htmlspecialchars($source); ?>" <?php echo $source_filter === $source ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $source))); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-bar__field">
        <select name="tag" class="form-select" aria-label="Tag" onchange="this.form.submit()">
            <option value="">All Tags</option>
            <?php foreach ($available_tags as $tagOption): ?>
                <option value="<?php echo htmlspecialchars($tagOption); ?>" <?php echo $tag_filter === $tagOption ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(crm_format_tag_label($tagOption)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-bar__actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-funnel-fill me-1"></i>Filter
        </button>
        <a href="/index.php?page=contacts<?php echo $view_mode ? '&view=' . urlencode($view_mode) : ''; ?>" class="btn btn-outline-secondary" title="Clear filters">
            <i class="bi bi-x-lg"></i><span class="d-none d-sm-inline ms-1">Clear</span>
        </a>
        <div class="btn-group view-toggle" role="group" aria-label="View mode">
            <?php
            $view_params = $_GET;
            $view_params['page'] = 'contacts';
            ?>
            <a href="/index.php?<?php echo http_build_query(array_merge($view_params, ['view' => 'cards'])); ?>"
               class="btn btn-outline-secondary <?php echo $view_mode === 'cards' ? 'active' : ''; ?>"
               aria-label="Cards view">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </a>
            <a href="/index.php?<?php echo http_build_query(array_merge($view_params, ['view' => 'list'])); ?>"
               class="btn btn-outline-secondary <?php echo $view_mode === 'list' ? 'active' : ''; ?>"
               aria-label="List view">
                <i class="bi bi-list-ul"></i>
            </a>
        </div>
    </div>
</form>

<!-- Pagination Controls -->
<?php if ($total_contacts > 0): ?>
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <label for="perPageSelect" class="mb-0">Show:</label>
                    <select id="perPageSelect" class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="500" <?php echo $per_page == 500 ? 'selected' : ''; ?>>500</option>
                    </select>
                    <span class="text-muted">
                        Showing <?php echo count($contacts); ?> of <?php echo number_format($total_contacts); ?> contacts
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Contacts pagination">
                    <ul class="pagination justify-content-end mb-0">
                        <!-- Previous button -->
                        <?php
                        $perPageFromQuery = (isset($_GET['per_page']) && $_GET['per_page'] !== '')
                            ? (int) $_GET['per_page']
                            : null;
                        $pagination_params = ContactsListPagination::buildPaginationParams(
                            $view_mode,
                            $type_filter,
                            $status_filter,
                            $enrichment_filter,
                            $source_filter,
                            $tag_filter,
                            $perPageFromQuery
                        );
                        ?>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => max(1, $page - 1)])); ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <!-- Page numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => 1])); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => $total_pages])); ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next button -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => min($total_pages, $page + 1)])); ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Contacts Display -->
<?php if ($view_mode === 'cards'): ?>
<!-- Cards View -->
<div class="row crm-card-grid">
    <?php foreach ($contacts as $contact): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="crm-card crm-card--grid">
            <div class="crm-card__header">
                <div class="crm-card__heading">
                    <h3 class="crm-card__title">
                        <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                    </h3>
                    <p class="crm-card__subtitle"><?php echo $contact['email'] ? htmlspecialchars($contact['email']) : 'No email'; ?></p>
                </div>
                <div class="crm-card__actions">
                    <a href="/index.php?page=view_contact&id=<?php echo $contact['id']; ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>View
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="enrichContact(<?php echo $contact['id']; ?>)">
                        <i class="bi bi-magic me-1"></i>Enrich
                    </button>
                </div>
            </div>

            <?php if ($contact['phone'] || $contact['company'] || !empty($contact['position'])): ?>
            <div class="crm-card__body">
                <?php if ($contact['phone']): ?>
                <p><i class="bi bi-telephone me-2 text-muted"></i><?php echo htmlspecialchars($contact['phone']); ?></p>
                <?php endif; ?>
                <?php if ($contact['company']): ?>
                <p><i class="bi bi-building me-2 text-muted"></i><?php echo crm_h($contact['company']); ?></p>
                <?php endif; ?>
                <?php if (!empty($contact['position'])): ?>
                <p><i class="bi bi-briefcase me-2 text-muted"></i><?php echo htmlspecialchars($contact['position']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="crm-card__footer">
                <div class="chip-row">
                    <?php echo crm_status_pill(ucfirst((string)$contact['contact_type']), crm_pill_for_contact_type($contact['contact_type'])); ?>
                    <?php echo crm_status_pill(ucfirst((string)$contact['contact_status']), crm_pill_for_contact_status($contact['contact_status'])); ?>
                    <?php if ($contact['enrichment_status']): ?>
                        <?php echo crm_status_pill(ucfirst((string)$contact['enrichment_status']), crm_pill_for_enrichment($contact['enrichment_status']), 'magic'); ?>
                    <?php endif; ?>
                </div>
                <small class="text-muted text-nowrap">
                    <?php echo date('M j, Y', strtotime($contact['created_at'])); ?>
                </small>
            </div>
            <?php if (!empty($contact['tags'])): ?>
                <div class="mt-2">
                    <?php echo crm_render_tag_chips($contact['tags'], $tag_filter !== '' ? $tag_filter : null); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
                    <table class="table table-hover crm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Company</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Enrichment</th>
                        <th>Tags</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></strong>
                            <?php if (!empty($contact['position'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($contact['position']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $contact['email'] ? htmlspecialchars($contact['email']) : '<span class="text-muted">No email</span>'; ?></td>
                        <td><?php echo $contact['phone'] ? htmlspecialchars($contact['phone']) : '-'; ?></td>
                        <td><?php echo $contact['company'] ? crm_h($contact['company']) : '-'; ?></td>
                        <td><?php echo crm_status_pill(ucfirst((string)$contact['contact_type']), crm_pill_for_contact_type($contact['contact_type'])); ?></td>
                        <td><?php echo crm_status_pill(ucfirst((string)$contact['contact_status']), crm_pill_for_contact_status($contact['contact_status'])); ?></td>
                        <td>
                            <?php if ($contact['enrichment_status']): ?>
                                <?php echo crm_status_pill(ucfirst((string)$contact['enrichment_status']), crm_pill_for_enrichment($contact['enrichment_status']), 'magic'); ?>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($contact['tags'])): ?>
                                <?php echo crm_render_tag_chips($contact['tags'], $tag_filter !== '' ? $tag_filter : null); ?>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="/index.php?page=view_contact&id=<?php echo $contact['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success" onclick="enrichContact(<?php echo $contact['id']; ?>)" title="Enrich">
                                    <i class="bi bi-magic"></i>
                                </button>
                                <a href="/index.php?page=edit_contact&id=<?php echo $contact['id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button onclick="deleteContact(<?php echo $contact['id']; ?>)" 
                                        class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($contacts)): ?>
<div class="text-center py-5">
    <i class="bi bi-people fs-1 text-muted mb-3"></i>
    <h5>No Contacts Found</h5>
    <p class="text-muted">Get started by adding your first contact.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
        <i class="bi bi-plus-lg me-2"></i>Add Contact
    </button>
</div>
<?php endif; ?>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addContactForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addContactModalLabel">Add New Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company" class="form-label">Company</label>
                                <input type="text" class="form-control" id="company" name="company">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="position" name="position">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_type" class="form-label">Type</label>
                                <select class="form-select" id="contact_type" name="contact_type" required>
                                    <option value="lead">Lead</option>
                                    <option value="customer">Customer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_status" class="form-label">Status</label>
                                <select class="form-select" id="contact_status" name="contact_status" required>
                                    <option value="new">New</option>
                                    <option value="qualified">Qualified</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="source" class="form-label">Source</label>
                        <select class="form-select" id="source" name="source">
                            <option value="">Select Source</option>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="social_media">Social Media</option>
                            <option value="email_campaign">Email Campaign</option>
                            <option value="cold_call">Cold Call</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1" aria-labelledby="editContactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editContactForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editContactModalLabel">Edit Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_contact_id" name="contact_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_company" class="form-label">Company</label>
                                <input type="text" class="form-control" id="edit_company" name="company">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="edit_position" name="position">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_contact_type" class="form-label">Type</label>
                                <select class="form-select" id="edit_contact_type" name="contact_type" required>
                                    <option value="lead">Lead</option>
                                    <option value="customer">Customer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_contact_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_contact_status" name="contact_status" required>
                                    <option value="new">New</option>
                                    <option value="qualified">Qualified</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_source" class="form-label">Source</label>
                        <select class="form-select" id="edit_source" name="source">
                            <option value="">Select Source</option>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="social_media">Social Media</option>
                            <option value="email_campaign">Email Campaign</option>
                            <option value="cold_call">Cold Call</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger me-auto" onclick="deleteContactFromModal()">
                        <i class="bi bi-trash me-2"></i>Delete Contact
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle form submissions
document.getElementById('addContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    fetch(crmApiUrl('contacts'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        } else {
            return response.json().then(error => {
                throw new Error(error.error || 'Failed to create contact');
            });
        }
    })
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
});

document.getElementById('editContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    const contactId = data.contact_id;
    delete data.contact_id;
    
    fetch(crmApiUrl(`contacts/${contactId}`), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        } else {
            return response.json().then(error => {
                throw new Error(error.error || 'Failed to update contact');
            });
        }
    })
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
});

function editContact(contactId) {
    // Fetch contact data and populate form
    fetch(crmApiUrl(`contacts/${contactId}`), {
        credentials: 'include'
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        } else {
            return response.json().then(error => {
                throw new Error(error.error || 'Failed to fetch contact');
            });
        }
    })
    .then(result => {
        if (result.success) {
            const contact = result.contact;
            document.getElementById('edit_contact_id').value = contact.id;
            document.getElementById('edit_first_name').value = contact.first_name;
            document.getElementById('edit_last_name').value = contact.last_name;
            document.getElementById('edit_email').value = contact.email || '';
            document.getElementById('edit_phone').value = contact.phone || '';
            document.getElementById('edit_company').value = contact.company || '';
            document.getElementById('edit_position').value = contact.position || '';
            document.getElementById('edit_contact_type').value = contact.contact_type;
            document.getElementById('edit_contact_status').value = contact.contact_status;
            document.getElementById('edit_source').value = contact.source || '';
            document.getElementById('edit_notes').value = contact.notes || '';
            
            new bootstrap.Modal(document.getElementById('editContactModal')).show();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
}

function viewContact(contactId) {
    // Redirect to contact detail page or show in modal
    alert('View contact functionality - Contact ID: ' + contactId);
}

function deleteContact(contactId) {
    if (confirm('Are you sure you want to delete this contact? This action cannot be undone.')) {
        fetch(crmApiUrl(`contacts/${contactId}`), {
            method: 'DELETE',
            credentials: 'include'
        })
        .then(response => {
            if (response.ok) {
                // DELETE operations return 204 No Content, so check if there's content to parse
                if (response.status === 204) {
                    // 204 No Content - no body to parse
                    return { success: true };
                } else {
                    // Other successful responses might have JSON content
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // Non-JSON successful response
                        return { success: true };
                    }
                }
            } else {
                return response.json().then(error => {
                    throw new Error(error.error || 'Failed to delete contact');
                });
            }
        })
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Network error: ' + error.message);
        });
    }
}

function deleteContactFromModal() {
    const contactId = document.getElementById('edit_contact_id').value;
    if (contactId && confirm('Are you sure you want to delete this contact? This action cannot be undone.')) {
        fetch(crmApiUrl(`contacts/${contactId}`), {
            method: 'DELETE',
            credentials: 'include'
        })
        .then(response => {
            if (response.ok) {
                // DELETE operations return 204 No Content, so check if there's content to parse
                if (response.status === 204) {
                    // 204 No Content - no body to parse
                    return { success: true };
                } else {
                    // Other successful responses might have JSON content
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // Non-JSON successful response
                        return { success: true };
                    }
                }
            } else {
                return response.json().then(error => {
                    throw new Error(error.error || 'Failed to delete contact');
                });
            }
        })
        .then(result => {
            if (result.success) {
                // Close the modal first
                const modal = bootstrap.Modal.getInstance(document.getElementById('editContactModal'));
                modal.hide();
                // Then reload the page
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Network error: ' + error.message);
        });
    }
}

// Individual contact enrichment
async function enrichContact(contactId) {
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    
    try {
        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-clockwise crm-spin me-1"></i>';
        
        const response = await fetch(crmApiUrl(`contacts/${contactId}/enrich`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getApiKey()}`
            },
            body: JSON.stringify({ strategy: 'auto' })
        });
        
        const result = await response.json().catch(() => ({}));
        if (result.outcome === 'skipped') {
            showSuccess(result.message || 'Already enriched recently; no new lookup.');
            button.innerHTML = originalText;
            button.disabled = false;
            return;
        }
        if (!response.ok) {
            showError(result.error || 'Enrichment failed');
            button.innerHTML = originalText;
            button.disabled = false;
            return;
        }
        showSuccess('Contact enriched successfully!');
        button.innerHTML = '<i class="bi bi-check me-1"></i>';
        button.classList.remove('btn-outline-success');
        button.classList.add('btn-outline-secondary');
        setTimeout(() => location.reload(), 1000);
    } catch (error) {
        showError('Network error: ' + error.message);
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Build contacts API URL for bulk-enrich list (excludes enriched unless an enrichment filter is set)
// includePageFilters: when false, omit type/status/source so the queue is not accidentally emptied by list filters (e.g. Customers while all rows are leads).
function buildBulkEnrichContactsApiUrl(limit, opts) {
    opts = opts || {};
    const includePageFilters = opts.includePageFilters !== false;
    const urlParams = new URLSearchParams(window.location.search);
    const type = includePageFilters ? (urlParams.get('type') || '') : '';
    const status = includePageFilters ? (urlParams.get('status') || '') : '';
    const source = includePageFilters ? (urlParams.get('source') || '') : '';
    const tag = includePageFilters ? (urlParams.get('tag') || '') : '';
    const enrichmentStatus = urlParams.get('enrichment_status') || '';
    let apiUrl = crmApiUrl('contacts?limit=' + limit);
    if (type) apiUrl += `&type=${encodeURIComponent(type)}`;
    if (status) apiUrl += `&status=${encodeURIComponent(status)}`;
    if (enrichmentStatus) {
        apiUrl += `&enrichment_status=${encodeURIComponent(enrichmentStatus)}`;
    } else {
        apiUrl += '&needs_enrichment=1';
    }
    if (source) apiUrl += `&source=${encodeURIComponent(source)}`;
    if (tag) apiUrl += `&tag=${encodeURIComponent(tag)}`;
    return apiUrl;
}

// Bulk enrichment
async function bulkEnrichContacts() {
    const modal = new bootstrap.Modal(document.getElementById('bulkEnrichModal'));
    modal.show();
    
    // Load contacts for selection
    await loadContactsForBulkEnrichment();
}

async function loadContactsForBulkEnrichment() {
    const container = document.getElementById('contactSelection');
    const urlParams = new URLSearchParams(window.location.search);
    const hasNarrowingFilters = !!(urlParams.get('type') || urlParams.get('status') || urlParams.get('source'));
    const fetchOpts = {
        headers: { 'Authorization': `Bearer ${getApiKey()}` },
        credentials: 'include'
    };

    const fetchList = async (includePageFilters) => {
        const response = await fetch(buildBulkEnrichContactsApiUrl(100, { includePageFilters }), fetchOpts);
        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = {};
        }
        return { response, data };
    };

    try {
        container.innerHTML = '<div class="text-muted">Loading…</div>';

        let { response, data } = await fetchList(true);
        if (!response.ok) {
            const msg = (data && data.error) ? data.error : ('HTTP ' + response.status);
            container.innerHTML = '<p class="text-danger small mb-0">' + escapeHtmlCrm(msg) + '</p>';
            showError('Failed to load contacts: ' + msg);
            return;
        }

        let list = Array.isArray(data.contacts) ? data.contacts : [];
        let banner = '';
        const totalFirst = data.total != null ? Number(data.total) : NaN;

        bulkListUsedStrictPageFilters = true;
        if (list.length === 0 && totalFirst === 0 && hasNarrowingFilters) {
            const retry = await fetchList(false);
            if (retry.response.ok) {
                const list2 = Array.isArray(retry.data.contacts) ? retry.data.contacts : [];
                if (list2.length > 0) {
                    response = retry.response;
                    data = retry.data;
                    list = list2;
                    bulkListUsedStrictPageFilters = false;
                    banner = '<div class="alert alert-info py-2 small mb-2">No contacts matched your <strong>type / status / source</strong> filters in the enrich queue. Showing the <strong>full queue</strong> instead (enrichment filter still applies).</div>';
                }
            }
        }

        if (list.length === 0) {
            const t = data.total != null ? Number(data.total) : NaN;
            const hint = Number.isFinite(t) ? ` API reports <strong>${t}</strong> matching rows.` : '';
            container.innerHTML = '<div class="text-muted">No contacts in this list.' + hint + ' Try clearing filters on the contacts page or changing enrichment filter (e.g. include failed / not found).</div>';
            return;
        }

        container.innerHTML = banner + list.map(contact => `
                <div class="form-check">
                    <input class="form-check-input contact-checkbox" type="checkbox" value="${contact.id}" 
                           id="contact_${contact.id}" onchange="updateSelectedCount()">
                    <label class="form-check-label" for="contact_${contact.id}">
                        ${contact.first_name} ${contact.last_name} 
                        ${contact.email ? `(${contact.email})` : ''}
                        ${contact.enrichment_status ? `<span class="status-pill status-pill--${contact.enrichment_status === 'enriched' ? 'done' : contact.enrichment_status === 'failed' ? 'blocked' : 'doing'} ms-2"><i class="bi bi-magic"></i>${contact.enrichment_status}</span>` : ''}
                    </label>
                </div>
            `).join('');

        updateSelectedCount();
    } catch (error) {
        container.innerHTML = '<p class="text-danger small mb-0">' + escapeHtmlCrm(error.message) + '</p>';
        showError('Failed to load contacts: ' + error.message);
    }
}

// Show progress modal and hide selection view
function showProgressModal(total) {
    // Set processing flag
    isProcessing = true;
    
    // Hide selection view
    document.getElementById('contactSelectionView').style.display = 'none';
    document.getElementById('selectionFooter').style.display = 'none';
    
    // Show progress view
    document.getElementById('progressView').style.display = 'block';
    document.getElementById('progressFooter').style.display = 'flex';
    
    // Initialize counters
    document.getElementById('totalContacts').textContent = total;
    document.getElementById('remainingCount').textContent = total;
    document.getElementById('successCount').textContent = '0';
    document.getElementById('failCount').textContent = '0';
    document.getElementById('currentContactNum').textContent = '0';
    
    // Clear recent results
    document.getElementById('recentResults').innerHTML = 
        '<div class="text-center text-muted py-3"><small>Results will appear here as contacts are processed...</small></div>';
    
    // Disable modal close button
    document.querySelector('#bulkEnrichModal .btn-close').style.display = 'none';
}

// Update progress display
function updateProgress(current, total, contactId, contactName, contactEmail, successful, failed) {
    const percentage = Math.round((current / total) * 100);
    const remaining = total - current;
    
    // Update progress bar
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = percentage + '%';
    progressBar.setAttribute('aria-valuenow', percentage);
    document.getElementById('progressText').textContent = percentage + '%';
    
    // Update counters
    document.getElementById('currentContactNum').textContent = current;
    document.getElementById('successCount').textContent = successful;
    document.getElementById('failCount').textContent = failed;
    document.getElementById('remainingCount').textContent = remaining;
    
    // Update current contact
    document.getElementById('currentContactName').textContent = contactName || `Contact #${contactId}`;
    document.getElementById('currentContactEmail').textContent = contactEmail || '-';
    
    // Calculate and update estimated time
    const avgTimePerContact = 3; // seconds (adjust based on actual performance)
    const estimatedSeconds = remaining * avgTimePerContact;
    document.getElementById('estimatedTime').textContent = formatTime(estimatedSeconds);
}

// Add result to recent results list
// status: enriched | skipped | failed
function addProgressResult(contactId, contactName, contactEmail, status, error) {
    const resultsDiv = document.getElementById('recentResults');
    
    // Remove placeholder if exists
    const placeholder = resultsDiv.querySelector('.text-center.text-muted');
    if (placeholder) {
        placeholder.remove();
    }
    
    // Create result item
    const resultItem = document.createElement('div');
    resultItem.className = 'd-flex align-items-center justify-content-between p-2 border-bottom';
    resultItem.setAttribute('data-contact-id', contactId);
    
    let icon = '<i class="bi bi-x-circle text-danger me-2"></i>';
    if (status === 'enriched') {
        icon = '<i class="bi bi-check-circle text-success me-2"></i>';
    } else if (status === 'skipped') {
        icon = '<i class="bi bi-skip-forward text-warning me-2"></i>';
    }
    
    const name = contactName || `Contact #${contactId}`;
    const email = contactEmail ? ` (${contactEmail})` : '';
    const errClass = status === 'skipped' ? 'text-warning' : 'text-danger';
    const errorText = error ? `<small class="${errClass} d-block">${error}</small>` : '';
    
    resultItem.innerHTML = `
        ${icon}
        <div class="flex-grow-1 ms-2">
            <div class="fw-bold">${name}${email}</div>
            ${errorText}
        </div>
        <small class="text-muted">${new Date().toLocaleTimeString()}</small>
    `;
    
    // Prepend to results (newest first)
    resultsDiv.insertBefore(resultItem, resultsDiv.firstChild);
    
    // Limit to last 20 results
    while (resultsDiv.children.length > 20) {
        resultsDiv.removeChild(resultsDiv.lastChild);
    }
}

// Show completion summary
function showCompletionSummary(successful, failed, skipped, errors) {
    // Update progress bar to 100%
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = '100%';
    progressBar.setAttribute('aria-valuenow', 100);
    document.getElementById('progressText').textContent = '100%';
    
    // Hide spinner, show completion message
    document.querySelector('#progressView .spinner-border').style.display = 'none';
    document.querySelector('#progressView h5').textContent = 'Enrichment Complete!';
    const parts = [`${successful} enriched`];
    if (skipped > 0) parts.push(`${skipped} skipped`);
    if (failed > 0) parts.push(`${failed} failed`);
    document.querySelector('#progressView .text-muted.mb-0').textContent = 
        `Processed ${successful + failed + skipped} contacts (${parts.join(', ')})`;
    
    // Update estimated time
    document.getElementById('estimatedTime').textContent = 'Complete';
    
    // Update footer
    const footer = document.getElementById('progressFooter');
    footer.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Close
        </button>
        ${failed > 0 ? `
            <button type="button" class="btn btn-warning" onclick="retryFailedContacts()">
                <i class="bi bi-arrow-clockwise me-2"></i>Retry Failed (${failed})
            </button>
        ` : ''}
        <button type="button" class="btn btn-primary" onclick="viewEnrichedContacts()">
            <i class="bi bi-eye me-2"></i>View Results
        </button>
    `;
    
    // Re-enable modal close button
    document.querySelector('#bulkEnrichModal .btn-close').style.display = 'block';
    
    // Clear processing flag to allow modal closing
    isProcessing = false;
    
    // Show success notification
    if (successful > 0) {
        showSuccess(`Successfully enriched ${successful} contact${successful !== 1 ? 's' : ''}`);
    }
    if (failed > 0) {
        showError(`${failed} contact${failed !== 1 ? 's' : ''} failed to enrich. Check recent results for details.`);
    }
}

// Format time helper
function formatTime(seconds) {
    if (seconds < 60) {
        return `${seconds} second${seconds !== 1 ? 's' : ''}`;
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${minutes}m ${secs}s`;
    } else {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
}

// Clear recent results
function clearRecentResults() {
    document.getElementById('recentResults').innerHTML = 
        '<div class="text-center text-muted py-3"><small>Results cleared</small></div>';
}

// Cancel bulk enrichment (placeholder for future async implementation)
function cancelBulkEnrichment() {
    if (confirm('Are you sure you want to cancel bulk enrichment? Contacts already processed will remain enriched.')) {
        // Clear processing flag
        isProcessing = false;
        // For now, just close modal
        // In future async implementation, this would cancel the job
        bootstrap.Modal.getInstance(document.getElementById('bulkEnrichModal')).hide();
        location.reload();
    }
}

// View enriched contacts (filter by enriched status)
function viewEnrichedContacts() {
    bootstrap.Modal.getInstance(document.getElementById('bulkEnrichModal')).hide();
    window.location.href = '/index.php?page=contacts&enrichment_status=enriched';
}

// Retry failed contacts (reload modal with failed contact IDs)
function retryFailedContacts() {
    // Get failed contact IDs from recent results
    const failedContactIds = [];
    document.querySelectorAll('#recentResults .fa-times-circle').forEach(icon => {
        const resultItem = icon.closest('[data-contact-id]');
        if (resultItem) {
            const contactId = resultItem.getAttribute('data-contact-id');
            if (contactId) {
                failedContactIds.push(parseInt(contactId));
            }
        }
    });
    
    // Check the checkboxes for failed contacts
    failedContactIds.forEach(contactId => {
        const checkbox = document.querySelector(`#contactSelection input[value="${contactId}"]`);
        if (checkbox) {
            checkbox.checked = true;
        }
    });
    
    // Close completion view, show selection view again
    document.getElementById('progressView').style.display = 'none';
    document.getElementById('progressFooter').style.display = 'none';
    document.getElementById('contactSelectionView').style.display = 'block';
    document.getElementById('selectionFooter').style.display = 'flex';
    document.querySelector('#bulkEnrichModal .btn-close').style.display = 'block';
    
    // Reset progress view elements
    document.querySelector('#progressView .spinner-border').style.display = 'block';
    document.querySelector('#progressView h5').textContent = 'Enriching Contacts...';
    
    updateSelectedCount();
}

async function startBulkEnrichment() {
    const selectedContacts = Array.from(document.querySelectorAll('#contactSelection input:checked'))
        .map(input => parseInt(input.value));
    
    if (selectedContacts.length === 0) {
        showError('Please select at least one contact');
        return;
    }
    
    const strategy = document.getElementById('bulkEnrichStrategy').value;
    const total = selectedContacts.length;
    
    // Get contact names for display (from the loaded contacts)
    // We'll fetch the contact data to get names and emails
    const contactMap = new Map();
    try {
        const apiUrl = buildBulkEnrichContactsApiUrl(100, { includePageFilters: bulkListUsedStrictPageFilters });
        
        const response = await fetch(apiUrl, {
            headers: { 'Authorization': `Bearer ${getApiKey()}` },
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            const rows = Array.isArray(data.contacts) ? data.contacts : [];
            rows.forEach(contact => {
                if (selectedContacts.includes(contact.id)) {
                    contactMap.set(contact.id, {
                        name: `${contact.first_name || ''} ${contact.last_name || ''}`.trim() || `Contact #${contact.id}`,
                        email: contact.email || null
                    });
                }
            });
        }
    } catch (error) {
        console.error('Failed to load contact details:', error);
    }
    
    // Fallback: if we couldn't load from API, parse from labels
    if (contactMap.size === 0) {
        document.querySelectorAll('#contactSelection .form-check').forEach(check => {
            const checkbox = check.querySelector('input');
            const label = check.querySelector('label');
            if (checkbox && label) {
                const id = parseInt(checkbox.value);
                const text = label.textContent.trim();
                // Extract name and email from label text (format: "Name (email)" or "Name")
                const match = text.match(/^(.+?)(?:\s*\(([^)]+)\))?/);
                contactMap.set(id, {
                    name: match ? match[1].trim() : `Contact #${id}`,
                    email: match && match[2] ? match[2].trim() : null
                });
            }
        });
    }
    
    // Show progress modal
    showProgressModal(total);
    
    // Process contacts one by one
    let successful = 0;
    let failed = 0;
    let skipped = 0;
    const errors = [];
    const betweenMs = 650;
    
    for (let i = 0; i < selectedContacts.length; i++) {
        const contactId = selectedContacts[i];
        const contactInfo = contactMap.get(contactId) || { name: `Contact #${contactId}`, email: null };
        
        // Update progress UI
        updateProgress(
            i + 1, 
            total, 
            contactId, 
            contactInfo.name, 
            contactInfo.email, 
            successful, 
            failed
        );
        
        try {
            const response = await fetch(crmApiUrl(`contacts/${contactId}/enrich`), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${getApiKey()}`
                },
                credentials: 'include',
                body: JSON.stringify({ strategy: strategy })
            });
            const result = await response.json().catch(() => ({}));
            if (result.outcome === 'skipped') {
                skipped++;
                addProgressResult(contactId, contactInfo.name, contactInfo.email, 'skipped', result.message || 'Skipped');
            } else if (!response.ok) {
                failed++;
                const errorMessage = result.error || result.message || ('HTTP ' + response.status);
                errors.push({ 
                    contact_id: contactId, 
                    contact_name: contactInfo.name,
                    error: errorMessage 
                });
                addProgressResult(contactId, contactInfo.name, contactInfo.email, 'failed', errorMessage);
            } else if (result.outcome === 'enriched' || (result.success && result.outcome !== 'skipped')) {
                successful++;
                addProgressResult(contactId, contactInfo.name, contactInfo.email, 'enriched', null);
            } else {
                failed++;
                const errorMessage = result.error || result.message || 'Enrichment did not complete';
                errors.push({ 
                    contact_id: contactId, 
                    contact_name: contactInfo.name,
                    error: errorMessage 
                });
                addProgressResult(contactId, contactInfo.name, contactInfo.email, 'failed', errorMessage);
            }
        } catch (error) {
            failed++;
            const errorMessage = error.message || 'Network error';
            errors.push({ 
                contact_id: contactId, 
                contact_name: contactInfo.name,
                error: errorMessage 
            });
            addProgressResult(contactId, contactInfo.name, contactInfo.email, 'failed', errorMessage);
        }
        
        await new Promise(resolve => setTimeout(resolve, betweenMs));
    }
    
    // Show completion summary
    showCompletionSummary(successful, failed, skipped, errors);
    
    // Auto-refresh page after 3 seconds to show updated enrichment statuses
    setTimeout(() => {
        // Don't auto-refresh, let user decide via "View Results" button
    }, 3000);
}

// Prevent modal from closing during processing
let isProcessing = false;
// Matches last bulk list load (strict URL filters vs relaxed after auto-retry)
let bulkListUsedStrictPageFilters = true;

// Add event listener to prevent modal closing during processing
document.addEventListener('DOMContentLoaded', function() {
    const bulkEnrichModal = document.getElementById('bulkEnrichModal');
    if (bulkEnrichModal) {
        // Prevent closing via backdrop click or ESC key during processing
        bulkEnrichModal.addEventListener('hide.bs.modal', function(event) {
            if (isProcessing) {
                event.preventDefault();
                return false;
            }
        });
        
        // Reset modal when it's closed
        bulkEnrichModal.addEventListener('hidden.bs.modal', function() {
            // Reset to selection view
            document.getElementById('contactSelectionView').style.display = 'block';
            document.getElementById('selectionFooter').style.display = 'flex';
            document.getElementById('progressView').style.display = 'none';
            document.getElementById('progressFooter').style.display = 'none';
            document.querySelector('#bulkEnrichModal .btn-close').style.display = 'block';
            
            // Reset progress view elements
            const spinner = document.querySelector('#progressView .spinner-border');
            if (spinner) spinner.style.display = 'block';
            const h5 = document.querySelector('#progressView h5');
            if (h5) h5.textContent = 'Enriching Contacts...';
            
            isProcessing = false;
        });
    }
});

// Select all/deselect all functions
function selectAllContacts() {
    const checkboxes = document.querySelectorAll('#contactSelection .contact-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelectedCount();
}

function deselectAllContacts() {
    const checkboxes = document.querySelectorAll('#contactSelection .contact-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const selectedCount = document.querySelectorAll('#contactSelection .contact-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selectedCount;
}

async function bulkDeleteByTag(tag, count) {
    if (!tag) {
        showError('No tag selected');
        return;
    }
    const label = tag;
    const n = Number(count) || 0;
    if (n < 1) {
        showError('No contacts with this tag');
        return;
    }
    const ok = confirm(
        `Delete ALL ${n} contact(s) tagged “${label}”?\n\nThis cannot be undone. Use for bad Outscraper / scrape batches only.`
    );
    if (!ok) {
        return;
    }
    const ok2 = confirm(`Final confirm: permanently delete ${n} contacts with tag “${label}”?`);
    if (!ok2) {
        return;
    }
    try {
        const response = await fetch(crmApiUrl('contacts/bulk-delete'), {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getApiKey()}`,
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ tag, confirm: true, limit: 500 }),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            showError(result.error || 'Bulk delete failed');
            return;
        }
        showSuccess(`Deleted ${result.deleted_count || 0} contact(s) with tag “${label}”.`);
        setTimeout(() => {
            const params = new URLSearchParams(window.location.search);
            params.delete('tag');
            params.set('page', 'contacts');
            window.location.href = '/index.php?' + params.toString();
        }, 900);
    } catch (error) {
        showError('Network error: ' + error.message);
    }
}

// Change per page function
function changePerPage(value) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', 'contacts'); // Ensure page parameter is set
    urlParams.set('per_page', value);
    urlParams.delete('page_num'); // Reset to page 1 when changing per_page
    window.location.href = '/index.php?' + urlParams.toString();
}

// Export CSV function
async function exportContactsCSV() {
    try {
        // Get current filter parameters from URL
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type') || '';
        const status = urlParams.get('status') || '';
        const enrichmentStatus = urlParams.get('enrichment_status') || '';
        const source = urlParams.get('source') || '';
        const tag = urlParams.get('tag') || '';
        
        // Build API URL with current filters
        let apiUrl = crmApiUrl('contacts/export?format=csv');
        if (type) apiUrl += `&type=${encodeURIComponent(type)}`;
        if (status) apiUrl += `&status=${encodeURIComponent(status)}`;
        if (enrichmentStatus) apiUrl += `&enrichment_status=${encodeURIComponent(enrichmentStatus)}`;
        if (source) apiUrl += `&source=${encodeURIComponent(source)}`;
        if (tag) apiUrl += `&tag=${encodeURIComponent(tag)}`;
        
        // Show loading state
        const exportBtn = document.querySelector('button[onclick="exportContactsCSV()"]');
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="bi bi-arrow-clockwise crm-spin me-2"></i>Exporting...';
        exportBtn.disabled = true;
        
        const response = await fetch(apiUrl, {
            headers: { 'Authorization': `Bearer ${getApiKey()}` }
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `contacts_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showSuccess('Contacts exported successfully!');
        } else {
            const error = await response.json();
            showError(error.error || 'Export failed');
        }
    } catch (error) {
        showError('Network error: ' + error.message);
    } finally {
        // Restore button state
        const exportBtn = document.querySelector('button[onclick="exportContactsCSV()"]');
        exportBtn.innerHTML = '<i class="bi bi-download me-2"></i>Export CSV';
        exportBtn.disabled = false;
    }
}

// Utility functions
function escapeHtmlCrm(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function getApiKey() {
    // Get API key from localStorage or session
    return localStorage.getItem('api_key') || '';
}

function showSuccess(message) {
    // Use existing notification system or create toast
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}

function showError(message) {
    // Use existing notification system or create toast
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}
</script>

<!-- Bulk Enrichment Modal -->
<div class="modal fade" id="bulkEnrichModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Enrich Contacts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Contact Selection View -->
                <div id="contactSelectionView">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Select contacts to enrich:</label>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllContacts()">
                                    <i class="bi bi-check-square me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllContacts()">
                                    <i class="bi bi-square me-1"></i>Deselect All
                                </button>
                            </div>
                        </div>
                        <small class="text-muted d-block mb-2">
                            Lists up to 100 contacts that still need a run (excludes enriched, failed, and not found). Pending and never-run are included.
                            Type, status, and source filters apply; if they match nobody in the queue, the list reloads without those filters so you still see work.
                            To retry failures or not-found rows, set the enrichment filter first, then open Bulk Enrich.
                        </small>
                        <div id="contactSelection">
                            <!-- Dynamic contact list with checkboxes -->
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <span id="selectedCount">0</span> contacts selected
                            </small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Enrichment Strategy:</label>
                        <select class="form-select" id="bulkEnrichStrategy">
                            <option value="auto">Auto (Best Available)</option>
                            <option value="email">Email Only</option>
                            <option value="linkedin">LinkedIn Only</option>
                            <option value="name_company">Name + Company</option>
                        </select>
                    </div>
                </div>
                
                <!-- Progress View (initially hidden) -->
                <div id="progressView" style="display: none;">
                    <div class="text-center mb-4">
                        <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <h5>Enriching Contacts...</h5>
                        <p class="text-muted mb-0">
                            Processing contact <span id="currentContactNum">0</span> of <span id="totalContacts">0</span>
                        </p>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="progress mb-3" style="height: 30px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" 
                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <span id="progressText" class="fw-bold">0%</span>
                        </div>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="h4 text-success mb-0" id="successCount">0</div>
                            <small class="text-muted">Successful</small>
                        </div>
                        <div class="col-4">
                            <div class="h4 text-danger mb-0" id="failCount">0</div>
                            <small class="text-muted">Failed</small>
                        </div>
                        <div class="col-4">
                            <div class="h4 text-info mb-0" id="remainingCount">0</div>
                            <small class="text-muted">Remaining</small>
                        </div>
                    </div>
                    
                    <!-- Estimated Time -->
                    <div class="text-center mb-3">
                        <small class="text-muted">
                            Estimated time remaining: <span id="estimatedTime">-</span>
                        </small>
                    </div>
                    
                    <!-- Current Contact Card -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <small class="text-muted d-block mb-1">Currently Processing:</small>
                            <div id="currentContactName" class="fw-bold">-</div>
                            <div id="currentContactEmail" class="text-muted small">-</div>
                        </div>
                    </div>
                    
                    <!-- Recent Results (Scrollable) -->
                    <div class="card" style="max-height: 250px; overflow-y: auto;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <small class="text-muted fw-bold">Recent Results</small>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearRecentResults()">
                                <i class="bi bi-x-lg"></i> Clear
                            </button>
                        </div>
                        <div class="card-body p-2" id="recentResults">
                            <div class="text-center text-muted py-3">
                                <small>Results will appear here as contacts are processed...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="selectionFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="startBulkEnrichment()">
                    <i class="bi bi-magic me-2"></i>Start Enrichment
                </button>
            </div>
            <div class="modal-footer" id="progressFooter" style="display: none;">
                <button type="button" class="btn btn-secondary" onclick="cancelBulkEnrichment()" id="cancelBtn">
                    <i class="bi bi-x-lg me-2"></i>Cancel
                </button>
                <div class="ms-auto">
                    <small class="text-muted">
                        Processing... Please wait
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter();
?> 
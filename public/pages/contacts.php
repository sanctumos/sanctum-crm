<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Contacts Page
 * Sanctum CRM - Contact Management
 */

// Get database instance
$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? 'list';
$contact_id = $_GET['id'] ?? null;

// Get filter parameters
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Handle view mode with session persistence
if (isset($_GET['view'])) {
    $_SESSION['contacts_view_mode'] = $_GET['view'];
}
$view_mode = $_SESSION['contacts_view_mode'] ?? 'cards'; // Default to cards view

// Session-based per_page persistence
if (isset($_GET['per_page'])) {
    $_SESSION['contacts_per_page'] = (int)$_GET['per_page'];
}
$per_page = $_SESSION['contacts_per_page'] ?? 100; // Default to 100

// Pagination calculation
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $per_page;

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

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM contacts WHERE $where";
$count_params = $params; // Copy params for count query
$total_result = $db->fetchOne($count_sql, $count_params);
$total_contacts = $total_result['total'] ?? 0;
$total_pages = ceil($total_contacts / $per_page);

// Get contacts with pagination
$sql = "SELECT * FROM contacts WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$query_params = $params; // Copy params for main query
$query_params[] = $per_page;
$query_params[] = $offset;
$contacts = $db->fetchAll($sql, $query_params);

// Render the page using the template system
renderHeader('Contacts');
?>

<style>
    .card {
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    .table {
        border-radius: 10px;
        overflow: hidden;
    }
    .btn-action {
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 0.875rem;
    }
    .contact-card {
        transition: transform 0.3s ease;
    }
    .contact-card:hover {
        transform: translateY(-2px);
    }
    .view-toggle .btn {
        border-radius: 6px;
    }
    .view-toggle .btn.active {
        background-color: #0d6efd;
        border-color: #0d6efd;
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

<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <form class="row g-3" method="GET" action="/index.php">
                    <input type="hidden" name="page" value="contacts">
                    <div class="col-md-4">
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="lead" <?php echo $type_filter === 'lead' ? 'selected' : ''; ?>>Leads</option>
                            <option value="customer" <?php echo $type_filter === 'customer' ? 'selected' : ''; ?>>Customers</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="qualified" <?php echo $status_filter === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                        <a href="/index.php?page=contacts" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex align-items-center justify-content-end gap-2">
                    <div class="btn-group me-3 view-toggle" role="group" aria-label="View mode">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'cards'])); ?>" 
                           class="btn btn-outline-secondary <?php echo $view_mode === 'cards' ? 'active' : ''; ?>">
                            <i class="fas fa-th-large"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" 
                           class="btn btn-outline-secondary <?php echo $view_mode === 'list' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                    <a href="/?page=import_contacts" class="btn btn-success">
                        <i class="fas fa-file-import me-2"></i>Import CSV
                    </a>
                    <button class="btn btn-info" onclick="exportContactsCSV()">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </button>
                    <button class="btn btn-warning" onclick="bulkEnrichContacts()">
                        <i class="fas fa-magic me-2"></i>Bulk Enrich
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                        <i class="fas fa-plus me-2"></i>Add Contact
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contacts Display -->
<?php if ($view_mode === 'cards'): ?>
<!-- Cards View -->
<div class="row">
    <?php foreach ($contacts as $contact): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card contact-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="card-title mb-1">
                            <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                        </h5>
                        <p class="text-muted mb-0"><?php echo $contact['email'] ? htmlspecialchars($contact['email']) : 'No email'; ?></p>
                    </div>
                    <a href="/index.php?page=view_contact&id=<?php echo $contact['id']; ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>View
                    </a>
                    <button class="btn btn-sm btn-outline-success" onclick="enrichContact(<?php echo $contact['id']; ?>)">
                        <i class="fas fa-magic me-1"></i>Enrich
                    </button>
                </div>
                
                <div class="mb-3">
                    <?php if ($contact['phone']): ?>
                    <p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($contact['phone']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($contact['company']): ?>
                    <p class="mb-1"><i class="fas fa-building me-2 text-muted"></i><?php echo htmlspecialchars($contact['company']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact['position'])): ?>
                    <p class="mb-1"><i class="fas fa-briefcase me-2 text-muted"></i><?php echo htmlspecialchars($contact['position']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-<?php echo $contact['contact_type'] === 'lead' ? 'warning' : 'success'; ?> me-2">
                            <?php echo ucfirst($contact['contact_type']); ?>
                        </span>
                        <span class="badge bg-secondary me-2">
                            <?php echo ucfirst($contact['contact_status']); ?>
                        </span>
                        <?php if ($contact['enrichment_status']): ?>
                            <span class="badge bg-<?php echo $contact['enrichment_status'] === 'enriched' ? 'success' : 'warning'; ?>">
                                <i class="fas fa-magic me-1"></i>
                                <?php echo ucfirst($contact['enrichment_status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">
                        <?php echo date('M j, Y', strtotime($contact['created_at'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Company</th>
                        <th>Type</th>
                        <th>Status</th>
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
                        <td><?php echo $contact['company'] ? htmlspecialchars($contact['company']) : '-'; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $contact['contact_type'] === 'lead' ? 'warning' : 'success'; ?> me-1">
                                <?php echo ucfirst($contact['contact_type']); ?>
                            </span>
                            <?php if ($contact['enrichment_status']): ?>
                                <span class="badge bg-<?php echo $contact['enrichment_status'] === 'enriched' ? 'success' : 'warning'; ?>">
                                    <i class="fas fa-magic me-1"></i>
                                    <?php echo ucfirst($contact['enrichment_status']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo ucfirst($contact['contact_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="/index.php?page=view_contact&id=<?php echo $contact['id']; ?>"
                                   class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success" onclick="enrichContact(<?php echo $contact['id']; ?>)" title="Enrich">
                                    <i class="fas fa-magic"></i>
                                </button>
                                <a href="/index.php?page=edit_contact&id=<?php echo $contact['id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteContact(<?php echo $contact['id']; ?>)" 
                                        class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
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

<!-- Pagination Controls -->
<?php if ($total_pages > 1 || !empty($contacts)): ?>
<div class="card mt-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0 text-muted">
                    Showing <?php echo count($contacts); ?> of <?php echo $total_contacts; ?> contacts
                </p>
            </div>
            <div class="col-md-6 text-end">
                <div class="d-flex align-items-center justify-content-end gap-3">
                    <label for="perPageSelect" class="mb-0 text-muted">Show per page:</label>
                    <select id="perPageSelect" class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="500" <?php echo $per_page == 500 ? 'selected' : ''; ?>>500</option>
                    </select>
                </div>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Contacts pagination" class="mt-3">
            <?php
            // Build pagination parameters
            $pagination_params = $_GET;
            $pagination_params['page'] = 'contacts';
            
            // Calculate page range to show (max 10 pages)
            $start_page = max(1, $page - 4);
            $end_page = min($total_pages, $page + 5);
            if ($end_page - $start_page < 9) {
                if ($start_page == 1) {
                    $end_page = min($total_pages, $start_page + 9);
                } else {
                    $start_page = max(1, $end_page - 9);
                }
            }
            ?>
            <ul class="pagination justify-content-center mb-0">
                <!-- Previous button -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => max(1, $page - 1)])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>
                
                <!-- First page -->
                <?php if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => 1])); ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <!-- Last page -->
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Next button -->
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="/index.php?<?php echo http_build_query(array_merge($pagination_params, ['page_num' => min($total_pages, $page + 1)])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($contacts)): ?>
<div class="text-center py-5">
    <i class="fas fa-users fa-3x text-muted mb-3"></i>
    <h5>No Contacts Found</h5>
    <p class="text-muted">Get started by adding your first contact.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
        <i class="fas fa-plus me-2"></i>Add Contact
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
                        <i class="fas fa-trash me-2"></i>Delete Contact
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Pagination function
function changePerPage(value) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', 'contacts'); // Ensure page parameter is set
    urlParams.set('per_page', value);
    urlParams.delete('page_num'); // Reset to page 1 when changing per_page
    window.location.href = '/index.php?' + urlParams.toString();
}

// Handle form submissions
document.getElementById('addContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    fetch('/api/v1/contacts', {
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
    
    fetch(`/api/v1/contacts/${contactId}`, {
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
    fetch(`/api/v1/contacts/${contactId}`, {
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
        fetch(`/api/v1/contacts/${contactId}`, {
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
                    return response.json();
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
        fetch(`/api/v1/contacts/${contactId}`, {
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
                    return response.json();
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
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enriching...';

        const response = await fetch(`/api/v1/contacts/${contactId}/enrich`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getApiKey()}`
            },
            body: JSON.stringify({ strategy: 'auto' })
        });

        if (response.ok) {
            const result = await response.json();
            showSuccess('Contact enriched successfully!');

            // Update button state
            button.innerHTML = '<i class="fas fa-check me-2"></i>Enriched';
            button.classList.remove('btn-success', 'btn-outline-success');
            button.classList.add('btn-secondary');

            // Refresh page to show updated data
            setTimeout(() => location.reload(), 1000);
        } else {
            const error = await response.json();
            showError(error.error || 'Enrichment failed');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    } catch (error) {
        showError('Network error: ' + error.message);
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Bulk enrichment functions
async function bulkEnrichContacts() {
    const modal = new bootstrap.Modal(document.getElementById('bulkEnrichModal'));
    modal.show();

    // Load contacts for selection
    await loadContactsForBulkEnrichment();
}

async function loadContactsForBulkEnrichment() {
    try {
        const response = await fetch('/api/v1/contacts', {
            headers: { 'Authorization': `Bearer ${getApiKey()}` }
        });

        if (response.ok) {
            const data = await response.json();
            const container = document.getElementById('contactSelection');

            container.innerHTML = data.contacts.map(contact => `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${contact.id}"
                           id="contact_${contact.id}">
                    <label class="form-check-label" for="contact_${contact.id}">
                        ${contact.first_name} ${contact.last_name}
                        ${contact.email ? `(${contact.email})` : ''}
                        ${contact.enrichment_status ? `<span class="badge bg-${contact.enrichment_status === 'enriched' ? 'success' : 'warning'} ms-2">${contact.enrichment_status}</span>` : ''}
                    </label>
                </div>
            `).join('');
        }
    } catch (error) {
        showError('Failed to load contacts: ' + error.message);
    }
}

async function startBulkEnrichment() {
    const selectedContacts = Array.from(document.querySelectorAll('#contactSelection input:checked'))
        .map(input => parseInt(input.value));

    if (selectedContacts.length === 0) {
        showError('Please select at least one contact');
        return;
    }

    const strategy = document.getElementById('bulkEnrichStrategy').value;

    try {
        const response = await fetch('/api/v1/contacts/bulk-enrich', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getApiKey()}`
            },
            body: JSON.stringify({
                contact_ids: selectedContacts,
                strategy: strategy
            })
        });

        if (response.ok) {
            const result = await response.json();
            showSuccess(`Bulk enrichment completed: ${result.successful} successful, ${result.failed} failed`);

            // Close modal and refresh page
            bootstrap.Modal.getInstance(document.getElementById('bulkEnrichModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            const error = await response.json();
            showError(error.error || 'Bulk enrichment failed');
        }
    } catch (error) {
        showError('Network error: ' + error.message);
    }
}

// Utility functions
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

<?php
renderFooter();
?> 
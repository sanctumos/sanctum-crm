<?php
/**
 * Contacts Page
 * Best Jobs in TA - Contact Management
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

// Get contacts
$sql = "SELECT * FROM contacts WHERE $where ORDER BY created_at DESC";
$contacts = $db->fetchAll($sql, $params);

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
                <form class="row g-3">
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
                    <a href="/pages/import_contacts.php" class="btn btn-success">
                        <i class="fas fa-file-import me-2"></i>Import CSV
                    </a>
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
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($contact['email']); ?></p>
                    </div>
                    <a href="/index.php?page=view_contact&id=<?php echo $contact['id']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>View
                    </a>
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
                        <span class="badge bg-secondary">
                            <?php echo ucfirst($contact['contact_status']); ?>
                        </span>
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
                        <td><?php echo htmlspecialchars($contact['email']); ?></td>
                        <td><?php echo $contact['phone'] ? htmlspecialchars($contact['phone']) : '-'; ?></td>
                        <td><?php echo $contact['company'] ? htmlspecialchars($contact['company']) : '-'; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $contact['contact_type'] === 'lead' ? 'warning' : 'success'; ?>">
                                <?php echo ucfirst($contact['contact_type']); ?>
                            </span>
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
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
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
                        <label for="edit_email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
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
            document.getElementById('edit_email').value = contact.email;
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
                return response.json();
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
                return response.json();
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
</script>

<?php
renderFooter();
?> 
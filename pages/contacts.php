<?php
/**
 * Contacts Page
 * FreeOpsDAO CRM - Contact Management
 */

// Get database instance
$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? 'list';
$contact_id = $_GET['id'] ?? null;

// Get filter parameters
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

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

// Get current user
$user = $auth->getUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white mb-0">
                            <i class="fas fa-users"></i> <?php echo APP_NAME; ?>
                        </h4>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="/index.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="/index.php?page=contacts">
                            <i class="fas fa-address-book me-2"></i> Contacts
                        </a>
                        <a class="nav-link" href="/index.php?page=deals">
                            <i class="fas fa-handshake me-2"></i> Deals
                        </a>
                        <?php if ($auth->isAdmin()): ?>
                        <a class="nav-link" href="/index.php?page=users">
                            <i class="fas fa-users-cog me-2"></i> Users
                        </a>
                        <?php endif; ?>
                        <a class="nav-link" href="/index.php?page=reports">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 px-0">
                <div class="main-content p-4">
                    <!-- Top Navigation -->
                    <nav class="navbar navbar-expand-lg navbar-light bg-white rounded-3 mb-4 shadow-sm">
                        <div class="container-fluid">
                            <span class="navbar-brand mb-0 h1">Contacts</span>
                            <div class="navbar-nav ms-auto">
                                <div class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-user-circle"></i> 
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </nav>
                    
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
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                        <i class="fas fa-plus me-2"></i>Add Contact
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contacts List -->
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
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editContact(<?php echo $contact['id']; ?>)">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                                <?php if ($contact['contact_type'] === 'lead'): ?>
                                                <li><a class="dropdown-item" href="#" onclick="convertContact(<?php echo $contact['id']; ?>)">
                                                    <i class="fas fa-exchange-alt me-2"></i>Convert to Customer
                                                </a></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteContact(<?php echo $contact['id']; ?>)">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo $contact['contact_type'] === 'lead' ? 'warning' : 'success'; ?> me-2">
                                            <?php echo ucfirst($contact['contact_type']); ?>
                                        </span>
                                        <span class="badge bg-secondary"><?php echo ucfirst($contact['contact_status']); ?></span>
                                    </div>
                                    
                                    <?php if ($contact['company']): ?>
                                    <p class="card-text mb-2">
                                        <i class="fas fa-building me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($contact['company']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($contact['phone']): ?>
                                    <p class="card-text mb-2">
                                        <i class="fas fa-phone me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($contact['phone']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($contact['evm_address']): ?>
                                    <p class="card-text mb-2">
                                        <i class="fab fa-ethereum me-2 text-muted"></i>
                                        <small class="text-muted"><?php echo substr($contact['evm_address'], 0, 10) . '...'; ?></small>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($contact['twitter_handle']): ?>
                                    <p class="card-text mb-2">
                                        <i class="fab fa-twitter me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($contact['twitter_handle']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="text-muted small">
                                        <i class="fas fa-calendar me-1"></i>
                                        Added <?php echo date('M j, Y', strtotime($contact['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($contacts)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No contacts found</h4>
                                <p class="text-muted">Get started by adding your first contact.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                    <i class="fas fa-plus me-2"></i>Add Contact
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Contact Modal -->
    <div class="modal fade" id="addContactModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addContactForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company</label>
                                <input type="text" class="form-control" name="company">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Type</label>
                                <select class="form-select" name="contact_type">
                                    <option value="lead">Lead</option>
                                    <option value="customer">Customer</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">EVM Address</label>
                                <input type="text" class="form-control" name="evm_address" placeholder="0x...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Twitter Handle</label>
                                <input type="text" class="form-control" name="twitter_handle" placeholder="@username">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">LinkedIn Profile</label>
                                <input type="url" class="form-control" name="linkedin_profile">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telegram Username</label>
                                <input type="text" class="form-control" name="telegram_username">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add contact form submission
        document.getElementById('addContactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('/api/v1/contacts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.id) {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the contact.');
            });
        });
        
        function editContact(id) {
            // TODO: Implement edit functionality
            alert('Edit functionality coming soon!');
        }
        
        function convertContact(id) {
            if (confirm('Convert this lead to a customer?')) {
                fetch(`/api/v1/contacts/${id}/convert`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.id) {
                        location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while converting the contact.');
                });
            }
        }
        
        function deleteContact(id) {
            if (confirm('Are you sure you want to delete this contact?')) {
                fetch(`/api/v1/contacts/${id}`, {
                    method: 'DELETE'
                })
                .then(response => {
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Error deleting contact');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the contact.');
                });
            }
        }
    </script>
</body>
</html> 
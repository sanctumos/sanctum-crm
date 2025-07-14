<?php
/**
 * User Management Page
 * FreeOpsDAO CRM
 */

define('CRM_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .users-card { max-width: 1100px; margin: 40px auto; }
        .role-badge { text-transform: uppercase; font-size: 0.85em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="users-card card shadow-sm mt-5">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-users"></i> User Management</h4>
                <button class="btn btn-success" id="addUserBtn"><i class="fas fa-user-plus"></i> Add User</button>
            </div>
            <div class="card-body">
                <div id="usersAlert" class="alert d-none" role="alert"></div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>API Key</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="userForm">
            <div class="modal-header">
              <h5 class="modal-title" id="userModalLabel">Add/Edit User</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="user_id" name="user_id">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label for="is_active" class="form-label">Status</label>
                    <select class="form-select" id="is_active" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let users = [];
    let currentUserId = null;
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        loadUsers();
        setupEventListeners();
    });
    
    function setupEventListeners() {
        document.getElementById('addUserBtn').addEventListener('click', function() {
            currentUserId = null;
            document.getElementById('userModalLabel').textContent = 'Add User';
            document.getElementById('userForm').reset();
            new bootstrap.Modal(document.getElementById('userModal')).show();
        });
        
        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveUser();
        });
    }
    
    async function loadUsers() {
        try {
            const response = await fetch('/api/v1/users');
            const result = await response.json();
            
            if (response.ok) {
                users = result.users || [];
                renderUsersTable();
            } else {
                showAlert('Failed to load users: ' + (result.error || 'Unknown error'), 'danger');
            }
        } catch (err) {
            showAlert('Network error while loading users', 'danger');
        }
    }
    
    function renderUsersTable() {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = '';
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${escapeHtml(user.username)}</td>
                <td>${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')}</td>
                <td>${escapeHtml(user.email)}</td>
                <td><span class="badge bg-${user.role === 'admin' ? 'danger' : 'secondary'} role-badge">${user.role}</span></td>
                <td><span class="badge bg-${user.is_active ? 'success' : 'warning'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                <td>
                    <code class="small">${user.api_key ? user.api_key.substring(0, 8) + '...' : 'None'}</code>
                    <button class="btn btn-sm btn-outline-primary ms-1" onclick="regenerateApiKey(${user.id})" title="Regenerate API Key">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(${user.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-${user.is_active ? 'warning' : 'success'}" onclick="toggleUserStatus(${user.id})" title="${user.is_active ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${user.is_active ? 'pause' : 'play'}"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
    
    async function saveUser() {
        const formData = new FormData(document.getElementById('userForm'));
        const data = Object.fromEntries(formData.entries());
        
        // Remove empty password field
        if (!data.password) {
            delete data.password;
        }
        
        try {
            const url = currentUserId ? `/api/v1/users/${currentUserId}` : '/api/v1/users';
            const method = currentUserId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showAlert(`User ${currentUserId ? 'updated' : 'created'} successfully!`, 'success');
                bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                loadUsers();
            } else {
                showAlert('Failed to save user: ' + (result.error || 'Unknown error'), 'danger');
            }
        } catch (err) {
            showAlert('Network error while saving user', 'danger');
        }
    }
    
    function editUser(userId) {
        const user = users.find(u => u.id == userId);
        if (!user) return;
        
        currentUserId = userId;
        document.getElementById('userModalLabel').textContent = 'Edit User';
        
        // Populate form
        document.getElementById('user_id').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('first_name').value = user.first_name || '';
        document.getElementById('last_name').value = user.last_name || '';
        document.getElementById('email').value = user.email;
        document.getElementById('role').value = user.role;
        document.getElementById('is_active').value = user.is_active ? '1' : '0';
        document.getElementById('password').value = '';
        
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }
    
    async function toggleUserStatus(userId) {
        if (!confirm('Are you sure you want to change this user\'s status?')) return;
        
        const user = users.find(u => u.id == userId);
        if (!user) return;
        
        try {
            const response = await fetch(`/api/v1/users/${userId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_active: !user.is_active })
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showAlert(`User ${user.is_active ? 'deactivated' : 'activated'} successfully!`, 'success');
                loadUsers();
            } else {
                showAlert('Failed to update user status: ' + (result.error || 'Unknown error'), 'danger');
            }
        } catch (err) {
            showAlert('Network error while updating user status', 'danger');
        }
    }
    
    async function regenerateApiKey(userId) {
        if (!confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) return;
        
        try {
            const response = await fetch(`/api/v1/users/${userId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ regenerate_api_key: true })
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showAlert('API key regenerated successfully!', 'success');
                loadUsers();
            } else {
                showAlert('Failed to regenerate API key: ' + (result.error || 'Unknown error'), 'danger');
            }
        } catch (err) {
            showAlert('Network error while regenerating API key', 'danger');
        }
    }
    
    async function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
        
        try {
            const response = await fetch(`/api/v1/users/${userId}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                showAlert('User deleted successfully!', 'success');
                loadUsers();
            } else {
                const result = await response.json();
                showAlert('Failed to delete user: ' + (result.error || 'Unknown error'), 'danger');
            }
        } catch (err) {
            showAlert('Network error while deleting user', 'danger');
        }
    }
    
    function showAlert(message, type) {
        const alertBox = document.getElementById('usersAlert');
        alertBox.textContent = message;
        alertBox.className = `alert alert-${type}`;
        alertBox.classList.remove('d-none');
        
        setTimeout(() => {
            alertBox.classList.add('d-none');
        }, 5000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>
</html> 
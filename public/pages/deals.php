<?php
/**
 * Deals Pipeline Page
 * FreeOpsDAO CRM
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Deals');
?>

<style>
    .deals-card { max-width: 1200px; margin: 0 auto; }
    .stage-badge { text-transform: uppercase; font-size: 0.85em; }
    .deal-card { transition: transform 0.2s; }
    .deal-card:hover { transform: translateY(-2px); }
    .amount { font-weight: bold; color: #28a745; }
    .probability { font-size: 0.9em; }
    .filter-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    .view-toggle .btn { margin-right: 10px; }
    
    /* Select2 custom styling */
    .select2-container {
        width: 100% !important;
        display: block !important;
    }
    .select2-selection {
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        background-color: #fff !important;
    }
    .select2-selection--single {
        line-height: 36px !important;
        padding: 0.375rem 0.75rem !important;
    }
    .select2-selection__rendered {
        line-height: 36px !important;
        padding-left: 0 !important;
    }
    .select2-selection__arrow {
        height: 36px !important;
    }
    .select2-dropdown {
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        z-index: 9999 !important;
    }
    .select2-container--open {
        z-index: 9999 !important;
    }
    /* Force Select2 to be visible in modal */
    .modal .select2-container {
        z-index: 9999 !important;
    }
    .modal .select2-dropdown {
        z-index: 9999 !important;
    }
</style>

<div class="deals-card">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-handshake"></i> Deals Pipeline</h2>
        <button class="btn btn-success" id="addDealBtn"><i class="fas fa-plus"></i> Add Deal</button>
    </div>

    <!-- Filters -->
    <div class="filter-section shadow-sm">
        <div class="row">
            <div class="col-md-3">
                <label for="stageFilter" class="form-label">Stage</label>
                <select class="form-select" id="stageFilter">
                    <option value="">All Stages</option>
                    <option value="prospecting">Prospecting</option>
                    <option value="qualification">Qualification</option>
                    <option value="proposal">Proposal</option>
                    <option value="negotiation">Negotiation</option>
                    <option value="closed_won">Closed Won</option>
                    <option value="closed_lost">Closed Lost</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="assignedFilter" class="form-label">Assigned To</label>
                <select class="form-select" id="assignedFilter">
                    <option value="">All Users</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="searchFilter" class="form-label">Search</label>
                <input type="text" class="form-control" id="searchFilter" placeholder="Search deals...">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button class="btn btn-outline-secondary" onclick="clearFilters()">Clear Filters</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Toggle -->
    <div class="view-toggle mb-3">
        <button class="btn btn-outline-primary active" onclick="setView('table')" id="tableViewBtn">
            <i class="fas fa-table"></i> Table View
        </button>
        <button class="btn btn-outline-primary" onclick="setView('kanban')" id="kanbanViewBtn">
            <i class="fas fa-columns"></i> Kanban View
        </button>
    </div>

    <!-- Alerts -->
    <div id="dealsAlert" class="alert d-none" role="alert"></div>

    <!-- Table View -->
    <div id="tableView" class="view-content">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="dealsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Contact</th>
                                <th>Amount</th>
                                <th>Stage</th>
                                <th>Probability</th>
                                <th>Assigned To</th>
                                <th>Expected Close</th>
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

    <!-- Kanban View -->
    <div id="kanbanView" class="view-content d-none">
        <div class="row" id="kanbanBoard">
            <!-- Populated by JS -->
        </div>
    </div>
</div>

<!-- Deal Modal -->
<div class="modal fade" id="dealModal" tabindex="-1" aria-labelledby="dealModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="dealForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="dealModalLabel">Add/Edit Deal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="deal_id" name="deal_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">Deal Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_id" class="form-label">Contact *</label>
                                <select class="form-select" id="contact_id" name="contact_id" required>
                                    <option value="">Select Contact</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stage" class="form-label">Stage</label>
                                <select class="form-select" id="stage" name="stage" required>
                                    <option value="prospecting">Prospecting</option>
                                    <option value="qualification">Qualification</option>
                                    <option value="proposal">Proposal</option>
                                    <option value="negotiation">Negotiation</option>
                                    <option value="closed_won">Closed Won</option>
                                    <option value="closed_lost">Closed Lost</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="probability" class="form-label">Probability (%)</label>
                                <input type="number" class="form-control" id="probability" name="probability" min="0" max="100" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assigned_to" class="form-label">Assigned To</label>
                                <select class="form-select" id="assigned_to" name="assigned_to">
                                    <option value="">Unassigned</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="expected_close_date" class="form-label">Expected Close Date</label>
                        <input type="date" class="form-control" id="expected_close_date" name="expected_close_date">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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

<script>
let deals = [];
let contacts = [];
let users = [];
let currentDealId = null;
let currentView = 'table';

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadDeals();
    loadContacts();
    loadUsers();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('addDealBtn').addEventListener('click', function() {
        currentDealId = null;
        document.getElementById('dealModalLabel').textContent = 'Add Deal';
        document.getElementById('dealForm').reset();
        
        const modal = new bootstrap.Modal(document.getElementById('dealModal'));
        
        // Initialize Select2 after modal is fully shown
        document.getElementById('dealModal').addEventListener('shown.bs.modal', function() {
            console.log('Modal shown, initializing Select2...');
            console.log('Contacts loaded:', contacts.length);
            console.log('Contact select element:', document.getElementById('contact_id'));
            console.log('Contact select options:', document.getElementById('contact_id').options.length);
            
            if ($('#contact_id').data('select2')) {
                $('#contact_id').select2('destroy');
            }
            
            try {
                // Hide the original select first
                $('#contact_id').hide();
                
                $('#contact_id').select2({
                    placeholder: 'Search for a contact...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#dealModal')
                });
                console.log('Select2 initialized successfully');
                console.log('Select2 container created:', $('.select2-container').length);
                console.log('Select2 container visible:', $('.select2-container').is(':visible'));
            } catch (error) {
                console.error('Select2 initialization failed:', error);
            }
        });
        
        modal.show();
    });
    
    document.getElementById('dealForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveDeal();
    });
    
    // Filter event listeners
    document.getElementById('stageFilter').addEventListener('change', filterDeals);
    document.getElementById('assignedFilter').addEventListener('change', filterDeals);
    document.getElementById('searchFilter').addEventListener('input', filterDeals);
}

async function loadDeals() {
    try {
        const response = await fetch('/api/v1/deals', {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (response.ok) {
            deals = result.deals || [];
            renderDeals();
        } else {
            showAlert('Failed to load deals: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while loading deals', 'danger');
    }
}

async function loadContacts() {
    try {
        console.log('Loading contacts...');
        const response = await fetch('/api/v1/contacts', {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (response.ok) {
            contacts = result.contacts || [];
            console.log('Contacts loaded:', contacts.length, contacts);
            populateContactSelect();
        } else {
            console.error('Failed to load contacts:', result.error);
        }
    } catch (err) {
        console.error('Failed to load contacts:', err);
    }
}

async function loadUsers() {
    try {
        const response = await fetch('/api/v1/users', {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (response.ok) {
            users = result.users || [];
            populateUserSelects();
        }
    } catch (err) {
        console.error('Failed to load users:', err);
    }
}

function populateContactSelect() {
    const select = document.getElementById('contact_id');
    select.innerHTML = '<option value="">Select Contact</option>';
    
    contacts.forEach(contact => {
        const option = document.createElement('option');
        option.value = contact.id;
        option.textContent = `${contact.first_name} ${contact.last_name} (${contact.email})`;
        select.appendChild(option);
    });
    
    // If Select2 is already initialized, update it
    if ($('#contact_id').data('select2')) {
        $('#contact_id').trigger('change');
    }
}

function populateUserSelects() {
    const assignedSelect = document.getElementById('assigned_to');
    const filterSelect = document.getElementById('assignedFilter');
    
    assignedSelect.innerHTML = '<option value="">Unassigned</option>';
    filterSelect.innerHTML = '<option value="">All Users</option>';
    
    users.forEach(user => {
        // Assigned to select
        const option1 = document.createElement('option');
        option1.value = user.id;
        option1.textContent = `${user.first_name} ${user.last_name}`;
        assignedSelect.appendChild(option1);
        
        // Filter select
        const option2 = document.createElement('option');
        option2.value = user.id;
        option2.textContent = `${user.first_name} ${user.last_name}`;
        filterSelect.appendChild(option2);
    });
}

function renderDeals() {
    if (currentView === 'table') {
        renderTableView();
    } else {
        renderKanbanView();
    }
}

function renderTableView() {
    const tbody = document.querySelector('#dealsTable tbody');
    tbody.innerHTML = '';
    
    deals.forEach(deal => {
        const contact = contacts.find(c => c.id == deal.contact_id);
        const assignedUser = users.find(u => u.id == deal.assigned_to);
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${deal.id}</td>
            <td><strong>${escapeHtml(deal.title)}</strong></td>
            <td>${contact ? escapeHtml(`${contact.first_name} ${contact.last_name}`) : 'Unknown'}</td>
            <td class="amount">${deal.amount ? '$' + parseFloat(deal.amount).toLocaleString() : '-'}</td>
            <td><span class="badge bg-${getStageColor(deal.stage)} stage-badge">${deal.stage.replace('_', ' ')}</span></td>
            <td class="probability">${deal.probability}%</td>
            <td>${assignedUser ? escapeHtml(`${assignedUser.first_name} ${assignedUser.last_name}`) : 'Unassigned'}</td>
            <td>${deal.expected_close_date || '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editDeal(${deal.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteDeal(${deal.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function renderKanbanView() {
    const board = document.getElementById('kanbanBoard');
    board.innerHTML = '';
    
    const stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
    
    stages.forEach(stage => {
        const stageDeals = deals.filter(deal => deal.stage === stage);
        
        const column = document.createElement('div');
        column.className = 'col-md-2';
        column.innerHTML = `
            <div class="card">
                <div class="card-header bg-${getStageColor(stage)} text-white">
                    <h6 class="mb-0">${stage.replace('_', ' ').toUpperCase()}</h6>
                    <small>${stageDeals.length} deals</small>
                </div>
                <div class="card-body p-2">
                    ${stageDeals.map(deal => {
                        const contact = contacts.find(c => c.id == deal.contact_id);
                        return `
                            <div class="deal-card card mb-2" onclick="editDeal(${deal.id})">
                                <div class="card-body p-2">
                                    <h6 class="card-title mb-1">${escapeHtml(deal.title)}</h6>
                                    <small class="text-muted">${contact ? escapeHtml(`${contact.first_name} ${contact.last_name}`) : 'Unknown'}</small>
                                    ${deal.amount ? `<div class="amount mt-1">$${parseFloat(deal.amount).toLocaleString()}</div>` : ''}
                                    <div class="probability">${deal.probability}%</div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
        board.appendChild(column);
    });
}

function getStageColor(stage) {
    const colors = {
        'prospecting': 'secondary',
        'qualification': 'info',
        'proposal': 'warning',
        'negotiation': 'primary',
        'closed_won': 'success',
        'closed_lost': 'danger'
    };
    return colors[stage] || 'secondary';
}

function setView(view) {
    currentView = view;
    
    // Update button states
    document.getElementById('tableViewBtn').classList.toggle('active', view === 'table');
    document.getElementById('kanbanViewBtn').classList.toggle('active', view === 'kanban');
    
    // Show/hide content
    document.getElementById('tableView').classList.toggle('d-none', view !== 'table');
    document.getElementById('kanbanView').classList.toggle('d-none', view !== 'kanban');
    
    renderDeals();
}

function filterDeals() {
    const stageFilter = document.getElementById('stageFilter').value;
    const assignedFilter = document.getElementById('assignedFilter').value;
    const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
    
    // This would typically be done server-side, but for now we'll filter client-side
    // In a real implementation, you'd send these filters to the API
    loadDeals(); // Reload with current filters
}

function clearFilters() {
    document.getElementById('stageFilter').value = '';
    document.getElementById('assignedFilter').value = '';
    document.getElementById('searchFilter').value = '';
    loadDeals();
}

async function saveDeal() {
    const formData = new FormData(document.getElementById('dealForm'));
    const data = Object.fromEntries(formData.entries());
    
    // Convert empty strings to null for optional fields
    if (!data.amount) data.amount = null;
    if (!data.assigned_to) data.assigned_to = null;
    if (!data.expected_close_date) data.expected_close_date = null;
    if (!data.description) data.description = null;
    
    try {
        const url = currentDealId ? `/api/v1/deals/${currentDealId}` : '/api/v1/deals';
        const method = currentDealId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showAlert(`Deal ${currentDealId ? 'updated' : 'created'} successfully!`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('dealModal')).hide();
            loadDeals();
        } else {
            showAlert('Failed to save deal: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while saving deal', 'danger');
    }
}

function editDeal(dealId) {
    const deal = deals.find(d => d.id == dealId);
    if (!deal) return;
    
    currentDealId = dealId;
    document.getElementById('dealModalLabel').textContent = 'Edit Deal';
    
    // Populate form
    document.getElementById('deal_id').value = deal.id;
    document.getElementById('title').value = deal.title;
    document.getElementById('contact_id').value = deal.contact_id;
    document.getElementById('amount').value = deal.amount || '';
    document.getElementById('stage').value = deal.stage;
    document.getElementById('probability').value = deal.probability || 0;
    document.getElementById('assigned_to').value = deal.assigned_to || '';
    document.getElementById('expected_close_date').value = deal.expected_close_date || '';
    document.getElementById('description').value = deal.description || '';
    
    const modal = new bootstrap.Modal(document.getElementById('dealModal'));
    
    // Initialize Select2 after modal is fully shown
    document.getElementById('dealModal').addEventListener('shown.bs.modal', function() {
        console.log('Modal shown, initializing Select2 for edit...');
        
        if ($('#contact_id').data('select2')) {
            $('#contact_id').select2('destroy');
        }
        
                    try {
                // Hide the original select first
                $('#contact_id').hide();
                
                $('#contact_id').select2({
                    placeholder: 'Search for a contact...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#dealModal')
                });
                console.log('Select2 initialized successfully for edit');
            } catch (error) {
                console.error('Select2 initialization failed for edit:', error);
            }
    });
    
    modal.show();
}

async function deleteDeal(dealId) {
    if (!confirm('Are you sure you want to delete this deal? This action cannot be undone.')) return;
    
    try {
        const response = await fetch(`/api/v1/deals/${dealId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (response.ok) {
            showAlert('Deal deleted successfully!', 'success');
            loadDeals();
        } else {
            const result = await response.json();
            showAlert('Failed to delete deal: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while deleting deal', 'danger');
    }
}

function showAlert(message, type) {
    const alertBox = document.getElementById('dealsAlert');
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

<?php
renderFooter();
?> 
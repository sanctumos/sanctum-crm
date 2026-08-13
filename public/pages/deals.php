<?php
/**
 * Deals Pipeline Page
 * Sanctum CRM
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Deals');
ob_start();
?>
<button class="btn crm-btn-primary" id="addDealBtn" type="button"><i class="bi bi-plus-lg me-1"></i>Add Deal</button>
<?php
renderPageHeader('Deals', 'Pipeline and opportunity tracking', ob_get_clean());
?>

<style>
    .deals-card { max-width: 1400px; margin: 0 auto; }
    .ts-dropdown { z-index: 2000 !important; }
</style>

<div class="deals-card">
    <!-- Filters -->
    <form class="filter-bar" role="search" onsubmit="return false;">
        <div class="filter-bar__field">
            <select class="form-select" id="stageFilter" aria-label="Deal stage">
                <option value="">All Stages</option>
                <option value="prospecting">Prospecting</option>
                <option value="qualification">Qualification</option>
                <option value="proposal">Proposal</option>
                <option value="negotiation">Negotiation</option>
                <option value="closed_won">Closed Won</option>
                <option value="closed_lost">Closed Lost</option>
            </select>
        </div>
        <div class="filter-bar__field">
            <select class="form-select" id="assignedFilter" aria-label="Assigned to">
                <option value="">All Users</option>
            </select>
        </div>
        <div class="filter-bar__search">
            <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control border-start-0" id="searchFilter" placeholder="Search deals…" aria-label="Search deals">
            </div>
        </div>
        <div class="filter-bar__actions">
            <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                <i class="bi bi-x-lg me-1"></i>Clear
            </button>
        </div>
    </form>

    <!-- View Toggle -->
    <nav class="tabbar" aria-label="Deals views">
        <button type="button" class="active" onclick="setView('table')" id="tableViewBtn">
            <i class="bi bi-table"></i> Table
        </button>
        <button type="button" onclick="setView('kanban')" id="kanbanViewBtn">
            <i class="bi bi-columns"></i> Kanban
        </button>
    </nav>

    <!-- Alerts -->
    <div id="dealsAlert" class="alert d-none" role="alert"></div>

    <!-- Table View -->
    <div id="tableView" class="view-content">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle crm-table" id="dealsTable">
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
        <div class="board" id="kanbanBoard">
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
let users = [];
let currentDealId = null;
let currentView = 'table';

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    setupEventListeners();
    await Promise.all([loadDeals(false), loadUsers()]);
    renderDeals();
});

function setupEventListeners() {
    document.getElementById('addDealBtn').addEventListener('click', function() {
        currentDealId = null;
        document.getElementById('dealModalLabel').textContent = 'Add Deal';
        document.getElementById('dealForm').reset();
        
        const modal = new bootstrap.Modal(document.getElementById('dealModal'));
        
        document.getElementById('dealModal').addEventListener('shown.bs.modal', function onShown() {
            document.getElementById('dealModal').removeEventListener('shown.bs.modal', onShown);
            initContactSelect(null);
        });
        
        modal.show();
    });
    
    document.getElementById('dealForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveDeal();
    });
    
    document.getElementById('stageFilter').addEventListener('change', filterDeals);
    document.getElementById('assignedFilter').addEventListener('change', filterDeals);
    document.getElementById('searchFilter').addEventListener('input', filterDeals);
}

let contactTomSelect = null;

function initContactSelect(selected) {
    const el = document.getElementById('contact_id');
    if (!el || typeof TomSelect === 'undefined') return;
    if (contactTomSelect) {
        contactTomSelect.destroy();
        contactTomSelect = null;
    }
    el.innerHTML = '';
    contactTomSelect = new TomSelect(el, {
        valueField: 'id',
        labelField: 'text',
        searchField: ['text'],
        placeholder: 'Search for a contact…',
        allowEmptyOption: true,
        maxOptions: 50,
        load: function(query, callback) {
            const url = crmApiUrl('contacts?limit=25&q=' + encodeURIComponent(query || ''));
            fetch(url, { credentials: 'include' })
                .then(function(r) { return r.json().then(function(body) { return { ok: r.ok, body: body }; }); })
                .then(function(res) {
                    if (!res.ok) { callback(); return; }
                    const rows = (res.body && res.body.contacts) ? res.body.contacts : [];
                    callback(rows.map(function(c) {
                        return {
                            id: String(c.id),
                            text: (c.first_name || '') + ' ' + (c.last_name || '') + ' (' + (c.email || 'No email') + ')'
                        };
                    }));
                })
                .catch(function() { callback(); });
        },
        render: {
            option: function(data, escape) {
                return '<div>' + escape(data.text) + '</div>';
            },
            item: function(data, escape) {
                return '<div>' + escape(data.text) + '</div>';
            }
        }
    });
    if (selected && selected.id) {
        contactTomSelect.addOption(selected);
        contactTomSelect.setValue(String(selected.id), true);
    }
}

async function loadDeals(shouldRender = true) {
    try {
        const response = await fetch(crmApiUrl('deals'), {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (response.ok) {
            deals = result.deals || [];
            if (shouldRender) {
                renderDeals();
            }
        } else {
            showAlert('Failed to load deals: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while loading deals', 'danger');
    }
}

async function loadUsers() {
    try {
        const response = await fetch(crmApiUrl('users'), {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (response.ok) {
            users = (result.users || []).filter(function(u) { return Number(u.is_active) === 1; });
            populateUserSelects();
        }
    } catch (err) {
        console.error('Failed to load users:', err);
    }
}

function populateUserSelects() {
    const assignedSelect = document.getElementById('assigned_to');
    const filterSelect = document.getElementById('assignedFilter');
    
    assignedSelect.innerHTML = '<option value="">Unassigned</option>';
    filterSelect.innerHTML = '<option value="">All Users</option>';
    
    users.forEach(user => {
        const option1 = document.createElement('option');
        option1.value = user.id;
        option1.textContent = `${user.first_name} ${user.last_name}`;
        assignedSelect.appendChild(option1);
        
        const option2 = document.createElement('option');
        option2.value = user.id;
        option2.textContent = `${user.first_name} ${user.last_name}`;
        filterSelect.appendChild(option2);
    });
}

function dealContactLabel(deal) {
    const name = (deal.contact_name || '').trim();
    if (name) return name;
    return 'Unknown';
}

function dealAssigneeLabel(deal) {
    const name = (deal.assigned_to_name || '').trim();
    if (name) return name;
    if (!deal.assigned_to) return 'Unassigned';
    const assignedUser = users.find(u => String(u.id) === String(deal.assigned_to));
    if (assignedUser) {
        return `${assignedUser.first_name} ${assignedUser.last_name}`;
    }
    return 'Unassigned';
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
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${deal.id}</td>
            <td><strong>${escapeHtml(deal.title)}</strong></td>
            <td>${escapeHtml(dealContactLabel(deal))}</td>
            <td class="amount">${deal.amount ? '$' + parseFloat(deal.amount).toLocaleString() : '-'}</td>
            <td><span class="status-pill status-pill--${getStagePillVariant(deal.stage)}">${escapeHtml(prettyStage(deal.stage))}</span></td>
            <td class="probability">${deal.probability}%</td>
            <td>${escapeHtml(dealAssigneeLabel(deal))}</td>
            <td>${deal.expected_close_date || '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editDeal(${deal.id})" title="Edit" aria-label="Edit deal">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteDeal(${deal.id})" title="Delete" aria-label="Delete deal">
                    <i class="bi bi-trash" aria-hidden="true"></i>
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
        column.className = 'swimlane';
        column.innerHTML = `
            <div class="swimlane__header">
                <span class="status-pill status-pill--${getStagePillVariant(stage)}">${escapeHtml(prettyStage(stage))}</span>
                <span class="swimlane__count">${stageDeals.length}</span>
            </div>
            <div class="swimlane__body">
                ${stageDeals.length ? stageDeals.map(deal => {
                        return `
                            <div class="crm-card crm-card--board crm-card--interactive" onclick="editDeal(${deal.id})" role="button" tabindex="0">
                                <div class="crm-card__title">${escapeHtml(deal.title)}</div>
                                <div class="crm-card__meta">${escapeHtml(dealContactLabel(deal))}</div>
                                <div class="crm-card__footer">
                                    ${deal.amount ? `<span class="crm-card__amount">$${parseFloat(deal.amount).toLocaleString()}</span>` : '<span></span>'}
                                    <span class="crm-card__probability">${deal.probability}%</span>
                                </div>
                            </div>
                        `;
                    }).join('') : '<div class="swimlane__empty">No deals</div>'}
            </div>
        `;
        board.appendChild(column);
    });
}

function getStagePillVariant(stage) {
    /* Mirror of crm_pill_for_deal_stage() in includes/layout.php — keep them in sync. */
    const m = {
        'prospecting':   'info',
        'qualification': 'info',
        'proposal':      'doing',
        'negotiation':   'doing',
        'closed_won':    'done',
        'closed_lost':   'blocked',
    };
    return m[stage] || 'default';
}

function prettyStage(stage) {
    if (!stage) return '';
    return stage.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
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
        const url = currentDealId ? crmApiUrl(`deals/${currentDealId}`) : crmApiUrl('deals');
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
    
    document.getElementById('deal_id').value = deal.id;
    document.getElementById('title').value = deal.title;
    document.getElementById('amount').value = deal.amount || '';
    document.getElementById('stage').value = deal.stage;
    document.getElementById('probability').value = deal.probability || 0;
    document.getElementById('assigned_to').value = deal.assigned_to || '';
    document.getElementById('expected_close_date').value = deal.expected_close_date || '';
    document.getElementById('description').value = deal.description || '';
    
    const selectedContact = deal.contact_id ? {
        id: deal.contact_id,
        text: dealContactLabel(deal) + (deal.contact_email ? ' (' + deal.contact_email + ')' : '')
    } : null;
    
    const modal = new bootstrap.Modal(document.getElementById('dealModal'));
    document.getElementById('dealModal').addEventListener('shown.bs.modal', function onShown() {
        document.getElementById('dealModal').removeEventListener('shown.bs.modal', onShown);
        initContactSelect(selectedContact);
    });
    
    modal.show();
}

async function deleteDeal(dealId) {
    if (!confirm('Are you sure you want to delete this deal? This action cannot be undone.')) return;
    
    try {
        const response = await fetch(crmApiUrl(`deals/${dealId}`), {
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
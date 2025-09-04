<?php
/**
 * Webhook Management Page
 * Best Jobs in TA
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Webhooks');
?>

<style>
    .webhooks-card { max-width: 1000px; margin: 0 auto; }
    .webhook-card { transition: transform 0.2s; }
    .webhook-card:hover { transform: translateY(-2px); }
    .status-badge { text-transform: uppercase; font-size: 0.85em; }
    .event-badge { margin-right: 5px; margin-bottom: 5px; }
</style>

<div class="webhooks-card">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-link"></i> Webhook Management</h2>
        <button class="btn btn-success" id="addWebhookBtn"><i class="fas fa-plus"></i> Add Webhook</button>
    </div>

    <!-- Alerts -->
    <div id="webhooksAlert" class="alert d-none" role="alert"></div>

    <!-- Webhooks List -->
    <div class="row" id="webhooksList">
        <!-- Populated by JS -->
    </div>
</div>

<!-- Webhook Modal -->
<div class="modal fade" id="webhookModal" tabindex="-1" aria-labelledby="webhookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="webhookForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="webhookModalLabel">Add/Edit Webhook</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="webhook_id" name="webhook_id">
                    <div class="mb-3">
                        <label for="url" class="form-label">Webhook URL *</label>
                        <input type="url" class="form-control" id="url" name="url" required placeholder="https://your-domain.com/webhook">
                    </div>
                    <div class="mb-3">
                        <label for="events" class="form-label">Events to Trigger *</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="event_contact_created" name="events[]" value="contact.created">
                                    <label class="form-check-label" for="event_contact_created">Contact Created</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="event_contact_updated" name="events[]" value="contact.updated">
                                    <label class="form-check-label" for="event_contact_updated">Contact Updated</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="event_contact_deleted" name="events[]" value="contact.deleted">
                                    <label class="form-check-label" for="event_contact_deleted">Contact Deleted</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="event_deal_created" name="events[]" value="deal.created">
                                    <label class="form-check-label" for="event_deal_created">Deal Created</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="event_deal_updated" name="events[]" value="deal.updated">
                                    <label class="form-check-label" for="event_deal_updated">Deal Updated</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="event_deal_deleted" name="events[]" value="deal.deleted">
                                    <label class="form-check-label" for="event_deal_deleted">Deal Deleted</label>
                                </div>
                            </div>
                        </div>
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

<!-- Test Webhook Modal -->
<div class="modal fade" id="testWebhookModal" tabindex="-1" aria-labelledby="testWebhookModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testWebhookModalLabel">Test Webhook</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will send a test payload to your webhook URL to verify it's working correctly.</p>
                <div id="testResult" class="alert d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendTestBtn">Send Test</button>
            </div>
        </div>
    </div>
</div>

<script>
let webhooks = [];
let currentWebhookId = null;
let currentTestWebhookId = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadWebhooks();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('addWebhookBtn').addEventListener('click', function() {
        currentWebhookId = null;
        document.getElementById('webhookModalLabel').textContent = 'Add Webhook';
        document.getElementById('webhookForm').reset();
        new bootstrap.Modal(document.getElementById('webhookModal')).show();
    });
    
    document.getElementById('webhookForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveWebhook();
    });
    
    document.getElementById('sendTestBtn').addEventListener('click', function() {
        sendTestWebhook();
    });
}

async function loadWebhooks() {
    try {
        const response = await fetch('/api/v1/webhooks', {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (response.ok) {
            webhooks = result.webhooks || [];
            renderWebhooks();
        } else {
            showAlert('Failed to load webhooks: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while loading webhooks', 'danger');
    }
}

function renderWebhooks() {
    const container = document.getElementById('webhooksList');
    container.innerHTML = '';
    
    if (webhooks.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card text-center p-5">
                    <i class="fas fa-link fa-3x text-muted mb-3"></i>
                    <h5>No Webhooks Found</h5>
                    <p class="text-muted">Get started by creating your first webhook to receive real-time updates.</p>
                    <button class="btn btn-primary" onclick="document.getElementById('addWebhookBtn').click()">
                        <i class="fas fa-plus"></i> Add Your First Webhook
                    </button>
                </div>
            </div>
        `;
        return;
    }
    
    webhooks.forEach(webhook => {
        const events = JSON.parse(webhook.events || '[]');
        const eventBadges = events.map(event => 
            `<span class="badge bg-info event-badge">${event}</span>`
        ).join('');
        
        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-4 mb-3';
        card.innerHTML = `
            <div class="card webhook-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-link"></i> Webhook</h6>
                    <span class="badge bg-${webhook.is_active ? 'success' : 'warning'} status-badge">
                        ${webhook.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                <div class="card-body">
                    <p class="card-text"><strong>URL:</strong><br><code class="small">${escapeHtml(webhook.url)}</code></p>
                    <p class="card-text"><strong>Events:</strong><br>${eventBadges}</p>
                    <p class="card-text"><small class="text-muted">Created: ${new Date(webhook.created_at).toLocaleDateString()}</small></p>
                </div>
                <div class="card-footer">
                    <div class="btn-group w-100" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editWebhook(${webhook.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="testWebhook(${webhook.id})" title="Test">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-${webhook.is_active ? 'warning' : 'success'}" onclick="toggleWebhookStatus(${webhook.id})" title="${webhook.is_active ? 'Deactivate' : 'Activate'}">
                            <i class="fas fa-${webhook.is_active ? 'pause' : 'play'}"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteWebhook(${webhook.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

async function saveWebhook() {
    const formData = new FormData(document.getElementById('webhookForm'));
    const data = {
        url: formData.get('url'),
        events: formData.getAll('events[]'),
        is_active: parseInt(formData.get('is_active'))
    };
    
    if (data.events.length === 0) {
        showAlert('Please select at least one event', 'warning');
        return;
    }
    
    try {
        const url = currentWebhookId ? `/api/v1/webhooks/${currentWebhookId}` : '/api/v1/webhooks';
        const method = currentWebhookId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showAlert(`Webhook ${currentWebhookId ? 'updated' : 'created'} successfully!`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('webhookModal')).hide();
            loadWebhooks();
        } else {
            showAlert('Failed to save webhook: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while saving webhook', 'danger');
    }
}

function editWebhook(webhookId) {
    const webhook = webhooks.find(w => w.id == webhookId);
    if (!webhook) return;
    
    currentWebhookId = webhookId;
    document.getElementById('webhookModalLabel').textContent = 'Edit Webhook';
    
    // Populate form
    document.getElementById('webhook_id').value = webhook.id;
    document.getElementById('url').value = webhook.url;
    document.getElementById('is_active').value = webhook.is_active ? '1' : '0';
    
    // Clear all checkboxes first
    document.querySelectorAll('input[name="events[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Check the events that are selected
    const events = JSON.parse(webhook.events || '[]');
    events.forEach(event => {
        const checkbox = document.getElementById(`event_${event.replace('.', '_')}`);
        if (checkbox) checkbox.checked = true;
    });
    
    new bootstrap.Modal(document.getElementById('webhookModal')).show();
}

function testWebhook(webhookId) {
    currentTestWebhookId = webhookId;
    document.getElementById('testResult').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('testWebhookModal')).show();
}

async function sendTestWebhook() {
    if (!currentTestWebhookId) return;
    
    const sendBtn = document.getElementById('sendTestBtn');
    const resultDiv = document.getElementById('testResult');
    
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    try {
        const response = await fetch(`/api/v1/webhooks/${currentTestWebhookId}/test`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
        });
        
        const result = await response.json();
        
        resultDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
        
        if (response.ok) {
            resultDiv.textContent = 'Test webhook sent successfully! Check your endpoint for the payload.';
            resultDiv.classList.add('alert-success');
        } else {
            resultDiv.textContent = 'Failed to send test webhook: ' + (result.error || 'Unknown error');
            resultDiv.classList.add('alert-danger');
        }
    } catch (err) {
        resultDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
        resultDiv.textContent = 'Network error while sending test webhook';
        resultDiv.classList.add('alert-danger');
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = 'Send Test';
    }
}

async function toggleWebhookStatus(webhookId) {
    const webhook = webhooks.find(w => w.id == webhookId);
    if (!webhook) return;
    
    try {
        const response = await fetch(`/api/v1/webhooks/${webhookId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ is_active: !webhook.is_active })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showAlert(`Webhook ${webhook.is_active ? 'deactivated' : 'activated'} successfully!`, 'success');
            loadWebhooks();
        } else {
            showAlert('Failed to update webhook status: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while updating webhook status', 'danger');
    }
}

async function deleteWebhook(webhookId) {
    if (!confirm('Are you sure you want to delete this webhook? This action cannot be undone.')) return;
    
    try {
        const response = await fetch(`/api/v1/webhooks/${webhookId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        if (response.ok) {
            showAlert('Webhook deleted successfully!', 'success');
            loadWebhooks();
        } else {
            const result = await response.json();
            showAlert('Failed to delete webhook: ' + (result.error || 'Unknown error'), 'danger');
        }
    } catch (err) {
        showAlert('Network error while deleting webhook', 'danger');
    }
}

function showAlert(message, type) {
    const alertBox = document.getElementById('webhooksAlert');
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
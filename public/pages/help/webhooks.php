<?php
/**
 * Webhooks Documentation Page
 * Best Jobs in TA - Webhooks documentation
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-link me-2"></i>Webhooks Documentation</h4>
    <span class="badge bg-success">Real-time Integration</span>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Webhooks</strong> allow you to receive real-time notifications when events occur in your CRM. Perfect for integrations with external systems.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Available Events</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>contact.created</strong>
                            <br><small class="text-muted">New contact added</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>contact.updated</strong>
                            <br><small class="text-muted">Contact information changed</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>contact.deleted</strong>
                            <br><small class="text-muted">Contact removed</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>deal.created</strong>
                            <br><small class="text-muted">New deal created</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>deal.updated</strong>
                            <br><small class="text-muted">Deal status changed</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>deal.deleted</strong>
                            <br><small class="text-muted">Deal removed</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>contact.enriched</strong>
                            <br><small class="text-muted">Contact data enriched</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Webhook Configuration</h5>
            </div>
            <div class="card-body">
                <h6>Required Fields:</h6>
                <ul>
                    <li><strong>URL:</strong> Your endpoint URL</li>
                    <li><strong>Events:</strong> Select which events to receive</li>
                    <li><strong>Secret:</strong> For signature verification</li>
                </ul>
                
                <h6 class="mt-3">Optional Fields:</h6>
                <ul>
                    <li><strong>Description:</strong> Internal notes</li>
                    <li><strong>Active:</strong> Enable/disable webhook</li>
                    <li><strong>Retry Count:</strong> Failed delivery retries</li>
                </ul>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Your endpoint must respond with HTTP 200 within 30 seconds.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-code me-2"></i>Webhook Payload Format</h5>
            </div>
            <div class="card-body">
                <p>All webhook payloads follow this structure:</p>
                <pre class="bg-light p-3"><code>{
  "event": "contact.created",
  "timestamp": "2025-09-08T12:00:00Z",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "company": "Acme Corp",
    "created_at": "2025-09-08T12:00:00Z",
    "updated_at": "2025-09-08T12:00:00Z"
  },
  "webhook_id": "webhook_123",
  "signature": "sha256=abc123..."
}</code></pre>
                
                <h6 class="mt-3">Signature Verification</h6>
                <p>Verify webhook authenticity using the signature header:</p>
                <pre class="bg-light p-2"><code>X-Webhook-Signature: sha256=abc123...</code></pre>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Tip:</strong> Always verify the signature to ensure the webhook came from your CRM.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Testing Webhooks</h5>
            </div>
            <div class="card-body">
                <h6>Test Endpoint</h6>
                <p>Use the test button in the webhook settings to send a test payload:</p>
                <pre class="bg-light p-2"><code>POST /api/v1/webhooks/{id}/test</code></pre>
                
                <h6 class="mt-3">Test Payload</h6>
                <pre class="bg-light p-2"><code>{
  "event": "webhook.test",
  "timestamp": "2025-09-08T12:00:00Z",
  "data": {
    "message": "This is a test webhook"
  }
}</code></pre>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Best Practices</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use HTTPS endpoints</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Verify signatures</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Respond quickly (under 30s)</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Handle duplicate events</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Log webhook activity</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use idempotent operations</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Common Issues</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Webhook Not Firing</h6>
                        <ul>
                            <li>Check if webhook is active</li>
                            <li>Verify URL is accessible</li>
                            <li>Check event selection</li>
                            <li>Review webhook logs</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Failed Deliveries</h6>
                        <ul>
                            <li>Check endpoint response time</li>
                            <li>Verify HTTP 200 response</li>
                            <li>Check server logs</li>
                            <li>Test with curl</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * API Documentation Page
 * Best Jobs in TA - API documentation
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-code me-2"></i>API Documentation</h4>
    <span class="badge bg-primary">REST API v1</span>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Base URL:</strong> <code><?php echo APP_URL; ?>/api/v1/</code><br>
    <strong>Authentication:</strong> Bearer token or session-based<br>
    <strong>Content-Type:</strong> <code>application/json</code>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Authentication</h6>
            </div>
            <div class="card-body">
                <h6>Bearer Token</h6>
                <pre class="bg-light p-2"><code>Authorization: Bearer YOUR_API_KEY</code></pre>
                
                <h6 class="mt-3">Session Cookie</h6>
                <pre class="bg-light p-2"><code>Cookie: crm_session=SESSION_ID</code></pre>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Rate Limits</h6>
            </div>
            <div class="card-body">
                <p><strong>1000 requests/hour</strong> per user</p>
                <p class="text-muted small">Rate limit headers included in responses</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Endpoints</h6>
            </div>
            <div class="card-body">
                <div class="accordion" id="apiEndpoints">
                    <!-- Contacts -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="contactsHeader">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#contactsCollapse">
                                <i class="fas fa-users me-2"></i>Contacts
                            </button>
                        </h2>
                        <div id="contactsCollapse" class="accordion-collapse collapse show" data-bs-parent="#apiEndpoints">
                            <div class="accordion-body">
                                <h6>GET /api/v1/contacts</h6>
                                <p>List all contacts</p>
                                <pre class="bg-light p-2"><code>curl -H "Authorization: Bearer YOUR_KEY" \
  "<?php echo APP_URL; ?>/api/v1/contacts"</code></pre>
                                
                                <h6 class="mt-3">POST /api/v1/contacts</h6>
                                <p>Create a new contact</p>
                                <pre class="bg-light p-2"><code>curl -X POST \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"john@example.com"}' \
  "<?php echo APP_URL; ?>/api/v1/contacts"</code></pre>
                                
                                <h6 class="mt-3">GET /api/v1/contacts/{id}</h6>
                                <p>Get specific contact</p>
                                
                                <h6 class="mt-3">PUT /api/v1/contacts/{id}</h6>
                                <p>Update contact</p>
                                
                                <h6 class="mt-3">DELETE /api/v1/contacts/{id}</h6>
                                <p>Delete contact</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deals -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="dealsHeader">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dealsCollapse">
                                <i class="fas fa-handshake me-2"></i>Deals
                            </button>
                        </h2>
                        <div id="dealsCollapse" class="accordion-collapse collapse" data-bs-parent="#apiEndpoints">
                            <div class="accordion-body">
                                <h6>GET /api/v1/deals</h6>
                                <p>List all deals</p>
                                
                                <h6 class="mt-3">POST /api/v1/deals</h6>
                                <p>Create a new deal</p>
                                
                                <h6 class="mt-3">GET /api/v1/deals/{id}</h6>
                                <p>Get specific deal</p>
                                
                                <h6 class="mt-3">PUT /api/v1/deals/{id}</h6>
                                <p>Update deal</p>
                                
                                <h6 class="mt-3">DELETE /api/v1/deals/{id}</h6>
                                <p>Delete deal</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Import -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="importHeader">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#importCollapse">
                                <i class="fas fa-file-import me-2"></i>Import
                            </button>
                        </h2>
                        <div id="importCollapse" class="accordion-collapse collapse" data-bs-parent="#apiEndpoints">
                            <div class="accordion-body">
                                <h6>POST /api/v1/import</h6>
                                <p>Import CSV data</p>
                                <pre class="bg-light p-2"><code>curl -X POST \
  -H "Authorization: Bearer YOUR_KEY" \
  -F "csvFile=@contacts.csv" \
  "<?php echo APP_URL; ?>/api/v1/import"</code></pre>
                                
                                <h6 class="mt-3">POST /api/v1/import (JSON)</h6>
                                <p>Process parsed CSV data</p>
                                <pre class="bg-light p-2"><code>curl -X POST \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"csvData":[...],"fieldMapping":{...}}' \
  "<?php echo APP_URL; ?>/api/v1/import"</code></pre>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enrichment -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="enrichmentHeader">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#enrichmentCollapse">
                                <i class="fas fa-user-plus me-2"></i>Enrichment
                            </button>
                        </h2>
                        <div id="enrichmentCollapse" class="accordion-collapse collapse" data-bs-parent="#apiEndpoints">
                            <div class="accordion-body">
                                <h6>POST /api/v1/enrichment/enrich</h6>
                                <p>Enrich a contact</p>
                                <pre class="bg-light p-2"><code>curl -X POST \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"contact_id":123}' \
  "<?php echo APP_URL; ?>/api/v1/enrichment/enrich"</code></pre>
                                
                                <h6 class="mt-3">GET /api/v1/enrichment/stats</h6>
                                <p>Get enrichment statistics</p>
                                
                                <h6 class="mt-3">GET /api/v1/enrichment/status</h6>
                                <p>Check enrichment service status</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Webhooks -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="webhooksHeader">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#webhooksCollapse">
                                <i class="fas fa-link me-2"></i>Webhooks
                            </button>
                        </h2>
                        <div id="webhooksCollapse" class="accordion-collapse collapse" data-bs-parent="#apiEndpoints">
                            <div class="accordion-body">
                                <h6>GET /api/v1/webhooks</h6>
                                <p>List all webhooks</p>
                                
                                <h6 class="mt-3">POST /api/v1/webhooks</h6>
                                <p>Create a new webhook</p>
                                
                                <h6 class="mt-3">GET /api/v1/webhooks/{id}</h6>
                                <p>Get specific webhook</p>
                                
                                <h6 class="mt-3">PUT /api/v1/webhooks/{id}</h6>
                                <p>Update webhook</p>
                                
                                <h6 class="mt-3">DELETE /api/v1/webhooks/{id}</h6>
                                <p>Delete webhook</p>
                                
                                <h6 class="mt-3">POST /api/v1/webhooks/{id}/test</h6>
                                <p>Test webhook</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Error Handling</h5>
            </div>
            <div class="card-body">
                <p>All API responses follow a consistent format:</p>
                <pre class="bg-light p-3"><code>{
  "success": true|false,
  "data": {...},
  "error": "Error message",
  "code": 200|400|401|404|500
}</code></pre>
                
                <h6 class="mt-3">Common HTTP Status Codes:</h6>
                <ul>
                    <li><strong>200:</strong> Success</li>
                    <li><strong>400:</strong> Bad Request (invalid data)</li>
                    <li><strong>401:</strong> Unauthorized (invalid/missing auth)</li>
                    <li><strong>404:</strong> Not Found</li>
                    <li><strong>429:</strong> Rate Limit Exceeded</li>
                    <li><strong>500:</strong> Internal Server Error</li>
                </ul>
            </div>
        </div>
    </div>
</div>

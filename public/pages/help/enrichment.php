<?php
/**
 * Lead Enrichment Help Page
 * Sanctum CRM - Lead enrichment documentation (RocketReach + Apollo)
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-person-plus me-2"></i>Lead Enrichment Guide</h4>
    <span class="badge bg-info">RocketReach &amp; Apollo</span>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Lead Enrichment</strong> fills in professional contact details from an external data provider.
    You pick <strong>one active provider</strong> in Settings: <strong>RocketReach</strong> or <strong>Apollo</strong>.
    Both keys can be stored; only the selected provider is used for Enrich buttons, bulk runs, cron, and the API.
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Setup &amp; Configuration</h5>
            </div>
            <div class="card-body">
                <h6>Step 1: Get an API key</h6>
                <ul>
                    <li><strong>RocketReach:</strong> <a href="https://rocketreach.co" target="_blank" rel="noopener">rocketreach.co</a> → account → API settings → create a key.</li>
                    <li><strong>Apollo:</strong> <a href="https://apollo.io" target="_blank" rel="noopener">apollo.io</a> → Settings → Integrations → API.
                        People enrichment needs a key with <code>people/match</code> (or master) access — trial/org-only keys cannot enrich people.</li>
                </ul>

                <h6 class="mt-4">Step 2: Configure in CRM</h6>
                <ol>
                    <li>Open <strong>Settings</strong>.</li>
                    <li>Find the <strong>Lead Enrichment</strong> section.</li>
                    <li>Choose the active provider (RocketReach or Apollo).</li>
                    <li>Paste the matching API key (you may store both keys for later switching).</li>
                    <li>Use <strong>Test Apollo</strong> (when Apollo is selected) to verify connectivity.</li>
                    <li>Save settings.</li>
                </ol>

                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Keep API keys secret. Do not put them in git, Tasks, or public docs.
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-hdd-stack me-2"></i>Enrichment Data</h5>
            </div>
            <div class="card-body">
                <h6>Typical fields (provider-dependent):</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Professional email</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Phone numbers</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Social / LinkedIn profiles</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Company information</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Job title</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Location</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Provider person / profile IDs</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Providers compared</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Topic</th>
                                <th>RocketReach</th>
                                <th>Apollo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Match inputs</td>
                                <td>Email, LinkedIn, name + company, Twitter (auto)</td>
                                <td>Email, LinkedIn, name + company (no Twitter)</td>
                            </tr>
                            <tr>
                                <td>Stored provider ID</td>
                                <td><code>rocketreach_profile_id</code></td>
                                <td><code>apollo_person_id</code></td>
                            </tr>
                            <tr>
                                <td>Settings key column</td>
                                <td><code>rocketreach_api_key</code></td>
                                <td><code>apollo_api_key</code></td>
                            </tr>
                            <tr>
                                <td>Active switch</td>
                                <td colspan="2"><code>settings.enrichment_provider</code> = <code>rocketreach</code> or <code>apollo</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-play me-2"></i>How to Use Enrichment</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Manual Enrichment</h6>
                        <ol>
                            <li>Open Contacts (or a contact detail page).</li>
                            <li>Click <strong>Enrich</strong> on the contact.</li>
                            <li>Wait for the run to finish.</li>
                            <li>Review stored enrichment on the contact.</li>
                        </ol>

                        <h6 class="mt-4">Bulk Enrichment</h6>
                        <ol>
                            <li>Select multiple contacts.</li>
                            <li>Use bulk actions → enrich selected.</li>
                            <li>Monitor outcomes (enriched / not found / failed).</li>
                        </ol>

                        <h6 class="mt-4">Scheduled cron</h6>
                        <p class="mb-0">When cron is enabled in Settings, <code>cron/enrichment.php</code> uses the <strong>active</strong> provider and the same per-run / daily caps.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>API — enrich one contact</h6>
                        <p class="small text-muted">Stock nginx: use <code>index.php?path=…</code>. Paths below are logical.</p>
                        <pre class="bg-light p-2 small"><code>POST /api/v1/contacts/{id}/enrich
Authorization: Bearer YOUR_KEY
Content-Type: application/json

{"strategy":"auto"}</code></pre>

                        <h6 class="mt-3">API — status &amp; bulk</h6>
                        <pre class="bg-light p-2 small mb-0"><code>GET  /api/v1/contacts/{id}/enrichment-status
POST /api/v1/contacts/bulk-enrich
     {"contact_ids":[1,2,3],"strategy":"auto"}

GET  /api/v1/enrichment/stats
GET|PUT /api/v1/enrichment/cron   (admin for writes)</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Limits &amp; costs</h5>
            </div>
            <div class="card-body">
                <h6>Provider plans</h6>
                <ul>
                    <li>Credits / rate limits follow your RocketReach or Apollo plan.</li>
                    <li>CRM cron caps (max per run / per day) limit how many contacts we attempt.</li>
                    <li>Not-found lookups still count against some provider plans — check their docs.</li>
                </ul>

                <h6 class="mt-3">Data quality</h6>
                <ul class="mb-0">
                    <li>Not every contact is in the provider database.</li>
                    <li>Accuracy and freshness vary.</li>
                    <li>Privacy / compliance settings can hide fields.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Statistics &amp; source</h5>
            </div>
            <div class="card-body">
                <p>Contact rows store <code>enrichment_status</code>, <code>enriched_at</code>, and provider source in enrichment sidecar / raw JSON.</p>
                <ul>
                    <li>Total enriched / failed / not found (via stats API and UI)</li>
                    <li>Active provider used for the last successful enrich</li>
                    <li>Cron last-run summary in Settings</li>
                </ul>
                <pre class="bg-light p-2 mb-0"><code>GET /api/v1/enrichment/stats</code></pre>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Troubleshooting</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Common issues</h6>
                        <ul>
                            <li><strong>Wrong provider:</strong> Settings → active provider must match the key you expect.</li>
                            <li><strong>Apollo 403 / inaccessible:</strong> Key lacks people match scope — use a master or people-enabled key.</li>
                            <li><strong>API key invalid:</strong> Re-paste key for the active provider; save; retest.</li>
                            <li><strong>No results:</strong> Add email or LinkedIn; name + company helps.</li>
                            <li><strong>Rate limited (429):</strong> Wait; lower cron caps; retry later.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Best practices</h6>
                        <ul>
                            <li>Start with contacts that have email or LinkedIn.</li>
                            <li>Use cron caps so you do not burn the whole credit pool overnight.</li>
                            <li>Switch providers deliberately — do not assume both run at once.</li>
                            <li>Review raw enrichment JSON on the contact when debugging mismatches.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

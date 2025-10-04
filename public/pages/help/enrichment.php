<?php
/**
 * Lead Enrichment Help Page
 * Best Jobs in TA - Lead enrichment documentation
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-user-plus me-2"></i>Lead Enrichment Guide</h4>
    <span class="badge bg-info">RocketReach Integration</span>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Lead Enrichment</strong> automatically enhances contact data using RocketReach's database of professional information.
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Setup & Configuration</h5>
            </div>
            <div class="card-body">
                <h6>Step 1: Get RocketReach API Key</h6>
                <ol>
                    <li>Visit <a href="https://rocketreach.co" target="_blank">RocketReach.co</a></li>
                    <li>Sign up for an account</li>
                    <li>Navigate to API settings</li>
                    <li>Generate your API key</li>
                </ol>
                
                <h6 class="mt-4">Step 2: Configure in CRM</h6>
                <ol>
                    <li>Go to Settings page</li>
                    <li>Find "RocketReach Lead Enrichment" section</li>
                    <li>Enable enrichment toggle</li>
                    <li>Enter your API key</li>
                    <li>Save settings</li>
                </ol>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Keep your API key secure and don't share it publicly.
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Enrichment Data</h5>
            </div>
            <div class="card-body">
                <h6>Available Information:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Professional email</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Phone numbers</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Social profiles</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Company information</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Job title</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Location data</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Education history</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Work experience</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-play me-2"></i>How to Use Enrichment</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Manual Enrichment</h6>
                        <ol>
                            <li>Go to Contacts page</li>
                            <li>Find the contact you want to enrich</li>
                            <li>Click "Enrich" button</li>
                            <li>Wait for enrichment to complete</li>
                            <li>Review enriched data</li>
                        </ol>
                        
                        <h6 class="mt-4">Bulk Enrichment</h6>
                        <ol>
                            <li>Select multiple contacts</li>
                            <li>Click "Bulk Actions"</li>
                            <li>Choose "Enrich Selected"</li>
                            <li>Monitor progress</li>
                            <li>Review results</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>API Enrichment</h6>
                        <p>Use the API to enrich contacts programmatically:</p>
                        <pre class="bg-light p-2"><code>POST /api/v1/enrichment/enrich
{
  "contact_id": 123
}</code></pre>
                        
                        <h6 class="mt-3">Check Enrichment Status</h6>
                        <pre class="bg-light p-2"><code>GET /api/v1/enrichment/status</code></pre>
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
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Limitations & Costs</h5>
            </div>
            <div class="card-body">
                <h6>RocketReach Limits</h6>
                <ul>
                    <li>API calls are limited by your plan</li>
                    <li>Each enrichment uses 1 credit</li>
                    <li>Failed lookups don't consume credits</li>
                    <li>Rate limits apply</li>
                </ul>
                
                <h6 class="mt-3">Data Quality</h6>
                <ul>
                    <li>Not all contacts can be enriched</li>
                    <li>Data accuracy varies by source</li>
                    <li>Some information may be outdated</li>
                    <li>Privacy settings may limit data</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Enrichment Statistics</h5>
            </div>
            <div class="card-body">
                <h6>Track Your Usage</h6>
                <p>Monitor enrichment activity and success rates:</p>
                <ul>
                    <li>Total contacts enriched</li>
                    <li>Success rate percentage</li>
                    <li>API credits remaining</li>
                    <li>Recent enrichment activity</li>
                </ul>
                
                <h6 class="mt-3">View Statistics</h6>
                <pre class="bg-light p-2"><code>GET /api/v1/enrichment/stats</code></pre>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Troubleshooting</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Common Issues</h6>
                        <ul>
                            <li><strong>API Key Invalid:</strong> Check key in settings</li>
                            <li><strong>No Results Found:</strong> Contact not in database</li>
                            <li><strong>Rate Limited:</strong> Wait and try again</li>
                            <li><strong>Enrichment Disabled:</strong> Check settings</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Best Practices</h6>
                        <ul>
                            <li>Enrich contacts with basic info first</li>
                            <li>Use company name for better results</li>
                            <li>Check enrichment status regularly</li>
                            <li>Monitor API usage and costs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips for Better Results</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>Contact Information</h6>
                        <ul>
                            <li>Include full name</li>
                            <li>Add company name</li>
                            <li>Provide job title</li>
                            <li>Include location</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>Enrichment Strategy</h6>
                        <ul>
                            <li>Start with high-value contacts</li>
                            <li>Enrich in batches</li>
                            <li>Review results before saving</li>
                            <li>Update existing data carefully</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>Data Management</h6>
                        <ul>
                            <li>Keep original data</li>
                            <li>Track enrichment source</li>
                            <li>Regular data validation</li>
                            <li>Monitor data quality</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

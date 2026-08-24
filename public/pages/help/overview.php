<?php
/**
 * Help Overview Page
 * Sanctum CRM - Help system overview
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-house me-2"></i>Help Overview</h4>
    <span class="badge bg-primary">Sanctum CRM v1.0.0</span>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-rocket-takeoff me-2"></i>Getting Started</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">Welcome to Sanctum CRM — Sanctum&rsquo;s CRM for contacts, deals, pipelines, and integrations.</p>
                <ul class="list-unstyled">
                    <li><i class="bi bi-check text-success me-2"></i>Import contacts from CSV files</li>
                    <li><i class="bi bi-check text-success me-2"></i>Enrich contact data with RocketReach or Apollo</li>
                    <li><i class="bi bi-check text-success me-2"></i>Manage deals and opportunities</li>
                    <li><i class="bi bi-check text-success me-2"></i>Set up webhooks for integrations</li>
                    <li><i class="bi bi-check text-success me-2"></i>Use the REST API for automation</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Quick Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><strong>CSV Import:</strong> Use the name splitting feature for full names</li>
                    <li class="mb-2"><strong>API Keys:</strong> Generate them in Settings for external access</li>
                    <li class="mb-2"><strong>Webhooks:</strong> Set up real-time notifications</li>
                    <li class="mb-2"><strong>Enrichment:</strong> Pick RocketReach or Apollo and set that provider&rsquo;s API key in Settings</li>
                    <li class="mb-2"><strong>Security:</strong> Use HTTPS in production</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-book me-2"></i>Documentation Sections</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-code-slash fs-1 text-primary mb-3"></i>
                                <h6>API Documentation</h6>
                                <p class="text-muted small">Complete REST API reference with examples and authentication</p>
                                <a href="?page=help&help_page=api" class="btn btn-outline-primary btn-sm">View API Docs</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-link-45deg fs-1 text-success mb-3"></i>
                                <h6>Webhooks</h6>
                                <p class="text-muted small">Set up real-time notifications and integrations</p>
                                <a href="?page=help&help_page=webhooks" class="btn btn-outline-success btn-sm">View Webhooks</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-file-earmark-arrow-up fs-1 text-warning mb-3"></i>
                                <h6>CSV Import</h6>
                                <p class="text-muted small">Import contacts with field mapping and name splitting</p>
                                <a href="?page=help&help_page=import" class="btn btn-outline-warning btn-sm">View Import Guide</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-person-plus fs-1 text-info mb-3"></i>
                                <h6>Lead Enrichment</h6>
                                <p class="text-muted small">Enhance contact data via RocketReach or Apollo</p>
                                <a href="?page=help&help_page=enrichment" class="btn btn-outline-info btn-sm">View Enrichment</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-tools fs-1 text-danger mb-3"></i>
                                <h6>Troubleshooting</h6>
                                <p class="text-muted small">Common issues and solutions</p>
                                <a href="?page=help&help_page=troubleshooting" class="btn btn-outline-danger btn-sm">View Troubleshooting</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-info-circle fs-1 text-secondary mb-3"></i>
                                <h6>System Info</h6>
                                <p class="text-muted small">System status and configuration details</p>
                                <a href="?page=help&help_page=system" class="btn btn-outline-secondary btn-sm">View System Info</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

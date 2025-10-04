<?php
/**
 * Help Overview Page
 * Best Jobs in TA - Help system overview
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-home me-2"></i>Help Overview</h4>
    <span class="badge bg-primary">Best Jobs in TA v1.0.0</span>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Getting Started</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">Welcome to Best Jobs in TA! This CRM system is designed specifically for talent acquisition professionals.</p>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>Import contacts from CSV files</li>
                    <li><i class="fas fa-check text-success me-2"></i>Enrich contact data with RocketReach</li>
                    <li><i class="fas fa-check text-success me-2"></i>Manage deals and opportunities</li>
                    <li><i class="fas fa-check text-success me-2"></i>Set up webhooks for integrations</li>
                    <li><i class="fas fa-check text-success me-2"></i>Use the REST API for automation</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><strong>CSV Import:</strong> Use the name splitting feature for full names</li>
                    <li class="mb-2"><strong>API Keys:</strong> Generate them in Settings for external access</li>
                    <li class="mb-2"><strong>Webhooks:</strong> Set up real-time notifications</li>
                    <li class="mb-2"><strong>Enrichment:</strong> Configure RocketReach API key in Settings</li>
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
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>Documentation Sections</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-code fa-3x text-primary mb-3"></i>
                                <h6>API Documentation</h6>
                                <p class="text-muted small">Complete REST API reference with examples and authentication</p>
                                <a href="?page=help&help_page=api" class="btn btn-outline-primary btn-sm">View API Docs</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-link fa-3x text-success mb-3"></i>
                                <h6>Webhooks</h6>
                                <p class="text-muted small">Set up real-time notifications and integrations</p>
                                <a href="?page=help&help_page=webhooks" class="btn btn-outline-success btn-sm">View Webhooks</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-import fa-3x text-warning mb-3"></i>
                                <h6>CSV Import</h6>
                                <p class="text-muted small">Import contacts with field mapping and name splitting</p>
                                <a href="?page=help&help_page=import" class="btn btn-outline-warning btn-sm">View Import Guide</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-user-plus fa-3x text-info mb-3"></i>
                                <h6>Lead Enrichment</h6>
                                <p class="text-muted small">Enhance contact data with RocketReach integration</p>
                                <a href="?page=help&help_page=enrichment" class="btn btn-outline-info btn-sm">View Enrichment</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-tools fa-3x text-danger mb-3"></i>
                                <h6>Troubleshooting</h6>
                                <p class="text-muted small">Common issues and solutions</p>
                                <a href="?page=help&help_page=troubleshooting" class="btn btn-outline-danger btn-sm">View Troubleshooting</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-info-circle fa-3x text-secondary mb-3"></i>
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

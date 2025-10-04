<?php
/**
 * Troubleshooting Help Page
 * Best Jobs in TA - Troubleshooting guide
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-tools me-2"></i>Troubleshooting Guide</h4>
    <span class="badge bg-danger">Common Issues</span>
</div>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Having issues?</strong> Check this guide for common problems and solutions.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>CSV Import Issues</h5>
            </div>
            <div class="card-body">
                <h6>Import Not Working</h6>
                <ul>
                    <li><strong>Check file format:</strong> Must be CSV</li>
                    <li><strong>File size:</strong> Try smaller files</li>
                    <li><strong>Encoding:</strong> Use UTF-8</li>
                    <li><strong>Headers:</strong> Include column names</li>
                </ul>
                
                <h6 class="mt-3">"Invalid Response" Error</h6>
                <ul>
                    <li>Check if you're logged in</li>
                    <li>Clear browser cache</li>
                    <li>Try different browser</li>
                    <li>Check server logs</li>
                </ul>
                
                <h6 class="mt-3">Field Mapping Issues</h6>
                <ul>
                    <li>Map required fields (First Name, Last Name)</li>
                    <li>Check column names match</li>
                    <li>Use name splitting for full names</li>
                    <li>Validate email formats</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>API Issues</h5>
            </div>
            <div class="card-body">
                <h6>Authentication Errors</h6>
                <ul>
                    <li><strong>401 Unauthorized:</strong> Check API key</li>
                    <li><strong>Session expired:</strong> Re-login</li>
                    <li><strong>Invalid token:</strong> Generate new key</li>
                    <li><strong>CORS issues:</strong> Check headers</li>
                </ul>
                
                <h6 class="mt-3">Rate Limiting</h6>
                <ul>
                    <li><strong>429 Too Many Requests:</strong> Wait and retry</li>
                    <li>Check rate limit headers</li>
                    <li>Implement exponential backoff</li>
                    <li>Consider batch operations</li>
                </ul>
                
                <h6 class="mt-3">Server Errors</h6>
                <ul>
                    <li><strong>500 Internal Error:</strong> Check server logs</li>
                    <li><strong>502 Bad Gateway:</strong> Server overloaded</li>
                    <li><strong>503 Service Unavailable:</strong> Maintenance mode</li>
                    <li>Contact support if persistent</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i>Webhook Issues</h5>
            </div>
            <div class="card-body">
                <h6>Webhook Not Firing</h6>
                <ul>
                    <li>Check if webhook is active</li>
                    <li>Verify URL is accessible</li>
                    <li>Check event selection</li>
                    <li>Review webhook logs</li>
                </ul>
                
                <h6 class="mt-3">Failed Deliveries</h6>
                <ul>
                    <li>Check endpoint response time</li>
                    <li>Verify HTTP 200 response</li>
                    <li>Check server logs</li>
                    <li>Test with curl</li>
                </ul>
                
                <h6 class="mt-3">Signature Verification</h6>
                <ul>
                    <li>Verify signature header</li>
                    <li>Check secret key</li>
                    <li>Validate payload format</li>
                    <li>Test signature generation</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Enrichment Issues</h5>
            </div>
            <div class="card-body">
                <h6>Enrichment Not Working</h6>
                <ul>
                    <li>Check API key in settings</li>
                    <li>Verify enrichment is enabled</li>
                    <li>Check RocketReach status</li>
                    <li>Review API credits</li>
                </ul>
                
                <h6 class="mt-3">No Results Found</h6>
                <ul>
                    <li>Contact not in database</li>
                    <li>Insufficient contact info</li>
                    <li>Privacy settings blocking</li>
                    <li>Try different search terms</li>
                </ul>
                
                <h6 class="mt-3">Rate Limiting</h6>
                <ul>
                    <li>Check API usage limits</li>
                    <li>Wait before retrying</li>
                    <li>Consider upgrading plan</li>
                    <li>Batch enrichment requests</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-server me-2"></i>Server Configuration Issues</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Nginx Configuration</h6>
                        <p>If API calls return 404 errors, check your Nginx configuration:</p>
                        <pre class="bg-light p-2"><code># API routing: send all /api/v1/* requests to api/v1/index.php
location ~ ^/api/v1/ {
    try_files $uri $uri/ /api/v1/index.php?$query_string;
}</code></pre>
                        
                        <h6 class="mt-3">PHP Configuration</h6>
                        <ul>
                            <li>Check PHP version (8.1+)</li>
                            <li>Enable required extensions</li>
                            <li>Set proper file permissions</li>
                            <li>Check memory limits</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Database Issues</h6>
                        <ul>
                            <li>Check SQLite file permissions</li>
                            <li>Verify database integrity</li>
                            <li>Check disk space</li>
                            <li>Review error logs</li>
                        </ul>
                        
                        <h6 class="mt-3">File Permissions</h6>
                        <ul>
                            <li>Ensure web server can read files</li>
                            <li>Check upload directory permissions</li>
                            <li>Verify database file access</li>
                            <li>Check log file permissions</li>
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
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bug me-2"></i>Debugging Tools</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>Browser Developer Tools</h6>
                        <ul>
                            <li>Check Console for errors</li>
                            <li>Monitor Network requests</li>
                            <li>Inspect response headers</li>
                            <li>Check for CORS issues</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>Server Logs</h6>
                        <ul>
                            <li>Check PHP error logs</li>
                            <li>Review web server logs</li>
                            <li>Monitor database queries</li>
                            <li>Check API request logs</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>API Testing</h6>
                        <ul>
                            <li>Use curl for testing</li>
                            <li>Test with Postman</li>
                            <li>Check response codes</li>
                            <li>Validate JSON responses</li>
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
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-life-ring me-2"></i>Getting Help</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Before Contacting Support</h6>
                        <ul>
                            <li>Check this troubleshooting guide</li>
                            <li>Review server error logs</li>
                            <li>Test with different browsers</li>
                            <li>Try clearing cache/cookies</li>
                            <li>Check system requirements</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Information to Provide</h6>
                        <ul>
                            <li>Error messages (exact text)</li>
                            <li>Steps to reproduce</li>
                            <li>Browser and version</li>
                            <li>Server configuration</li>
                            <li>Relevant log entries</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

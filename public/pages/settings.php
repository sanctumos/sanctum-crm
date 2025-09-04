<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Settings Page
 * Best Jobs in TA - System Settings (Admin Only)
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();
$user = $auth->getUser();

// Check if user is admin
if (!$auth->isAdmin()) {
    header('Location: /index.php');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showDefaultCredentials = isset($_POST['show_default_credentials']) ? 1 : 0;
    
    // Update settings in database
    $db->update('settings', [
        'show_default_credentials' => $showDefaultCredentials,
        'updated_at' => getCurrentTimestamp()
    ], 'id = 1');
    
    $success = 'Settings updated successfully!';
}

// Get current settings
$settings = $db->fetchOne("SELECT * FROM settings WHERE id = 1");
if (!$settings) {
    // Create default settings if they don't exist
    $db->insert('settings', [
        'show_default_credentials' => 1,
        'created_at' => getCurrentTimestamp(),
        'updated_at' => getCurrentTimestamp()
    ]);
    $settings = ['show_default_credentials' => 1];
}

// Render the page
renderHeader('Settings');
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>System Settings</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Nginx Configuration Help Card -->
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-server me-2"></i>Nginx Configuration Help</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p class="mb-3">
                                    <strong>Having API issues?</strong> If you're getting 404 errors when trying to access API endpoints 
                                    (like when importing contacts), your Nginx configuration might be missing the API routing rules.
                                </p>
                                <h6 class="text-warning">Required Nginx Configuration:</h6>
                                <p class="mb-3">Your <code>/etc/nginx/sites-available/bestjobsinta.com</code> file should include this API routing block:</p>
                                <div class="bg-dark text-light p-3 rounded mb-3">
                                    <pre class="mb-0"><code># API routing: send all /api/v1/* requests to api/v1/index.php
location ~ ^/api/v1/ {
    try_files $uri $uri/ /api/v1/index.php?$query_string;
}</code></pre>
                                </div>
                                <h6 class="text-warning">Complete Configuration Example:</h6>
                                <div class="bg-light p-3 rounded mb-3">
                                    <pre class="mb-0 small"><code>server {
    server_name bestjobsinta.com www.bestjobsinta.com;
    root /var/www/bestjobsinta.com/html;
    index index.php index.html index.htm;

    # PHP handling for all .php files - MUST COME FIRST
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }

    # API routing: send all /api/v1/* requests to api/v1/index.php
    location ~ ^/api/v1/ {
        try_files $uri $uri/ /api/v1/index.php?$query_string;
    }

    # Main app routing: fallback to index.php for all other requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}</code></pre>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="bg-light p-4 rounded mb-3">
                                        <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                                        <h6>Server Configuration</h6>
                                        <p class="small text-muted mb-0">Fix API routing issues</p>
                                    </div>
                                    <div class="alert alert-info alert-sm">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>After updating:</strong><br>
                                        1. Test config: <code>nginx -t</code><br>
                                        2. Reload: <code>systemctl reload nginx</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> This configuration is required for LEMP servers (Linux, Nginx, MySQL, PHP). 
                            The API endpoints won't work properly without this routing rule.
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <h6 class="mb-3">Login Page Settings</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="show_default_credentials" 
                                   name="show_default_credentials" 
                                   <?php echo ($settings['show_default_credentials'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="show_default_credentials">
                                Show default login credentials on login page
                            </label>
                        </div>
                        <small class="text-muted">
                            When enabled, the login page will display "Default credentials: admin / admin123" 
                            to help with initial setup and testing.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Settings</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    These settings control system-wide behavior and are only accessible to administrators.
                </p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-shield-alt me-2"></i>Security Note</h6>
                    <p class="mb-0 small">
                        Consider disabling the default credentials display in production environments 
                        for enhanced security.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter();
?> 
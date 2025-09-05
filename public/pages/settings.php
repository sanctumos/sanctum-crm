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
 * Sanctum CRM - System Settings (Admin Only)
 */

$auth = new Auth();
$auth->requireAuth();
$user = $auth->getUser();

// Check if user is admin
if (!$auth->isAdmin()) {
    header('Location: /index.php');
    exit;
}

// Get configuration manager
$config = ConfigManager::getInstance();
$db = Database::getInstance();
$envDetector = new EnvironmentDetector();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $success = '';
    $error = '';
    
    try {
        switch ($action) {
            case 'company':
                $companyName = trim($_POST['company_name'] ?? '');
                $timezone = $_POST['timezone'] ?? 'UTC';
                
                if (empty($companyName)) {
                    throw new Exception('Company name is required');
                }
                
                $config->setCompanyInfo([
                    'company_name' => $companyName,
                    'timezone' => $timezone
                ]);
                
                $success = 'Company information updated successfully!';
                break;
                
            case 'application':
                $appName = trim($_POST['app_name'] ?? '');
                $appUrl = trim($_POST['app_url'] ?? '');
                $timezone = $_POST['timezone'] ?? 'UTC';
                
                if (empty($appName)) {
                    throw new Exception('Application name is required');
                }
                
                if (empty($appUrl)) {
                    throw new Exception('Application URL is required');
                }
                
                if (!validateUrl($appUrl)) {
                    throw new Exception('Invalid application URL');
                }
                
                $config->setCategory('application', [
                    'app_name' => $appName,
                    'app_url' => $appUrl,
                    'timezone' => $timezone
                ]);
                
                $success = 'Application settings updated successfully!';
                break;
                
            case 'security':
                $sessionLifetime = (int)($_POST['session_lifetime'] ?? 3600);
                $apiRateLimit = (int)($_POST['api_rate_limit'] ?? 1000);
                $passwordMinLength = (int)($_POST['password_min_length'] ?? 8);
                
                if ($sessionLifetime < 300) {
                    throw new Exception('Session lifetime must be at least 5 minutes (300 seconds)');
                }
                
                if ($apiRateLimit < 100) {
                    throw new Exception('API rate limit must be at least 100 requests per hour');
                }
                
                if ($passwordMinLength < 6) {
                    throw new Exception('Password minimum length must be at least 6 characters');
                }
                
                $config->setCategory('security', [
                    'session_lifetime' => $sessionLifetime,
                    'api_rate_limit' => $apiRateLimit,
                    'password_min_length' => $passwordMinLength
                ]);
                
                $success = 'Security settings updated successfully!';
                break;
                
            case 'database':
                $backupEnabled = isset($_POST['backup_enabled']) ? 1 : 0;
                
                $config->setCategory('database', [
                    'backup_enabled' => $backupEnabled
                ]);
                
                $success = 'Database settings updated successfully!';
                break;
                
            case 'legacy':
    $showDefaultCredentials = isset($_POST['show_default_credentials']) ? 1 : 0;
    
    $db->update('settings', [
        'show_default_credentials' => $showDefaultCredentials,
        'updated_at' => getCurrentTimestamp()
    ], 'id = 1');
    
                $success = 'Legacy settings updated successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$companyInfo = $config->getCompanyInfo();
$appConfig = $config->getCategory('application');
$securityConfig = $config->getCategory('security');
$databaseConfig = $config->getCategory('database');

// Get legacy settings
$legacySettings = $db->fetchOne("SELECT * FROM settings WHERE id = 1");
if (!$legacySettings) {
    $legacySettings = ['show_default_credentials' => 1];
}

// Get server information
$serverInfo = $envDetector->getEnvironmentInfo();
$serverInfo['database_path'] = DB_PATH;
$serverInfo['database_size'] = file_exists(DB_PATH) ? filesize(DB_PATH) : 0;

// Get environment recommendations
$envRecommendations = $envDetector->getRecommendedConfig();
$deploymentGuide = $envDetector->getDeploymentGuide();
$productionReady = $envDetector->isProductionReady();

// Render the page
$pageTitle = 'System Settings';
include __DIR__ . '/../includes/layout.php';
?>

<div class="container-fluid">
<div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">System Settings</h1>
            </div>
            
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="company-tab" data-bs-toggle="tab" data-bs-target="#company" type="button" role="tab">
                        <i class="bi bi-building"></i> Company
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="application-tab" data-bs-toggle="tab" data-bs-target="#application" type="button" role="tab">
                        <i class="bi bi-gear"></i> Application
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="bi bi-shield-lock"></i> Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="database-tab" data-bs-toggle="tab" data-bs-target="#database" type="button" role="tab">
                        <i class="bi bi-database"></i> Database
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="server-tab" data-bs-toggle="tab" data-bs-target="#server" type="button" role="tab">
                        <i class="bi bi-server"></i> Server Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="environment-tab" data-bs-toggle="tab" data-bs-target="#environment" type="button" role="tab">
                        <i class="bi bi-gear-wide-connected"></i> Environment
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabsContent">
                <!-- Company Settings -->
                <div class="tab-pane fade show active" id="company" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Company Information</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="company">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                                   value="<?= htmlspecialchars($companyInfo['company_name'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-select" id="timezone" name="timezone">
                                                <option value="UTC" <?= ($companyInfo['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                                <option value="America/New_York" <?= ($companyInfo['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                                <option value="America/Chicago" <?= ($companyInfo['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                                <option value="America/Denver" <?= ($companyInfo['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                                <option value="America/Los_Angeles" <?= ($companyInfo['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                                <option value="Europe/London" <?= ($companyInfo['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                                <option value="Europe/Paris" <?= ($companyInfo['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                                <option value="Asia/Tokyo" <?= ($companyInfo['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Company Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Application Settings -->
                <div class="tab-pane fade" id="application" role="tabpanel">
                    <div class="card mt-3">
                    <div class="card-body">
                            <h5 class="card-title">Application Configuration</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="application">
                                
                        <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="app_name" class="form-label">Application Name *</label>
                                            <input type="text" class="form-control" id="app_name" name="app_name" 
                                                   value="<?= htmlspecialchars($appConfig['app_name'] ?? 'Sanctum CRM') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="app_url" class="form-label">Application URL *</label>
                                            <input type="url" class="form-control" id="app_url" name="app_url" 
                                                   value="<?= htmlspecialchars($appConfig['app_url'] ?? 'http://localhost') ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="app_timezone" class="form-label">Application Timezone</label>
                                    <select class="form-select" id="app_timezone" name="timezone">
                                        <option value="UTC" <?= ($appConfig['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= ($appConfig['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                        <option value="America/Chicago" <?= ($appConfig['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                        <option value="America/Denver" <?= ($appConfig['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                        <option value="America/Los_Angeles" <?= ($appConfig['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                        <option value="Europe/London" <?= ($appConfig['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                        <option value="Europe/Paris" <?= ($appConfig['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                        <option value="Asia/Tokyo" <?= ($appConfig['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Application Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Security Configuration</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="security">
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="session_lifetime" class="form-label">Session Lifetime (seconds) *</label>
                                            <input type="number" class="form-control" id="session_lifetime" name="session_lifetime" 
                                                   value="<?= htmlspecialchars($securityConfig['session_lifetime'] ?? 3600) ?>" 
                                                   min="300" required>
                                            <div class="form-text">Minimum 300 seconds (5 minutes)</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="api_rate_limit" class="form-label">API Rate Limit (per hour) *</label>
                                            <input type="number" class="form-control" id="api_rate_limit" name="api_rate_limit" 
                                                   value="<?= htmlspecialchars($securityConfig['api_rate_limit'] ?? 1000) ?>" 
                                                   min="100" required>
                                            <div class="form-text">Minimum 100 requests per hour</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="password_min_length" class="form-label">Password Min Length *</label>
                                            <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                   value="<?= htmlspecialchars($securityConfig['password_min_length'] ?? 8) ?>" 
                                                   min="6" required>
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Security Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Database Settings -->
                <div class="tab-pane fade" id="database" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Database Configuration</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="database">
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="backup_enabled" name="backup_enabled" 
                                               <?= ($databaseConfig['backup_enabled'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="backup_enabled">
                                            Enable Database Backups
                            </label>
                        </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <strong>Database Path:</strong> <?= htmlspecialchars(DB_PATH) ?><br>
                                    <strong>Database Size:</strong> <?= number_format($serverInfo['database_size'] / 1024, 2) ?> KB
                    </div>
                    
                                <button type="submit" class="btn btn-primary">Update Database Settings</button>
                </form>
            </div>
        </div>
    </div>
    
                <!-- Server Information -->
                <div class="tab-pane fade" id="server" role="tabpanel">
                    <div class="card mt-3">
            <div class="card-body">
                            <h5 class="card-title">Server Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>PHP Version</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['php_version']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Server Software</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['server_software']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Operating System</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['operating_system']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Memory Limit</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['memory_limit']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Max Execution Time</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['max_execution_time']) ?> seconds</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Upload Max Filesize</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['upload_max_filesize']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Post Max Size</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['post_max_size']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Timezone</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['timezone']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Database Path</strong></td>
                                            <td><?= htmlspecialchars($serverInfo['database_path']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Database Size</strong></td>
                                            <td><?= number_format($serverInfo['database_size'] / 1024, 2) ?> KB</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Environment Detection -->
                <div class="tab-pane fade" id="environment" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Environment Analysis</h5>
                            
                            <!-- Production Readiness Status -->
                            <div class="alert alert-<?= $productionReady['ready'] ? 'success' : 'warning' ?>">
                                <h6><i class="bi bi-<?= $productionReady['ready'] ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                                    Production Readiness: <?= $productionReady['ready'] ? 'Ready' : 'Needs Attention' ?></h6>
                                <?php if (!$productionReady['ready']): ?>
                                    <p class="mb-0">Issues found: <?= implode(', ', $productionReady['issues']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Environment Recommendations -->
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>PHP Version</h6>
                                    <div class="alert alert-<?= $envRecommendations['php_version']['status'] === 'success' ? 'success' : 'warning' ?>">
                                        <strong>Current:</strong> <?= htmlspecialchars($envRecommendations['php_version']['current']) ?><br>
                                        <strong>Recommended:</strong> <?= htmlspecialchars($envRecommendations['php_version']['recommended']) ?><br>
                                        <small><?= htmlspecialchars($envRecommendations['php_version']['message']) ?></small>
                                    </div>
                                    
                                    <h6>Memory Limit</h6>
                                    <div class="alert alert-<?= $envRecommendations['memory_limit']['status'] === 'success' ? 'success' : 'warning' ?>">
                                        <strong>Current:</strong> <?= htmlspecialchars($envRecommendations['memory_limit']['current']) ?><br>
                                        <strong>Recommended:</strong> <?= htmlspecialchars($envRecommendations['memory_limit']['recommended']) ?><br>
                                        <small><?= htmlspecialchars($envRecommendations['memory_limit']['message']) ?></small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>PHP Extensions</h6>
                                    <div class="alert alert-<?= $envRecommendations['extensions']['status'] === 'success' ? 'success' : 'danger' ?>">
                                        <?php if (empty($envRecommendations['extensions']['missing'])): ?>
                                            <i class="bi bi-check-circle"></i> All required extensions are installed
                                        <?php else: ?>
                                            <strong>Missing Extensions:</strong><br>
                                            <?php foreach ($envRecommendations['extensions']['missing'] as $ext): ?>
                                                <span class="badge bg-danger me-1"><?= htmlspecialchars($ext) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h6>Security</h6>
                                    <div class="alert alert-<?= $envRecommendations['security']['status'] === 'success' ? 'success' : 'warning' ?>">
                                        <strong>HTTPS:</strong> <?= $envRecommendations['security']['https_enabled'] ? 'Enabled' : 'Disabled' ?><br>
                                        <?php foreach ($envRecommendations['security']['recommendations'] as $rec): ?>
                                            <small><?= htmlspecialchars($rec) ?></small><br>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Deployment Guide -->
                            <?php if (!empty($deploymentGuide)): ?>
                                <h6>Deployment Guide for <?= htmlspecialchars($deploymentGuide['platform']) ?></h6>
                                <div class="card">
                                    <div class="card-body">
                                        <?php if (isset($deploymentGuide['commands'])): ?>
                                            <h6>Commands:</h6>
                                            <ul class="list-unstyled">
                                                <?php foreach ($deploymentGuide['commands'] as $desc => $cmd): ?>
                                                    <li class="mb-2">
                                                        <strong><?= htmlspecialchars($desc) ?>:</strong><br>
                                                        <code class="bg-light p-2 d-block"><?= htmlspecialchars($cmd) ?></code>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($deploymentGuide['additional_commands'])): ?>
                                            <h6>Additional Commands for <?= htmlspecialchars($deploymentGuide['web_server']) ?>:</h6>
                                            <ul class="list-unstyled">
                                                <?php foreach ($deploymentGuide['additional_commands'] as $desc => $cmd): ?>
                                                    <li class="mb-2">
                                                        <strong><?= htmlspecialchars($desc) ?>:</strong><br>
                                                        <code class="bg-light p-2 d-block"><?= htmlspecialchars($cmd) ?></code>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($deploymentGuide['recommendations'])): ?>
                                            <h6>Recommendations:</h6>
                                            <ul>
                                                <?php foreach ($deploymentGuide['recommendations'] as $rec): ?>
                                                    <li><?= htmlspecialchars($rec) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>
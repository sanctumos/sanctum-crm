<?php
/**
 * System Info Help Page
 * Best Jobs in TA - System information
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-info-circle me-2"></i>System Information</h4>
    <span class="badge bg-secondary">Technical Details</span>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>System Information</strong> provides technical details about your CRM installation and configuration.
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-server me-2"></i>Server Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>PHP Version:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Software:</strong></td>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Operating System:</strong></td>
                        <td><?php echo PHP_OS; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Document Root:</strong></td>
                        <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Name:</strong></td>
                        <td><?php echo $_SERVER['SERVER_NAME'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Port:</strong></td>
                        <td><?php echo $_SERVER['SERVER_PORT'] ?? 'Unknown'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Information</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $db = Database::getInstance();
                    $dbPath = DB_PATH;
                    $dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;
                    $dbSizeFormatted = $dbSize > 0 ? number_format($dbSize / 1024, 2) . ' KB' : 'Unknown';
                    
                    // Get table information
                    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'");
                    $tableCount = count($tables);
                    
                    // Get contact count
                    $contactCount = $db->fetchOne("SELECT COUNT(*) as count FROM contacts")['count'] ?? 0;
                    
                    // Get deal count
                    $dealCount = $db->fetchOne("SELECT COUNT(*) as count FROM deals")['count'] ?? 0;
                } catch (Exception $e) {
                    $dbPath = 'Error';
                    $dbSizeFormatted = 'Error';
                    $tableCount = 'Error';
                    $contactCount = 'Error';
                    $dealCount = 'Error';
                }
                ?>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Database Type:</strong></td>
                        <td>SQLite3</td>
                    </tr>
                    <tr>
                        <td><strong>Database Path:</strong></td>
                        <td><?php echo $dbPath; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database Size:</strong></td>
                        <td><?php echo $dbSizeFormatted; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tables:</strong></td>
                        <td><?php echo $tableCount; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Contacts:</strong></td>
                        <td><?php echo $contactCount; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Deals:</strong></td>
                        <td><?php echo $dealCount; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>PHP Configuration</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Memory Limit:</strong></td>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Max Execution Time:</strong></td>
                        <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
                    </tr>
                    <tr>
                        <td><strong>Upload Max Filesize:</strong></td>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Post Max Size:</strong></td>
                        <td><?php echo ini_get('post_max_size'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Max Input Vars:</strong></td>
                        <td><?php echo ini_get('max_input_vars'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Session Save Path:</strong></td>
                        <td><?php echo session_save_path() ?: 'Default'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-puzzle-piece me-2"></i>PHP Extensions</h5>
            </div>
            <div class="card-body">
                <?php
                $requiredExtensions = [
                    'sqlite3' => 'SQLite3 Database',
                    'json' => 'JSON Processing',
                    'curl' => 'cURL HTTP Client',
                    'mbstring' => 'Multibyte String',
                    'openssl' => 'OpenSSL Encryption',
                    'session' => 'Session Management'
                ];
                ?>
                <table class="table table-sm">
                    <?php foreach ($requiredExtensions as $ext => $name): ?>
                    <tr>
                        <td><strong><?php echo $name; ?>:</strong></td>
                        <td>
                            <?php if (extension_loaded($ext)): ?>
                                <span class="badge bg-success">Loaded</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i>Integration Status</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $db = Database::getInstance();
                    $settings = $db->fetchOne("SELECT rocketreach_api_key FROM settings WHERE id = 1");
                    $apiKeySet = !empty($settings['rocketreach_api_key']);
                    $enrichmentEnabled = $apiKeySet; // Auto-detect based on API key presence
                } catch (Exception $e) {
                    $enrichmentEnabled = 0;
                    $apiKeySet = false;
                }
                ?>
                <table class="table table-sm">
                    <tr>
                        <td><strong>RocketReach Enrichment:</strong></td>
                        <td>
                            <?php if ($enrichmentEnabled && $apiKeySet): ?>
                                <span class="badge bg-success">Enabled</span>
                            <?php elseif ($apiKeySet): ?>
                                <span class="badge bg-warning">Configured but Disabled</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Not Configured</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>API Key Status:</strong></td>
                        <td>
                            <?php if ($apiKeySet): ?>
                                <span class="badge bg-success">Set</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Not Set</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Webhooks:</strong></td>
                        <td>
                            <?php
                            try {
                                $webhookCount = $db->fetchOne("SELECT COUNT(*) as count FROM webhooks")['count'] ?? 0;
                                if ($webhookCount > 0) {
                                    echo '<span class="badge bg-success">' . $webhookCount . ' Active</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">None</span>';
                                }
                            } catch (Exception $e) {
                                echo '<span class="badge bg-danger">Error</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Status</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>HTTPS:</strong></td>
                        <td>
                            <?php if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'): ?>
                                <span class="badge bg-success">Enabled</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Not Detected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Session Security:</strong></td>
                        <td>
                            <?php if (ini_get('session.cookie_secure') && ini_get('session.cookie_httponly')): ?>
                                <span class="badge bg-success">Secure</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Basic</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Debug Mode:</strong></td>
                        <td>
                            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                                <span class="badge bg-warning">Enabled</span>
                            <?php else: ?>
                                <span class="badge bg-success">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>System Requirements</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Minimum Requirements</h6>
                        <ul>
                            <li>PHP 8.1 or higher</li>
                            <li>SQLite3 extension</li>
                            <li>JSON extension</li>
                            <li>cURL extension</li>
                            <li>50MB disk space</li>
                            <li>128MB RAM</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Recommended Requirements</h6>
                        <ul>
                            <li>PHP 8.3 or higher</li>
                            <li>All required extensions</li>
                            <li>1GB disk space</li>
                            <li>512MB RAM</li>
                            <li>HTTPS enabled</li>
                            <li>Regular backups</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

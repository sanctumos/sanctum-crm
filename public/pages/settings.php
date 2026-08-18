<?php
/**
 * Settings Page
 * Sanctum CRM - System Settings (Admin Only)
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
require_once __DIR__ . '/../includes/LeadEnrichmentService.php';
require_once __DIR__ . '/../includes/MockLeadEnrichmentService.php';
require_once __DIR__ . '/../includes/EnrichmentCronService.php';
$enrichmentCronService = new EnrichmentCronService(new MockLeadEnrichmentService());

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'credentials';

    if ($action === 'save_skin') {
        require_once __DIR__ . '/../includes/skin-lab-env.php';
        $mine = crmSkinNormalizeSlug((string) ($_POST['skin_slug'] ?? ''));
        $db->update('users', [
            'skin_slug' => $mine,
            'updated_at' => getCurrentTimestamp(),
        ], 'id = ?', [(int) $user['id']]);
        $orgDefault = crmSkinNormalizeSlug((string) ($_POST['default_skin_slug'] ?? '')) ?? 'hey';
        $db->update('settings', [
            'default_skin_slug' => $orgDefault,
            'updated_at' => getCurrentTimestamp(),
        ], 'id = 1');
        $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [(int) $user['id']]) ?: $user;
        $success = 'Theme preferences saved.';
    } elseif ($action === 'save_branding') {
        require_once __DIR__ . '/../includes/ConfigManager.php';
        $appName = trim((string) ($_POST['app_name'] ?? ''));
        if ($appName === '') {
            $error = 'Application name is required.';
        } elseif (strlen($appName) > 100) {
            $error = 'Application name must be 100 characters or fewer.';
        } elseif (preg_match('/<[^>]*>/', $appName)) {
            $error = 'Application name cannot contain HTML.';
        } else {
            ConfigManager::getInstance()->set('application', 'app_name', $appName);
            $success = 'Branding saved. Header and login will show the new name.';
        }
    } elseif ($action === 'enrichment_cron') {
        $enrichmentCronService->updateConfig([
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'interval_minutes' => $_POST['interval_minutes'] ?? 60,
            'strategy' => $_POST['strategy'] ?? 'auto',
            'max_per_run' => $_POST['max_per_run'] ?? 10,
            'max_per_day' => $_POST['max_per_day'] ?? 400,
            'max_attempts_per_contact' => $_POST['max_attempts_per_contact'] ?? 3,
            'retry_failed' => isset($_POST['retry_failed']) ? 1 : 0,
            'eligible_enrichment_statuses' => $_POST['eligible_enrichment_statuses'] ?? [],
            'contact_types' => $_POST['contact_types'] ?? [],
            'contact_statuses' => $_POST['contact_statuses'] ?? [],
            'sources' => $_POST['sources'] ?? [],
            'assigned_to' => $_POST['assigned_to'] ?? '',
            'min_contact_age_days' => $_POST['min_contact_age_days'] ?? 0,
        ]);
        $success = 'Enrichment automation settings updated successfully!';
    } else {
        $showDefaultCredentials = isset($_POST['show_default_credentials']) ? 1 : 0;
        $rocketreachApiKey = $_POST['rocketreach_api_key'] ?? '';
        
        // Update settings in database
        $db->update('settings', [
            'show_default_credentials' => $showDefaultCredentials,
            'rocketreach_api_key' => $rocketreachApiKey,
            'updated_at' => getCurrentTimestamp()
        ], 'id = 1');
        
        $success = 'Settings updated successfully!';
    }
}

// Get current settings
$settings = $db->fetchOne("SELECT * FROM settings WHERE id = 1");
if (!$settings) {
    // Create default settings if they don't exist
    $db->insert('settings', [
        'show_default_credentials' => 1,
        'rocketreach_api_key' => '',
        'created_at' => getCurrentTimestamp(),
        'updated_at' => getCurrentTimestamp()
    ]);
    $settings = ['show_default_credentials' => 1, 'rocketreach_api_key' => ''];
}
$enrichmentCronConfig = $enrichmentCronService->getConfig();
$lastEnrichmentCronRun = $enrichmentCronService->getLastRun();
$availableSources = array_column(
    $db->fetchAll("SELECT DISTINCT source FROM contacts WHERE source IS NOT NULL AND source != '' ORDER BY source"),
    'source'
);
$activeUsers = $db->fetchAll("SELECT id, username, first_name, last_name FROM users WHERE is_active = 1 ORDER BY username");

require_once __DIR__ . '/../includes/skin-lab-env.php';
$userSkin = crmSkinUserOverrideSlug(is_array($user) ? $user : null) ?? '';
$defaultSkin = crmSkinMasterSlug();

require_once __DIR__ . '/../includes/ConfigManager.php';
$appNameSetting = getAppName();

// Render the page
renderHeader('Settings');
renderPageHeader('Settings', 'System configuration');
?>

<div class="row">
    <div class="col-lg-8">
        <div class="surface mb-3">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-type me-2"></i>Branding</h5>
            </div>
            <div class="surface__body">
                <?php if (isset($error) && (($_POST['action'] ?? '') === 'save_branding')): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($success) && (($_POST['action'] ?? '') === 'save_branding')): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <p class="text-muted small mb-3">
                    Shown in the top navbar and on the login page. Default is <code>Sanctum CRM</code>.
                </p>
                <form method="POST" action="" class="row g-3" autocomplete="off">
                    <input type="hidden" name="action" value="save_branding">
                    <div class="col-md-8">
                        <label class="form-label" for="app_name">Application name</label>
                        <input type="text" class="form-control" id="app_name" name="app_name"
                               value="<?php echo htmlspecialchars($appNameSetting); ?>"
                               maxlength="100" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save branding
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="surface mb-3">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-palette me-2"></i>Theme</h5>
            </div>
            <div class="surface__body">
                <?php if (isset($success) && (($_POST['action'] ?? '') === 'save_skin' || isset($_GET['skin']))): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <p class="text-muted small">
                    Skins match Sanctum Tasks and Docket:
                    <code>hey</code>, <code>ledger</code>, <code>brutalist</code>, <code>obsidian</code>.
                    Preview any page with <code>?preview_skin=obsidian</code> (does not save).
                </p>
                <form method="POST" action="" class="row g-3">
                    <input type="hidden" name="action" value="save_skin">
                    <div class="col-md-6">
                        <label class="form-label" for="skin_slug">Your skin</label>
                        <select class="form-select" id="skin_slug" name="skin_slug">
                            <option value="" <?php echo $userSkin === '' ? 'selected' : ''; ?>>Use site default</option>
                            <?php foreach (crmSkinAvailableSlugs() as $slug): ?>
                                <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo $userSkin === $slug ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slug); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="default_skin_slug">Site default</label>
                        <select class="form-select" id="default_skin_slug" name="default_skin_slug">
                            <?php foreach (crmSkinAvailableSlugs() as $slug): ?>
                                <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo $defaultSkin === $slug ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slug); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save theme
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="surface mb-3">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>System Settings</h5>
            </div>
            <div class="surface__body">
                <?php if (isset($success) && ($_POST['action'] ?? 'credentials') !== 'save_skin' && ($_POST['action'] ?? '') !== 'enrichment_cron' && ($_POST['action'] ?? '') !== 'save_branding'): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php elseif (isset($success) && ($_POST['action'] ?? '') === 'enrichment_cron'): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Need help with server configuration?</strong> 
                    Check out the <a href="/help" class="text-decoration-none">Help & Configuration</a> page for Nginx setup and troubleshooting guides.
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="credentials">
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
                    
                    <div class="mb-4">
                        <h6 class="mb-3">RocketReach Lead Enrichment</h6>
                        <div class="mb-3">
                            <label for="rocketreach_api_key" class="form-label">RocketReach API Key</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="rocketreach_api_key" 
                                       name="rocketreach_api_key" 
                                       value="<?php echo htmlspecialchars($settings['rocketreach_api_key'] ?? ''); ?>"
                                       placeholder="Enter your RocketReach API key">
                                <button class="btn btn-outline-secondary" type="button" id="toggleRocketReachKey">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                Lead enrichment will be automatically enabled when you add your RocketReach API key. 
                                Get your API key from <a href="https://rocketreach.co" target="_blank">RocketReach.co</a>.
                            </small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Settings
                    </button>
                </form>

                <hr class="my-5">

                <form method="POST" action="">
                    <input type="hidden" name="action" value="enrichment_cron">
                    <div class="mb-4">
                        <h6 class="mb-3">Enrichment Automation</h6>
                        <p class="text-muted mb-3">Schedule RocketReach enrichment from cron while enforcing caps and filters before spending lookup quota.</p>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enrichment_enabled" name="enabled" <?php echo $enrichmentCronConfig['enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enrichment_enabled">Enable scheduled enrichment</label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="interval_minutes" class="form-label">Run Interval (minutes)</label>
                            <input type="number" class="form-control" id="interval_minutes" name="interval_minutes" min="1" value="<?php echo htmlspecialchars($enrichmentCronConfig['interval_minutes']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="strategy" class="form-label">Strategy</label>
                            <select class="form-select" id="strategy" name="strategy">
                                <?php foreach (['auto' => 'Auto', 'email' => 'Email', 'linkedin' => 'LinkedIn', 'name_company' => 'Name + Company'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $enrichmentCronConfig['strategy'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="max_per_run" class="form-label">Max Per Run</label>
                            <input type="number" class="form-control" id="max_per_run" name="max_per_run" min="1" value="<?php echo htmlspecialchars($enrichmentCronConfig['max_per_run']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="max_per_day" class="form-label">Max Per Day</label>
                            <input type="number" class="form-control" id="max_per_day" name="max_per_day" min="1" value="<?php echo htmlspecialchars($enrichmentCronConfig['max_per_day']); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="max_attempts_per_contact" class="form-label">Max Attempts Per Contact</label>
                            <input type="number" class="form-control" id="max_attempts_per_contact" name="max_attempts_per_contact" min="1" value="<?php echo htmlspecialchars($enrichmentCronConfig['max_attempts_per_contact']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="min_contact_age_days" class="form-label">Minimum Contact Age (days)</label>
                            <input type="number" class="form-control" id="min_contact_age_days" name="min_contact_age_days" min="0" value="<?php echo htmlspecialchars($enrichmentCronConfig['min_contact_age_days']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="assigned_to" class="form-label">Assigned User</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">All users</option>
                                <?php foreach ($activeUsers as $activeUser): ?>
                                    <?php $userLabel = trim(($activeUser['first_name'] ?? '') . ' ' . ($activeUser['last_name'] ?? '')) ?: $activeUser['username']; ?>
                                    <option value="<?php echo (int) $activeUser['id']; ?>" <?php echo (string) $enrichmentCronConfig['assigned_to'] === (string) $activeUser['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($userLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="retry_failed" name="retry_failed" <?php echo $enrichmentCronConfig['retry_failed'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="retry_failed">Retry failed contacts</label>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Eligible Enrichment Statuses</label>
                            <?php foreach (['empty' => 'Not Enriched', 'pending' => 'Pending', 'failed' => 'Failed'] as $value => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="eligible_enrichment_statuses[]" id="status_<?php echo $value; ?>" value="<?php echo $value; ?>" <?php echo in_array($value, $enrichmentCronConfig['eligible_enrichment_statuses'], true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status_<?php echo $value; ?>"><?php echo $label; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Types</label>
                            <?php foreach (['lead' => 'Leads', 'customer' => 'Customers'] as $value => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="contact_types[]" id="type_<?php echo $value; ?>" value="<?php echo $value; ?>" <?php echo in_array($value, $enrichmentCronConfig['contact_types'], true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="type_<?php echo $value; ?>"><?php echo $label; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Statuses</label>
                            <?php foreach (['new' => 'New', 'qualified' => 'Qualified', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="contact_statuses[]" id="contact_status_<?php echo $value; ?>" value="<?php echo $value; ?>" <?php echo in_array($value, $enrichmentCronConfig['contact_statuses'], true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="contact_status_<?php echo $value; ?>"><?php echo $label; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="sources" class="form-label">Sources</label>
                        <select class="form-select" id="sources" name="sources[]" multiple size="5">
                            <?php foreach ($availableSources as $source): ?>
                                <option value="<?php echo htmlspecialchars($source); ?>" <?php echo in_array($source, $enrichmentCronConfig['sources'], true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $source))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave blank to allow all sources.</small>
                    </div>

                    <div class="alert alert-secondary">
                        <strong>Cron command:</strong><br>
                        <code>php /var/www/localhost/html/cron/enrichment.php</code>
                        <?php if ($lastEnrichmentCronRun): ?>
                            <hr>
                            <strong>Last run:</strong>
                            <?php echo htmlspecialchars($lastEnrichmentCronRun['status']); ?>
                            at <?php echo htmlspecialchars($lastEnrichmentCronRun['started_at']); ?>.
                            Processed <?php echo (int) $lastEnrichmentCronRun['processed_count']; ?>,
                            enriched <?php echo (int) $lastEnrichmentCronRun['enriched_count']; ?>,
                            failed <?php echo (int) $lastEnrichmentCronRun['failed_count']; ?>.
                            <?php if (!empty($lastEnrichmentCronRun['skipped_reason'])): ?>
                                Skipped reason: <?php echo htmlspecialchars($lastEnrichmentCronRun['skipped_reason']); ?>.
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-secondary">
                        <strong>Merge candidates cron</strong> (proposes only — never auto-merges):<br>
                        <code>php /var/www/localhost/html/cron/merge_candidates.php</code><br>
                        <small class="text-muted">Review and accept under <a href="/index.php?page=merges">Merge</a>. Mass accept is high confidence (≥0.85) only.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Enrichment Automation
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="surface mb-3">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>About Settings</h5>
            </div>
            <div class="surface__body">
                <p class="text-muted">
                    These settings control system-wide behavior and are only accessible to administrators.
                </p>
                
                <div class="alert alert-info">
                    <h6><i class="bi bi-shield-check me-2"></i>Security Note</h6>
                    <p class="mb-0 small">
                        Consider disabling the default credentials display in production environments 
                        for enhanced security.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle RocketReach API key visibility
document.getElementById('toggleRocketReachKey').addEventListener('click', function() {
    const apiKeyInput = document.getElementById('rocketreach_api_key');
    const toggleBtn = this;
    const icon = toggleBtn.querySelector('i');
    
    if (apiKeyInput.type === 'password') {
        apiKeyInput.type = 'text';
        icon.className = 'bi bi-eye-slash';
        toggleBtn.title = 'Hide API Key';
    } else {
        apiKeyInput.type = 'password';
        icon.className = 'bi bi-eye';
        toggleBtn.title = 'Show API Key';
    }
});

</script>

<?php
renderFooter();
?> 
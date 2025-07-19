<?php
/**
 * Settings Page
 * FreeOpsDAO CRM - System Settings (Admin Only)
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
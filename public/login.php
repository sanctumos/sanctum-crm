<?php
/**
 * Login Page
 * Sanctum CRM - Authentication
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/ConfigManager.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/skin-lab-env.php';

// Ensure session name is set before any session is started
if (session_status() === PHP_SESSION_NONE) {
    session_name('crm_session');
}

// Initialize authentication
$auth = new Auth();

// Get database instance to check settings
$db = Database::getInstance();
$settings = $db->fetchOne("SELECT * FROM settings WHERE id = 1");
$showDefaultCredentials = $settings ? ($settings['show_default_credentials'] ?? 1) : 1;

// Check if already logged in
if ($auth->isAuthenticated()) {
    if ($auth->mustChangePassword()) {
        header('Location: /index.php?page=profile&must_change=1');
        exit;
    }
    header('Location: /index.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        if ($auth->login($username, $password)) {
            if ($auth->mustChangePassword()) {
                header('Location: /index.php?page=profile&must_change=1');
                exit;
            }
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
$skin = crmSkinPreviewSlug() ?? crmSkinMasterSlug();
$appName = getAppName();
?>
<!DOCTYPE html>
<html lang="en" data-skin-comp="<?php echo htmlspecialchars($skin); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &middot; <?php echo htmlspecialchars($appName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/crm.css?v=14" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(crmSkinStylesheetHref($skin)); ?>" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="crm-login-shell">
        <div class="crm-login-card">
            <div class="crm-login-brand">
                <span class="crm-glyph-tile crm-glyph-tile--accent crm-glyph-tile--lg"><i class="bi bi-people"></i></span>
                <h1 class="crm-login-brand__title"><?php echo htmlspecialchars($appName); ?></h1>
                <p class="crm-login-brand__subtitle">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php
            $loginFormAction = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/login.php', ENT_QUOTES, 'UTF-8');
            ?>
            <form method="post" action="<?php echo $loginFormAction; ?>" autocomplete="on">
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               autocomplete="username"
                               autocapitalize="none"
                               spellcheck="false"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                    </div>
                </div>

                <button type="submit" class="btn crm-btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <?php if ($showDefaultCredentials): ?>
                <p class="crm-login-fineprint mt-4 mb-0">
                    Default credentials: admin / admin123
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
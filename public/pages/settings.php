<?php
/**
 * User Settings Page
 * FreeOpsDAO CRM
 */

define('CRM_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->requireAuth();
$user = $auth->getUser();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .settings-card { max-width: 500px; margin: 40px auto; }
        .avatar { width: 64px; height: 64px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #667eea; }
    </style>
</head>
<body>
    <div class="container">
        <div class="settings-card card shadow-sm mt-5">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-cog"></i> User Settings</h4>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar mb-2"><i class="fas fa-user"></i></div>
                    <div><strong><?php echo htmlspecialchars($user['username']); ?></strong> <span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($user['role']); ?></span></div>
                </div>
                <form id="settingsForm">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                    </div>
                    <div id="settingsAlert" class="alert d-none" role="alert"></div>
                    <div class="d-flex justify-content-between">
                        <a href="/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('settingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const alertBox = document.getElementById('settingsAlert');
        alertBox.classList.add('d-none');
        alertBox.classList.remove('alert-success', 'alert-danger');
        const data = {
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            email: document.getElementById('email').value,
        };
        const password = document.getElementById('password').value;
        if (password) data.password = password;
        try {
            const response = await fetch('/api/v1/settings.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (response.ok && result.success) {
                alertBox.textContent = 'Settings updated successfully!';
                alertBox.classList.add('alert', 'alert-success');
                alertBox.classList.remove('d-none');
                document.getElementById('password').value = '';
            } else {
                alertBox.textContent = result.error || 'Failed to update settings.';
                alertBox.classList.add('alert', 'alert-danger');
                alertBox.classList.remove('d-none');
            }
        } catch (err) {
            alertBox.textContent = 'Network error.';
            alertBox.classList.add('alert', 'alert-danger');
            alertBox.classList.remove('d-none');
        }
    });
    </script>
</body>
</html> 
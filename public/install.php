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
 * Installation Wizard
 * First boot setup for Sanctum CRM
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/ConfigManager.php';
require_once __DIR__ . '/includes/InstallationManager.php';

$installationManager = new InstallationManager();
$config = ConfigManager::getInstance();

// Get current step
$currentStep = $installationManager->getCurrentStep();
$step = $_GET['step'] ?? $currentStep;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'environment':
            $validation = $installationManager->validateEnvironment();
            if ($validation['valid']) {
                $installationManager->completeStep('environment');
                header('Location: /install.php?step=database');
                exit;
            } else {
                $errors = $validation['errors'];
            }
            break;
            
        case 'database':
            if ($installationManager->initializeDatabase()) {
                $installationManager->setupDefaultConfig();
                $installationManager->completeStep('database');
                header('Location: /install.php?step=company');
                exit;
            } else {
                $errors = ['Database initialization failed'];
            }
            break;
            
        case 'company':
            $companyName = trim($_POST['company_name'] ?? '');
            $timezone = $_POST['timezone'] ?? 'UTC';
            
            $validation = $installationManager->validateStep('company', ['company_name' => $companyName]);
            if ($validation['valid']) {
                $installationManager->setupCompany($companyName, $timezone);
                $installationManager->completeStep('company');
                header('Location: /install.php?step=admin');
                exit;
            } else {
                $errors = $validation['errors'];
            }
            break;
            
        case 'admin':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $firstName = trim($_POST['first_name'] ?? 'Admin');
            $lastName = trim($_POST['last_name'] ?? 'User');
            
            $validation = $installationManager->validateStep('admin', [
                'username' => $username,
                'email' => $email,
                'password' => $password
            ]);
            
            if ($validation['valid']) {
                if ($installationManager->createAdminUser($username, $email, $password, $firstName, $lastName)) {
                    $installationManager->completeStep('admin');
                    $installationManager->completeInstallation();
                    header('Location: /install.php?step=complete');
                    exit;
                } else {
                    $errors = ['Failed to create admin user'];
                }
            } else {
                $errors = $validation['errors'];
            }
            break;
    }
}

// Get installation status
$status = $installationManager->getInstallationStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanctum CRM - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .installation-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 60%;
            width: 80%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step.completed:not(:last-child)::after {
            background: #28a745;
        }
        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dee2e6;
            color: #6c757d;
            font-weight: bold;
            z-index: 2;
            position: relative;
        }
        .step.completed .step-icon {
            background: #28a745;
            color: white;
        }
        .step.current .step-icon {
            background: #007bff;
            color: white;
        }
        .step-label {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            text-align: center;
        }
        .installation-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body class="bg-light">
    <div class="installation-container">
        <div class="text-center mb-4">
            <h1 class="h2">Sanctum CRM Installation</h1>
            <p class="text-muted">Welcome to Sanctum CRM. Let's get you set up.</p>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <?php foreach ($status as $stepKey => $stepInfo): ?>
                <div class="step <?= $stepInfo['completed'] ? 'completed' : ($stepInfo['current'] ? 'current' : '') ?>">
                    <div class="step-icon">
                        <?php if ($stepInfo['completed']): ?>
                            <i class="bi bi-check"></i>
                        <?php else: ?>
                            <?= array_search($stepKey, array_keys($status)) + 1 ?>
                        <?php endif; ?>
                    </div>
                    <div class="step-label"><?= htmlspecialchars($stepInfo['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Installation Content -->
        <div class="card installation-card">
            <div class="card-body p-4">
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6>Please fix the following errors:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'environment'): ?>
                    <h4>Environment Check</h4>
                    <p>Let's verify your server meets the requirements.</p>
                    
                    <?php
                    $envCheck = $installationManager->validateEnvironment();
                    ?>
                    
                    <?php if ($envCheck['valid']): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> All requirements are met!
                        </div>
                        
                        <?php if (!empty($envCheck['warnings'])): ?>
                            <div class="alert alert-warning">
                                <h6>Warnings:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($envCheck['warnings'] as $warning): ?>
                                        <li><?= htmlspecialchars($warning) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="environment">
                            <button type="submit" class="btn btn-primary">Continue</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h6>Requirements not met:</h6>
                            <ul class="mb-0">
                                <?php foreach ($envCheck['errors'] as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <p>Please fix the above issues and refresh the page.</p>
                    <?php endif; ?>
                    
                <?php elseif ($step === 'database'): ?>
                    <h4>Database Setup</h4>
                    <p>Setting up the database and initial configuration.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="database">
                        <button type="submit" class="btn btn-primary">Initialize Database</button>
                    </form>
                    
                <?php elseif ($step === 'company'): ?>
                    <h4>Company Information</h4>
                    <p>Tell us about your company.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="company">
                        
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name *</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="UTC" <?= ($_POST['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                <option value="America/New_York" <?= ($_POST['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                <option value="America/Chicago" <?= ($_POST['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                <option value="America/Denver" <?= ($_POST['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?= ($_POST['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                <option value="Europe/London" <?= ($_POST['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                <option value="Europe/Paris" <?= ($_POST['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                <option value="Asia/Tokyo" <?= ($_POST['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Continue</button>
                    </form>
                    
                <?php elseif ($step === 'admin'): ?>
                    <h4>Admin Account</h4>
                    <p>Create your administrator account.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="admin">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? 'Admin') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? 'User') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
                            <div class="form-text">Minimum <?= PASSWORD_MIN_LENGTH ?> characters</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Complete Installation</button>
                    </form>
                    
                <?php elseif ($step === 'complete'): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4>Installation Complete!</h4>
                        <p class="text-muted">Sanctum CRM has been successfully installed and configured.</p>
                        <a href="/" class="btn btn-primary btn-lg">Go to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

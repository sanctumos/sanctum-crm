<?php
/**
 * Sanctum CRM - Main Entry Point
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

// Define CRM loaded constant
define('CRM_LOADED', true);

// Handle API requests first
if (preg_match('/^\/api\/v1\//', $_SERVER['REQUEST_URI'])) {
    require __DIR__ . '/api/v1/index.php';
    exit;
}

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/ConfigManager.php';
require_once __DIR__ . '/includes/InstallationManager.php';
require_once __DIR__ . '/includes/auth.php';

// Check if first boot is needed
$installationManager = new InstallationManager();
if ($installationManager->isFirstBoot()) {
    // Redirect to installation wizard
    header('Location: /install.php');
    exit;
}

// Initialize authentication
$auth = new Auth();
require_once __DIR__ . '/includes/layout.php';

// Check if user is authenticated
if (!$auth->isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// Validate page
$allowedPages = ['dashboard', 'contacts', 'deals', 'users', 'reports', 'webhooks', 'settings', 'view_contact', 'edit_contact', 'import_contacts'];
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Include the page
$pageFile = __DIR__ . "/pages/$page.php";
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    // Fallback to dashboard if page doesn't exist
    include __DIR__ . '/pages/dashboard.php';
} 
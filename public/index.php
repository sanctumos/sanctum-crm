<?php
/**
 * Main Entry Point
 * FreeOpsDAO CRM - Web Interface
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

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
$allowedPages = ['dashboard', 'contacts', 'deals', 'users', 'reports', 'webhooks', 'settings'];
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
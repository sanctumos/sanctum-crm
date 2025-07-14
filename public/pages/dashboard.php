<?php
/**
 * Dashboard Page
 * FreeOpsDAO CRM - Main Dashboard
 */

define('CRM_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Dashboard');
renderDashboardStats();
renderRecentActivity();
renderFooter();
?> 
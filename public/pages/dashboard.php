<?php
/**
 * Dashboard Page
 * Sanctum CRM - Main Dashboard
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Dashboard');
renderPageHeader('Dashboard', 'Pipeline snapshot and recent activity');
renderDashboardStats();
renderRecentActivity();
renderFooter();
?> 
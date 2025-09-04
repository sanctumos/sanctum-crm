<?php
/**
 * Dashboard Page
 * Best Jobs in TA - Main Dashboard
 */

// Remove any require_once for auth.php and layout.php

$auth = new Auth();
$auth->requireAuth();

// Render the page using the template system
renderHeader('Dashboard');
renderDashboardStats();
renderRecentActivity();
renderFooter();
?> 
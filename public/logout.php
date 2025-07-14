<?php
/**
 * Logout Page
 * FreeOpsDAO CRM - Logout Handler
 */

// Define CRM loaded constant
define('CRM_LOADED', true);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

// Initialize authentication
$auth = new Auth();

// Logout the user
$auth->logout();

// Redirect to login page
header('Location: /login.php');
exit; 
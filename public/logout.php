<?php
/**
 * Logout Handler
 * Best Jobs in TA
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
if (!defined('CRM_TESTING')) header('Location: /login.php');
exit; 
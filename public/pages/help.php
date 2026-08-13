<?php
/**
 * Help Page
 * Sanctum CRM - Help and documentation
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

// Check if user is admin
if (!$auth->isAdmin()) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Render the page using the template system
renderHeader('Help & Documentation');
renderPageHeader('Help & Documentation', 'Guides and configuration notes');
?>

<div class="crm-shell-help">
    <?php
    // Include the help navigation module
    include __DIR__ . '/../includes/help_nav.php';
    ?>
</div>

<?php
renderFooter();
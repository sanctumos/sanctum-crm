<?php
/**
 * Help Page
 * Best Jobs in TA - Help and documentation
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
?>

<div class="container mt-4">
    <?php
    // Include the help navigation module
    include __DIR__ . '/../includes/help_nav.php';
    ?>
</div>

<?php
renderFooter();
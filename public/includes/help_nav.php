<?php
/**
 * Help Navigation Module
 * Decision Science Corp CRM - Help system navigation
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

// Get current help page
$currentHelpPage = $_GET['help_page'] ?? 'overview';
?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Help Center</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="?page=help&help_page=overview" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'overview' ? 'active' : ''; ?>">
                        <i class="bi bi-house me-2"></i>Overview
                    </a>
                    <a href="?page=help&help_page=api" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'api' ? 'active' : ''; ?>">
                        <i class="bi bi-code-slash me-2"></i>API Documentation
                    </a>
                    <a href="?page=help&help_page=webhooks" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'webhooks' ? 'active' : ''; ?>">
                        <i class="bi bi-link-45deg me-2"></i>Webhooks
                    </a>
                    <a href="?page=help&help_page=import" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'import' ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-arrow-up me-2"></i>CSV Import
                    </a>
                    <a href="?page=help&help_page=enrichment" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'enrichment' ? 'active' : ''; ?>">
                        <i class="bi bi-person-plus me-2"></i>Lead Enrichment
                    </a>
                    <a href="?page=help&help_page=troubleshooting" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'troubleshooting' ? 'active' : ''; ?>">
                        <i class="bi bi-tools me-2"></i>Troubleshooting
                    </a>
                    <a href="?page=help&help_page=system" 
                       class="list-group-item list-group-item-action <?php echo $currentHelpPage === 'system' ? 'active' : ''; ?>">
                        <i class="bi bi-info-circle me-2"></i>System Info
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card">
            <div class="card-body">
                <?php
                // Include the appropriate help page content
                switch ($currentHelpPage) {
                    case 'api':
                        include __DIR__ . '/../pages/help/api.php';
                        break;
                    case 'webhooks':
                        include __DIR__ . '/../pages/help/webhooks.php';
                        break;
                    case 'import':
                        include __DIR__ . '/../pages/help/import.php';
                        break;
                    case 'enrichment':
                        include __DIR__ . '/../pages/help/enrichment.php';
                        break;
                    case 'troubleshooting':
                        include __DIR__ . '/../pages/help/troubleshooting.php';
                        break;
                    case 'system':
                        include __DIR__ . '/../pages/help/system.php';
                        break;
                    case 'overview':
                    default:
                        include __DIR__ . '/../pages/help/overview.php';
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</div>

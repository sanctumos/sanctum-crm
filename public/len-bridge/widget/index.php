<?php
/**
 * Widget Documentation Page
 * Main widget documentation and embed instructions
 */

// Include common functions
require_once '../config/settings.php';
require_once '../includes/utils.php';

// Set page title and content
$page_title = "Sanctum Chat Widget - Embed Instructions";
$page_content = file_get_contents('templates/widget.html');

// Output the page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/widget.css">
</head>
<body>
    <?php echo $page_content; ?>
</body>
</html>

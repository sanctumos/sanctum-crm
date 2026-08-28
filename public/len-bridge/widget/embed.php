<?php
/**
 * Widget Embed Endpoint
 * Iframe-compatible widget endpoint
 */

require_once '../config/settings.php';
require_once '../includes/utils.php';

// Get configuration from query parameters
$api_key = $_GET['apiKey'] ?? '';
$position = $_GET['position'] ?? 'bottom-right';
$theme = $_GET['theme'] ?? 'light';
$title = $_GET['title'] ?? 'Chat with us';
$primary_color = $_GET['primaryColor'] ?? '#007bff';

// Validate API key
if (empty($api_key)) {
    http_response_code(400);
    echo '<html><body><h1>Error: API key required</h1></body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="assets/css/widget.css">
    <style>
        body { margin: 0; padding: 0; }
        .sanctum-chat-widget { position: relative !important; }
    </style>
</head>
<body>
    <script>
        // Auto-initialize widget with URL parameters
        window.addEventListener('load', function() {
            if (typeof SanctumChat !== 'undefined') {
                SanctumChat.init({
                    apiKey: '<?php echo htmlspecialchars($api_key); ?>',
                    position: '<?php echo htmlspecialchars($position); ?>',
                    theme: '<?php echo htmlspecialchars($theme); ?>',
                    title: '<?php echo htmlspecialchars($title); ?>',
                    primaryColor: '<?php echo htmlspecialchars($primary_color); ?>',
                    autoOpen: true
                });
            }
        });
    </script>
    <script src="assets/js/chat-widget.js"></script>
</body>
</html>

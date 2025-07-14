<?php
// Stub error page for test and fallback use
if (!headers_sent()) {
    http_response_code(500);
}
echo "<h1>Error</h1><p>An error occurred.</p>"; 
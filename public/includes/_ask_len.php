<?php
/**
 * Ask Len Vernal — floating webchat bubble (logged-in CRM users).
 */
declare(strict_types=1);

if (!defined('CRM_LOADED')) {
    return;
}

require_once dirname(__DIR__) . '/len-bridge/includes/connection_config.php';

global $auth;

if (!len_bridge_is_ui_enabled()) {
    return;
}
if (!isset($auth) || !$auth instanceof Auth || !$auth->isAuthenticated()) {
    return;
}

require_once dirname(__DIR__) . '/len-bridge/includes/page_context.php';

$user = $auth->getUser();
$conn = len_bridge_get_connection_config();
$lenTitle = trim((string) ($conn['agent_label'] ?? '')) ?: 'Len Vernal';
$lenColor = '#6b4ce6';
$chatterUsername = trim((string) ($user['username'] ?? ''));
$pageContext = len_bridge_enrich_page_context(
    len_bridge_detect_page_context(),
    is_array($user) ? $user : null
);
?>
<link rel="stylesheet" href="/len-bridge/widget/assets/css/widget.css?v=1">
<script src="/len-bridge/widget/assets/js/markdown-lite.js?v=1"></script>
<script src="/len-bridge/widget/assets/js/composer-paste.js?v=1"></script>
<script src="/len-bridge/widget/assets/js/chat-widget.js?v=1"></script>
<script>
window.CRM_ASK_LEN_PAGE = <?= json_encode($pageContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
document.addEventListener('DOMContentLoaded', function () {
    if (typeof SanctumChat === 'undefined') {
        return;
    }
    try {
        SanctumChat.init({
            apiBase: '/len-bridge/api/v1/',
            useSessionAuth: true,
            apiKey: 'session',
            position: 'bottom-right',
            theme: 'light',
            title: <?= json_encode($lenTitle, JSON_UNESCAPED_UNICODE) ?>,
            chatterUsername: <?= json_encode($chatterUsername, JSON_UNESCAPED_UNICODE) ?>,
            primaryColor: <?= json_encode($lenColor) ?>,
            greeting: 'Hey — Len. I read relationships with you: who matters, what changed, what we owe them.',
            persistSession: true,
            historyLimit: 6,
            autoOpen: false,
            pageContext: window.CRM_ASK_LEN_PAGE || null
        });
    } catch (e) {
        console.warn('Ask Len widget failed to init', e);
    }
});
</script>

<?php
/**
 * Len bridge integration — separate webchat DB + chatter session lifecycle.
 */

require_once __DIR__ . '/../bootstrap.php';

class LenBridgeIntegrationTest
{
    private string $bridgeDb;

    public function runAllTests(): void
    {
        echo "Running LenBridgeIntegrationTest...\n";
        $this->bridgeDb = sys_get_temp_dir() . '/len_bridge_test_' . getmypid() . '.db';
        if (file_exists($this->bridgeDb)) {
            unlink($this->bridgeDb);
        }
        putenv('CRM_LEN_BRIDGE_DB_PATH=' . $this->bridgeDb);

        require_once __DIR__ . '/../../public/len-bridge/config/database.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/utils.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/composer_message.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/chatter.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/page_context.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/rate_limit_config.php';

        init_database();
        $this->testComposerPayload();
        $this->testUserSessionLifecycle();
        $this->testUidValidation();
        $this->testRateLimitDefaults();
        $this->testFormatContextBlock();

        if (file_exists($this->bridgeDb)) {
            unlink($this->bridgeDb);
        }
        echo "LenBridgeIntegrationTest: all passed\n";
    }

    private function testComposerPayload(): void
    {
        echo "  Testing composer payload normalization... ";
        $norm = len_bridge_normalize_composer_payload([
            'message' => 'Hello Len',
            'caption' => '  note  ',
        ]);
        if ($norm['message'] !== 'Hello Len') {
            throw new Exception('message mismatch');
        }
        if ($norm['caption'] !== 'note') {
            throw new Exception('caption trim failed');
        }
        echo "PASS\n";
    }

    private function testUserSessionLifecycle(): void
    {
        echo "  Testing ensure user session + history... ";
        $userId = TestUtils::createTestUser();
        $ensured = len_bridge_ensure_user_session($userId);
        if (($ensured['session_id'] ?? '') !== 'session_crm_' . $userId) {
            throw new Exception('bad session id');
        }
        $pdo = get_db_connection();
        $ins = $pdo->prepare('INSERT INTO web_chat_messages (session_id, message) VALUES (?, ?)');
        $ins->execute([$ensured['session_id'], 'hi']);
        $hist = len_bridge_fetch_user_recent_history($userId, 5);
        if (count($hist['items'] ?? []) < 1) {
            throw new Exception('expected history item');
        }
        echo "PASS\n";
    }

    private function testUidValidation(): void
    {
        echo "  Testing web chat uid helpers... ";
        $uid = generate_web_chat_uid();
        if (!validate_uid($uid)) {
            throw new Exception('generated uid invalid');
        }
        if (validate_uid('not-a-uid')) {
            throw new Exception('expected invalid uid');
        }
        echo "PASS\n";
    }

    private function testRateLimitDefaults(): void
    {
        echo "  Testing rate limit config defaults... ";
        $cfg = len_bridge_get_rate_limit_config();
        if (empty($cfg['user_endpoints']['/api/messages'])) {
            throw new Exception('missing messages limit');
        }
        echo "PASS\n";
    }

    private function testFormatContextBlock(): void
    {
        echo "  Testing chat context block format... ";
        $block = len_bridge_format_chat_context_block([
            'admin_origin' => 'https://crm.test',
            'screen_label' => 'Contact dossier #9',
            'contact_id' => 9,
            'username' => 'admin',
        ]);
        if (!str_contains($block, 'Contact dossier #9') || !str_contains($block, 'admin')) {
            throw new Exception('context block missing fields');
        }
        echo "PASS\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    (new LenBridgeIntegrationTest())->runAllTests();
}

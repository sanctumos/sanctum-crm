<?php
/**
 * Len bridge poll-key authentication.
 */

require_once __DIR__ . '/../bootstrap.php';

class LenBridgeAuthTest
{
    public function runAllTests(): void
    {
        echo "Running LenBridgeAuthTest...\n";
        $pollFile = sys_get_temp_dir() . '/len_bridge_poll_' . getmypid() . '.txt';
        file_put_contents($pollFile, 'test-poll-key-' . bin2hex(random_bytes(8)));
        putenv('CRM_LEN_BRIDGE_POLL_API_KEY=');
        putenv('CRM_LEN_BRIDGE_DB_PATH=' . sys_get_temp_dir() . '/len_bridge_auth_' . getmypid() . '.db');

        require_once __DIR__ . '/../../public/len-bridge/config/settings.php';
        require_once __DIR__ . '/../../public/len-bridge/config/database.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/api_response.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/rate_limit_config.php';
        require_once __DIR__ . '/../../public/len-bridge/includes/auth.php';

        init_database();
        $this->testPlaceholderRejected($pollFile);
        $this->testValidPollKey($pollFile);

        @unlink($pollFile);
        echo "LenBridgeAuthTest: all passed\n";
    }

    private function testPlaceholderRejected(string $pollFile): void
    {
        echo "  Testing placeholder poll key rejected... ";
        putenv('CRM_LEN_BRIDGE_POLL_API_KEY=CHANGE_ME');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer CHANGE_ME';
        if (len_bridge_is_placeholder_secret('CHANGE_ME') !== true) {
            throw new Exception('placeholder helper failed');
        }
        echo "PASS\n";
    }

    private function testValidPollKey(string $pollFile): void
    {
        echo "  Testing valid poll key file... ";
        $key = trim((string) file_get_contents($pollFile));
        putenv('CRM_LEN_BRIDGE_POLL_API_KEY=' . $key);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $key;
        if (!is_authenticated()) {
            throw new Exception('expected authenticated with valid poll key');
        }
        echo "PASS\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    (new LenBridgeAuthTest())->runAllTests();
}

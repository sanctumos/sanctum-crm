<?php
/**
 * Len bridge session + API key resolution for SMCP.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/len-bridge/includes/crm_session.php';

class LenBridgeSessionTest
{
    public function runAllTests(): void
    {
        echo "Running LenBridgeSessionTest...\n";
        $this->testAutoMintApiKey();
        $this->testExistingApiKeyPreserved();
        echo "LenBridgeSessionTest: all passed\n";
    }

    private function testAutoMintApiKey(): void
    {
        echo "  Testing auto-mint users.api_key... ";
        $userId = TestUtils::createTestUser(['api_key' => '']);
        $db = TestUtils::getTestDatabase();
        $db->query('UPDATE users SET api_key = NULL WHERE id = ?', [$userId]);
        $key = crm_len_bridge_user_api_key($userId);
        if ($key === null || strlen($key) < 16) {
            throw new Exception('expected minted api key');
        }
        $row = $db->fetchOne('SELECT api_key FROM users WHERE id = ?', [$userId]);
        if (($row['api_key'] ?? '') !== $key) {
            throw new Exception('key not persisted on user row');
        }
        echo "PASS\n";
    }

    private function testExistingApiKeyPreserved(): void
    {
        echo "  Testing existing api_key preserved... ";
        $existing = bin2hex(random_bytes(16));
        $userId = TestUtils::createTestUser();
        $db = TestUtils::getTestDatabase();
        $db->query('UPDATE users SET api_key = ? WHERE id = ?', [$existing, $userId]);
        $key = crm_len_bridge_user_api_key($userId);
        if ($key !== $existing) {
            throw new Exception('expected existing key unchanged');
        }
        echo "PASS\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    (new LenBridgeSessionTest())->runAllTests();
}

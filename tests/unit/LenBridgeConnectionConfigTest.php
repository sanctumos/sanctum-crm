<?php
/**
 * Len bridge connection config tests.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/len-bridge/includes/connection_config.php';

class LenBridgeConnectionConfigTest
{
    public function runAllTests(): void
    {
        echo "Running LenBridgeConnectionConfigTest...\n";
        len_bridge_clear_connection_config_cache();
        $this->testDefaults();
        $this->testSaveAndLoad();
        len_bridge_clear_connection_config_cache();
        echo "LenBridgeConnectionConfigTest: all passed\n";
    }

    private function testDefaults(): void
    {
        echo "  Testing connection defaults... ";
        $d = len_bridge_connection_defaults();
        if (($d['agent_label'] ?? '') !== 'Len Vernal') {
            throw new Exception('expected Len Vernal label');
        }
        if (!array_key_exists('enabled', $d)) {
            throw new Exception('missing enabled key');
        }
        echo "PASS\n";
    }

    private function testSaveAndLoad(): void
    {
        echo "  Testing save and load connection config... ";
        $userId = TestUtils::createTestUser(['role' => 'admin']);
        $result = len_bridge_save_connection_config([
            'enabled' => true,
            'sanctum_url' => 'https://sanctum.example.test',
            'agent_id' => 'agent-test-len',
            'agent_label' => 'Len Test',
        ], $userId);
        if (empty($result['success'])) {
            throw new Exception($result['error'] ?? 'save failed');
        }
        len_bridge_clear_connection_config_cache();
        $loaded = len_bridge_get_connection_config();
        if (!$loaded['enabled']) {
            throw new Exception('enabled not persisted');
        }
        if ($loaded['sanctum_url'] !== 'https://sanctum.example.test') {
            throw new Exception('sanctum_url mismatch');
        }
        if ($loaded['agent_id'] !== 'agent-test-len') {
            throw new Exception('agent_id mismatch');
        }
        if ($loaded['agent_label'] !== 'Len Test') {
            throw new Exception('agent_label mismatch');
        }
        echo "PASS\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    (new LenBridgeConnectionConfigTest())->runAllTests();
}

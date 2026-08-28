<?php
/**
 * Len bridge page context detection.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/len-bridge/includes/page_context.php';

class LenBridgePageContextTest
{
    public function runAllTests(): void
    {
        echo "Running LenBridgePageContextTest...\n";
        $this->testContactDossier();
        $this->testDealsList();
        $this->testEnrichUser();
        echo "LenBridgePageContextTest: all passed\n";
    }

    private function withQuery(array $get, callable $fn): void
    {
        $saved = $_GET;
        $savedUri = $_SERVER['REQUEST_URI'] ?? '';
        $_GET = $get;
        $_SERVER['REQUEST_URI'] = '/index.php?' . http_build_query($get);
        try {
            $fn();
        } finally {
            $_GET = $saved;
            $_SERVER['REQUEST_URI'] = $savedUri;
        }
    }

    private function testContactDossier(): void
    {
        echo "  Testing view_contact page context... ";
        $this->withQuery(['page' => 'view_contact', 'id' => '42'], function () {
            $ctx = len_bridge_detect_page_context();
            if (($ctx['surface'] ?? '') !== 'contact' || (int) ($ctx['contact_id'] ?? 0) !== 42) {
                throw new Exception('contact context wrong: ' . json_encode($ctx));
            }
        });
        echo "PASS\n";
    }

    private function testDealsList(): void
    {
        echo "  Testing deals page context... ";
        $this->withQuery(['page' => 'deals', 'stage' => 'prospecting'], function () {
            $ctx = len_bridge_detect_page_context();
            if (($ctx['surface'] ?? '') !== 'deals' || ($ctx['stage'] ?? '') !== 'prospecting') {
                throw new Exception('deals context wrong');
            }
        });
        echo "PASS\n";
    }

    private function testEnrichUser(): void
    {
        echo "  Testing enrich adds product and label... ";
        $user = ['id' => 7, 'username' => 'rizzn', 'role' => 'admin'];
        $raw = ['surface' => 'contact', 'contact_id' => 5];
        $out = len_bridge_enrich_page_context($raw, $user);
        if (($out['product'] ?? '') !== 'sanctum_crm') {
            throw new Exception('product not sanctum_crm');
        }
        if (($out['username'] ?? '') !== 'rizzn') {
            throw new Exception('username missing');
        }
        if (strpos((string) ($out['screen_label'] ?? ''), '#5') === false) {
            throw new Exception('screen_label missing contact id');
        }
        echo "PASS\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    (new LenBridgePageContextTest())->runAllTests();
}

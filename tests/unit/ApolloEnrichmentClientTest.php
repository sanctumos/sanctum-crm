<?php
/**
 * Unit checks for Apollo client helpers (no live network).
 */
define('CRM_LOADED', true);

require_once __DIR__ . '/../../public/includes/enrichment/EnrichmentProviders.php';
require_once __DIR__ . '/../../public/includes/enrichment/ApolloEnrichmentClient.php';

$failed = 0;
function assert_true($cond, $msg) {
    global $failed;
    if (!$cond) {
        echo "FAIL: $msg\n";
        $failed++;
    } else {
        echo "OK: $msg\n";
    }
}

assert_true(EnrichmentProviders::normalize('Apollo') === 'apollo', 'normalize apollo');
assert_true(EnrichmentProviders::normalize('x') === 'rocketreach', 'normalize default rr');
assert_true(EnrichmentProviders::label('apollo') === 'Apollo', 'label apollo');

$ex = new ApolloApiException('This API key is not authorized', 403, ['error_code' => 'API_INACCESSIBLE']);
assert_true($ex->isPlanOrScopeBlocked(), 'scope blocked detection');
$ex2 = new ApolloApiException('rate', 429, []);
assert_true($ex2->isRateLimited(), '429 detection');

// base URL rewrite via reflection of constructor behavior
$c = new ApolloEnrichmentClient('test-key-abcdefghijklmnopqrstuvwxyz', 'https://api.apollo.io/v1');
$ref = new ReflectionClass($c);
$prop = $ref->getProperty('baseUrl');
$prop->setAccessible(true);
assert_true($prop->getValue($c) === 'https://api.apollo.io/api/v1', 'rewrite /v1 to /api/v1');

if ($failed > 0) {
    fwrite(STDERR, "$failed assertion(s) failed\n");
    exit(1);
}
echo "All Apollo unit checks passed\n";

<?php
/**
 * Regression tests for contacts pagination with active filters (GitHub #13).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/includes/ContactsListPagination.php';

class ContactsListPaginationTest
{
    public function runAllTests(): void
    {
        echo "Running ContactsListPaginationTest...\n";
        $this->testPageNumWinsWhenFiltersPresent();
        $this->testNoPageNumDefaultsToOne();
        $this->testPageNumFloorsAtOne();
        $this->testCapPageWhenBeyondTotal();
        $this->testPaginationParamsPreserveNullSentinelFilters();
        $this->testPaginationParamsPreserveTagFilter();
        echo "ContactsListPaginationTest: all passed\n";
    }

    public function testPageNumWinsWhenFiltersPresent(): void
    {
        echo "  Testing page_num respected with filters (#13)... ";
        $page = ContactsListPagination::resolvePage([
            'page' => 'contacts',
            'type' => 'lead',
            'enrichment_status' => 'pending',
            'page_num' => '3',
        ]);
        if ($page !== 3) {
            throw new Exception("expected page 3, got {$page}");
        }
        echo "PASS\n";
    }

    public function testNoPageNumDefaultsToOne(): void
    {
        echo "  Testing missing page_num defaults to 1... ";
        $page = ContactsListPagination::resolvePage([
            'page' => 'contacts',
            'type' => 'lead',
        ]);
        if ($page !== 1) {
            throw new Exception("expected page 1, got {$page}");
        }
        echo "PASS\n";
    }

    public function testPageNumFloorsAtOne(): void
    {
        echo "  Testing page_num floors at 1... ";
        $page = ContactsListPagination::resolvePage(['page_num' => '0']);
        if ($page !== 1) {
            throw new Exception("expected page 1, got {$page}");
        }
        echo "PASS\n";
    }

    public function testCapPageWhenBeyondTotal(): void
    {
        echo "  Testing capPage when page_num exceeds total pages... ";
        $capped = ContactsListPagination::capPage(9, 3);
        if ($capped !== 3) {
            throw new Exception("expected capped page 3, got {$capped}");
        }
        echo "PASS\n";
    }

    public function testPaginationParamsPreserveNullSentinelFilters(): void
    {
        echo "  Testing pagination params keep null sentinel filters... ";
        $params = ContactsListPagination::buildPaginationParams(
            'cards',
            'lead',
            '',
            'null',
            'null',
            '',
            null
        );
        if (($params['enrichment_status'] ?? '') !== 'null' || ($params['source'] ?? '') !== 'null') {
            throw new Exception('null sentinel filters not preserved');
        }
        echo "PASS\n";
    }

    public function testPaginationParamsPreserveTagFilter(): void
    {
        echo "  Testing pagination params keep tag filter... ";
        $params = ContactsListPagination::buildPaginationParams(
            'list',
            '',
            'new',
            'pending',
            '',
            'vip-client',
            50
        );
        if (($params['tag'] ?? '') !== 'vip-client' || ($params['per_page'] ?? 0) !== 50) {
            throw new Exception('tag or per_page not preserved');
        }
        echo "PASS\n";
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    (new ContactsListPaginationTest())->runAllTests();
}

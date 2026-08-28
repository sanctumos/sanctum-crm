<?php
/**
 * Contacts list pagination helpers (page resolution + filter-preserving query params).
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ContactsListPagination
{
    public static function filtersApplied(
        string $typeFilter,
        string $statusFilter,
        string $enrichmentFilter,
        string $sourceFilter,
        string $tagFilter
    ): bool {
        return $typeFilter !== ''
            || $statusFilter !== ''
            || $enrichmentFilter !== ''
            || $sourceFilter !== ''
            || $tagFilter !== '';
    }

    /**
     * Resolve current page from query string.
     * When page_num is present it always wins — even if filters are active (#13).
     */
    public static function resolvePage(array $query): int
    {
        if (array_key_exists('page_num', $query)) {
            return max(1, (int) $query['page_num']);
        }

        return 1;
    }

    public static function capPage(int $page, int $totalPages): int
    {
        if ($totalPages < 1) {
            return 1;
        }

        return min(max(1, $page), $totalPages);
    }

    /**
     * Build GET params for pagination links (preserves active filters + view mode).
     */
    public static function buildPaginationParams(
        string $viewMode,
        string $typeFilter,
        string $statusFilter,
        string $enrichmentFilter,
        string $sourceFilter,
        string $tagFilter,
        ?int $perPageFromQuery
    ): array {
        $params = ['page' => 'contacts'];

        if ($viewMode !== '') {
            $params['view'] = $viewMode;
        }
        if ($typeFilter !== '') {
            $params['type'] = $typeFilter;
        }
        if ($statusFilter !== '') {
            $params['status'] = $statusFilter;
        }
        if ($enrichmentFilter === 'null') {
            $params['enrichment_status'] = 'null';
        } elseif ($enrichmentFilter !== '') {
            $params['enrichment_status'] = $enrichmentFilter;
        }
        if ($sourceFilter === 'null') {
            $params['source'] = 'null';
        } elseif ($sourceFilter !== '') {
            $params['source'] = $sourceFilter;
        }
        if ($tagFilter !== '') {
            $params['tag'] = $tagFilter;
        }
        if ($perPageFromQuery !== null && $perPageFromQuery > 0) {
            $params['per_page'] = $perPageFromQuery;
        }

        return $params;
    }
}

<?php

declare(strict_types=1);

namespace RocketReach\SDK\Models;

/**
 * Search Response model
 * 
 * Represents the response from the People Search API
 */
class SearchResponse
{
    private array $profiles = [];
    private array $pagination = [];

    public function __construct(array $data)
    {
        $this->profiles = $data['profiles'] ?? [];
        $this->pagination = $data['pagination'] ?? [];
    }

    /**
     * Get profiles array
     *
     * @return array
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Get pagination info
     *
     * @return array
     */
    public function getPagination(): array
    {
        return $this->pagination;
    }

    /**
     * Get total number of results
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->pagination['total'] ?? 0;
    }

    /**
     * Get current page
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->pagination['start'] ?? 1;
    }

    /**
     * Get next page number
     *
     * @return int|null
     */
    public function getNextPage(): ?int
    {
        return $this->pagination['next'] ?? null;
    }

    /**
     * Check if there are more pages
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->getNextPage() !== null;
    }

    /**
     * Get number of profiles in current page
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->profiles);
    }

    /**
     * Check if response is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->profiles);
    }
}

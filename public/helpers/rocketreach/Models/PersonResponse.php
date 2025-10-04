<?php

declare(strict_types=1);

namespace RocketReach\SDK\Models;

/**
 * Person Response model
 * 
 * Represents the response from the Person Lookup API
 */
class PersonResponse
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get person ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->data['id'] ?? null;
    }

    /**
     * Get person name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->data['name'] ?? null;
    }

    /**
     * Get current title
     *
     * @return string|null
     */
    public function getCurrentTitle(): ?string
    {
        return $this->data['current_title'] ?? null;
    }

    /**
     * Get current employer
     *
     * @return string|null
     */
    public function getCurrentEmployer(): ?string
    {
        return $this->data['current_employer'] ?? null;
    }

    /**
     * Get LinkedIn URL
     *
     * @return string|null
     */
    public function getLinkedinUrl(): ?string
    {
        return $this->data['linkedin_url'] ?? null;
    }

    /**
     * Get location
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->data['location'] ?? null;
    }

    /**
     * Get emails array
     *
     * @return array
     */
    public function getEmails(): array
    {
        return $this->data['emails'] ?? [];
    }

    /**
     * Get phones array
     *
     * @return array
     */
    public function getPhones(): array
    {
        return $this->data['phones'] ?? [];
    }

    /**
     * Get status
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    /**
     * Check if lookup is complete
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->getStatus() === 'complete';
    }

    /**
     * Check if lookup is still searching
     *
     * @return bool
     */
    public function isSearching(): bool
    {
        return $this->getStatus() === 'searching';
    }

    /**
     * Get all data as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}

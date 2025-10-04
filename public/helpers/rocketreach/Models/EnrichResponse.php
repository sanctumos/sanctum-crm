<?php

declare(strict_types=1);

namespace RocketReach\SDK\Models;

/**
 * Enrich Response model
 * 
 * Represents the response from the Person Enrich API
 * Contains both person and company data
 */
class EnrichResponse
{
    private array $person = [];
    private array $company = [];

    public function __construct(array $data)
    {
        // Handle "not found" case
        if (isset($data['not_found']) && $data['not_found']) {
            $this->person = ['not_found' => true, 'message' => $data['message'] ?? 'Person not found'];
            $this->company = [];
            return;
        }
        
        // The API returns data directly, not nested under 'person' and 'company'
        $this->person = $data;
        $this->company = [
            'id' => $data['current_employer_id'] ?? null,
            'name' => $data['current_employer'] ?? null,
            'domain' => $data['current_employer_domain'] ?? null,
            'website' => $data['current_employer_website'] ?? null,
            'linkedin_url' => $data['current_employer_linkedin_url'] ?? null
        ];
    }

    /**
     * Get person data
     *
     * @return array
     */
    public function getPerson(): array
    {
        return $this->person;
    }

    /**
     * Get company data
     *
     * @return array
     */
    public function getCompany(): array
    {
        return $this->company;
    }

    /**
     * Get person ID
     *
     * @return int|null
     */
    public function getPersonId(): ?int
    {
        return $this->person['id'] ?? null;
    }

    /**
     * Get person name
     *
     * @return string|null
     */
    public function getPersonName(): ?string
    {
        return $this->person['name'] ?? null;
    }

    /**
     * Get person emails
     *
     * @return array
     */
    public function getPersonEmails(): array
    {
        return $this->person['emails'] ?? [];
    }

    /**
     * Get person phones
     *
     * @return array
     */
    public function getPersonPhones(): array
    {
        return $this->person['phones'] ?? [];
    }

    /**
     * Get company ID
     *
     * @return int|null
     */
    public function getCompanyId(): ?int
    {
        return $this->company['id'] ?? null;
    }

    /**
     * Get company name
     *
     * @return string|null
     */
    public function getCompanyName(): ?string
    {
        return $this->company['name'] ?? null;
    }

    /**
     * Get company domain
     *
     * @return string|null
     */
    public function getCompanyDomain(): ?string
    {
        return $this->company['domain'] ?? null;
    }

    /**
     * Get company industry
     *
     * @return string|null
     */
    public function getCompanyIndustry(): ?string
    {
        return $this->company['industry'] ?? null;
    }

    /**
     * Get company employee count
     *
     * @return string|null
     */
    public function getCompanyEmployeeCount(): ?string
    {
        return $this->company['employee_count'] ?? null;
    }

    /**
     * Get company location
     *
     * @return string|null
     */
    public function getCompanyLocation(): ?string
    {
        return $this->company['location'] ?? null;
    }

    /**
     * Get all data as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'person' => $this->person,
            'company' => $this->company
        ];
    }
}

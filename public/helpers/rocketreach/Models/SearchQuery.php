<?php

declare(strict_types=1);

namespace RocketReach\SDK\Models;

/**
 * Search Query model
 * 
 * Represents the search parameters for the People Search API
 */
class SearchQuery
{
    private array $name = [];
    private array $currentTitle = [];
    private array $currentEmployer = [];
    private array $currentEmployerDomain = [];
    private array $location = [];
    private array $linkedinUrl = [];
    private array $contactMethod = [];
    private array $industry = [];
    private array $companySize = [];
    private array $companyFunding = [];
    private array $companyRevenue = [];
    private array $seniority = [];
    private array $skills = [];
    private array $education = [];
    private ?string $orderBy = null;
    private ?int $page = null;
    private ?int $pageSize = null;

    public function setName(array $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setCurrentTitle(array $currentTitle): self
    {
        $this->currentTitle = $currentTitle;
        return $this;
    }

    public function setCurrentEmployer(array $currentEmployer): self
    {
        $this->currentEmployer = $currentEmployer;
        return $this;
    }

    public function setCurrentEmployerDomain(array $currentEmployerDomain): self
    {
        $this->currentEmployerDomain = $currentEmployerDomain;
        return $this;
    }

    public function setLocation(array $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function setLinkedinUrl(array $linkedinUrl): self
    {
        $this->linkedinUrl = $linkedinUrl;
        return $this;
    }

    public function setContactMethod(array $contactMethod): self
    {
        $this->contactMethod = $contactMethod;
        return $this;
    }

    public function setIndustry(array $industry): self
    {
        $this->industry = $industry;
        return $this;
    }

    public function setCompanySize(array $companySize): self
    {
        $this->companySize = $companySize;
        return $this;
    }

    public function setCompanyFunding(array $companyFunding): self
    {
        $this->companyFunding = $companyFunding;
        return $this;
    }

    public function setCompanyRevenue(array $companyRevenue): self
    {
        $this->companyRevenue = $companyRevenue;
        return $this;
    }

    public function setSeniority(array $seniority): self
    {
        $this->seniority = $seniority;
        return $this;
    }

    public function setSkills(array $skills): self
    {
        $this->skills = $skills;
        return $this;
    }

    public function setEducation(array $education): self
    {
        $this->education = $education;
        return $this;
    }

    public function setOrderBy(string $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function setPageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * Convert to array for API request
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        if (!empty($this->name)) {
            $data['name'] = $this->name;
        }
        if (!empty($this->currentTitle)) {
            $data['current_title'] = $this->currentTitle;
        }
        if (!empty($this->currentEmployer)) {
            $data['current_employer'] = $this->currentEmployer;
        }
        if (!empty($this->currentEmployerDomain)) {
            $data['current_employer_domain'] = $this->currentEmployerDomain;
        }
        if (!empty($this->location)) {
            $data['location'] = $this->location;
        }
        if (!empty($this->linkedinUrl)) {
            $data['linkedin_url'] = $this->linkedinUrl;
        }
        if (!empty($this->contactMethod)) {
            $data['contact_method'] = $this->contactMethod;
        }
        if (!empty($this->industry)) {
            $data['industry'] = $this->industry;
        }
        if (!empty($this->companySize)) {
            $data['company_size'] = $this->companySize;
        }
        if (!empty($this->companyFunding)) {
            $data['company_funding'] = $this->companyFunding;
        }
        if (!empty($this->companyRevenue)) {
            $data['company_revenue'] = $this->companyRevenue;
        }
        if (!empty($this->seniority)) {
            $data['seniority'] = $this->seniority;
        }
        if (!empty($this->skills)) {
            $data['skills'] = $this->skills;
        }
        if (!empty($this->education)) {
            $data['education'] = $this->education;
        }
        if ($this->orderBy !== null) {
            $data['order_by'] = $this->orderBy;
        }
        if ($this->page !== null) {
            $data['page'] = $this->page;
        }
        if ($this->pageSize !== null) {
            $data['page_size'] = $this->pageSize;
        }

        return $data;
    }
}

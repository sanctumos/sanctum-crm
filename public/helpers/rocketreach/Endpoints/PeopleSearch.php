<?php

declare(strict_types=1);

namespace RocketReach\SDK\Endpoints;

use RocketReach\SDK\Http\HttpClient;
use RocketReach\SDK\Models\SearchQuery;
use RocketReach\SDK\Models\SearchResponse;

/**
 * People Search endpoint
 * 
 * Provides functionality to search for professional profiles
 * by various criteria such as name, title, company, location, etc.
 */
class PeopleSearch
{
    private HttpClient $httpClient;
    private SearchQuery $query;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->query = new SearchQuery();
    }

    /**
     * Set name filter
     *
     * @param array $names
     * @return self
     */
    public function name(array $names): self
    {
        $this->query->setName($names);
        return $this;
    }

    /**
     * Set current title filter
     *
     * @param array $titles
     * @return self
     */
    public function currentTitle(array $titles): self
    {
        $this->query->setCurrentTitle($titles);
        return $this;
    }

    /**
     * Set current employer filter
     *
     * @param array $employers
     * @return self
     */
    public function currentEmployer(array $employers): self
    {
        $this->query->setCurrentEmployer($employers);
        return $this;
    }

    /**
     * Set current employer domain filter
     *
     * @param array $domains
     * @return self
     */
    public function currentEmployerDomain(array $domains): self
    {
        $this->query->setCurrentEmployerDomain($domains);
        return $this;
    }

    /**
     * Set location filter
     *
     * @param array $locations
     * @return self
     */
    public function location(array $locations): self
    {
        $this->query->setLocation($locations);
        return $this;
    }

    /**
     * Set LinkedIn URL filter
     *
     * @param array $urls
     * @return self
     */
    public function linkedinUrl(array $urls): self
    {
        $this->query->setLinkedinUrl($urls);
        return $this;
    }

    /**
     * Set contact method filter
     *
     * @param array $methods
     * @return self
     */
    public function contactMethod(array $methods): self
    {
        $this->query->setContactMethod($methods);
        return $this;
    }

    /**
     * Set industry filter
     *
     * @param array $industries
     * @return self
     */
    public function industry(array $industries): self
    {
        $this->query->setIndustry($industries);
        return $this;
    }

    /**
     * Set company size filter
     *
     * @param array $sizes
     * @return self
     */
    public function companySize(array $sizes): self
    {
        $this->query->setCompanySize($sizes);
        return $this;
    }

    /**
     * Set company funding filter
     *
     * @param array $funding
     * @return self
     */
    public function companyFunding(array $funding): self
    {
        $this->query->setCompanyFunding($funding);
        return $this;
    }

    /**
     * Set company revenue filter
     *
     * @param array $revenue
     * @return self
     */
    public function companyRevenue(array $revenue): self
    {
        $this->query->setCompanyRevenue($revenue);
        return $this;
    }

    /**
     * Set seniority filter
     *
     * @param array $seniority
     * @return self
     */
    public function seniority(array $seniority): self
    {
        $this->query->setSeniority($seniority);
        return $this;
    }

    /**
     * Set skills filter
     *
     * @param array $skills
     * @return self
     */
    public function skills(array $skills): self
    {
        $this->query->setSkills($skills);
        return $this;
    }

    /**
     * Set education filter
     *
     * @param array $education
     * @return self
     */
    public function education(array $education): self
    {
        $this->query->setEducation($education);
        return $this;
    }

    /**
     * Set order by parameter
     *
     * @param string $orderBy
     * @return self
     */
    public function orderBy(string $orderBy): self
    {
        $this->query->setOrderBy($orderBy);
        return $this;
    }

    /**
     * Set page parameter
     *
     * @param int $page
     * @return self
     */
    public function page(int $page): self
    {
        $this->query->setPage($page);
        return $this;
    }

    /**
     * Set page size parameter
     *
     * @param int $pageSize
     * @return self
     */
    public function pageSize(int $pageSize): self
    {
        $this->query->setPageSize($pageSize);
        return $this;
    }

    /**
     * Execute the search
     *
     * @return SearchResponse
     */
    public function search(): SearchResponse
    {
        $queryData = $this->query->toArray();
        
        // Extract pagination and ordering parameters from query
        $page = $queryData['page'] ?? 1;
        $pageSize = $queryData['page_size'] ?? 10;
        $orderBy = $queryData['order_by'] ?? 'relevance';
        
        // Remove pagination and ordering from query data
        unset($queryData['page'], $queryData['page_size'], $queryData['order_by']);
        
        // Create payload with query object and top-level pagination/ordering
        $data = $this->httpClient->post('/person/search', [
            'query' => $queryData,
            'page' => $page,
            'page_size' => $pageSize,
            'order_by' => $orderBy
        ]);

        return new SearchResponse($data);
    }
}

<?php

declare(strict_types=1);

namespace RocketReach\SDK\Endpoints;

use RocketReach\SDK\Http\HttpClient;
use RocketReach\SDK\Models\LookupQuery;
use RocketReach\SDK\Models\PersonResponse;

/**
 * Person Lookup endpoint
 * 
 * Provides functionality to lookup contact details for specific individuals
 * using identifiers such as name + company, LinkedIn URL, or profile ID.
 */
class PersonLookup
{
    private HttpClient $httpClient;
    private LookupQuery $query;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->query = new LookupQuery();
    }

    /**
     * Set profile ID
     *
     * @param int $id
     * @return self
     */
    public function id(int $id): self
    {
        $this->query->setId($id);
        return $this;
    }

    /**
     * Set LinkedIn URL
     *
     * @param string $url
     * @return self
     */
    public function linkedinUrl(string $url): self
    {
        $this->query->setLinkedinUrl($url);
        return $this;
    }

    /**
     * Set name (requires current employer)
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->query->setName($name);
        return $this;
    }

    /**
     * Set current employer (required with name)
     *
     * @param string $employer
     * @return self
     */
    public function currentEmployer(string $employer): self
    {
        $this->query->setCurrentEmployer($employer);
        return $this;
    }

    /**
     * Set title (optional, helps with disambiguation)
     *
     * @param string $title
     * @return self
     */
    public function title(string $title): self
    {
        $this->query->setTitle($title);
        return $this;
    }

    /**
     * Set email (optional, helps with lookup)
     *
     * @param string $email
     * @return self
     */
    public function email(string $email): self
    {
        $this->query->setEmail($email);
        return $this;
    }

    /**
     * Set NPI number (for healthcare professionals)
     *
     * @param int $npiNumber
     * @return self
     */
    public function npiNumber(int $npiNumber): self
    {
        $this->query->setNpiNumber($npiNumber);
        return $this;
    }

    /**
     * Execute the lookup
     *
     * @return PersonResponse
     */
    public function lookup(): PersonResponse
    {
        $data = $this->httpClient->get('/person/lookup', $this->query->toArray());
        return new PersonResponse($data);
    }
}

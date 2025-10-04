<?php

declare(strict_types=1);

namespace RocketReach\SDK\Models;

/**
 * Lookup Query model
 * 
 * Represents the lookup parameters for Person Lookup and Enrich APIs
 */
class LookupQuery
{
    private ?int $id = null;
    private ?string $linkedinUrl = null;
    private ?string $name = null;
    private ?string $currentEmployer = null;
    private ?string $title = null;
    private ?string $email = null;
    private ?int $npiNumber = null;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setLinkedinUrl(string $linkedinUrl): self
    {
        $this->linkedinUrl = $linkedinUrl;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setCurrentEmployer(string $currentEmployer): self
    {
        $this->currentEmployer = $currentEmployer;
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setNpiNumber(int $npiNumber): self
    {
        $this->npiNumber = $npiNumber;
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

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        if ($this->linkedinUrl !== null) {
            $data['linkedin_url'] = $this->linkedinUrl;
        }
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->currentEmployer !== null) {
            $data['current_employer'] = $this->currentEmployer;
        }
        if ($this->title !== null) {
            $data['title'] = $this->title;
        }
        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->npiNumber !== null) {
            $data['npi_number'] = $this->npiNumber;
        }

        return $data;
    }
}

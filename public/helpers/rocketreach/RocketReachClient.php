<?php

declare(strict_types=1);

namespace RocketReach\SDK;

use RocketReach\SDK\Endpoints\PeopleSearch;
use RocketReach\SDK\Endpoints\PersonLookup;
use RocketReach\SDK\Endpoints\PersonEnrich;
use RocketReach\SDK\Http\HttpClient;
use RocketReach\SDK\Exceptions\InvalidApiKeyException;

/**
 * Main RocketReach SDK Client
 * 
 * Provides access to all RocketReach API endpoints including
 * People Search, Person Lookup, and Person Enrich functionality.
 * Uses native PHP cURL instead of GuzzleHttp for better compatibility.
 */
class RocketReachClient
{
    private const BASE_URL = 'https://api.rocketreach.co/api/v2';
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_RETRY_ATTEMPTS = 3;

    private HttpClient $httpClient;
    private string $apiKey;

    /**
     * Create a new RocketReach client instance
     *
     * @param string $apiKey Your RocketReach API key
     * @param array $config Optional configuration array
     * @throws InvalidApiKeyException
     */
    public function __construct(
        string $apiKey,
        array $config = []
    ) {
        if (empty($apiKey)) {
            throw new InvalidApiKeyException('API key cannot be empty');
        }

        $this->apiKey = $apiKey;
        
        $httpConfig = array_merge([
            'base_uri' => self::BASE_URL,
            'timeout' => $config['timeout'] ?? self::DEFAULT_TIMEOUT,
            'verify_ssl' => $config['verify_ssl'] ?? true, // Allow disabling SSL verification for dev
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'RocketReach-PHP-SDK/1.0.0'
            ]
        ], $config);

        $this->httpClient = new HttpClient($httpConfig);
    }

    /**
     * Get the People Search endpoint
     *
     * @return PeopleSearch
     */
    public function peopleSearch(): PeopleSearch
    {
        return new PeopleSearch($this->httpClient);
    }

    /**
     * Get the Person Lookup endpoint
     *
     * @return PersonLookup
     */
    public function personLookup(): PersonLookup
    {
        return new PersonLookup($this->httpClient);
    }

    /**
     * Get the Person Enrich endpoint
     *
     * @return PersonEnrich
     */
    public function personEnrich(): PersonEnrich
    {
        return new PersonEnrich($this->httpClient);
    }

    /**
     * Get the underlying HTTP client
     *
     * @return HttpClient
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * Get the API key (for testing purposes)
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}

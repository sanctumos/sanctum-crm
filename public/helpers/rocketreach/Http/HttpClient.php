<?php

declare(strict_types=1);

namespace RocketReach\SDK\Http;

use RocketReach\SDK\Exceptions\ApiException;
use RocketReach\SDK\Exceptions\RateLimitException;
use RocketReach\SDK\Exceptions\NetworkException;

/**
 * HTTP Client wrapper for RocketReach API
 * 
 * Handles HTTP requests with retry logic, rate limiting,
 * and proper error handling using native PHP cURL.
 */
class HttpClient
{
    private array $config;
    private int $retryAttempts;
    private int $retryDelay;

    public function __construct(
        array $config,
        int $retryAttempts = 3,
        int $retryDelay = 1000
    ) {
        $this->config = $config;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;
    }

    /**
     * Make a GET request
     *
     * @param string $endpoint
     * @param array $query
     * @return array
     * @throws ApiException
     * @throws RateLimitException
     * @throws NetworkException
     */
    public function get(string $endpoint, array $query = []): array
    {
        $url = $this->buildUrl($endpoint, $query);
        return $this->makeRequest('GET', $url);
    }

    /**
     * Make a POST request
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws ApiException
     * @throws RateLimitException
     * @throws NetworkException
     */
    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->makeRequest('POST', $url, $data);
    }

    /**
     * Build full URL from endpoint and query parameters
     *
     * @param string $endpoint
     * @param array $query
     * @return string
     */
    private function buildUrl(string $endpoint, array $query = []): string
    {
        $baseUrl = rtrim($this->config['base_uri'], '/');
        $endpoint = ltrim($endpoint, '/');
        $url = $baseUrl . '/' . $endpoint;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        return $url;
    }

    /**
     * Make an HTTP request with retry logic using cURL
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @return array
     * @throws ApiException
     * @throws RateLimitException
     * @throws NetworkException
     */
    private function makeRequest(string $method, string $url, array $data = []): array
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $response = $this->executeCurlRequest($method, $url, $data);
                return $this->handleResponse($response);
            } catch (ApiException $e) {
                $lastException = $e;
                
                // Handle 404 as a valid response for "person not found"
                if ($e->getCode() === 404) {
                    $responseData = $e->getResponseData();
                    if (isset($responseData['detail']) && strpos($responseData['detail'], 'Could not find the person') !== false) {
                        return ['not_found' => true, 'message' => $responseData['detail']];
                    }
                    return ['not_found' => true, 'message' => $responseData['detail'] ?? 'Resource not found'];
                }
                
                // Don't retry on client errors (4xx) except 429
                if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                    throw $e;
                }
                
                // Handle rate limiting
                if ($e->getCode() === 429) {
                    $retryAfter = $e->getRetryAfter() ?? 60;
                    if ($attempt < $this->retryAttempts) {
                        usleep($retryAfter * 1000000); // Convert to microseconds
                        continue;
                    }
                    throw new RateLimitException(
                        'Rate limit exceeded',
                        $e->getCode(),
                        $e,
                        $retryAfter
                    );
                }
                
                // For network errors, retry with exponential backoff
                if ($attempt < $this->retryAttempts) {
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                    continue;
                }
            } catch (Exception $e) {
                $lastException = $e;
                
                // For network errors, retry with exponential backoff
                if ($attempt < $this->retryAttempts) {
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                    continue;
                }
            }
        }
        
        // If we get here, all retries failed
        if ($lastException instanceof ApiException) {
            throw $lastException;
        }
        
        throw new NetworkException('Network request failed after all retries', 0, $lastException);
    }

    /**
     * Execute cURL request
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @return array
     * @throws ApiException
     * @throws NetworkException
     */
    private function executeCurlRequest(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();
        
        // Basic cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'] ?? true,
            CURLOPT_SSL_VERIFYHOST => $this->config['verify_ssl'] ?? true ? 2 : 0,
            CURLOPT_USERAGENT => $this->config['headers']['User-Agent'] ?? 'RocketReach-PHP-SDK/1.0.0',
            CURLOPT_HTTPHEADER => $this->buildHeaders(),
        ]);
        
        // Set method-specific options
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new NetworkException("cURL error: $error", 0);
        }
        
        if ($response === false) {
            throw new NetworkException('Failed to execute cURL request', 0);
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new NetworkException('Invalid JSON response: ' . json_last_error_msg(), 0);
        }
        
        return [
            'status_code' => $httpCode,
            'body' => $response,
            'data' => $data ?? []
        ];
    }

    /**
     * Build HTTP headers array
     *
     * @return array
     */
    private function buildHeaders(): array
    {
        $headers = [];
        foreach ($this->config['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        return $headers;
    }

    /**
     * Handle HTTP response
     *
     * @param array $response
     * @return array
     * @throws ApiException
     */
    private function handleResponse(array $response): array
    {
        $statusCode = $response['status_code'];
        $data = $response['data'];
        
        if ($statusCode >= 400) {
            // Handle 404 as a valid response for "person not found"
            if ($statusCode === 404 && isset($data['detail']) && strpos($data['detail'], 'Could not find the person') !== false) {
                return ['not_found' => true, 'message' => $data['detail']];
            }
            
            // Handle other 404 cases
            if ($statusCode === 404) {
                return ['not_found' => true, 'message' => $data['detail'] ?? 'Resource not found'];
            }
            
            throw new ApiException(
                $data['message'] ?? $data['detail'] ?? 'API request failed',
                $statusCode,
                null,
                $data
            );
        }
        
        return $data ?? [];
    }
}

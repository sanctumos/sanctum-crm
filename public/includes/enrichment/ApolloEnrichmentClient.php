<?php
/**
 * Apollo.io HTTP client (people match + org enrich + health).
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ApolloEnrichmentClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeoutSeconds;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.apollo.io/api/v1', int $timeoutSeconds = 45)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : 'https://api.apollo.io/api/v1', '/');
        // Normalize legacy /v1 → /api/v1
        if (preg_match('#^https://api\.apollo\.io/v1$#', $this->baseUrl)) {
            $this->baseUrl = 'https://api.apollo.io/api/v1';
        }
        $this->timeoutSeconds = max(5, $timeoutSeconds);
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Apollo API key is required');
        }
    }

    /**
     * @return array{healthy:bool,is_logged_in?:bool,raw:array}
     */
    public function health(): array
    {
        $raw = $this->request('GET', '/auth/health');
        return [
            'healthy' => !empty($raw['healthy']),
            'is_logged_in' => !empty($raw['is_logged_in']),
            'raw' => $raw,
        ];
    }

    /**
     * People enrichment. Returns not_found when Apollo has no match / empty person.
     *
     * @param array<string,mixed> $params email|linkedin_url|first_name|last_name|organization_name|domain|…
     * @return array{not_found?:true,message?:string,person?:array,raw?:array}
     */
    public function matchPerson(array $params): array
    {
        $body = array_merge([
            'reveal_personal_emails' => false,
            'reveal_phone_number' => false,
        ], $params);
        try {
            $raw = $this->request('POST', '/people/match', $body);
        } catch (ApolloApiException $e) {
            if ($e->isPlanOrScopeBlocked()) {
                throw $e;
            }
            if ($e->getHttpStatus() === 404) {
                return ['not_found' => true, 'message' => 'Person not found in Apollo', 'raw' => $e->getResponseBody()];
            }
            throw $e;
        }

        $person = $raw['person'] ?? null;
        if (!is_array($person) || $person === []) {
            return ['not_found' => true, 'message' => 'Person not found in Apollo', 'raw' => $raw];
        }
        return ['person' => $person, 'raw' => $raw];
    }

    /**
     * @param list<array<string,mixed>> $details
     * @return array{matches:list<array>,raw:array}
     */
    public function bulkMatchPeople(array $details): array
    {
        if (count($details) > 10) {
            $details = array_slice($details, 0, 10);
        }
        $raw = $this->request('POST', '/people/bulk_match', [
            'details' => array_values($details),
            'reveal_personal_emails' => false,
            'reveal_phone_number' => false,
        ]);
        $matches = $raw['matches'] ?? $raw['people'] ?? [];
        if (!is_array($matches)) {
            $matches = [];
        }
        return ['matches' => $matches, 'raw' => $raw];
    }

    /**
     * @return array{not_found?:true,message?:string,organization?:array,raw?:array}
     */
    public function enrichOrganization(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0];
        if ($domain === '') {
            throw new InvalidArgumentException('Organization domain is required');
        }
        $raw = $this->request('POST', '/organizations/enrich', ['domain' => $domain]);
        $org = $raw['organization'] ?? null;
        if (!is_array($org) || $org === []) {
            return ['not_found' => true, 'message' => 'Organization not found in Apollo', 'raw' => $raw];
        }
        return ['organization' => $org, 'raw' => $raw];
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'X-Api-Key: ' . $this->apiKey,
        ];
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ApolloApiException('Failed to init HTTP client', 0, []);
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new ApolloApiException('Network error talking to Apollo: ' . $error, 0, []);
        }

        $decoded = [];
        if (is_string($response) && $response !== '') {
            $tmp = json_decode($response, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }

        if ($status >= 400) {
            $msg = (string) ($decoded['error'] ?? $decoded['message'] ?? ('Apollo HTTP ' . $status));
            throw new ApolloApiException($msg, $status, $decoded);
        }

        return $decoded;
    }
}

class ApolloApiException extends Exception
{
    private int $httpStatus;
    /** @var array<string,mixed> */
    private array $responseBody;

    /**
     * @param array<string,mixed> $responseBody
     */
    public function __construct(string $message, int $httpStatus = 0, array $responseBody = [])
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->responseBody = $responseBody;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** @return array<string,mixed> */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }

    public function isPlanOrScopeBlocked(): bool
    {
        $code = (string) ($this->responseBody['error_code'] ?? '');
        if ($code === 'API_INACCESSIBLE') {
            return true;
        }
        $msg = strtolower($this->getMessage());
        return $this->httpStatus === 403 && (
            str_contains($msg, 'not included')
            || str_contains($msg, 'not authorized')
            || str_contains($msg, 'plan')
            || str_contains($msg, 'scope')
        );
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }
}

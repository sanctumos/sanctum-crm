<?php
/**
 * Lead Enrichment Service
 * Sanctum CRM - RocketReach Integration Service
 */

// Prevent direct access
if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

// Include RocketReach SDK files in correct order
require_once __DIR__ . '/../helpers/rocketreach/Exceptions/ApiException.php';
require_once __DIR__ . '/../helpers/rocketreach/Exceptions/InvalidApiKeyException.php';
require_once __DIR__ . '/../helpers/rocketreach/Exceptions/RateLimitException.php';
require_once __DIR__ . '/../helpers/rocketreach/Exceptions/NetworkException.php';
require_once __DIR__ . '/../helpers/rocketreach/Http/HttpClient.php';
require_once __DIR__ . '/../helpers/rocketreach/Models/EnrichResponse.php';
require_once __DIR__ . '/../helpers/rocketreach/Models/SearchResponse.php';
require_once __DIR__ . '/../helpers/rocketreach/Models/PersonResponse.php';
require_once __DIR__ . '/../helpers/rocketreach/Models/LookupQuery.php';
require_once __DIR__ . '/../helpers/rocketreach/Models/SearchQuery.php';
require_once __DIR__ . '/../helpers/rocketreach/Endpoints/PersonEnrich.php';
require_once __DIR__ . '/../helpers/rocketreach/Endpoints/PeopleSearch.php';
require_once __DIR__ . '/../helpers/rocketreach/Endpoints/PersonLookup.php';
require_once __DIR__ . '/../helpers/rocketreach/RocketReachClient.php';

use RocketReach\SDK\RocketReachClient;
use RocketReach\SDK\Models\EnrichResponse;
use RocketReach\SDK\Exceptions\ApiException;
use RocketReach\SDK\Exceptions\RateLimitException;
use RocketReach\SDK\Exceptions\NetworkException;

class LeadEnrichmentService
{
    private const ENRICHMENT_SCHEMA = 2;

    private ?RocketReachClient $client = null;
    private Database $db;
    private bool $enabled;

    public function __construct()
    {
        $this->db = Database::getInstance();
        
        // Get RocketReach API key from database
        $settings = $this->db->fetchOne("SELECT rocketreach_api_key FROM settings WHERE id = 1");
        $apiKey = $settings['rocketreach_api_key'] ?? '';
        
        // Auto-detect if enrichment is available based on API key presence
        $this->enabled = !empty($apiKey);
        
        if ($this->enabled) {
            try {
                // Configure for different environments
                $config = [];
                
                // Only disable SSL verification in Windows development environment
                if (defined('DEBUG_MODE') && DEBUG_MODE && PHP_OS_FAMILY === 'Windows') {
                    $config['verify_ssl'] = false; // Disable SSL verification only on Windows dev
                } else {
                    $config['verify_ssl'] = true; // Enable SSL verification on Ubuntu production
                }
                
                $this->client = new RocketReachClient($apiKey, $config);
            } catch (Exception $e) {
                // If RocketReach client fails to initialize, disable enrichment
                $this->enabled = false;
                $this->client = null;
            }
        } else {
            $this->client = null;
        }
    }
    
    /**
     * Enrich a single contact using RocketReach
     *
     * @param int $contactId Contact ID to enrich
     * @param string $strategy Enrichment strategy (email, linkedin, name_company, twitter, auto)
     * @return array Enrichment result
     * @throws Exception
     */
    public function enrichContact(int $contactId, string $strategy = 'auto'): array
    {
        $contact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        if (!$contact) {
            throw new Exception('Contact not found');
        }

        if (!$this->enabled) {
            return $this->recordEnrichmentOutcome(
                $contactId,
                $contact,
                'failed',
                'RocketReach enrichment is not enabled or API key is missing'
            );
        }
        
        // Check if already enriched recently
        if ($contact['enrichment_status'] === 'enriched' && 
            $contact['enriched_at'] && 
            strtotime($contact['enriched_at']) > (time() - 86400)) { // 24 hours
            return [
                'success' => true,
                'outcome' => 'skipped',
                'contact' => $contact,
                'message' => 'Contact already enriched recently (no lookup run)',
            ];
        }
        
        // Prior not_found may have been email/LI/name miss — allow retry when Twitter is available
        // or when caller explicitly asks for twitter / force.
        if ($contact['enrichment_status'] === 'not_found') {
            $strategyNorm = strtolower(trim($strategy));
            $hasTwitter = trim((string) ($contact['twitter_handle'] ?? '')) !== '';
            if (!$hasTwitter && !in_array($strategyNorm, ['twitter', 'force'], true)) {
                return [
                    'success' => false,
                    'outcome' => 'not_found',
                    'contact' => $contact,
                    'message' => 'Contact previously marked as not found in RocketReach database',
                ];
            }
        }

        if (!$this->canEnrich($contact)) {
            return $this->recordEnrichmentOutcome(
                $contactId,
                $contact,
                'failed',
                'Insufficient data for enrichment. Need email, LinkedIn profile, name+company, or Twitter handle'
            );
        }
        
        // Increment attempt counter before any external lookup
        $this->db->update('contacts', [
            'enrichment_attempts' => ($contact['enrichment_attempts'] ?? 0) + 1,
            'enrichment_status' => 'processing',
            'enrichment_error' => null,
            'updated_at' => getCurrentTimestamp(),
        ], 'id = ?', [$contactId]);
        $contact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        
        try {
            $enrichmentData = $this->performEnrichment($contact, $strategy);
            
            // Handle "not found" case
            if (isset($enrichmentData['not_found']) && $enrichmentData['not_found']) {
                $this->db->update('contacts', [
                    'enrichment_status' => 'not_found',
                    'enrichment_error' => $enrichmentData['message'] ?? 'Person not found in RocketReach database',
                    'updated_at' => getCurrentTimestamp()
                ], 'id = ?', [$contactId]);
                
                $updatedContact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
                return [
                    'success' => false,
                    'outcome' => 'not_found',
                    'message' => $enrichmentData['message'] ?? 'Person not found in RocketReach database',
                    'contact' => $updatedContact,
                ];
            }
            
            if ($enrichmentData) {
                $normalized = $enrichmentData['normalized'];
                $rawPerson = $enrichmentData['raw_person'];
                $updateData = $this->mapEnrichmentData($normalized, $rawPerson, $contact);
                $updateData['enrichment_status'] = 'enriched';
                $updateData['enriched_at'] = getCurrentTimestamp();
                $updateData['enrichment_source'] = 'rocketreach';
                // Card still gets a compact enrichment_data summary for UI; full raw → sidecar (Doc #919).
                $updateData['enrichment_data'] = $this->encodeJson($normalized);
                if (isset($rawPerson['id']) && is_numeric($rawPerson['id'])) {
                    $updateData['rocketreach_profile_id'] = (int) $rawPerson['id'];
                }
                $updateData['updated_at'] = getCurrentTimestamp();

                $this->db->update('contacts', $updateData, 'id = ?', [$contactId]);

                $runId = null;
                try {
                    require_once __DIR__ . '/ContactDataStore.php';
                    $store = new ContactDataStore($this->db);
                    $store->ensureSchema();
                    $lookup = $enrichmentData['lookup_used'] ?? null;
                    $lookupLabel = 'RocketReach enrichment';
                    if (is_array($lookup) && !empty($lookup['type'])) {
                        $lookupLabel = 'RocketReach via ' . $lookup['type']
                            . (!empty($lookup['source']) ? ' (' . $lookup['source'] . ')' : '');
                    }
                    $recorded = $store->recordRun($contactId, [
                        'source' => 'rocketreach',
                        'outcome' => 'enriched',
                        'label' => $lookupLabel,
                        'raw_payload' => [
                            'raw' => $rawPerson,
                            'normalized' => $normalized,
                            'lookup_used' => $lookup,
                            'lookup_attempts' => $enrichmentData['lookup_attempts'] ?? [],
                        ],
                        'facts' => $store->factsFromRocketReach(
                            is_array($rawPerson) ? $rawPerson : [],
                            is_array($normalized) ? $normalized : null
                        ),
                    ]);
                    $runId = $recorded['run_id'];
                } catch (Exception $sidecarEx) {
                    error_log('ContactDataStore rocketreach write failed: ' . $sidecarEx->getMessage());
                }
                
                $updatedContact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);

                return [
                    'success' => true,
                    'outcome' => 'enriched',
                    'contact' => $updatedContact,
                    'enrichment_data' => $normalized,
                    'enrichment_raw' => $rawPerson,
                    'data_run_id' => $runId,
                ];
            } else {
                throw new Exception('No enrichment data found');
            }
            
        } catch (Exception $e) {
            return $this->recordEnrichmentOutcome($contactId, $contact, 'failed', $e->getMessage());
        }
    }

    /**
     * Persist enrichment attempt outcome so pending/processing rows cannot loop forever.
     */
    private function recordEnrichmentOutcome(int $contactId, array $contact, string $status, ?string $errorMessage): array
    {
        $attempts = (int) ($contact['enrichment_attempts'] ?? 0);
        if (($contact['enrichment_status'] ?? '') !== 'processing') {
            $attempts++;
        }

        $updateData = [
            'enrichment_attempts' => $attempts,
            'enrichment_status' => $status,
            'enrichment_error' => $errorMessage,
            'updated_at' => getCurrentTimestamp(),
        ];
        $this->db->update('contacts', $updateData, 'id = ?', [$contactId]);

        $updatedContact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        $success = $status === 'enriched';
        $outcome = $status === 'enriched' ? 'enriched' : ($status === 'not_found' ? 'not_found' : 'failed');

        return [
            'success' => $success,
            'outcome' => $outcome,
            'message' => $errorMessage,
            'contact' => $updatedContact,
        ];
    }

    /**
     * Enrich multiple contacts in batch
     *
     * @param array $contactIds Array of contact IDs
     * @param string $strategy Enrichment strategy
     * @return array Batch enrichment results
     */
    public function enrichContacts(array $contactIds, string $strategy = 'auto'): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'enriched_contacts' => [],
            'errors' => []
        ];

        foreach ($contactIds as $contactId) {
            try {
                $result = $this->enrichContact($contactId, $strategy);
                $outcome = $result['outcome'] ?? (empty($result['success']) ? 'error' : 'enriched');
                if ($outcome === 'enriched') {
                    $results['successful']++;
                    $results['enriched_contacts'][] = [
                        'id' => $contactId,
                        'enrichment_status' => $result['contact']['enrichment_status'] ?? 'enriched',
                        'enriched_at' => $result['contact']['enriched_at'] ?? null,
                    ];
                } elseif ($outcome === 'skipped') {
                    $results['skipped']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'contact_id' => $contactId,
                        'error' => $result['message'] ?? 'Enrichment did not complete',
                    ];
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'contact_id' => $contactId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get enrichment status for a contact
     *
     * @param int $contactId Contact ID
     * @return array Enrichment status
     */
    public function getEnrichmentStatus(int $contactId): array
    {
        $contact = $this->db->fetchOne(
            "SELECT enrichment_status, enrichment_attempts, enrichment_error, enriched_at, enrichment_source 
             FROM contacts WHERE id = ?", 
            [$contactId]
        );

        if (!$contact) {
            throw new Exception('Contact not found');
        }

        return [
            'status' => $contact['enrichment_status'] ?? 'pending',
            'attempts' => $contact['enrichment_attempts'] ?? 0,
            'last_error' => $contact['enrichment_error'],
            'enriched_at' => $contact['enriched_at'],
            'source' => $contact['enrichment_source']
        ];
    }
    
    /**
     * Perform enrichment via RocketReach — try primary card fields then all sidecar alts
     * (accepted-merge emails / LinkedIn facts count as the same person).
     *
     * @param array $contact Contact data
     * @param string $strategy Enrichment strategy
     * @return array|null Enrichment data
     * @throws Exception
     */
    private function performEnrichment(array $contact, string $strategy): ?array
    {
        try {
            $contactId = (int) ($contact['id'] ?? 0);
            $lookups = $this->collectEnrichmentLookups($contactId, $contact);
            $lookups = $this->filterLookupsForStrategy($lookups, $strategy, $contact);

            if ($lookups === []) {
                throw new Exception('Insufficient data for enrichment. Need email (card or sidecar), LinkedIn, name+company, or Twitter handle');
            }

            $attempts = [];
            $lastNotFound = null;

            foreach ($lookups as $lookup) {
                $attempt = [
                    'type' => $lookup['type'],
                    'value' => $lookup['value'],
                    'source' => $lookup['source'] ?? null,
                ];
                try {
                    $personEnrich = $this->client->personEnrich();
                    switch ($lookup['type']) {
                        case 'email':
                            $response = $personEnrich->email($lookup['value'])->enrich();
                            break;
                        case 'linkedin':
                            $response = $personEnrich->linkedinUrl($lookup['value'])->enrich();
                            break;
                        case 'name_company':
                            $response = $personEnrich
                                ->name($lookup['value'])
                                ->currentEmployer($lookup['employer'])
                                ->enrich();
                            break;
                        case 'twitter':
                            $rrId = $this->resolveRocketReachIdByTwitterHandle(
                                (string) $lookup['value'],
                                $contact
                            );
                            if ($rrId === null) {
                                $attempt['outcome'] = 'not_found';
                                $attempts[] = $attempt;
                                $lastNotFound = [
                                    'not_found' => true,
                                    'message' => 'No RocketReach profile matched Twitter handle @' . $lookup['value'],
                                    'lookup_attempts' => $attempts,
                                ];
                                continue 2;
                            }
                            $attempt['rocketreach_id'] = $rrId;
                            $response = $this->client->personEnrich()->id($rrId)->enrich();
                            break;
                        default:
                            continue 2;
                    }
                    $payload = $this->buildEnrichmentPayload($response);
                    $attempt['outcome'] = !empty($payload['not_found']) ? 'not_found' : 'hit';
                    $attempts[] = $attempt;

                    if (!empty($payload['not_found'])) {
                        $lastNotFound = $payload;
                        continue;
                    }

                    $payload['lookup_used'] = $lookup;
                    $payload['lookup_attempts'] = $attempts;
                    return $payload;
                } catch (RateLimitException $e) {
                    throw $e;
                } catch (NetworkException $e) {
                    throw $e;
                } catch (ApiException $e) {
                    $attempt['outcome'] = 'api_error';
                    $attempt['error'] = $e->getMessage();
                    $attempts[] = $attempt;
                    $lastNotFound = [
                        'not_found' => true,
                        'message' => $e->getMessage(),
                        'lookup_attempts' => $attempts,
                    ];
                    continue;
                }
            }

            if ($lastNotFound !== null) {
                $lastNotFound['lookup_attempts'] = $attempts;
                return $lastNotFound;
            }

            throw new Exception('Enrichment failed: no usable lookup produced a result');
        } catch (RateLimitException $e) {
            throw new Exception('RocketReach rate limit exceeded. Please try again later.');
        } catch (NetworkException $e) {
            throw new Exception('Network error connecting to RocketReach: ' . $e->getMessage());
        } catch (ApiException $e) {
            throw new Exception('RocketReach API error: ' . $e->getMessage());
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'Enrichment failed:')
                || str_starts_with($msg, 'Insufficient data')
                || str_starts_with($msg, 'RocketReach')
                || str_starts_with($msg, 'Network error')) {
                throw $e;
            }
            throw new Exception('Enrichment failed: ' . $msg);
        }
    }

    /**
     * Ordered lookups: primary email, sidecar emails, LinkedIn (card + facts), Twitter handle, name+company.
     *
     * @return list<array{type:string,value:string,source?:string,employer?:string}>
     */
    public function collectEnrichmentLookups(int $contactId, array $contact): array
    {
        $lookups = [];
        $seenEmail = [];
        $seenLi = [];

        $addEmail = static function (string $email, string $source) use (&$lookups, &$seenEmail): void {
            $email = strtolower(trim($email));
            if ($email === '' || !str_contains($email, '@') || isset($seenEmail[$email])) {
                return;
            }
            $seenEmail[$email] = true;
            $lookups[] = ['type' => 'email', 'value' => $email, 'source' => $source];
        };

        $addLinkedIn = static function (string $url, string $source) use (&$lookups, &$seenLi): void {
            $url = trim($url);
            if ($url === '') {
                return;
            }
            $key = strtolower($url);
            if (isset($seenLi[$key])) {
                return;
            }
            $seenLi[$key] = true;
            $lookups[] = ['type' => 'linkedin', 'value' => $url, 'source' => $source];
        };

        $addEmail((string) ($contact['email'] ?? ''), 'primary');
        $addLinkedIn((string) ($contact['linkedin_profile'] ?? ''), 'primary');

        if ($contactId > 0) {
            try {
                require_once __DIR__ . '/ContactDataStore.php';
                $store = new ContactDataStore($this->db);
                $store->ensureSchema();
                $store->backfillLegacyRocketReach($contactId, $contact);

                foreach ($store->listFacts($contactId, 'email') as $fact) {
                    $label = trim((string) ($fact['label'] ?? 'sidecar'));
                    $addEmail((string) ($fact['value'] ?? ''), 'sidecar:' . $label);
                }
                foreach ($store->listFacts($contactId, 'social') as $fact) {
                    $label = strtolower(trim((string) ($fact['label'] ?? '')));
                    $value = (string) ($fact['value'] ?? '');
                    if ($label === 'linkedin' || stripos($value, 'linkedin.com') !== false) {
                        $addLinkedIn($value, 'sidecar:' . ($label !== '' ? $label : 'social'));
                    }
                }
            } catch (Exception $e) {
                error_log('collectEnrichmentLookups sidecar read failed: ' . $e->getMessage());
            }
        }

        $tw = $this->normalizeTwitterHandle((string) ($contact['twitter_handle'] ?? ''));
        if ($tw !== '') {
            $lookups[] = [
                'type' => 'twitter',
                'value' => $tw,
                'source' => 'primary',
            ];
        }

        $fn = trim((string) ($contact['first_name'] ?? ''));
        $ln = trim((string) ($contact['last_name'] ?? ''));
        $co = trim((string) ($contact['company'] ?? ''));
        if ($fn !== '' && $ln !== '' && strcasecmp($ln, 'Unknown') !== 0 && $co !== '') {
            $lookups[] = [
                'type' => 'name_company',
                'value' => $fn . ' ' . $ln,
                'employer' => $co,
                'source' => 'primary',
            ];
        }

        return $lookups;
    }

    /**
     * @param list<array> $lookups
     * @return list<array>
     */
    private function filterLookupsForStrategy(array $lookups, string $strategy, array $contact): array
    {
        $strategy = strtolower(trim($strategy));
        if ($strategy === 'auto' || $strategy === '') {
            $emails = array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'email'));
            $lis = array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'linkedin'));
            $tw = array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'twitter'));
            $names = array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'name_company'));
            // Prefer exact identifiers, then Twitter search, then fuzzy name+company
            return array_merge($emails, $lis, $tw, $names);
        }
        if ($strategy === 'email') {
            return array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'email'));
        }
        if ($strategy === 'linkedin') {
            return array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'linkedin'));
        }
        if ($strategy === 'twitter') {
            return array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'twitter'));
        }
        if ($strategy === 'name_company') {
            return array_values(array_filter($lookups, static fn($l) => ($l['type'] ?? '') === 'name_company'));
        }
        return $lookups;
    }

    /**
     * People Search by social handle → RocketReach profile id for lookup/enrich.
     * Search itself does not burn lookup credits; the subsequent id enrich does.
     */
    private function resolveRocketReachIdByTwitterHandle(string $handle, array $contact): ?int
    {
        $handle = $this->normalizeTwitterHandle($handle);
        if ($handle === '' || $this->client === null) {
            return null;
        }

        $response = $this->client->peopleSearch()
            ->handle([$handle])
            ->pageSize(10)
            ->orderBy('relevance')
            ->search();

        $profiles = $response->getProfiles();
        if ($profiles === []) {
            return null;
        }

        $picked = $this->pickTwitterSearchMatch($profiles, $handle, $contact);
        if ($picked === null) {
            return null;
        }
        $id = $picked['id'] ?? null;
        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param list<array> $profiles
     */
    private function pickTwitterSearchMatch(array $profiles, string $handle, array $contact): ?array
    {
        $handleLower = strtolower($handle);
        $exact = [];
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            if ($this->profileLinksContainTwitterHandle($profile, $handleLower)) {
                $exact[] = $profile;
            }
        }
        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($exact) > 1) {
            return $exact[0];
        }

        // Single search hit — accept
        if (count($profiles) === 1 && is_array($profiles[0])) {
            return $profiles[0];
        }

        // Disambiguate by display name when multiple
        $fn = strtolower(trim((string) ($contact['first_name'] ?? '')));
        $ln = strtolower(trim((string) ($contact['last_name'] ?? '')));
        $full = trim($fn . ' ' . $ln);
        if ($full !== '' && !str_starts_with($ln, '@')) {
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }
                $pname = strtolower(trim((string) ($profile['name'] ?? '')));
                if ($pname === '' ) {
                    continue;
                }
                if ($pname === $full || str_contains($pname, $fn) && str_contains($pname, $ln)) {
                    return $profile;
                }
            }
        }

        return null;
    }

    private function profileLinksContainTwitterHandle(array $profile, string $handleLower): bool
    {
        $needles = [
            'twitter.com/' . $handleLower,
            'x.com/' . $handleLower,
            'twitter.com/' . $handleLower . '?',
            'x.com/' . $handleLower . '?',
        ];
        $blobs = [];
        foreach (['links', 'social_links'] as $key) {
            if (!isset($profile[$key])) {
                continue;
            }
            if (is_array($profile[$key])) {
                foreach ($profile[$key] as $k => $v) {
                    if (is_string($v)) {
                        $blobs[] = strtolower($v);
                    } elseif (is_string($k) && is_scalar($v)) {
                        $blobs[] = strtolower((string) $v);
                    }
                }
            } elseif (is_string($profile[$key])) {
                $blobs[] = strtolower($profile[$key]);
            }
        }
        foreach (['twitter_url', 'twitter', 'teaser'] as $key) {
            if (!empty($profile[$key]) && is_string($profile[$key])) {
                $blobs[] = strtolower($profile[$key]);
            }
        }
        $hay = implode(' ', $blobs);
        foreach ($needles as $n) {
            if (str_contains($hay, rtrim($n, '?'))) {
                return true;
            }
        }
        // Some search rows expose handle fields directly
        foreach (['twitter_handle', 'handle'] as $key) {
            $h = $this->normalizeTwitterHandle((string) ($profile[$key] ?? ''));
            if ($h !== '' && strtolower($h) === $handleLower) {
                return true;
            }
        }
        return false;
    }

    private function normalizeTwitterHandle(string $raw): string
    {
        $h = trim($raw);
        if ($h === '') {
            return '';
        }
        if (preg_match('~(?:twitter\.com|x\.com)/@?([A-Za-z0-9_]{1,15})~i', $h, $m)) {
            return $m[1];
        }
        $h = ltrim($h, '@');
        $h = preg_replace('/[^A-Za-z0-9_]/', '', $h) ?? '';
        return $h;
    }

    /**
     * Full RocketReach profile (raw) plus normalized CRM payload (schema 2).
     *
     * @return array{not_found?:true,message?:string,raw_person?:array,normalized?:array}
     */
    private function buildEnrichmentPayload(EnrichResponse $response): array
    {
        if (!method_exists($response, 'getPerson')) {
            throw new Exception('Invalid enrichment response');
        }
        $raw = $response->getPerson();
        if (!is_array($raw) || $raw === []) {
            throw new Exception('Empty enrichment response from RocketReach');
        }
        if (isset($raw['not_found']) && $raw['not_found']) {
            return ['not_found' => true, 'message' => $raw['message'] ?? 'Person not found'];
        }
        $sdkCompany = method_exists($response, 'getCompany') ? $response->getCompany() : [];
        if (!is_array($sdkCompany)) {
            $sdkCompany = [];
        }
        $normalized = $this->buildNormalizedPayload($raw, $sdkCompany);
        return ['raw_person' => $raw, 'normalized' => $normalized];
    }

    /**
     * Canonical enrichment_data JSON (schema 2) for UI and exports.
     */
    private function buildNormalizedPayload(array $raw, array $sdkCompany): array
    {
        $company = [
            'name' => $raw['current_employer'] ?? $sdkCompany['name'] ?? null,
            'id' => $raw['current_employer_id'] ?? $sdkCompany['id'] ?? null,
            'domain' => $raw['current_employer_domain'] ?? $sdkCompany['domain'] ?? null,
            'website' => $raw['current_employer_website'] ?? $sdkCompany['website'] ?? null,
            'linkedin_url' => $raw['current_employer_linkedin_url'] ?? $sdkCompany['linkedin_url'] ?? null,
            'industry' => $raw['current_employer_industry'] ?? $sdkCompany['industry'] ?? null,
            'employee_count' => $sdkCompany['employee_count'] ?? null,
            'location' => $sdkCompany['location'] ?? null,
        ];

        $links = $raw['links'] ?? null;
        if (!is_array($links)) {
            $links = [];
        }

        return [
            'schema' => self::ENRICHMENT_SCHEMA,
            'captured_at' => gmdate('c'),
            'rocketreach_profile_id' => $raw['id'] ?? null,
            'lookup_status' => $raw['status'] ?? null,
            'recommended' => [
                'professional_email' => $raw['recommended_professional_email'] ?? null,
                'personal_email' => $raw['recommended_personal_email'] ?? null,
                'email' => $raw['recommended_email'] ?? null,
                'current_work_email' => $raw['current_work_email'] ?? null,
                'current_personal_email' => $raw['current_personal_email'] ?? null,
            ],
            'emails' => $raw['emails'] ?? [],
            'phones' => $raw['phones'] ?? [],
            'social_links' => $links,
            'education' => $raw['education'] ?? [],
            'job_history' => $raw['job_history'] ?? [],
            'skills' => $raw['skills'] ?? [],
            'company' => $company,
            'location' => [
                'full' => $raw['location'] ?? null,
                'city' => $raw['city'] ?? null,
                'region' => $raw['region'] ?? null,
                'country' => $raw['country'] ?? null,
                'country_code' => $raw['country_code'] ?? null,
            ],
            'profile_pic' => $raw['profile_pic'] ?? null,
            'current_title' => $raw['current_title'] ?? null,
            'linkedin_url' => $raw['linkedin_url'] ?? null,
            'name' => $raw['name'] ?? null,
        ];
    }

    private function encodeJson($data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json !== false ? $json : '{}';
    }

    private function pickBestEmailFromRaw(array $raw): ?string
    {
        foreach ([
            'recommended_professional_email',
            'recommended_email',
            'current_work_email',
            'recommended_personal_email',
            'current_personal_email',
        ] as $k) {
            if (!empty($raw[$k]) && is_string($raw[$k])) {
                $v = trim($raw[$k]);
                if ($v !== '') {
                    return $v;
                }
            }
        }
        $list = $raw['emails'] ?? [];
        if (!is_array($list) || $list === []) {
            return null;
        }
        $best = null;
        $bestScore = -1;
        foreach ($list as $item) {
            $addr = is_array($item) ? ($item['email'] ?? '') : (string) $item;
            $addr = trim($addr);
            if ($addr === '') {
                continue;
            }
            $grade = is_array($item) ? (string) ($item['grade'] ?? '') : '';
            $score = 0;
            if (preg_match('/^A/i', $grade)) {
                $score = 4;
            } elseif ($grade !== '') {
                $score = 2;
            }
            if (is_array($item) && (($item['type'] ?? '') === 'professional')) {
                $score += 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $addr;
            }
        }
        return $best;
    }

    private function pickBestPhoneFromRaw(array $raw): ?string
    {
        $list = $raw['phones'] ?? [];
        if (!is_array($list) || $list === []) {
            return null;
        }
        $recommended = null;
        $first = null;
        foreach ($list as $item) {
            $num = is_array($item) ? ($item['number'] ?? '') : (string) $item;
            $num = trim($num);
            if ($num === '') {
                continue;
            }
            if ($first === null) {
                $first = $num;
            }
            if (is_array($item) && !empty($item['recommended'])) {
                $recommended = $num;
                break;
            }
        }
        return $recommended ?? $first;
    }

    /**
     * @return array{twitter_handle:string,github_username:string,telegram_username:string,discord_username:string}
     */
    private function harvestSocialHandles(array $raw): array
    {
        $out = [
            'twitter_handle' => '',
            'github_username' => '',
            'telegram_username' => '',
            'discord_username' => '',
        ];
        $links = $raw['links'] ?? null;
        if (!is_array($links)) {
            return $out;
        }
        foreach ($links as $platform => $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }
            $p = strtolower((string) $platform);
            if (str_contains($p, 'twitter') || str_contains($p, 'x.com')) {
                $h = $this->handleFromUrl($url, 'twitter');
                if ($h !== '') {
                    $out['twitter_handle'] = $h;
                }
            } elseif (str_contains($p, 'github')) {
                $h = $this->handleFromUrl($url, 'github');
                if ($h !== '') {
                    $out['github_username'] = $h;
                }
            } elseif (str_contains($p, 'telegram')) {
                $h = $this->handleFromUrl($url, 'telegram');
                if ($h !== '') {
                    $out['telegram_username'] = $h;
                }
            } elseif (str_contains($p, 'discord')) {
                $h = $this->handleFromUrl($url, 'discord');
                if ($h !== '') {
                    $out['discord_username'] = $h;
                }
            }
        }
        return $out;
    }

    private function handleFromUrl(string $url, string $kind): string
    {
        $url = trim($url);
        $path = parse_url($url, PHP_URL_PATH);
        $path = $path ? trim($path, '/') : '';
        $segments = $path !== '' ? array_values(array_filter(explode('/', $path))) : [];
        if ($kind === 'twitter') {
            if ($segments === []) {
                return '';
            }
            $h = $segments[count($segments) - 1];
            return ltrim($h, '@');
        }
        if ($kind === 'github' && $segments !== []) {
            return $segments[0];
        }
        if ($kind === 'telegram' && $segments !== []) {
            return ltrim($segments[count($segments) - 1], '@');
        }
        if ($kind === 'discord') {
            return strlen($url) <= 50 ? $url : substr($url, 0, 50);
        }
        return '';
    }

    /**
     * @return array{first:string,last:string}|null
     */
    private function splitDisplayName(string $name): ?array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            return null;
        }
        $pos = strpos($name, ' ');
        if ($pos === false) {
            return ['first' => $name, 'last' => ''];
        }
        return ['first' => substr($name, 0, $pos), 'last' => trim(substr($name, $pos + 1))];
    }

    private function collapseNameSpaces(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function crmDisplayNameMatchesRocketReach(string $crmFirst, string $crmLast, string $rrFullName): bool
    {
        $a = $this->collapseNameSpaces($crmFirst . ' ' . $crmLast);
        $b = $this->collapseNameSpaces($rrFullName);
        return strcasecmp($a, $b) === 0;
    }

    /** Plain one-line fragment for notes (not HTML-entity encoded). */
    private function sanitizeNoteLine(string $s): string
    {
        $s = strip_tags($s);
        return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\0"], ' ', $s)));
    }

    /**
     * Map normalized + raw RocketReach data onto contact columns.
     *
     * @param array $normalized Schema 2 payload from buildNormalizedPayload
     * @param array $rawPerson    Full API profile object
     */
    private function mapEnrichmentData(array $normalized, array $rawPerson, array $originalContact): array
    {
        $updateData = [];
        $notes = $originalContact['notes'] ?? '';

        $email = $this->pickBestEmailFromRaw($rawPerson);
        if ($email && empty($originalContact['email'])) {
            $updateData['email'] = sanitizeInput($email);
        }

        $phone = $this->pickBestPhoneFromRaw($rawPerson);
        if ($phone && empty($originalContact['phone'])) {
            $updateData['phone'] = sanitizeInput($phone);
        }

        $title = $normalized['current_title'] ?? null;
        if ($title && empty($originalContact['position'])) {
            $updateData['position'] = sanitizeInput($title);
        }

        $li = $normalized['linkedin_url'] ?? null;
        if ($li && empty($originalContact['linkedin_profile'])) {
            $updateData['linkedin_profile'] = sanitizeInput($li);
        }

        $locBlock = $normalized['location'] ?? [];
        $fullLoc = $locBlock['full'] ?? null;
        if ($fullLoc && empty($originalContact['address'])) {
            $updateData['address'] = sanitizeInput($fullLoc);
        }
        $mapLoc = ['city' => 'city', 'region' => 'state', 'country' => 'country'];
        foreach ($mapLoc as $nk => $col) {
            if (!empty($locBlock[$nk]) && empty($originalContact[$col])) {
                $updateData[$col] = sanitizeInput((string) $locBlock[$nk]);
            }
        }

        $company = $normalized['company'] ?? [];
        if (!empty($company['name']) && empty($originalContact['company'])) {
            $updateData['company'] = sanitizeInput((string) $company['name']);
        }
        if (!empty($company['domain']) && empty($originalContact['website'])) {
            $updateData['website'] = 'https://' . sanitizeInput((string) $company['domain']);
        }

        $handles = $this->harvestSocialHandles($rawPerson);
        foreach (['twitter_handle', 'github_username', 'telegram_username', 'discord_username'] as $col) {
            if (!empty($handles[$col]) && empty($originalContact[$col])) {
                $updateData[$col] = sanitizeInput(substr($handles[$col], 0, 50));
            }
        }

        $rrName = isset($normalized['name']) ? trim((string) $normalized['name']) : '';
        if ($rrName !== '') {
            $oldFirst = trim((string) ($originalContact['first_name'] ?? ''));
            $oldLast = trim((string) ($originalContact['last_name'] ?? ''));
            if (!$this->crmDisplayNameMatchesRocketReach($oldFirst, $oldLast, $rrName)) {
                $parts = $this->splitDisplayName($rrName);
                if ($parts !== null && $parts['first'] !== '') {
                    $didUpdate = false;
                    $newLast = $parts['last'];
                    $updatedDisplay = '';
                    if ($newLast !== '') {
                        $updateData['first_name'] = sanitizeInput(substr($parts['first'], 0, 50));
                        $updateData['last_name'] = sanitizeInput(substr($newLast, 0, 50));
                        $updatedDisplay = $this->collapseNameSpaces($parts['first'] . ' ' . $newLast);
                        $didUpdate = true;
                    } elseif (strcasecmp($parts['first'], $oldFirst) !== 0) {
                        $updateData['first_name'] = sanitizeInput(substr($parts['first'], 0, 50));
                        $updatedDisplay = $this->collapseNameSpaces($parts['first'] . ' ' . $oldLast);
                        $didUpdate = true;
                    }
                    if ($didUpdate) {
                        $crmDisplay = $this->collapseNameSpaces($oldFirst . ' ' . $oldLast);
                        $notes .= "\n\n--- Enrichment: name ---\n";
                        $notes .= 'Previous name on record: '
                            . ($crmDisplay !== '' ? $this->sanitizeNoteLine($crmDisplay) : '(empty)')
                            . "\n";
                        $notes .= 'RocketReach name: ' . $this->sanitizeNoteLine($this->collapseNameSpaces($rrName)) . "\n";
                        $notes .= 'Updated to: ' . $this->sanitizeNoteLine($updatedDisplay) . "\n";
                    }
                }
            }
        }

        $companyInfo = [];
        if (!empty($company['industry'])) {
            $companyInfo[] = 'Industry: ' . $company['industry'];
        }
        if (!empty($company['employee_count'])) {
            $companyInfo[] = 'Employees: ' . $company['employee_count'];
        }
        if (!empty($company['location'])) {
            $companyInfo[] = 'Location: ' . $company['location'];
        }
        if ($companyInfo !== [] && strpos($notes, '--- Enriched Data ---') === false) {
            $notes .= "\n\n--- Enriched Data ---\n" . implode("\n", $companyInfo);
        }

        if ($notes !== ($originalContact['notes'] ?? '')) {
            $updateData['notes'] = $notes;
        }

        return $updateData;
    }

    /**
     * Check if enrichment is available for a contact
     *
     * @param array $contact Contact data
     * @return bool True if enrichment is possible
     */
    public function canEnrich(array $contact): bool
    {
        if (!empty($contact['email'])
            || !empty($contact['linkedin_profile'])
            || $this->normalizeTwitterHandle((string) ($contact['twitter_handle'] ?? '')) !== ''
            || (!empty($contact['first_name']) && !empty($contact['last_name']) && !empty($contact['company']))) {
            return true;
        }
        $id = (int) ($contact['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        return $this->collectEnrichmentLookups($id, $contact) !== [];
    }

    /**
     * Get enrichment statistics
     *
     * @return array Enrichment statistics
     */
    public function getEnrichmentStats(): array
    {
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_contacts,
                SUM(CASE WHEN enrichment_status = 'enriched' THEN 1 ELSE 0 END) as enriched_count,
                SUM(CASE WHEN enrichment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN enrichment_status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM contacts
        ");

        return [
            'total_contacts' => $stats['total_contacts'] ?? 0,
            'enriched_count' => $stats['enriched_count'] ?? 0,
            'failed_count' => $stats['failed_count'] ?? 0,
            'pending_count' => $stats['pending_count'] ?? 0,
            'enrichment_rate' => $stats['total_contacts'] > 0 ? 
                round(($stats['enriched_count'] / $stats['total_contacts']) * 100, 2) : 0
        ];
    }
}
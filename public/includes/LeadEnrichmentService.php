<?php
/**
 * Lead Enrichment Service
 * Best Jobs in TA - RocketReach Integration Service
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
require_once __DIR__ . '/../helpers/rocketreach/Endpoints/PersonEnrich.php';
require_once __DIR__ . '/../helpers/rocketreach/Endpoints/PeopleSearch.php';
require_once __DIR__ . '/../helpers/rocketreach/Endpoints/PersonLookup.php';
require_once __DIR__ . '/../helpers/rocketreach/RocketReachClient.php';

use RocketReach\SDK\RocketReachClient;
use RocketReach\SDK\Exceptions\ApiException;
use RocketReach\SDK\Exceptions\RateLimitException;
use RocketReach\SDK\Exceptions\NetworkException;

class LeadEnrichmentService
{
    private RocketReachClient $client;
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
     * @param string $strategy Enrichment strategy (email, linkedin, name_company, auto)
     * @return array Enrichment result
     * @throws Exception
     */
    public function enrichContact(int $contactId, string $strategy = 'auto'): array
    {
        if (!$this->enabled) {
            throw new Exception('RocketReach enrichment is not enabled or API key is missing');
        }
        
        // Get contact data
        $contact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
        if (!$contact) {
            throw new Exception('Contact not found');
        }
        
        // Skip contacts that are marked as not_found (prevents wasting API quota)
        if ($contact['enrichment_status'] === 'not_found') {
            return [
                'success' => false,
                'contact' => $contact,
                'message' => 'Contact previously marked as not found in RocketReach database'
            ];
        }
        
        // Check if already enriched recently
        if ($contact['enrichment_status'] === 'enriched' && 
            $contact['enriched_at'] && 
            strtotime($contact['enriched_at']) > (time() - 86400)) { // 24 hours
            return [
                'success' => true,
                'contact' => $contact,
                'message' => 'Contact already enriched recently'
            ];
        }
        
        // Increment attempt counter
        $this->db->update('contacts', [
            'enrichment_attempts' => ($contact['enrichment_attempts'] ?? 0) + 1,
            'enrichment_status' => 'processing',
            'enrichment_error' => null
        ], 'id = ?', [$contactId]);
        
        try {
            $enrichmentData = $this->performEnrichment($contact, $strategy);
            
            // Handle "not found" case
            if (isset($enrichmentData['not_found']) && $enrichmentData['not_found']) {
                $this->db->update('contacts', [
                    'enrichment_status' => 'not_found',
                    'enrichment_error' => $enrichmentData['message'] ?? 'Person not found in RocketReach database',
                    'updated_at' => getCurrentTimestamp()
                ], 'id = ?', [$contactId]);
                
                return [
                    'success' => false,
                    'message' => $enrichmentData['message'] ?? 'Person not found in RocketReach database',
                    'contact' => $contact
                ];
            }
            
            if ($enrichmentData) {
                // Update contact with enriched data
                $updateData = $this->mapEnrichmentData($enrichmentData, $contact);
                $updateData['enrichment_status'] = 'enriched';
                $updateData['enriched_at'] = getCurrentTimestamp();
                $updateData['enrichment_source'] = 'rocketreach';
                $updateData['enrichment_data'] = json_encode($enrichmentData);
                $updateData['updated_at'] = getCurrentTimestamp();

                $this->db->update('contacts', $updateData, 'id = ?', [$contactId]);
                
                // Get updated contact
                $updatedContact = $this->db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);

                return [
                    'success' => true,
                    'contact' => $updatedContact,
                    'enrichment_data' => $enrichmentData
                ];
            } else {
                throw new Exception('No enrichment data found');
            }
            
        } catch (Exception $e) {
            // Update contact with error status
            $this->db->update('contacts', [
                'enrichment_status' => 'failed',
                'enrichment_error' => $e->getMessage(),
                'updated_at' => getCurrentTimestamp()
            ], 'id = ?', [$contactId]);

            throw $e;
        }
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
            'enriched_contacts' => [],
            'errors' => []
        ];

        foreach ($contactIds as $contactId) {
            try {
                $result = $this->enrichContact($contactId, $strategy);
                $results['successful']++;
                $results['enriched_contacts'][] = [
                    'id' => $contactId,
                    'enrichment_status' => 'enriched',
                    'enriched_at' => $result['contact']['enriched_at']
                ];
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
     * Perform the actual enrichment using RocketReach SDK
     *
     * @param array $contact Contact data
     * @param string $strategy Enrichment strategy
     * @return array|null Enrichment data
     * @throws Exception
     */
    private function performEnrichment(array $contact, string $strategy): ?array
    {
        try {
        $personEnrich = $this->client->personEnrich();
        
            // Determine enrichment strategy
            switch ($strategy) {
                case 'email':
                    if (empty($contact['email'])) {
                        throw new Exception('Email required for email strategy');
                    }
                    $response = $personEnrich->email($contact['email'])->enrich();
                    break;
                    
                case 'linkedin':
                    if (empty($contact['linkedin_profile'])) {
                        throw new Exception('LinkedIn profile required for linkedin strategy');
                    }
                    $response = $personEnrich->linkedinUrl($contact['linkedin_profile'])->enrich();
                    break;
                    
                case 'name_company':
                    if (empty($contact['first_name']) || empty($contact['last_name']) || empty($contact['company'])) {
                        throw new Exception('Name and company required for name_company strategy');
                    }
                    $response = $personEnrich
                        ->name($contact['first_name'] . ' ' . $contact['last_name'])
                        ->currentEmployer($contact['company'])
                        ->enrich();
                    break;
                    
                case 'auto':
                default:
                    // Try different strategies in order of preference
                    if (!empty($contact['email'])) {
                        $response = $personEnrich->email($contact['email'])->enrich();
                    } elseif (!empty($contact['linkedin_profile'])) {
                        $response = $personEnrich->linkedinUrl($contact['linkedin_profile'])->enrich();
                    } elseif (!empty($contact['first_name']) && !empty($contact['last_name']) && !empty($contact['company'])) {
                        $response = $personEnrich
                            ->name($contact['first_name'] . ' ' . $contact['last_name'])
                            ->currentEmployer($contact['company'])
                            ->enrich();
                    } else {
                        throw new Exception('Insufficient data for enrichment. Need email, LinkedIn profile, or name+company');
                    }
                    break;
            }

            return $this->extractEnrichmentData($response);

        } catch (RateLimitException $e) {
            throw new Exception('RocketReach rate limit exceeded. Please try again later.');
        } catch (NetworkException $e) {
            throw new Exception('Network error connecting to RocketReach: ' . $e->getMessage());
        } catch (ApiException $e) {
            throw new Exception('RocketReach API error: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('Enrichment failed: ' . $e->getMessage());
        }
    }

    /**
     * Extract enrichment data from RocketReach response
     *
     * @param mixed $response RocketReach response object
     * @return array Extracted enrichment data
     */
    private function extractEnrichmentData($response): array
    {
        $data = [];

        // Check if person was not found
        if (method_exists($response, 'getPerson') && $response->getPerson()) {
            $person = $response->getPerson();
            if (isset($person['not_found']) && $person['not_found']) {
                return ['not_found' => true, 'message' => $person['message'] ?? 'Person not found'];
            }
            
            $data['person'] = [
                'id' => $person['id'] ?? null,
                'name' => $person['name'] ?? null,
                'emails' => $person['emails'] ?? [],
                'phones' => $person['phones'] ?? [],
                'title' => $person['current_title'] ?? null,
                'location' => $person['location'] ?? null,
                'linkedin_url' => $person['linkedin_url'] ?? null
            ];
        }

        // Extract company data
        if (method_exists($response, 'getCompany') && $response->getCompany()) {
            $company = $response->getCompany();
            $data['company'] = [
                'id' => $company['id'] ?? null,
                'name' => $company['name'] ?? null,
                'domain' => $company['domain'] ?? null,
                'industry' => $company['industry'] ?? null,
                'employee_count' => $company['employee_count'] ?? null,
                'location' => $company['location'] ?? null
            ];
        }

        return $data;
    }

    /**
     * Map enrichment data to contact fields
     *
     * @param array $enrichmentData Enrichment data from RocketReach
     * @param array $originalContact Original contact data
     * @return array Mapped contact update data
     */
    private function mapEnrichmentData(array $enrichmentData, array $originalContact): array
    {
        $updateData = [];

        // Map person data
        if (isset($enrichmentData['person'])) {
            $person = $enrichmentData['person'];

            // Update email if not already present
            if (empty($originalContact['email']) && !empty($person['emails'])) {
                // Extract the email address from the first email object
                $firstEmail = is_array($person['emails'][0]) ? $person['emails'][0] : $person['emails'][0];
                $emailAddress = is_array($firstEmail) ? ($firstEmail['email'] ?? '') : $firstEmail;
                if (!empty($emailAddress)) {
                    $updateData['email'] = sanitizeInput($emailAddress);
                }
            }

            // Update phone if not already present
            if (empty($originalContact['phone']) && !empty($person['phones'])) {
                // Extract the phone number from the first phone object
                $firstPhone = is_array($person['phones'][0]) ? $person['phones'][0] : $person['phones'][0];
                $phoneNumber = is_array($firstPhone) ? ($firstPhone['number'] ?? '') : $firstPhone;
                if (!empty($phoneNumber)) {
                    $updateData['phone'] = sanitizeInput($phoneNumber);
                }
            }

            // Update position if not already present
            if (empty($originalContact['position']) && !empty($person['title'])) {
                $updateData['position'] = sanitizeInput($person['title']);
            }

            // Update LinkedIn profile if not already present
            if (empty($originalContact['linkedin_profile']) && !empty($person['linkedin_url'])) {
                $updateData['linkedin_profile'] = sanitizeInput($person['linkedin_url']);
            }

            // Update address if not already present
            if (empty($originalContact['address']) && !empty($person['location'])) {
                $updateData['address'] = sanitizeInput($person['location']);
            }
        }

        // Map company data
        if (isset($enrichmentData['company'])) {
            $company = $enrichmentData['company'];

            // Update company name if not already present
            if (empty($originalContact['company']) && !empty($company['name'])) {
                $updateData['company'] = sanitizeInput($company['name']);
            }

            // Update website if not already present
            if (empty($originalContact['website']) && !empty($company['domain'])) {
                $updateData['website'] = 'https://' . sanitizeInput($company['domain']);
            }

            // Add company info to notes
            $notes = $originalContact['notes'] ?? '';
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

            if (!empty($companyInfo)) {
                $enrichmentNote = "\n\n--- Enriched Data ---\n" . implode("\n", $companyInfo);
                $updateData['notes'] = $notes . $enrichmentNote;
            }
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
        return !empty($contact['email']) || 
               !empty($contact['linkedin_profile']) || 
               (!empty($contact['first_name']) && !empty($contact['last_name']) && !empty($contact['company']));
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
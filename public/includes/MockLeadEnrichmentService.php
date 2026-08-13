<?php
/**
 * Mock LeadEnrichmentService for Production Testing
 * Sanctum CRM - Production Mock Service
 */

// Define CRM loaded constant if not already defined
if (!defined('CRM_LOADED')) {
    define('CRM_LOADED', true);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class MockLeadEnrichmentService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function canEnrich($contact) {
        $hasEmail = !empty($contact['email']);
        $hasLinkedIn = !empty($contact['linkedin_profile']);
        $hasNameCompany = !empty($contact['first_name']) && !empty($contact['last_name']) && !empty($contact['company']);
        if ($hasEmail || $hasLinkedIn || $hasNameCompany) {
            return true;
        }
        // Sidecar alts (accepted-merge emails) count when primary card is thin
        $id = (int) ($contact['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        try {
            require_once __DIR__ . '/ContactDataStore.php';
            $store = new ContactDataStore($this->db);
            $store->ensureSchema();
            foreach ($store->listFacts($id, 'email') as $fact) {
                $v = trim((string) ($fact['value'] ?? ''));
                if ($v !== '' && str_contains($v, '@')) {
                    return true;
                }
            }
            foreach ($store->listFacts($id, 'social') as $fact) {
                $label = strtolower((string) ($fact['label'] ?? ''));
                $v = (string) ($fact['value'] ?? '');
                if ($label === 'linkedin' || stripos($v, 'linkedin.com') !== false) {
                    return true;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
    
    public function getEnrichmentStatus($contactId) {
        try {
            $contact = $this->db->fetchOne("SELECT enrichment_status, enrichment_attempts, enrichment_error, enriched_at, enrichment_source FROM contacts WHERE id = ?", [$contactId]);
            
            if (!$contact) {
                throw new Exception("Contact not found.");
            }
            
            return [
                'status' => $contact['enrichment_status'] ?? 'pending',
                'attempts' => $contact['enrichment_attempts'] ?? 0,
                'last_error' => $contact['enrichment_error'] ?? null,
                'enriched_at' => $contact['enriched_at'] ?? null,
                'source' => $contact['enrichment_source'] ?? null
            ];
        } catch (Exception $e) {
            // Return default values if there's an error
            return [
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => null,
                'enriched_at' => null,
                'source' => null
            ];
        }
    }
    
    public function getEnrichmentStats() {
        try {
            $totalContacts = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts")['count'];
            $enriched = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'enriched'")['count'];
            $failed = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'failed'")['count'];
            $pending = $this->db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE enrichment_status = 'pending'")['count'];
            
            $enrichmentRate = $totalContacts > 0 ? round(($enriched / $totalContacts) * 100, 2) : 0;
            
            return [
                'total_contacts' => $totalContacts,
                'enriched_count' => $enriched,
                'failed_count' => $failed,
                'pending_count' => $pending,
                'enrichment_rate' => $enrichmentRate,
                'api_configured' => false,
                'message' => 'RocketReach API key not configured. Add your API key in Settings to enable lead enrichment.'
            ];
        } catch (Exception $e) {
            return [
                'total_contacts' => 0,
                'enriched_count' => 0,
                'failed_count' => 0,
                'pending_count' => 0,
                'enrichment_rate' => 0,
                'api_configured' => false,
                'message' => 'RocketReach API key not configured. Add your API key in Settings to enable lead enrichment.'
            ];
        }
    }
    
    public function enrichContact($contactId, $strategy = 'auto') {
        // Check if API key is present but client is not available
        $settings = $this->db->fetchOne("SELECT rocketreach_api_key FROM settings WHERE id = 1");
        $hasApiKey = !empty($settings['rocketreach_api_key']);
        
        if ($hasApiKey) {
            throw new Exception("RocketReach API key is configured but the RocketReach client is not available. Please ensure all dependencies are installed.");
        } else {
            throw new Exception("RocketReach API key not configured. Please add your RocketReach API key in Settings to enable lead enrichment.");
        }
    }
    
    public function enrichContacts($contactIds, $strategy = 'auto') {
        // Check if API key is present but client is not available
        $settings = $this->db->fetchOne("SELECT rocketreach_api_key FROM settings WHERE id = 1");
        $hasApiKey = !empty($settings['rocketreach_api_key']);
        
        if ($hasApiKey) {
            throw new Exception("RocketReach API key is configured but the RocketReach client is not available. Please ensure all dependencies are installed.");
        } else {
            throw new Exception("RocketReach API key not configured. Please add your RocketReach API key in Settings to enable lead enrichment.");
        }
    }
}
?>

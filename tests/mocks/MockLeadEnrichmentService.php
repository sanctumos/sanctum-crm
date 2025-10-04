<?php
/**
 * Mock LeadEnrichmentService for Testing
 * Best Jobs in TA - Test Mock
 */

class MockLeadEnrichmentService {
    private $db;
    
    public function __construct() {
        // Use production database for integration tests
        $this->db = new SQLite3(__DIR__ . '/../../db/crm.db');
    }
    
    public function canEnrich($contact) {
        // Check if contact has sufficient data for enrichment
        $hasEmail = !empty($contact['email']);
        $hasLinkedIn = !empty($contact['linkedin_profile']);
        $hasNameCompany = !empty($contact['first_name']) && !empty($contact['last_name']) && !empty($contact['company']);
        
        return $hasEmail || $hasLinkedIn || $hasNameCompany;
    }
    
    public function getEnrichmentStatus($contactId) {
        try {
            $stmt = $this->db->prepare("SELECT enrichment_status, enrichment_attempts, enrichment_error, enriched_at, enrichment_source FROM contacts WHERE id = ?");
            $stmt->bindValue(1, $contactId);
            $result = $stmt->execute();
            $contact = $result->fetchArray(SQLITE3_ASSOC);
            
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
        $totalContacts = $this->db->querySingle("SELECT COUNT(*) FROM contacts");
        $enriched = $this->db->querySingle("SELECT COUNT(*) FROM contacts WHERE enrichment_status = 'enriched'");
        $failed = $this->db->querySingle("SELECT COUNT(*) FROM contacts WHERE enrichment_status = 'failed'");
        $pending = $this->db->querySingle("SELECT COUNT(*) FROM contacts WHERE enrichment_status = 'pending'");
        
        $enrichmentRate = $totalContacts > 0 ? round(($enriched / $totalContacts) * 100, 2) : 0;
        
        return [
            'total_contacts' => $totalContacts,
            'enriched_count' => $enriched,
            'failed_count' => $failed,
            'pending_count' => $pending,
            'enrichment_rate' => $enrichmentRate
        ];
    }
    
    public function enrichContact($contactId, $strategy = 'auto') {
        // Mock enrichment - just update status
        $stmt = $this->db->prepare("
            UPDATE contacts SET 
                enrichment_status = 'enriched',
                enriched_at = ?,
                enrichment_source = 'mock',
                enrichment_attempts = 1,
                enrichment_data = ?
            WHERE id = ?
        ");
        $stmt->bindValue(1, date('Y-m-d H:i:s'));
        $stmt->bindValue(2, json_encode(['mock' => true, 'strategy' => $strategy]));
        $stmt->bindValue(3, $contactId);
        $stmt->execute();
        
        // Get updated contact
        $stmt = $this->db->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->bindValue(1, $contactId);
        $result = $stmt->execute();
        $contact = $result->fetchArray(SQLITE3_ASSOC);
        
        return [
            'contact' => $contact,
            'enrichment_data' => ['person' => [], 'company' => []]
        ];
    }
    
    public function enrichContacts($contactIds, $strategy = 'auto') {
        $successful = 0;
        $failed = 0;
        $enrichedContacts = [];
        $errors = [];
        
        foreach ($contactIds as $contactId) {
            try {
                $this->enrichContact($contactId, $strategy);
                $enrichedContacts[] = [
                    'id' => $contactId,
                    'enrichment_status' => 'enriched',
                    'enriched_at' => date('Y-m-d H:i:s')
                ];
                $successful++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = ['contact_id' => $contactId, 'error' => $e->getMessage()];
            }
        }
        
        return [
            'successful' => $successful,
            'failed' => $failed,
            'enriched_contacts' => $enrichedContacts,
            'errors' => $errors
        ];
    }
}
?>

<?php
/**
 * API v1 resource handler — extracted from index.php (behavior-compatible).
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../../../includes/ContactCreateInput.php';

function handleContacts($method, $id, $input, $auth, $action = null) {
    debugLog("[DEBUG] handleContacts ENTRY: method=$method id=$id action=$action input=" . json_encode($input));
    $db = Database::getInstance();
    
    // Special case: convert action
    if ($action === 'convert') {
        debugLog("[DEBUG] convert action: id=$id");
        if (!$id) {
            debugLog("[ERROR] convert: missing id");
            http_response_code(400);
            echo json_encode([
                'error' => 'Contact ID required for convert',
                'code' => 400
            ]);
            return;
        }
        $existing = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
        if (!$existing) {
            debugLog("[ERROR] convert: contact not found id=$id");
            http_response_code(404);
            echo json_encode([
                'error' => 'Contact not found',
                'code' => 404
            ]);
            return;
        }
        $updateData = [
            'contact_type' => 'customer',
            'contact_status' => 'active',
            'first_purchase_date' => date('Y-m-d'),
            'updated_at' => getCurrentTimestamp()
        ];
        $db->update('contacts', $updateData, 'id = ?', [$id]);
        $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
        debugLog("[DEBUG] convert: success id=$id");
        http_response_code(200);
        echo json_encode($contact);
        return;
    }

    // Special case: enrich action
    if ($action === 'enrich') {
        debugLog("[DEBUG] enrich action: id=$id");
        if (!$id) {
            debugLog("[ERROR] enrich: missing id");
            http_response_code(400);
            echo json_encode([
                'error' => 'Contact ID required for enrichment',
                'code' => 400
            ]);
            return;
        }
        
        try {
            $enrichmentService = new EnrichmentService();
            $strategy = $input['strategy'] ?? 'auto';
            $result = $enrichmentService->enrichContact($id, $strategy);
            $outcome = $result['outcome'] ?? ($result['success'] ? 'enriched' : 'error');

            if ($outcome === 'skipped') {
                debugLog("[DEBUG] enrich: skipped id=$id strategy=$strategy");
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'outcome' => 'skipped',
                    'message' => $result['message'] ?? 'Skipped',
                    'contact' => $result['contact'],
                    'enrichment_data' => $result['enrichment_data'] ?? null,
                ]);
                return;
            }

            if (empty($result['success'])) {
                debugLog("[DEBUG] enrich: not success id=$id outcome=$outcome");
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'outcome' => $outcome,
                    'error' => $result['message'] ?? 'Enrichment failed',
                    'contact' => $result['contact'] ?? null,
                ]);
                return;
            }

            debugLog("[DEBUG] enrich: enriched id=$id strategy=$strategy");
            http_response_code(200);
            $payload = [
                'success' => true,
                'outcome' => 'enriched',
                'contact' => $result['contact'],
                'enrichment_data' => $result['enrichment_data'] ?? null,
            ];
            if (!empty($result['enrichment_raw'])) {
                $payload['enrichment_raw'] = $result['enrichment_raw'];
            }
            echo json_encode($payload);
        } catch (Exception $e) {
            debugLog("[ERROR] enrich: failed id=$id error=" . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
                'code' => 500
            ]);
        }
        return;
    }

    // Special case: enrichment-status action
    if ($action === 'enrichment-status') {
        debugLog("[DEBUG] enrichment-status action: id=$id");
        if (!$id) {
            debugLog("[ERROR] enrichment-status: missing id");
            http_response_code(400);
            echo json_encode([
                'error' => 'Contact ID required for enrichment status',
                'code' => 400
            ]);
            return;
        }
        
        try {
            $enrichmentService = new EnrichmentService();
            $status = $enrichmentService->getEnrichmentStatus($id);
            
            debugLog("[DEBUG] enrichment-status: success id=$id");
            http_response_code(200);
            echo json_encode($status);
        } catch (Exception $e) {
            debugLog("[ERROR] enrichment-status: failed id=$id error=" . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
                'code' => 500
            ]);
        }
        return;
    }
    
    // Special case: bulk-enrich action
    if ($action === 'bulk-enrich') {
        debugLog("[DEBUG] bulk-enrich action");
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'error' => 'Method not allowed for bulk enrichment',
                'code' => 405
            ]);
            return;
        }
        
        if (empty($input['contact_ids']) || !is_array($input['contact_ids'])) {
            http_response_code(400);
            echo json_encode([
                'error' => 'contact_ids array is required for bulk enrichment',
                'code' => 400
            ]);
            return;
        }
        
        try {
            $enrichmentService = new EnrichmentService();
            $strategy = $input['strategy'] ?? 'auto';
            $result = $enrichmentService->enrichContacts($input['contact_ids'], $strategy);
            
            debugLog("[DEBUG] bulk-enrich: success count=" . count($input['contact_ids']));
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'total_processed' => count($input['contact_ids']),
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'] ?? 0,
                'enriched_contacts' => $result['enriched_contacts'],
                'errors' => $result['errors']
            ]);
        } catch (Exception $e) {
            debugLog("[ERROR] bulk-enrich: failed error=" . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
                'code' => 500
            ]);
        }
        return;
    }

    // POST /contacts/bulk-delete — delete by contact_ids or by tag (Outscraper batch nuke)
    if ($action === 'bulk-delete') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
            return;
        }
        if (empty($input['confirm'])) {
            http_response_code(400);
            echo json_encode([
                'error' => 'confirm=true required for bulk delete',
                'code' => 400,
            ]);
            return;
        }

        $tagService = new ContactTagService($db);
        $ids = [];
        $tagUsed = '';
        if (!empty($input['tag'])) {
            $tagUsed = $tagService->normalizeTag((string) $input['tag']);
            if ($tagUsed === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid tag', 'code' => 400]);
                return;
            }
            $ids = $tagService->listContactIdsWithTag($tagUsed, (int) ($input['limit'] ?? 500));
        } elseif (!empty($input['contact_ids']) && is_array($input['contact_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $input['contact_ids'])));
            $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
            if (count($ids) > 500) {
                http_response_code(400);
                echo json_encode(['error' => 'Max 500 contact_ids per request', 'code' => 400]);
                return;
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'error' => 'tag or contact_ids required',
                'code' => 400,
            ]);
            return;
        }

        $deleted = [];
        $missing = [];
        foreach ($ids as $cid) {
            $existing = $db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$cid]);
            if (!$existing) {
                $missing[] = $cid;
                continue;
            }
            if ($db->delete('contacts', 'id = ?', [$cid])) {
                $deleted[] = $cid;
                if (function_exists('crm_dispatch_webhook')) {
                    crm_dispatch_webhook('contact.deleted', [
                        'contact_id' => $cid,
                        'contact' => $existing,
                    ]);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'tag' => $tagUsed !== '' ? $tagUsed : null,
            'deleted_count' => count($deleted),
            'deleted_ids' => $deleted,
            'missing_ids' => $missing,
        ]);
        return;
    }

    // GET /contacts/tags — tag catalog (distinct tags + usage counts)
    if ($action === 'tags' && !$id) {
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
            return;
        }
        $tagService = new ContactTagService($db);
        $tags = $tagService->listCatalog();
        echo json_encode([
            'tags' => $tags,
            'count' => count($tags),
        ]);
        return;
    }

    // /contacts/{id}/tags — list or attach tags on one contact
    if ($action === 'tags' && $id) {
        $contactId = (int) $id;
        $existing = $db->fetchOne('SELECT id FROM contacts WHERE id = ?', [$contactId]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Contact not found', 'code' => 404]);
            return;
        }
        $tagService = new ContactTagService($db);
        if ($method === 'GET') {
            $tags = $tagService->listTags($contactId);
            echo json_encode([
                'contact_id' => $contactId,
                'tags' => $tags,
                'count' => count($tags),
            ]);
            return;
        }
        if ($method === 'POST') {
            $rawTag = is_array($input) ? ($input['tag'] ?? '') : '';
            $tag = $tagService->normalizeTag((string) $rawTag);
            if ($tag === '') {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Missing or invalid tag',
                    'code' => 400,
                ]);
                return;
            }
            $tagService->addTag($contactId, $tag);
            $tags = $tagService->listTags($contactId);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'contact_id' => $contactId,
                'tag' => $tag,
                'tags' => $tags,
            ]);
            return;
        }
        if ($method === 'DELETE') {
            $rawTag = is_array($input) ? ($input['tag'] ?? '') : '';
            if ($rawTag === '' && isset($_GET['tag'])) {
                $rawTag = (string) $_GET['tag'];
            }
            $tag = $tagService->normalizeTag((string) $rawTag);
            if ($tag === '') {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Missing or invalid tag',
                    'code' => 400,
                ]);
                return;
            }
            $tagService->removeTag($contactId, $tag);
            $tags = $tagService->listTags($contactId);
            echo json_encode([
                'success' => true,
                'contact_id' => $contactId,
                'tag' => $tag,
                'tags' => $tags,
            ]);
            return;
        }
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
        return;
    }

    // Import handling moved to handleImport function
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get specific contact
                $sql = "SELECT * FROM contacts WHERE id = ?";
                $contact = $db->fetchOne($sql, [$id]);
                
                if (!$contact) {
                    http_response_code(404);
                    echo json_encode([
                        'error' => 'Contact not found',
                        'code' => 404
                    ]);
                    return;
                }

                $tagService = new ContactTagService($db);
                $contact['tags'] = $tagService->listTags((int) $contact['id']);
                
                echo json_encode($contact);
            } else {
                // List contacts with optional filtering and pagination
                $where = "1=1";
                $params = [];
                
                if (isset($_GET['type']) && (string) $_GET['type'] !== '') {
                    $where .= " AND contact_type = ?";
                    $params[] = $_GET['type'];
                }
                
                if (isset($_GET['status']) && (string) $_GET['status'] !== '') {
                    $where .= " AND contact_status = ?";
                    $params[] = $_GET['status'];
                }
                
                if (isset($_GET['enrichment_status']) && (string) $_GET['enrichment_status'] !== '') {
                    if ($_GET['enrichment_status'] === 'null') {
                        $where .= " AND COALESCE(NULLIF(TRIM(enrichment_status), ''), '') != 'enriched'";
                    } else {
                        $where .= " AND enrichment_status = ?";
                        $params[] = $_GET['enrichment_status'];
                    }
                } elseif (!empty($_GET['needs_enrichment']) && $_GET['needs_enrichment'] !== '0' && $_GET['needs_enrichment'] !== 'false') {
                    // Bulk-enrich pool: omit finished states (use filters to retry failed / not_found)
                    $where .= " AND (enrichment_status IS NULL OR enrichment_status = '' OR enrichment_status NOT IN ('enriched','failed','not_found','processing'))";
                }
                
                if (isset($_GET['source']) && (string) $_GET['source'] !== '') {
                    if ($_GET['source'] === 'null') {
                        $where .= " AND (source IS NULL OR source = '')";
                    } else {
                        $where .= " AND source = ?";
                        $params[] = $_GET['source'];
                    }
                }

                if (!empty($_GET['email'])) {
                    $where .= " AND LOWER(email) = LOWER(?)";
                    $params[] = trim((string) $_GET['email']);
                }

                if (!empty($_GET['q'])) {
                    $needle = '%' . trim((string) $_GET['q']) . '%';
                    $where .= " AND (
                        first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?
                        OR (TRIM(COALESCE(first_name,'') || ' ' || COALESCE(last_name,'')) LIKE ?)
                    )";
                    array_push($params, $needle, $needle, $needle, $needle, $needle);
                }

                if (!empty($_GET['tag'])) {
                    $tagService = new ContactTagService($db);
                    $tagFilter = $tagService->normalizeTag((string) $_GET['tag']);
                    if ($tagFilter !== '') {
                        $where .= " AND contacts.id IN (SELECT contact_id FROM contact_tags WHERE tag = ?)";
                        $params[] = $tagFilter;
                    }
                }
                
                // Handle pagination (only numeric page — avoids bogus offsets if ?page=contacts leaks onto the API URL)
                $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 50;
                $offset = 0;
                
                if (isset($_GET['limit']) && isset($_GET['page']) && is_numeric($_GET['page'])) {
                    $page = max(1, (int) $_GET['page']);
                    $limit = max(1, min(500, (int) $_GET['limit']));
                    $offset = ($page - 1) * $limit;
                } elseif (isset($_GET['offset'])) {
                    $offset = max(0, (int) $_GET['offset']);
                }
                
                // Get total count
                $countSql = "SELECT COUNT(*) as total FROM contacts WHERE $where";
                $totalResult = $db->fetchOne($countSql, $params);
                $total = $totalResult['total'];
                
                // Get contacts with limit and offset
                $sql = "SELECT * FROM contacts WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                $contacts = $db->fetchAll($sql, $params);

                $tagService = new ContactTagService($db);
                $tagMap = $tagService->listTagsForContactIds(array_column($contacts, 'id'));
                foreach ($contacts as &$row) {
                    $row['tags'] = $tagMap[(int) $row['id']] ?? [];
                }
                unset($row);
                
                echo json_encode([
                    'contacts' => $contacts,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
            }
            break;
            
        case 'POST':
            debugLog("contacts POST input=" . json_encode($input));
            // Normalize webhook / form-tool shapes onto first_name + last_name
            // (strict clients unchanged; nested/alias payloads become usable).
            $input = ContactCreateInput::normalize(is_array($input) ? $input : []);
            // Optional query overrides for webhook URLs that cannot set body fields
            // (e.g. ?source=wix&api_key=…). Only fills empty keys.
            foreach (['source', 'contact_type', 'contact_status'] as $qKey) {
                if (empty($input[$qKey]) && !empty($_GET[$qKey]) && is_scalar($_GET[$qKey])) {
                    $input[$qKey] = (string) $_GET[$qKey];
                }
            }
            // Create new contact
            $required = ['first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'error' => "Missing required field: $field",
                        'code' => 400
                    ]);
                    return;
                }
            }
            
            // Validate email (only if provided)
            if (!empty($input['email']) && !validateEmail($input['email'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid email address',
                    'code' => 400
                ]);
                return;
            }
            
            // Check if email already exists (only if provided)
            if (!empty($input['email'])) {
                $existing = $db->fetchOne("SELECT id FROM contacts WHERE email = ?", [$input['email']]);
                if ($existing) {
                    http_response_code(409);
                    echo json_encode([
                        'error' => 'Contact with this email already exists',
                        'code' => 409,
                        'existing_contact_id' => (int) $existing['id']
                    ]);
                    return;
                }
            }
            
            // Prepare contact data with sanitization
            $contactData = [
                'first_name' => sanitizeInput($input['first_name']),
                'last_name' => sanitizeInput($input['last_name']),
                'email' => !empty($input['email']) ? $input['email'] : null,
                'phone' => sanitizeInput($input['phone'] ?? null),
                'company' => sanitizeInput($input['company'] ?? null),
                'address' => sanitizeInput($input['address'] ?? null),
                'city' => sanitizeInput($input['city'] ?? null),
                'state' => sanitizeInput($input['state'] ?? null),
                'zip_code' => sanitizeInput($input['zip_code'] ?? null),
                'country' => sanitizeInput($input['country'] ?? null),
                'evm_address' => !empty($input['evm_address']) && validateEVMAddress($input['evm_address']) ? $input['evm_address'] : null,
                'twitter_handle' => sanitizeInput($input['twitter_handle'] ?? null),
                'linkedin_profile' => sanitizeInput($input['linkedin_profile'] ?? null),
                'telegram_username' => sanitizeInput($input['telegram_username'] ?? null),
                'discord_username' => sanitizeInput($input['discord_username'] ?? null),
                'github_username' => sanitizeInput($input['github_username'] ?? null),
                'website' => sanitizeInput($input['website'] ?? null),
                'contact_type' => sanitizeInput($input['contact_type'] ?? 'lead'),
                'contact_status' => sanitizeInput($input['contact_status'] ?? 'new'),
                'source' => sanitizeInput($input['source'] ?? null),
                'assigned_to' => $input['assigned_to'] ?? null,
                'notes' => sanitizeInput($input['notes'] ?? null)
            ];
            
            $contactId = $db->insert('contacts', $contactData);
            
            // Get the created contact
            $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$contactId]);
            
            crm_dispatch_webhook('contact.created', ['contact' => $contact]);
            
            http_response_code(201);
            echo json_encode($contact);
            exit; // Prevent any further output
            
        case 'PUT':
            if (!$id) {
                debugLog("contact PUT: missing id");
                http_response_code(400);
                echo json_encode([
                    'error' => 'Contact ID required for update',
                    'code' => 400
                ]);
                return;
            }
            
            // Check if contact exists
            debugLog("contact PUT: checking existence for id=$id");
            $existing = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
            debugLog("contact PUT: existence result=" . json_encode($existing));
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Contact not found',
                    'code' => 404
                ]);
                return;
            }
            
            // Handle special convert action
            if (isset($action) && $action === 'convert') {
                debugLog("contact convert: id=$id action=$action");
                $updateData = [
                    'contact_type' => 'customer',
                    'contact_status' => 'active',
                    'first_purchase_date' => date('Y-m-d'),
                    'updated_at' => getCurrentTimestamp()
                ];
                
                debugLog("contact convert update data=" . json_encode($updateData));
                
                $result = $db->update('contacts', $updateData, 'id = :id', ['id' => $id]);
                
                debugLog("contact convert result=$result");
                
                $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
                debugLog("contact convert final contact=" . json_encode($contact));
                crm_dispatch_webhook('contact.updated', ['contact' => $contact]);
                echo json_encode($contact);
                return;
            }
            
            // Regular update
            $updateData = array_intersect_key($input, array_flip([
                'first_name', 'last_name', 'email', 'phone', 'company', 'address',
                'city', 'state', 'zip_code', 'country', 'evm_address', 'twitter_handle',
                'linkedin_profile', 'telegram_username', 'discord_username', 'github_username',
                'website', 'contact_type', 'contact_status', 'source', 'assigned_to', 'notes'
            ]));
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No valid data to update',
                    'code' => 400
                ]);
                return;
            }
            
            $updateData['updated_at'] = getCurrentTimestamp();
            
            debugLog("contact update data=" . json_encode($updateData) . " id=$id");
            
            $result = $db->update('contacts', $updateData, 'id = :id', ['id' => $id]);
            
            debugLog("contact update result=$result");
            
            $contact = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
            crm_dispatch_webhook('contact.updated', ['contact' => $contact]);
            echo json_encode([
                'success' => true,
                'contact' => $contact
            ]);
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Contact ID required for deletion',
                    'code' => 400
                ]);
                return;
            }
            
            $existing = $db->fetchOne("SELECT * FROM contacts WHERE id = ?", [$id]);
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Contact not found',
                    'code' => 404
                ]);
                return;
            }
            
            $deleted = $db->delete('contacts', 'id = ?', [$id]);
            
            if ($deleted) {
                crm_dispatch_webhook('contact.deleted', [
                    'contact_id' => (int) $id,
                    'contact' => $existing,
                ]);
                http_response_code(204);
                exit; // Ensure no content is sent for 204 response
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Contact not found',
                    'code' => 404
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'error' => 'Method not allowed',
                'code' => 405
            ]);
    }
}

/**
 * Handle deals endpoints
 */


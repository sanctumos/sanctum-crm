<?php
/**
 * API v1 resource handler — extracted from index.php (behavior-compatible).
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

function handleDeals($method, $id, $input, $auth) {
    debugLog("handleDeals method=$method id=$id input=" . json_encode($input));
    $db = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $sql = "SELECT d.*,
                    TRIM(COALESCE(c.first_name, '') || ' ' || COALESCE(c.last_name, '')) AS contact_name,
                    c.email AS contact_email,
                    TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')) AS assigned_to_name
                    FROM deals d
                    LEFT JOIN contacts c ON c.id = d.contact_id
                    LEFT JOIN users u ON u.id = d.assigned_to
                    WHERE d.id = ?";
                $deal = $db->fetchOne($sql, [$id]);
                
                if (!$deal) {
                    http_response_code(404);
                    echo json_encode([
                        'error' => 'Deal not found',
                        'code' => 404
                    ]);
                    return;
                }
                
                echo json_encode($deal);
            } else {
                $sql = "SELECT d.*,
                    TRIM(COALESCE(c.first_name, '') || ' ' || COALESCE(c.last_name, '')) AS contact_name,
                    c.email AS contact_email,
                    TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')) AS assigned_to_name
                    FROM deals d
                    LEFT JOIN contacts c ON c.id = d.contact_id
                    LEFT JOIN users u ON u.id = d.assigned_to
                    ORDER BY d.created_at DESC";
                $deals = $db->fetchAll($sql);
                
                echo json_encode([
                    'deals' => $deals,
                    'count' => count($deals)
                ]);
            }
            break;
            
        case 'POST':
            debugLog("deals POST input=" . json_encode($input));
            if (empty($input['title']) || empty($input['contact_id'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Title and contact_id are required',
                    'code' => 400
                ]);
                return;
            }
            
            $dealData = [
                'title' => $input['title'],
                'contact_id' => $input['contact_id'],
                'amount' => $input['amount'] ?? null,
                'stage' => $input['stage'] ?? 'prospecting',
                'probability' => $input['probability'] ?? 0,
                'expected_close_date' => $input['expected_close_date'] ?? null,
                'assigned_to' => $input['assigned_to'] ?? null,
                'description' => $input['description'] ?? null
            ];
            
            $dealId = $db->insert('deals', $dealData);
            $deal = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$dealId]);
            
            crm_dispatch_webhook('deal.created', ['deal' => $deal]);
            
            http_response_code(201);
            echo json_encode($deal);
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Deal ID required for update',
                    'code' => 400
                ]);
                return;
            }
            
            $updateData = array_intersect_key($input, array_flip([
                'title', 'contact_id', 'amount', 'stage', 'probability',
                'expected_close_date', 'assigned_to', 'description'
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
            
            debugLog("deal update data=" . json_encode($updateData) . " id=$id");
            
            $result = $db->update('deals', $updateData, 'id = :id', ['id' => $id]);
            
            debugLog("deal update result=$result");
            
            $deal = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$id]);
            crm_dispatch_webhook('deal.updated', ['deal' => $deal]);
            echo json_encode($deal);
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Deal ID required for deletion',
                    'code' => 400
                ]);
                return;
            }
            
            $existingDeal = $db->fetchOne("SELECT * FROM deals WHERE id = ?", [$id]);
            if (!$existingDeal) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Deal not found',
                    'code' => 404
                ]);
                return;
            }
            
            $deleted = $db->delete('deals', 'id = ?', [$id]);
            
            if ($deleted) {
                crm_dispatch_webhook('deal.deleted', [
                    'deal_id' => (int) $id,
                    'deal' => $existingDeal,
                ]);
                http_response_code(204);
                exit; // Ensure no content is sent for 204 response
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Deal not found',
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
 * Handle users endpoints (admin only)
 */


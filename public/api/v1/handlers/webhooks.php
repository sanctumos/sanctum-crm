<?php
/**
 * API v1 resource handler — extracted from index.php (behavior-compatible).
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

function handleWebhooks($method, $id, $input, $auth, $action = null) {
    debugLog("[DEBUG] handleWebhooks ENTRY: method=$method id=$id action=$action input=" . json_encode($input));
    $db = Database::getInstance();
    
    // Special case: test action
    if ($action === 'test') {
        debugLog("[DEBUG] test action: id=$id");
        if (!$id) {
            debugLog("[ERROR] test: missing id");
            http_response_code(400);
            echo json_encode([
                'error' => 'Webhook ID required for test',
                'code' => 400
            ]);
            return;
        }
        $webhook = $db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$id]);
        if (!$webhook) {
            debugLog("[ERROR] test: webhook not found id=$id");
            http_response_code(404);
            echo json_encode([
                'error' => 'Webhook not found',
                'code' => 404
            ]);
            return;
        }
        // Deliver when WebhookDispatcher is present (dev lane); otherwise simulate (main).
        if (class_exists('WebhookDispatcher')) {
            $dispatcher = new WebhookDispatcher();
            $delivery = $dispatcher->sendTest((string) $webhook['url']);
            debugLog("[DEBUG] test: delivery=" . json_encode($delivery));
            http_response_code($delivery['success'] ? 200 : 502);
            echo json_encode([
                'success' => $delivery['success'],
                'message' => $delivery['success']
                    ? 'Test webhook sent successfully'
                    : 'Test webhook delivery failed',
                'http_code' => $delivery['http_code'],
                'error' => $delivery['error'],
            ]);
        } else {
            debugLog("[DEBUG] test: simulated success id=$id (no WebhookDispatcher)");
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Test webhook sent successfully'
            ]);
        }
        return;
    }
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $sql = "SELECT * FROM webhooks WHERE id = ? AND user_id = ?";
                $webhook = $db->fetchOne($sql, [$id, $auth->getUserId()]);
                
                if (!$webhook) {
                    http_response_code(404);
                    echo json_encode([
                        'error' => 'Webhook not found',
                        'code' => 404
                    ]);
                    return;
                }
                
                echo json_encode($webhook);
            } else {
                $sql = "SELECT * FROM webhooks WHERE user_id = ? ORDER BY created_at DESC";
                $webhooks = $db->fetchAll($sql, [$auth->getUserId()]);
                
                echo json_encode([
                    'webhooks' => $webhooks,
                    'count' => count($webhooks)
                ]);
            }
            break;
            
        case 'POST':
            if (empty($input['url']) || empty($input['events'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'URL and events are required',
                    'code' => 400
                ]);
                return;
            }
            
            // Validate URL
            if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid URL format',
                    'code' => 400
                ]);
                return;
            }
            
            $webhookData = [
                'user_id' => $auth->getUserId(),
                'url' => $input['url'],
                'events' => json_encode($input['events']),
                'is_active' => $input['is_active'] ?? 1
            ];
            
            $webhookId = $db->insert('webhooks', $webhookData);
            $webhook = $db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$webhookId]);
            
            http_response_code(201);
            echo json_encode($webhook);
            break;
            
        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Webhook ID required for update',
                    'code' => 400
                ]);
                return;
            }
            
            // Check if webhook belongs to user
            $existing = $db->fetchOne("SELECT * FROM webhooks WHERE id = ? AND user_id = ?", [$id, $auth->getUserId()]);
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Webhook not found',
                    'code' => 404
                ]);
                return;
            }
            
            $updateData = [];
            
            if (isset($input['url'])) {
                if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'Invalid URL format',
                        'code' => 400
                    ]);
                    return;
                }
                $updateData['url'] = $input['url'];
            }
            
            if (isset($input['events'])) {
                $updateData['events'] = json_encode($input['events']);
            }
            
            if (isset($input['is_active'])) {
                $updateData['is_active'] = $input['is_active'];
            }
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No valid data to update',
                    'code' => 400
                ]);
                return;
            }
            
            $db->update('webhooks', $updateData, 'id = :id', ['id' => $id]);
            $webhook = $db->fetchOne("SELECT * FROM webhooks WHERE id = ?", [$id]);
            
            echo json_encode($webhook);
            break;
            
        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Webhook ID required for deletion',
                    'code' => 400
                ]);
                return;
            }
            
            // Check if webhook belongs to user
            $existing = $db->fetchOne("SELECT * FROM webhooks WHERE id = ? AND user_id = ?", [$id, $auth->getUserId()]);
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Webhook not found',
                    'code' => 404
                ]);
                return;
            }
            
            $deleted = $db->delete('webhooks', 'id = ?', [$id]);
            
            if ($deleted) {
                http_response_code(204);
                exit; // Ensure no content is sent for 204 response
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Webhook not found',
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

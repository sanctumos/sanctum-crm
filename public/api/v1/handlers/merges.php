<?php
/**
 * Merge candidates + contact merge API (Doc #919).
 *
 * GET    /api/v1/merges/candidates
 * GET    /api/v1/merges/candidates/{id}
 * POST   /api/v1/merges/candidates/accept
 * POST   /api/v1/merges/candidates/reject
 * POST   /api/v1/merges/candidates/flip   — swap keep/absorb on a pending candidate
 * POST   /api/v1/merges                    — direct merge (dry_run supported)
 * GET    /api/v1/contacts/{id}/data-runs
 * GET    /api/v1/contacts/{id}/data-runs/{runId}
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../../../includes/ContactMergeService.php';
require_once __DIR__ . '/../../../includes/ContactDataStore.php';

/**
 * @param string|null $action Path segment after /merges/ (candidates|stats|null)
 * @param string|null $sub    Next segment (candidate id | accept | reject)
 */
function handleMerges($method, $sub, $input, $auth, $action = null)
{
    $svc = new ContactMergeService();
    $actorId = null;
    if (method_exists($auth, 'getUser')) {
        $u = $auth->getUser();
        $actorId = isset($u['id']) ? (int) $u['id'] : null;
    }

    if ($action === 'candidates') {
        if ($method === 'GET' && $sub !== null && $sub !== '' && is_numeric($sub)) {
            $row = $svc->getCandidate((int) $sub);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Candidate not found', 'code' => 404]);
                return;
            }
            echo json_encode($row);
            return;
        }

        if ($method === 'GET' && ($sub === null || $sub === '')) {
            $filters = [
                'status' => $_GET['status'] ?? 'pending',
                'tier' => $_GET['tier'] ?? '',
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 50,
                'offset' => isset($_GET['offset']) ? (int) $_GET['offset'] : 0,
            ];
            if (isset($_GET['min_confidence']) && $_GET['min_confidence'] !== '') {
                $filters['min_confidence'] = (float) $_GET['min_confidence'];
            }
            $result = $svc->listCandidates($filters);
            $result['pending_high'] = $svc->pendingHighCount();
            $result['mass_accept_floor'] = ContactMergeService::MASS_ACCEPT_FLOOR;
            echo json_encode($result);
            return;
        }

        if ($method === 'POST' && $sub === 'accept') {
            $ids = $input['ids'] ?? $input['candidate_ids'] ?? [];
            if (!is_array($ids) || $ids === []) {
                http_response_code(400);
                echo json_encode(['error' => 'ids required', 'code' => 400]);
                return;
            }
            // High floor only when caller opts in (mass / require_high_tier).
            // Multi-id accept without those flags = reviewed selection; any pending tier OK.
            if (array_key_exists('require_high_tier', $input)) {
                $requireHigh = (bool) $input['require_high_tier'];
            } else {
                $requireHigh = !empty($input['mass']);
            }
            if (!empty($input['allow_below_floor'])) {
                $requireHigh = false;
            }
            $out = $svc->acceptCandidates(
                $ids,
                $actorId,
                $requireHigh,
                is_array($input['field_overrides'] ?? null) ? $input['field_overrides'] : []
            );
            http_response_code($out['accepted'] > 0 ? 200 : 422);
            echo json_encode($out);
            return;
        }

        if ($method === 'POST' && $sub === 'reject') {
            $ids = $input['ids'] ?? $input['candidate_ids'] ?? [];
            if (!is_array($ids) || $ids === []) {
                http_response_code(400);
                echo json_encode(['error' => 'ids required', 'code' => 400]);
                return;
            }
            echo json_encode($svc->rejectCandidates($ids, $actorId));
            return;
        }

        if ($method === 'POST' && ($sub === 'flip' || $sub === 'swap')) {
            $id = (int) ($input['id'] ?? $input['candidate_id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'id required', 'code' => 400]);
                return;
            }
            $out = $svc->swapCandidateSides($id);
            http_response_code(!empty($out['success']) ? 200 : 422);
            echo json_encode($out);
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Invalid merges/candidates request', 'code' => 400]);
        return;
    }

    if ($method === 'GET' && $action === 'stats') {
        $pending = $svc->listCandidates(['status' => 'pending', 'limit' => 1]);
        echo json_encode([
            'pending_total' => $pending['total'],
            'pending_high' => $svc->pendingHighCount(),
            'mass_accept_floor' => ContactMergeService::MASS_ACCEPT_FLOOR,
            'tiers' => [
                'high' => '>= 0.85',
                'medium' => '0.60–0.84',
                'low' => '0.40–0.59',
            ],
        ]);
        return;
    }

    // POST /merges — direct merge (dry_run supported)
    if ($method === 'POST' && ($action === null || $action === '')) {
        $survivorId = (int) ($input['survivor_id'] ?? 0);
        $mergeIds = $input['merge_ids'] ?? [];
        if (!is_array($mergeIds)) {
            $mergeIds = [];
        }
        $out = $svc->merge(
            $survivorId,
            $mergeIds,
            is_array($input['field_overrides'] ?? null) ? $input['field_overrides'] : [],
            !empty($input['dry_run']),
            $actorId
        );
        http_response_code(!empty($out['success']) ? 200 : 422);
        echo json_encode($out);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
}

/**
 * Attach data-runs under contacts resource.
 */
function handleContactDataRuns($method, $contactId, $runId, $auth)
{
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed', 'code' => 405]);
        return;
    }
    $db = Database::getInstance();
    $contact = $db->fetchOne('SELECT id FROM contacts WHERE id = ?', [(int) $contactId]);
    if (!$contact) {
        http_response_code(404);
        echo json_encode(['error' => 'Contact not found', 'code' => 404]);
        return;
    }
    $store = new ContactDataStore($db);
    $store->ensureSchema();
    if ($runId) {
        $run = $store->getRun((int) $contactId, (int) $runId);
        if (!$run) {
            http_response_code(404);
            echo json_encode(['error' => 'Run not found', 'code' => 404]);
            return;
        }
        echo json_encode($run);
        return;
    }
    $source = $_GET['source'] ?? null;
    echo json_encode([
        'runs' => $store->listRuns((int) $contactId, $source !== '' ? $source : null),
        'facts' => $store->listFacts((int) $contactId, $_GET['fact_type'] ?? null),
    ]);
}

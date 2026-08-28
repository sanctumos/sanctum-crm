<?php
/**
 * Len bridge — CRM logged-in user identity (username, first contact).
 */
declare(strict_types=1);

require_once __DIR__ . '/crm_session.php';
require_once __DIR__ . '/composer_message.php';

/**
 * @return array{crm_user_id:int,crm_username:string,crm_display_name:string}|null
 */
function len_bridge_chatter_from_crm_user(int $userId): ?array
{
    $user = crm_len_bridge_user_row($userId);
    if ($user === null || empty($user['username'])) {
        return null;
    }
    $username = (string) $user['username'];
    return [
        'crm_user_id' => $userId,
        'crm_username' => $username,
        'crm_display_name' => $username,
    ];
}

/** @deprecated alias for Broca plugins migrated from Tasks naming */
function len_bridge_chatter_from_tasks_user(int $userId): ?array
{
    return len_bridge_chatter_from_crm_user($userId);
}

function len_bridge_prior_message_count_for_crm_user(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM web_chat_messages m
        INNER JOIN web_chat_sessions s ON s.id = m.session_id
        WHERE CAST(json_extract(s.metadata, '$.crm_user_id') AS INTEGER) = ?
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) ($row['c'] ?? 0);
}

function len_bridge_prior_message_count_for_tasks_user(int $userId): int
{
    return len_bridge_prior_message_count_for_crm_user($userId);
}

/**
 * @param array<string, mixed> $sessionMeta
 * @return array{session_meta: array<string, mixed>, is_first_contact: bool}
 */
function len_bridge_prepare_chatter_context(int $userId, array $sessionMeta = []): array
{
    $chatter = len_bridge_chatter_from_crm_user($userId);
    if ($chatter === null) {
        return ['session_meta' => $sessionMeta, 'is_first_contact' => false];
    }
    $prior = len_bridge_prior_message_count_for_crm_user($userId);
    $isFirst = $prior === 0;
    $sessionMeta = array_merge($sessionMeta, $chatter);
    return ['session_meta' => $sessionMeta, 'is_first_contact' => $isFirst];
}

function len_bridge_is_first_contact_for_inbox_row(int $userId, int $messageId): bool
{
    if ($userId <= 0 || $messageId <= 0) {
        return false;
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT MIN(m.id) AS min_id
        FROM web_chat_messages m
        INNER JOIN web_chat_sessions s ON s.id = m.session_id
        WHERE CAST(json_extract(s.metadata, '$.crm_user_id') AS INTEGER) = ?
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) ($row['min_id'] ?? 0) === $messageId;
}

function len_bridge_session_belongs_to_crm_user(string $sessionId, int $userId): bool
{
    if ($sessionId === '' || $userId <= 0) {
        return false;
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT metadata FROM web_chat_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $meta = json_decode((string) ($row['metadata'] ?? ''), true);
    if (!is_array($meta)) {
        return false;
    }
    return (int) ($meta['crm_user_id'] ?? 0) === $userId;
}

function len_bridge_session_belongs_to_tasks_user(string $sessionId, int $userId): bool
{
    return len_bridge_session_belongs_to_crm_user($sessionId, $userId);
}

/**
 * @return array{items: list<array{role:string,text:string,timestamp:string,id:string}>, latest_response_at: ?string}
 */
function len_bridge_fetch_recent_history(string $sessionId, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT 'user' AS role, message AS text, timestamp, id
        FROM web_chat_messages
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("
        SELECT 'assistant' AS role, response AS text, timestamp, id
        FROM web_chat_responses
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    usort($items, static function ($a, $b) {
        return strcmp((string) ($a['timestamp'] ?? ''), (string) ($b['timestamp'] ?? ''));
    });

    $latestResponse = null;
    foreach ($items as $row) {
        if (($row['role'] ?? '') === 'assistant') {
            $latestResponse = (string) ($row['timestamp'] ?? null);
        }
    }

    if (count($items) > $limit) {
        $items = array_slice($items, -$limit);
    }

    $out = [];
    foreach ($items as $row) {
        $out[] = [
            'role' => (string) ($row['role'] ?? 'user'),
            'text' => (string) ($row['text'] ?? ''),
            'timestamp' => (string) ($row['timestamp'] ?? ''),
            'id' => (string) ($row['id'] ?? ''),
        ];
    }

    return ['items' => $out, 'latest_response_at' => $latestResponse];
}

function len_bridge_canonical_session_id(int $userId): string
{
    return 'session_crm_' . max(0, $userId);
}

/**
 * @return array{session_id:string,session_meta:array<string,mixed>}
 */
function len_bridge_ensure_user_session(int $userId): array
{
    $sessionId = len_bridge_canonical_session_id($userId);
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT id, metadata FROM web_chat_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $meta = [];
    if ($row) {
        $decoded = json_decode((string) ($row['metadata'] ?? ''), true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    } else {
        $meta = [
            'crm_user_id' => $userId,
            'platform_user_id' => 'crm:' . $userId,
        ];
        $ins = $pdo->prepare('INSERT INTO web_chat_sessions (id, created_at, last_active, metadata) VALUES (?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?)');
        $ins->execute([$sessionId, json_encode($meta)]);
    }
    $prepared = len_bridge_prepare_chatter_context($userId, $meta);
    return [
        'session_id' => $sessionId,
        'session_meta' => $prepared['session_meta'],
    ];
}

function len_bridge_fetch_user_recent_history(int $userId, int $limit = 6): array
{
    $ensured = len_bridge_ensure_user_session($userId);
    return len_bridge_fetch_recent_history($ensured['session_id'], $limit);
}

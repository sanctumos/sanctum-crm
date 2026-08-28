<?php
/**
 * Ask Len composer — inbound message + text attachment validation.
 */

declare(strict_types=1);

if (!defined('Q_BRIDGE_MAX_TEXT_ATTACHMENT_BYTES')) {
    define('Q_BRIDGE_MAX_TEXT_ATTACHMENT_BYTES', 512 * 1024);
}
if (!defined('Q_BRIDGE_MAX_ATTACHMENTS_PER_MESSAGE')) {
    define('Q_BRIDGE_MAX_ATTACHMENTS_PER_MESSAGE', 3);
}

/**
 * Store chat message body raw UTF-8 (escape on output only).
 */
function sanitize_chat_message_body(string $input): string
{
    return trim(str_replace("\0", '', $input));
}

/**
 * @param array<string, mixed> $input Decoded JSON body
 * @return array{message: string, caption: string, attachments: list<array<string, mixed>>, metadata: array<string, mixed>}
 */
function len_bridge_normalize_composer_payload(array $input): array
{
    $caption = sanitize_chat_message_body((string)($input['caption'] ?? ''));
    $rawMessage = isset($input['message']) ? (string)$input['message'] : '';
    $attachmentsIn = is_array($input['attachments'] ?? null) ? $input['attachments'] : [];

    $attachments = [];
    foreach ($attachmentsIn as $row) {
        if (!is_array($row)) {
            continue;
        }
        $kind = (string)($row['kind'] ?? 'text');
        if ($kind !== 'text') {
            throw new InvalidArgumentException('Only text attachments are supported');
        }
        $text = sanitize_chat_message_body((string)($row['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $sizeBytes = strlen($text);
        if ($sizeBytes > Q_BRIDGE_MAX_TEXT_ATTACHMENT_BYTES) {
            throw new InvalidArgumentException('Attachment exceeds size limit');
        }
        $filename = trim((string)($row['filename'] ?? 'pasted.txt'));
        if ($filename === '' || strlen($filename) > 120) {
            $filename = 'pasted-' . date('Ymd-His') . '.txt';
        }
        $attachments[] = [
            'id' => (string)($row['id'] ?? ('att-' . bin2hex(random_bytes(4)))),
            'kind' => 'text',
            'filename' => $filename,
            'mime_type' => 'text/plain',
            'size_bytes' => $sizeBytes,
            'text' => $text,
        ];
        if (count($attachments) > Q_BRIDGE_MAX_ATTACHMENTS_PER_MESSAGE) {
            throw new InvalidArgumentException('Too many attachments');
        }
    }

    if ($attachments !== []) {
        $blocks = [];
        foreach ($attachments as $i => $att) {
            $label = $att['filename'];
            $human = $att['size_bytes'] >= 1024
                ? round($att['size_bytes'] / 1024, 1) . ' KB'
                : $att['size_bytes'] . ' B';
            $blocks[] = '[Attached text ' . ($i + 1) . ': ' . $label . ' (' . $human . ")]\n" . $att['text'];
        }
        $message = ($caption !== '' ? $caption . "\n\n" : '') . implode("\n\n", $blocks);
    } elseif ($rawMessage !== '') {
        $message = sanitize_chat_message_body($rawMessage);
    } else {
        $message = $caption;
    }

    if ($message === '') {
        throw new InvalidArgumentException('Message is empty');
    }

    if (!validate_message($message)) {
        throw new InvalidArgumentException('Message exceeds length limits');
    }

    $metadata = [];
    if ($caption !== '') {
        $metadata['caption'] = $caption;
    }
    if ($attachments !== []) {
        $metadata['attachments'] = array_map(static function (array $att): array {
            return [
                'id' => $att['id'],
                'kind' => $att['kind'],
                'filename' => $att['filename'],
                'mime_type' => $att['mime_type'],
                'size_bytes' => $att['size_bytes'],
            ];
        }, $attachments);
        $metadata['attachment_count'] = count($attachments);
    }

    return [
        'message' => $message,
        'caption' => $caption,
        'attachments' => $attachments,
        'metadata' => $metadata,
    ];
}

/**
 * @param array<string, mixed> $metadata
 * @return array{caption: string, attachments: list<array<string, mixed>>}|null
 */
function len_bridge_display_payload_from_metadata(array $metadata): ?array
{
    $attachments = $metadata['attachments'] ?? null;
    if (!is_array($attachments) || $attachments === []) {
        $caption = trim((string)($metadata['caption'] ?? ''));
        return $caption !== '' ? ['caption' => $caption, 'attachments' => []] : null;
    }
    return [
        'caption' => trim((string)($metadata['caption'] ?? '')),
        'attachments' => $attachments,
    ];
}

<?php
/**
 * Inbound Wix / website form leads → CRM contacts.
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/ConfigManager.php';

final class WixLeadIngest
{
    private Database $db;
    private ConfigManager $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = ConfigManager::getInstance();
    }

    public function ingestSecret(): ?string
    {
        $secret = trim((string) $this->config->get('integrations', 'wix_lead_ingest_secret', ''));
        return $secret !== '' ? $secret : null;
    }

    public function verifyRequestSecret(string $provided): bool
    {
        $expected = $this->ingestSecret();
        if ($expected === null || $provided === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, body: array<string, mixed>}
     */
    public function handlePayload(array $payload, string $source = 'wix'): array
    {
        $flat = $this->flattenPayload($payload);
        $email = $this->pick($flat, ['email', 'e_mail', 'contact_email', 'email_address']);
        $first = $this->pick($flat, ['first_name', 'firstname', 'first', 'fname']);
        $last = $this->pick($flat, ['last_name', 'lastname', 'last', 'lname']);
        $fullName = $this->pick($flat, ['name', 'full_name', 'fullname', 'contact_name']);

        if ($first === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }

        if ($first === '') {
            $first = 'Website';
        }
        if ($last === '') {
            $last = 'Lead';
        }

        $phone = $this->pick($flat, ['phone', 'tel', 'mobile', 'phone_number']);
        $company = $this->pick($flat, ['company', 'business', 'organization']);
        $message = $this->pick($flat, ['message', 'comments', 'body', 'notes', 'inquiry', 'description']);
        $formName = $this->pick($flat, ['form_name', 'formname', 'form_id', 'form_title', 'submission_type']);
        $kind = $this->pick($flat, ['kind', 'form_kind', 'lead_type']);

        if ($email !== '' && !validateEmail($email)) {
            return [
                'ok' => false,
                'status' => 400,
                'body' => ['error' => 'Invalid email address', 'code' => 400],
            ];
        }

        $noteLines = [];
        if ($formName !== '') {
            $noteLines[] = 'Form: ' . $formName;
        }
        if ($kind !== '') {
            $noteLines[] = 'Kind: ' . $kind;
        }
        if ($message !== '') {
            $noteLines[] = 'Message: ' . $message;
        }
        $noteLines[] = 'Received: ' . gmdate('c');
        $notes = implode("\n", $noteLines);

        if ($email !== '') {
            $existing = $this->db->fetchOne('SELECT * FROM contacts WHERE email = ?', [$email]);
            if ($existing) {
                $mergedNotes = trim((string) ($existing['notes'] ?? ''));
                if ($mergedNotes !== '') {
                    $mergedNotes .= "\n\n---\n";
                }
                $mergedNotes .= $notes;
                $this->db->update('contacts', [
                    'notes' => sanitizeInput($mergedNotes),
                    'updated_at' => getCurrentTimestamp(),
                ], 'id = :id', ['id' => (int) $existing['id']]);
                $contact = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [(int) $existing['id']]);
                if (function_exists('crm_dispatch_webhook')) {
                    crm_dispatch_webhook('contact.updated', ['contact' => $contact]);
                }
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => [
                        'status' => 'updated',
                        'contact_id' => (int) $existing['id'],
                        'contact' => $contact,
                    ],
                ];
            }
        }

        $contactData = [
            'first_name' => sanitizeInput($first),
            'last_name' => sanitizeInput($last),
            'email' => $email !== '' ? $email : null,
            'phone' => sanitizeInput($phone !== '' ? $phone : null),
            'company' => sanitizeInput($company !== '' ? $company : null),
            'contact_type' => 'lead',
            'contact_status' => 'new',
            'source' => sanitizeInput($source),
            'notes' => sanitizeInput($notes),
        ];

        $contactId = $this->db->insert('contacts', $contactData);
        $contact = $this->db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$contactId]);
        if (function_exists('crm_dispatch_webhook')) {
            crm_dispatch_webhook('contact.created', ['contact' => $contact]);
        }

        return [
            'ok' => true,
            'status' => 201,
            'body' => [
                'status' => 'created',
                'contact_id' => (int) $contactId,
                'contact' => $contact,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function flattenPayload(array $payload): array
    {
        $out = [];
        $walker = function ($node, string $prefix = '') use (&$out, &$walker) {
            if (!is_array($node)) {
                if ($prefix !== '' && is_scalar($node)) {
                    $out[$prefix] = trim((string) $node);
                }
                return;
            }
            foreach ($node as $key => $value) {
                $k = is_int($key) ? $prefix : ($prefix === '' ? (string) $key : $prefix . '.' . $key);
                if (is_array($value)) {
                    $walker($value, $k);
                } elseif (is_scalar($value)) {
                    $out[$k] = trim((string) $value);
                    $out[(string) $key] = trim((string) $value);
                }
            }
        };
        $walker($payload);
        return $out;
    }

  /**
   * @param array<string, string> $flat
   * @param array<int, string> $keys
   */
    private function pick(array $flat, array $keys): string
    {
        foreach ($keys as $key) {
            foreach ($flat as $k => $v) {
                $norm = strtolower(preg_replace('/[^a-z0-9]+/', '_', (string) $k) ?? '');
                if ($norm === strtolower($key) || str_ends_with($norm, '_' . strtolower($key))) {
                    if ($v !== '') {
                        return $v;
                    }
                }
            }
            if (isset($flat[$key]) && $flat[$key] !== '') {
                return $flat[$key];
            }
        }
        return '';
    }
}

<?php
/**
 * Normalize inbound contact-create payloads for POST /api/v1/contacts.
 *
 * Accepts strict API shape (first_name / last_name) and common webhook /
 * form-tool shapes (name, nested objects, alternate key spellings).
 * Not provider-specific — Zapier, Make, Wix HTTP, site forms, etc.
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

final class ContactCreateInput
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $flat = self::flatten($input);

        $email = self::pick($flat, [
            'email', 'e_mail', 'contact_email', 'email_address', 'login_email',
            'primary_info_email', 'primaryemail', 'primary_email',
        ]);
        $first = self::pick($flat, [
            'first_name', 'firstname', 'first', 'fname', 'given_name',
            'info_name_first', 'name_first', 'contactinfo_name_first',
        ]);
        $last = self::pick($flat, [
            'last_name', 'lastname', 'last', 'lname', 'family_name', 'surname',
            'info_name_last', 'name_last', 'contactinfo_name_last',
        ]);
        $fullName = self::pick($flat, [
            'name', 'full_name', 'fullname', 'contact_name', 'display_name',
            'info_name_formatted', 'name_formatted',
        ]);

        if ($first === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $first = $parts[0] ?? '';
            if ($last === '') {
                $last = $parts[1] ?? '';
            }
        }

        if ($first === '') {
            $first = 'Website';
        }
        if ($last === '') {
            $last = 'Lead';
        }

        $phone = self::pick($flat, [
            'phone', 'tel', 'mobile', 'phone_number', 'primary_info_phone',
        ]);
        $company = self::pick($flat, ['company', 'business', 'organization', 'company_name']);
        $message = self::pick($flat, [
            'message', 'comments', 'body', 'notes', 'inquiry', 'description',
        ]);
        $formName = self::pick($flat, [
            'form_name', 'formname', 'form_id', 'form_title', 'submission_type',
        ]);

        $out = $input;
        $out['first_name'] = $first;
        $out['last_name'] = $last;
        if ($email !== '') {
            $out['email'] = $email;
        }
        if ($phone !== '' && empty($out['phone'])) {
            $out['phone'] = $phone;
        }
        if ($company !== '' && empty($out['company'])) {
            $out['company'] = $company;
        }

        $noteLines = [];
        if (!empty($out['notes']) && is_scalar($out['notes'])) {
            $noteLines[] = trim((string) $out['notes']);
        }
        if ($formName !== '') {
            $noteLines[] = 'Form: ' . $formName;
        }
        if ($message !== '' && stripos(implode("\n", $noteLines), $message) === false) {
            $noteLines[] = 'Message: ' . $message;
        }
        if ($noteLines !== []) {
            $out['notes'] = implode("\n", array_filter($noteLines, static fn($l) => $l !== ''));
        }

        if (empty($out['source'])) {
            $out['source'] = self::pick($flat, ['source', 'lead_source']) ?: null;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private static function flatten(array $payload): array
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
                $k = is_int($key)
                    ? $prefix
                    : ($prefix === '' ? (string) $key : $prefix . '.' . $key);
                if (is_array($value)) {
                    $walker($value, $k);
                } elseif (is_scalar($value)) {
                    $val = trim((string) $value);
                    $out[$k] = $val;
                    $out[(string) $key] = $val;
                    $norm = strtolower(preg_replace('/[^a-z0-9]+/', '_', (string) $key) ?? '');
                    if ($norm !== '') {
                        $out[$norm] = $val;
                    }
                    $normPath = strtolower(preg_replace('/[^a-z0-9]+/', '_', $k) ?? '');
                    if ($normPath !== '') {
                        $out[$normPath] = $val;
                    }
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
    private static function pick(array $flat, array $keys): string
    {
        foreach ($keys as $key) {
            $want = strtolower($key);
            foreach ($flat as $k => $v) {
                $norm = strtolower(preg_replace('/[^a-z0-9]+/', '_', (string) $k) ?? '');
                if ($norm === $want || str_ends_with($norm, '_' . $want)) {
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

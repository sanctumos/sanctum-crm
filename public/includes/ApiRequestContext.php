<?php
/**
 * Request correlation + light API contract helpers.
 * Sanctum CRM
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ApiRequestContext
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_CORRELATION_ID'] ?? '';
        $incoming = trim((string) $incoming);
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming)) {
            self::$requestId = $incoming;
        } else {
            self::$requestId = bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }

    public static function logError(string $message, array $context = []): void
    {
        $payload = array_merge([
            'request_id' => self::requestId(),
            'message' => $message,
        ], $context);
        error_log('CRM_API ' . json_encode($payload));
    }

    /** @param array<string,mixed> $body */
    public static function errorResponse(int $code, string $error, ?string $details = null): void
    {
        http_response_code($code);
        $out = [
            'error' => $error,
            'code' => $code,
            'request_id' => self::requestId(),
        ];
        if ($details !== null && $details !== '') {
            $out['details'] = $details;
        }
        self::logError($error, ['http_code' => $code, 'details' => $details]);
        echo json_encode($out);
    }
}

class ApiContract
{
    /**
     * @param array<string,mixed>|null $json
     * @param list<string> $requiredKeys
     */
    public static function hasKeys(?array $json, array $requiredKeys): bool
    {
        if (!is_array($json)) {
            return false;
        }
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $json)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed>|null $json */
    public static function contactsListOk(?array $json): bool
    {
        return self::hasKeys($json, ['contacts', 'total', 'limit', 'offset'])
            && is_array($json['contacts']);
    }

    /** @param array<string,mixed>|null $json */
    public static function dealsListOk(?array $json): bool
    {
        return self::hasKeys($json, ['deals', 'count'])
            && is_array($json['deals']);
    }

    /** @param array<string,mixed>|null $json */
    public static function usersListOk(?array $json): bool
    {
        return self::hasKeys($json, ['users', 'count'])
            && is_array($json['users']);
    }

    /** @param array<string,mixed>|null $json */
    public static function webhooksListOk(?array $json): bool
    {
        return self::hasKeys($json, ['webhooks', 'count'])
            && is_array($json['webhooks']);
    }

    /** @param array<string,mixed>|null $json */
    public static function reportsAnalyticsOk(?array $json): bool
    {
        return self::hasKeys($json, ['metrics', 'charts', 'analytics', 'range', 'empty'])
            && is_array($json['metrics'])
            && is_array($json['charts'])
            && is_array($json['analytics']);
    }

    /** @param array<string,mixed>|null $json */
    public static function dealEnrichedOk(?array $json): bool
    {
        return is_array($json)
            && array_key_exists('id', $json)
            && array_key_exists('contact_name', $json)
            && array_key_exists('assigned_to_name', $json);
    }
}


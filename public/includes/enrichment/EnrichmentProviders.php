<?php
/**
 * Enrichment provider capability flags + name constants.
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

final class EnrichmentProviders
{
    public const ROCKETREACH = 'rocketreach';
    public const APOLLO = 'apollo';

    public static function normalize(?string $provider): string
    {
        $p = strtolower(trim((string) $provider));
        return $p === self::APOLLO ? self::APOLLO : self::ROCKETREACH;
    }

    public static function label(string $provider): string
    {
        return self::normalize($provider) === self::APOLLO ? 'Apollo' : 'RocketReach';
    }
}

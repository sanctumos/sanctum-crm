<?php
/**
 * CRM UI skins — same lab as Sanctum Tasks / Docket.
 * Slugs: hey, ledger, brutalist, obsidian.
 * Resolution: ?preview_skin= → user.skin_slug → settings.default_skin_slug → hey.
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

function crmSkinAvailableSlugs(): array
{
    return ['hey', 'ledger', 'brutalist', 'obsidian'];
}

function crmSkinNormalizeSlug(?string $slug): ?string
{
    $s = strtolower(trim((string) $slug));
    return in_array($s, crmSkinAvailableSlugs(), true) ? $s : null;
}

function crmSkinMasterSlug(): string
{
    try {
        $db = Database::getInstance();
        $row = $db->fetchOne('SELECT default_skin_slug FROM settings WHERE id = 1');
        $raw = $row['default_skin_slug'] ?? null;
        return crmSkinNormalizeSlug(is_string($raw) ? $raw : null) ?? 'hey';
    } catch (Throwable $e) {
        return 'hey';
    }
}

function crmSkinUserOverrideSlug(?array $userRow): ?string
{
    if (!$userRow) {
        return null;
    }
    $raw = $userRow['skin_slug'] ?? null;
    if ($raw === null || $raw === '') {
        return null;
    }
    return crmSkinNormalizeSlug((string) $raw);
}

/**
 * Optional one-request preview (?preview_skin=hey|ledger|brutalist|obsidian).
 * Does not persist — for Skin Lab / design checks.
 */
function crmSkinPreviewSlug(): ?string
{
    if (!isset($_GET['preview_skin'])) {
        return null;
    }
    return crmSkinNormalizeSlug((string) $_GET['preview_skin']);
}

/** Effective skin for the current request. */
function crmSkinEffectiveSlug(?array $userRow = null): string
{
    $preview = crmSkinPreviewSlug();
    if ($preview !== null) {
        return $preview;
    }
    $override = crmSkinUserOverrideSlug($userRow);
    if ($override !== null) {
        return $override;
    }
    return crmSkinMasterSlug();
}

function crmSkinStylesheetHref(string $slug): string
{
    $slug = crmSkinNormalizeSlug($slug) ?? 'hey';
    return '/assets/css/skins/' . $slug . '.css?v=1';
}

/** Light skins paint a pale admin-nav; navbar-dark keeps a white hamburger → invisible. */
function crmSkinUsesLightNav(string $slug): bool
{
    return in_array($slug, ['hey', 'ledger'], true);
}

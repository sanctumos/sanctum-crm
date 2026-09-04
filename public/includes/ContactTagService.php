<?php
/**
 * Contact tags (many-to-many labels per contact).
 */

if (!defined('CRM_LOADED')) {
    die('Direct access not permitted');
}

class ContactTagService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        // Schema is owned by tools/migrate.php (baseline migration), not request path.
    }

    public function ensureSchema(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS contact_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact_id INTEGER NOT NULL,
                tag VARCHAR(64) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(contact_id, tag),
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
            )"
        );
        $this->db->query(
            "CREATE INDEX IF NOT EXISTS idx_contact_tags_tag ON contact_tags(tag)"
        );
        $this->db->query(
            "CREATE INDEX IF NOT EXISTS idx_contact_tags_contact_id ON contact_tags(contact_id)"
        );
    }

    public function normalizeTag(string $tag): string
    {
        $t = strtolower(trim($tag));
        $t = preg_replace('/[^a-z0-9_-]+/', '-', $t) ?? '';
        $t = trim($t, '-');
        return substr($t, 0, 64);
    }

    public function listTags(int $contactId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT tag FROM contact_tags WHERE contact_id = ? ORDER BY tag",
            [$contactId]
        );
        return array_values(array_map(static fn ($r) => (string) $r['tag'], $rows));
    }

    /** @return array<int, array<string>> */
    public function listTagsForContactIds(array $contactIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $contactIds)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT contact_id, tag FROM contact_tags WHERE contact_id IN ($placeholders) ORDER BY tag",
            $ids
        );
        $out = [];
        foreach ($rows as $row) {
            $cid = (int) $row['contact_id'];
            $out[$cid][] = (string) $row['tag'];
        }
        return $out;
    }

    public function addTag(int $contactId, string $tag): bool
    {
        $tag = $this->normalizeTag($tag);
        if ($tag === '') {
            return false;
        }
        try {
            $this->db->insert('contact_tags', [
                'contact_id' => $contactId,
                'tag' => $tag,
                'created_at' => getCurrentTimestamp(),
            ]);
            return true;
        } catch (Exception $e) {
            // UNIQUE(contact_id, tag) — already tagged
            return false;
        }
    }

    public function removeTag(int $contactId, string $tag): bool
    {
        $tag = $this->normalizeTag($tag);
        if ($tag === '') {
            return false;
        }
        $this->db->delete('contact_tags', 'contact_id = ? AND tag = ?', [$contactId, $tag]);
        return true;
    }

    public function countContactsWithTag(string $tag): int
    {
        $tag = $this->normalizeTag($tag);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM contact_tags WHERE tag = ?",
            [$tag]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** @return list<int> */
    public function listContactIdsWithTag(string $tag, int $limit = 500): array
    {
        $tag = $this->normalizeTag($tag);
        if ($tag === '') {
            return [];
        }
        $limit = max(1, min(2000, $limit));
        $rows = $this->db->fetchAll(
            "SELECT contact_id FROM contact_tags WHERE tag = ? ORDER BY contact_id ASC LIMIT {$limit}",
            [$tag]
        );
        return array_values(array_map(static fn ($r) => (int) $r['contact_id'], $rows));
    }

    /** @return list<array{tag: string, contact_count: int}> */
    public function listCatalog(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT tag, COUNT(*) AS contact_count FROM contact_tags GROUP BY tag ORDER BY tag"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'tag' => (string) $row['tag'],
                'contact_count' => (int) ($row['contact_count'] ?? 0),
            ];
        }
        return $out;
    }
}

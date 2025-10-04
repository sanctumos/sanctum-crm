<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// View Contact Page
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    renderHeader('View Contact');
    echo '<div class="alert alert-danger mt-4">Invalid contact ID.</div>';
    renderFooter();
    return;
}

$contactId = (int)$_GET['id'];
$contact = $db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$contactId]);

if (!$contact) {
    renderHeader('View Contact');
    echo '<div class="alert alert-danger mt-4">Contact not found.</div>';
    renderFooter();
    return;
}

renderHeader('View Contact');
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="/index.php?page=contacts" class="btn btn-secondary">&larr; Back to Contacts</a>
        <div class="btn-group">
            <a href="/index.php?page=edit_contact&id=<?php echo $contactId; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit Contact
            </a>
            <button class="btn btn-success" onclick="enrichContact(<?php echo $contactId; ?>)"
                    <?php echo $contact['enrichment_status'] === 'enriched' ? 'disabled' : ''; ?>>
                <i class="fas fa-magic me-2"></i>
                <?php echo $contact['enrichment_status'] === 'enriched' ? 'Enriched' : 'Enrich Contact'; ?>
            </button>
        </div>
    </div>
    

    <!-- View Mode -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title mb-2">
                <?php echo htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')); ?>
            </h3>
            <h6 class="card-subtitle mb-3 text-muted">
                <?php echo htmlspecialchars($contact['email'] ?? ''); ?>
            </h6>
            <div class="mb-3">
                <?php if (!empty($contact['phone'])): ?>
                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($contact['phone']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['company'])): ?>
                    <div><strong>Company:</strong> <?php echo htmlspecialchars($contact['company']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['position'])): ?>
                    <div><strong>Position:</strong> <?php echo htmlspecialchars($contact['position']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['twitter_handle'])): ?>
                    <div><strong>Twitter:</strong> <?php echo htmlspecialchars($contact['twitter_handle']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['linkedin_profile'])): ?>
                    <div><strong>LinkedIn:</strong> <?php echo htmlspecialchars($contact['linkedin_profile']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['telegram_username'])): ?>
                    <div><strong>Telegram:</strong> <?php echo htmlspecialchars($contact['telegram_username']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['discord_username'])): ?>
                    <div><strong>Discord:</strong> <?php echo htmlspecialchars($contact['discord_username']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['github_username'])): ?>
                    <div><strong>GitHub:</strong> <?php echo htmlspecialchars($contact['github_username']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['website'])): ?>
                    <div><strong>Website:</strong> <a href="<?php echo htmlspecialchars($contact['website']); ?>" target="_blank"><?php echo htmlspecialchars($contact['website']); ?></a></div>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <span class="badge bg-warning text-dark me-2"><?php echo ucfirst($contact['contact_type'] ?? ''); ?></span>
                <span class="badge bg-secondary me-2"><?php echo ucfirst($contact['contact_status'] ?? ''); ?></span>
                <?php if ($contact['enrichment_status']): ?>
                    <span class="badge bg-<?php echo $contact['enrichment_status'] === 'enriched' ? 'success' : 'warning'; ?>">
                        <i class="fas fa-magic me-1"></i>
                        <?php echo ucfirst($contact['enrichment_status']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($contact['source'])): ?>
                    <span class="badge bg-info text-dark me-2">Source: <?php echo htmlspecialchars($contact['source']); ?></span>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <strong>Notes:</strong><br>
                <div class="border rounded p-2 bg-light" style="min-height:2em;white-space:pre-wrap;">
                    <?php echo htmlspecialchars($contact['notes'] ?? ''); ?>
                </div>
            </div>
            <div class="text-muted small">
                <div>Created: <?php echo !empty($contact['created_at']) ? date('M j, Y H:i', strtotime($contact['created_at'])) : ''; ?></div>
                <div>Last Updated: <?php echo !empty($contact['updated_at']) ? date('M j, Y H:i', strtotime($contact['updated_at'])) : ''; ?></div>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter(); 
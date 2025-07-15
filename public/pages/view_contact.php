<?php
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
                <?php if (!empty($contact['evm_address'])): ?>
                    <div><strong>EVM Address:</strong> <?php echo htmlspecialchars($contact['evm_address']); ?></div>
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
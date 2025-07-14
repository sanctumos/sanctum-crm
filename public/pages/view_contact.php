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
renderHeader('View Contact');
if (!$contact) {
    echo '<div class="alert alert-danger mt-4">Contact not found.</div>';
    renderFooter();
    return;
}
?>
<div class="container mt-4">
    <a href="/index.php?page=contacts" class="btn btn-secondary mb-3">&larr; Back to Contacts</a>
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title mb-2">
                <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
            </h3>
            <h6 class="card-subtitle mb-3 text-muted">
                <?php echo htmlspecialchars($contact['email']); ?>
            </h6>
            <div class="mb-3">
                <?php if ($contact['phone']): ?>
                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($contact['phone']); ?></div>
                <?php endif; ?>
                <?php if ($contact['company']): ?>
                    <div><strong>Company:</strong> <?php echo htmlspecialchars($contact['company']); ?></div>
                <?php endif; ?>
                <?php if ($contact['position']): ?>
                    <div><strong>Position:</strong> <?php echo htmlspecialchars($contact['position']); ?></div>
                <?php endif; ?>
                <?php if ($contact['evm_address']): ?>
                    <div><strong>EVM Address:</strong> <?php echo htmlspecialchars($contact['evm_address']); ?></div>
                <?php endif; ?>
                <?php if ($contact['twitter_handle']): ?>
                    <div><strong>Twitter:</strong> <?php echo htmlspecialchars($contact['twitter_handle']); ?></div>
                <?php endif; ?>
                <?php if ($contact['linkedin_profile']): ?>
                    <div><strong>LinkedIn:</strong> <?php echo htmlspecialchars($contact['linkedin_profile']); ?></div>
                <?php endif; ?>
                <?php if ($contact['telegram_username']): ?>
                    <div><strong>Telegram:</strong> <?php echo htmlspecialchars($contact['telegram_username']); ?></div>
                <?php endif; ?>
                <?php if ($contact['discord_username']): ?>
                    <div><strong>Discord:</strong> <?php echo htmlspecialchars($contact['discord_username']); ?></div>
                <?php endif; ?>
                <?php if ($contact['github_username']): ?>
                    <div><strong>GitHub:</strong> <?php echo htmlspecialchars($contact['github_username']); ?></div>
                <?php endif; ?>
                <?php if ($contact['website']): ?>
                    <div><strong>Website:</strong> <a href="<?php echo htmlspecialchars($contact['website']); ?>" target="_blank"><?php echo htmlspecialchars($contact['website']); ?></a></div>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <span class="badge bg-warning text-dark me-2"><?php echo ucfirst($contact['contact_type']); ?></span>
                <span class="badge bg-secondary me-2"><?php echo ucfirst($contact['contact_status']); ?></span>
                <?php if ($contact['source']): ?>
                    <span class="badge bg-info text-dark me-2">Source: <?php echo htmlspecialchars($contact['source']); ?></span>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <strong>Notes:</strong><br>
                <div class="border rounded p-2 bg-light" style="min-height:2em;white-space:pre-wrap;">
                    <?php echo htmlspecialchars($contact['notes']); ?>
                </div>
            </div>
            <div class="text-muted small">
                <div>Created: <?php echo date('M j, Y H:i', strtotime($contact['created_at'])); ?></div>
                <div>Last Updated: <?php echo date('M j, Y H:i', strtotime($contact['updated_at'])); ?></div>
            </div>
        </div>
    </div>
</div>
<?php
renderFooter(); 
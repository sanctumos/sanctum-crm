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

require_once __DIR__ . '/../includes/ContactTagService.php';
$contactTags = (new ContactTagService($db))->listTags($contactId);

renderHeader('View Contact');
?>
<?php
renderPageHeader(
    trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
    $contact['email'] ? (string) $contact['email'] : 'No email provided',
    '<a href="/index.php?page=contacts" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>'
      . '<a href="/index.php?page=edit_contact&id=' . (int) $contactId . '" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i>Edit</a>'
      . '<button type="button" class="btn btn-success" onclick="enrichContact(' . (int) $contactId . ')"'
      . ($contact['enrichment_status'] === 'enriched' ? ' disabled' : '') . '>'
      . '<i class="bi bi-magic me-1"></i>'
      . ($contact['enrichment_status'] === 'enriched' ? 'Enriched' : 'Enrich')
      . '</button>'
);
?>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="surface surface-pad mb-3">
            <div class="section-title">Profile</div>
            <div class="mb-3">
                <?php if (!empty($contact['phone'])): ?>
                    <div class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i><?php echo crm_h($contact['phone']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['company'])): ?>
                    <div class="mb-1"><i class="bi bi-building me-2 text-muted"></i><?php echo crm_h($contact['company']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['position'])): ?>
                    <div class="mb-1"><i class="bi bi-briefcase me-2 text-muted"></i><?php echo crm_h($contact['position']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['website'])): ?>
                    <div class="mb-1"><i class="bi bi-globe me-2 text-muted"></i><a href="<?php echo crm_h($contact['website']); ?>" target="_blank" rel="noopener"><?php echo crm_h($contact['website']); ?></a></div>
                <?php endif; ?>
            </div>
            <?php
            $socials = [
                'twitter_handle' => ['Twitter', 'twitter-x'],
                'linkedin_profile' => ['LinkedIn', 'linkedin'],
                'telegram_username' => ['Telegram', 'telegram'],
                'discord_username' => ['Discord', 'discord'],
                'github_username' => ['GitHub', 'github'],
                'evm_address' => ['EVM', 'wallet2'],
            ];
            $hasSocial = false;
            foreach ($socials as $key => $_meta) {
                if (!empty($contact[$key])) { $hasSocial = true; break; }
            }
            ?>
            <?php if ($hasSocial): ?>
            <div class="section-title">Links</div>
            <div class="mb-3">
                <?php foreach ($socials as $key => $meta): ?>
                    <?php if (!empty($contact[$key])): ?>
                        <div class="mb-1"><i class="bi bi-<?php echo $meta[1]; ?> me-2 text-muted"></i><?php echo crm_h($contact[$key]); ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="section-title">Notes</div>
            <div class="markdown-body border rounded p-2 bg-light" style="min-height:2em;">
                <?php echo crm_h($contact['notes'] ?? '') ?: '<span class="text-muted">No notes</span>'; ?>
            </div>
        </div>

        <?php
        $enrichBundle = null;
        if (!empty($contact['enrichment_data'])) {
            $dec = json_decode($contact['enrichment_data'], true);
            if (is_array($dec) && (int) ($dec['schema'] ?? 0) >= 2) {
                $enrichBundle = $dec;
            }
        }
        ?>
        <?php if ($enrichBundle): ?>
        <div class="surface mb-3">
            <div class="surface__header">
                <h5><i class="bi bi-hdd-stack me-2 text-muted"></i>Stored enrichment</h5>
            </div>
            <div class="surface__body small">
                <?php if (!empty($contact['rocketreach_profile_id'])): ?>
                    <p class="mb-2 text-muted">RocketReach profile ID: <?php echo (int) $contact['rocketreach_profile_id']; ?></p>
                <?php endif; ?>
                <?php if (!empty($enrichBundle['lookup_status'])): ?>
                    <p class="mb-2"><strong>Lookup status:</strong> <?php echo crm_h((string) $enrichBundle['lookup_status']); ?></p>
                <?php endif; ?>
                <?php if (!empty($contact['enrichment_raw'])): ?>
                    <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#enrichmentRawCollapse" role="button">Show raw API JSON</a>
                    <div class="collapse mt-2" id="enrichmentRawCollapse">
                        <pre class="bg-light border rounded p-2 small" style="max-height:20rem;overflow:auto;"><?php
                        $rawDec = json_decode($contact['enrichment_raw'], true);
                        echo htmlspecialchars(json_encode($rawDec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '');
                        ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-4">
        <aside class="metadata-rail">
            <div class="metadata-rail__row">
                <label>Type</label>
                <div class="metadata-rail__value">
                    <?php echo crm_status_pill(ucfirst((string)($contact['contact_type'] ?? '')), crm_pill_for_contact_type($contact['contact_type'] ?? '')); ?>
                </div>
            </div>
            <div class="metadata-rail__row">
                <label>Status</label>
                <div class="metadata-rail__value">
                    <?php echo crm_status_pill(ucfirst((string)($contact['contact_status'] ?? '')), crm_pill_for_contact_status($contact['contact_status'] ?? '')); ?>
                </div>
            </div>
            <div class="metadata-rail__row">
                <label>Enrichment</label>
                <div class="metadata-rail__value">
                    <?php if ($contact['enrichment_status']): ?>
                        <?php echo crm_status_pill(ucfirst((string)$contact['enrichment_status']), crm_pill_for_enrichment($contact['enrichment_status']), 'magic'); ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="metadata-rail__row">
                <label>Source</label>
                <div class="metadata-rail__value"><?php echo !empty($contact['source']) ? crm_h($contact['source']) : '—'; ?></div>
            </div>
            <div class="metadata-rail__row metadata-rail__row--block">
                <label>Tags</label>
                <div class="metadata-rail__value">
                    <?php if ($contactTags !== []): ?>
                        <?php echo crm_render_tag_chips($contactTags); ?>
                    <?php else: ?>
                        <span class="text-muted">No tags</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="metadata-rail__row">
                <label>Created</label>
                <div class="metadata-rail__value"><?php echo !empty($contact['created_at']) ? date('M j, Y H:i', strtotime($contact['created_at'])) : '—'; ?></div>
            </div>
            <div class="metadata-rail__row">
                <label>Updated</label>
                <div class="metadata-rail__value"><?php echo !empty($contact['updated_at']) ? date('M j, Y H:i', strtotime($contact['updated_at'])) : '—'; ?></div>
            </div>
        </aside>
    </div>
</div>

<script>
// Individual contact enrichment
async function enrichContact(contactId) {
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    
    try {
        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-clockwise crm-spin me-2"></i>Enriching...';
        
        const response = await fetch(crmApiUrl(`contacts/${contactId}/enrich`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${getApiKey()}`
            },
            body: JSON.stringify({ strategy: 'auto' })
        });
        
        const result = await response.json().catch(() => ({}));
        if (result.outcome === 'skipped') {
            if (typeof crmToast === 'function') crmToast(result.message || 'Already enriched recently');
            else showSuccess(result.message || 'Already enriched recently; no new lookup.');
            button.innerHTML = originalText;
            button.disabled = false;
            return;
        }
        if (!response.ok) {
            if (typeof crmToast === 'function') crmToast(result.error || 'Enrichment failed', { variant: 'err' });
            else showError(result.error || 'Enrichment failed');
            button.innerHTML = originalText;
            button.disabled = false;
            return;
        }
        if (typeof crmToast === 'function') crmToast('Contact enriched successfully!');
        else showSuccess('Contact enriched successfully!');
        button.innerHTML = '<i class="bi bi-check me-2"></i>Enriched';
        button.classList.remove('btn-success');
        button.classList.add('btn-secondary');
        setTimeout(() => location.reload(), 1000);
    } catch (error) {
        if (typeof crmToast === 'function') crmToast('Network error: ' + error.message, { variant: 'err' });
        else showError('Network error: ' + error.message);
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

function getApiKey() {
    return localStorage.getItem('api_key') || '';
}

function showSuccess(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}

function showError(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}
</script>
<?php renderFooter(); ?>

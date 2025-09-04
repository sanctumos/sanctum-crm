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

// Edit Contact Page
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    renderHeader('Edit Contact');
    echo '<div class="alert alert-danger mt-4">Invalid contact ID.</div>';
    renderFooter();
    return;
}

$contactId = (int)$_GET['id'];
$contact = $db->fetchOne('SELECT * FROM contacts WHERE id = ?', [$contactId]);

if (!$contact) {
    renderHeader('Edit Contact');
    echo '<div class="alert alert-danger mt-4">Contact not found.</div>';
    renderFooter();
    return;
}

renderHeader('Edit Contact');
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="/index.php?page=view_contact&id=<?php echo $contactId; ?>" class="btn btn-secondary">&larr; Back to Contact</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <form id="editContactForm">
                <input type="hidden" name="contact_id" value="<?php echo $contactId; ?>">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($contact['first_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($contact['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($contact['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($contact['phone'] ?? ''); ?>">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" class="form-control" id="company" name="company" value="<?php echo htmlspecialchars($contact['company'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($contact['position'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="contact_type" class="form-label">Type</label>
                            <select class="form-select" id="contact_type" name="contact_type" required>
                                <option value="lead" <?php echo ($contact['contact_type'] ?? '') === 'lead' ? 'selected' : ''; ?>>Lead</option>
                                <option value="customer" <?php echo ($contact['contact_type'] ?? '') === 'customer' ? 'selected' : ''; ?>>Customer</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="contact_status" class="form-label">Status</label>
                            <select class="form-select" id="contact_status" name="contact_status" required>
                                <option value="new" <?php echo ($contact['contact_status'] ?? '') === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="qualified" <?php echo ($contact['contact_status'] ?? '') === 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                                <option value="active" <?php echo ($contact['contact_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($contact['contact_status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="source" class="form-label">Source</label>
                    <select class="form-select" id="source" name="source">
                        <option value="">Select Source</option>
                        <option value="website" <?php echo ($contact['source'] ?? '') === 'website' ? 'selected' : ''; ?>>Website</option>
                        <option value="referral" <?php echo ($contact['source'] ?? '') === 'referral' ? 'selected' : ''; ?>>Referral</option>
                        <option value="social_media" <?php echo ($contact['source'] ?? '') === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                        <option value="email_campaign" <?php echo ($contact['source'] ?? '') === 'email_campaign' ? 'selected' : ''; ?>>Email Campaign</option>
                        <option value="cold_call" <?php echo ($contact['source'] ?? '') === 'cold_call' ? 'selected' : ''; ?>>Cold Call</option>
                        <option value="other" <?php echo ($contact['source'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($contact['notes'] ?? ''); ?></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="/index.php?page=view_contact&id=<?php echo $contactId; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    const contactId = data.contact_id;
    delete data.contact_id;
    fetch(`/api/v1/contacts/${contactId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.location.href = `/index.php?page=view_contact&id=${contactId}`;
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Network error: ' + error.message);
    });
});
</script>

<?php renderFooter(); ?> 
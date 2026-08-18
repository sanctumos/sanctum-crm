<?php
/**
 * Account — change password + skin preference (all authenticated users)
 */

$auth = new Auth();
$auth->requireAuth();
$user = $auth->getUser();
$db = Database::getInstance();
require_once __DIR__ . '/../includes/skin-lab-env.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'password');
    $forceChange = !empty($user['must_change_password']);

    if ($action === 'save_skin') {
        if ($forceChange) {
            $error = 'Set a new password before changing other account settings.';
        } else {
            $mine = crmSkinNormalizeSlug((string) ($_POST['skin_slug'] ?? ''));
            $db->update('users', [
                'skin_slug' => $mine,
                'updated_at' => getCurrentTimestamp(),
            ], 'id = ?', [(int) $user['id']]);
            $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [(int) $user['id']]) ?: $user;
            $success = 'Theme preference saved.';
        }
    } else {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . (int) PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($new !== $confirm) {
            $error = 'The two passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->update('users', [
                'password_hash' => $hash,
                'must_change_password' => 0,
                'updated_at' => getCurrentTimestamp(),
            ], 'id = ?', [$user['id']]);
            logActivity($user['id'], 'password_change', 'User changed password via account page');
            $user['must_change_password'] = 0;
            $success = 'Your password has been updated.';
            if ($forceChange || !empty($_GET['must_change']) || !empty($_POST['must_change'])) {
                header('Location: /index.php');
                exit;
            }
        }
    }
}

$userSkin = crmSkinUserOverrideSlug(is_array($user) ? $user : null) ?? '';
$defaultSkin = crmSkinMasterSlug();
$forceChange = !empty($user['must_change_password']);

renderHeader('Account');
renderPageHeader('Account', $forceChange ? 'Choose a new password' : 'Password and theme');
?>

<?php if ($forceChange): ?>
    <div class="alert alert-warning" role="alert">
        You need to set a new password before you can use the CRM.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <?php if (!$forceChange): ?>
        <div class="surface mb-3">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-palette me-2"></i>Theme</h5>
            </div>
            <div class="surface__body">
                <?php if ($success && (($_POST['action'] ?? '') === 'save_skin')): ?>
                    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <p class="text-muted small mb-3">
                    Same skins as Tasks and Docket. Leave on site default
                    (currently <code><?php echo htmlspecialchars($defaultSkin); ?></code>)
                    or pick your own. Preview: <code>?preview_skin=obsidian</code>.
                </p>
                <form method="POST" action="" class="row g-3">
                    <input type="hidden" name="action" value="save_skin">
                    <div class="col-md-8">
                        <label class="form-label" for="skin_slug">Your skin</label>
                        <select class="form-select" id="skin_slug" name="skin_slug">
                            <option value="" <?php echo $userSkin === '' ? 'selected' : ''; ?>>Use site default</option>
                            <?php foreach (crmSkinAvailableSlugs() as $slug): ?>
                                <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo $userSkin === $slug ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slug); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save theme
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="surface">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Your account</h5>
            </div>
            <div class="surface__body">
                <p class="text-muted mb-4">
                    Signed in as <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    (<?php echo htmlspecialchars($user['email']); ?>).
                </p>

                <?php if ($success && (($_POST['action'] ?? 'password') !== 'save_skin')): ?>
                    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <h6 class="mb-3">Change password</h6>
                <form method="POST" action="" autocomplete="off">
                    <input type="hidden" name="action" value="password">
                    <?php if ($forceChange): ?>
                    <input type="hidden" name="must_change" value="1">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required
                               minlength="<?php echo (int) PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password">
                        <small class="text-muted">At least <?php echo (int) PASSWORD_MIN_LENGTH; ?> characters.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                               minlength="<?php echo (int) PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-2"></i>Update password
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="surface">
            <div class="surface__header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Note</h5>
            </div>
            <div class="surface__body">
                <p class="text-muted small mb-0">
                    There is no email-based &ldquo;forgot password&rdquo; flow. If you are locked out, an administrator
                    can set a temporary password from <strong>Users</strong>, or reset the hash on the server.
                    Site-wide theme default is set under <strong>Settings → Theme</strong> (admins).
                </p>
            </div>
        </div>
    </div>
</div>

<?php
renderFooter();

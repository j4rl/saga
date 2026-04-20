<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('school_admin');

$user = current_user();
$errors = [];

if (is_post()) {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? 'review_registration');

    if ($action === 'update_school_profile') {
        $customEnabled = isset($_POST['theme_custom_enabled']) ? 1 : 0;
        $colors = default_theme_colors();

        foreach (array_keys($colors) as $field) {
            $colors[$field] = normalize_hex_color((string) ($_POST[$field] ?? ''));
            if ($customEnabled === 1 && !$colors[$field]) {
                $errors[] = 'Alla färger i det egna temat måste anges som giltiga hex-färger.';
                break;
            }
        }

        $logoUpload = validate_school_logo_upload($_FILES['logo_file'] ?? []);
        if (!$logoUpload['ok']) {
            $errors[] = $logoUpload['error'];
        }

        if (!$errors) {
            $school = fetch_school_profile($conn, (int) $user['school_id']);
            $storedLogo = null;

            try {
                if ($logoUpload['file']) {
                    $storedLogo = store_school_logo($logoUpload['file']);
                }

                $params = [
                    $customEnabled,
                    $customEnabled ? $colors['theme_primary'] : null,
                    $customEnabled ? $colors['theme_secondary'] : null,
                    $customEnabled ? $colors['theme_bg'] : null,
                    $customEnabled ? $colors['theme_surface'] : null,
                    $customEnabled ? $colors['theme_text'] : null,
                ];
                $types = 'isssss';
                $logoSql = '';

                if ($storedLogo) {
                    $logoSql = ', logo_filename = ?, logo_original_name = ?, logo_mime = ?';
                    $types .= 'sss';
                    array_push($params, $storedLogo['stored_name'], $storedLogo['original_name'], $storedLogo['mime']);
                } elseif (isset($_POST['remove_logo'])) {
                    $logoSql = ', logo_filename = NULL, logo_original_name = NULL, logo_mime = NULL';
                }

                $types .= 'i';
                $params[] = (int) $user['school_id'];

                execute_prepared(
                    $conn,
                    "UPDATE schools
                     SET theme_mode = 'auto', theme_custom_enabled = ?, theme_primary = ?, theme_secondary = ?,
                         theme_bg = ?, theme_surface = ?, theme_text = ?$logoSql
                     WHERE id = ?",
                    $types,
                    $params
                );

                if (($storedLogo || isset($_POST['remove_logo'])) && $school && !empty($school['logo_filename'])) {
                    $oldPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . basename((string) $school['logo_filename']);
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }

                log_event($conn, (int) $user['id'], 'school_profile_update', 'school', (int) $user['school_id']);
                set_flash('success', 'Skolans utseende har sparats.');
                redirect('dashboard_school_admin.php');
            } catch (Throwable $exception) {
                if ($storedLogo) {
                    $newPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedLogo['stored_name'];
                    if (is_file($newPath)) {
                        unlink($newPath);
                    }
                }
                $errors[] = 'Skolans utseende kunde inte sparas.';
            }
        }
    }

    if ($action === 'review_registration') {
        $registrationId = (int) ($_POST['user_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $status = match ($decision) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => '',
        };

        if ($registrationId <= 0 || $status === '') {
            $errors[] = 'Ogiltig registrering.';
        } elseif (review_registration($conn, $registrationId, $status, $user, (int) $user['school_id'])) {
            set_flash('success', $status === 'approved' ? 'Registreringen har godkänts.' : 'Registreringen har avvisats.');
            redirect('dashboard_school_admin.php');
        } else {
            $errors[] = 'Registreringen kunde inte uppdateras.';
        }
    }
}

$schoolProfile = fetch_school_profile($conn, (int) $user['school_id']);
$themeColors = default_theme_colors();
foreach (array_keys($themeColors) as $field) {
    if (!empty($schoolProfile[$field]) && is_hex_color($schoolProfile[$field])) {
        $themeColors[$field] = $schoolProfile[$field];
    }
}
$registrations = fetch_registration_requests($conn, (int) $user['school_id']);
$pendingCount = count(array_filter($registrations, static fn (array $row): bool => $row['approval_status'] === 'pending'));
$pageTitle = 'Skoladministration';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Skoladministration</p>
            <h1>Registreringar på <?= h($user['school_name']) ?></h1>
        </div>
        <span class="status-pill status-pending"><?= (int) $pendingCount ?> väntar</span>
    </div>
</section>

<?php if ($errors): ?>
    <section class="section section-tight">
        <div class="notice notice-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="section-heading">
        <div>
            <h2>Skolans utseende</h2>
            <p class="muted">Färger och logotyp för <?= h($user['school_name']) ?>.</p>
        </div>
    </div>

    <form class="settings-layout" method="post" action="dashboard_school_admin.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_school_profile">

        <div class="settings-panel">
            <h3>Färger</h3>
            <label class="check-option">
                <input type="checkbox" name="theme_custom_enabled" value="1" <?= (int) ($schoolProfile['theme_custom_enabled'] ?? 0) === 1 ? 'checked' : '' ?> data-theme-custom-toggle>
                <span>Använd eget tema</span>
            </label>

            <div class="theme-builder" data-theme-builder>
                <div class="field">
                    <label for="theme_primary">Primär färg</label>
                    <input id="theme_primary" name="theme_primary" type="color" value="<?= h($themeColors['theme_primary']) ?>" data-theme-color="--primary">
                </div>
                <div class="field">
                    <label for="theme_secondary">Länkfärg</label>
                    <input id="theme_secondary" name="theme_secondary" type="color" value="<?= h($themeColors['theme_secondary']) ?>" data-theme-color="--secondary">
                </div>
                <div class="field">
                    <label for="theme_bg">Bakgrund</label>
                    <input id="theme_bg" name="theme_bg" type="color" value="<?= h($themeColors['theme_bg']) ?>" data-theme-color="--bg">
                </div>
                <div class="field">
                    <label for="theme_surface">Ytor</label>
                    <input id="theme_surface" name="theme_surface" type="color" value="<?= h($themeColors['theme_surface']) ?>" data-theme-color="--surface">
                </div>
                <div class="field">
                    <label for="theme_text">Text</label>
                    <input id="theme_text" name="theme_text" type="color" value="<?= h($themeColors['theme_text']) ?>" data-theme-color="--text">
                </div>
            </div>
        </div>

        <div class="settings-panel">
            <h3>Logotyp</h3>
            <?php if ($schoolProfile && $schoolProfile['logo_filename']): ?>
                <div class="logo-preview">
                    <img src="school_logo.php?id=<?= (int) $schoolProfile['id'] ?>" alt="">
                    <span><?= h($schoolProfile['logo_original_name']) ?></span>
                </div>
                <label class="check-option">
                    <input type="checkbox" name="remove_logo" value="1">
                    <span>Ta bort logotyp</span>
                </label>
            <?php else: ?>
                <p class="muted">Ingen logotyp är uppladdad ännu.</p>
            <?php endif; ?>

            <div class="field">
                <label for="logo_file">Ladda upp logotyp</label>
                <input id="logo_file" name="logo_file" type="file" accept="image/png,image/jpeg,image/webp">
                <p class="field-help">PNG, JPG eller WebP. Max 2 MB.</p>
            </div>
        </div>

        <div class="settings-panel theme-preview" data-theme-preview>
            <h3>Förhandsvisning</h3>
            <div class="preview-brand">
                <?php if ($schoolProfile && $schoolProfile['logo_filename']): ?>
                    <img src="school_logo.php?id=<?= (int) $schoolProfile['id'] ?>" alt="">
                <?php else: ?>
                    <span class="brand-mark">S</span>
                <?php endif; ?>
                <div>
                    <strong><?= h($user['school_name']) ?></strong>
                    <small>SAGA · Gymnasiearbeten</small>
                </div>
            </div>
            <p>Exempel på kort, text och länkar i skolans tema.</p>
            <a href="#">Exempellänk</a>
            <button class="button button-primary" type="button">Knapp</button>
        </div>

        <div class="settings-actions">
            <button class="button button-primary" type="submit">Spara utseende</button>
        </div>
    </form>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Registreringar</h2>
        <span><?= (int) count($registrations) ?> konton</span>
    </div>

    <?php if (!$registrations): ?>
        <p class="empty-state">Det finns inga registreringar för skolan ännu.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Användarnamn</th>
                    <th>Namn</th>
                    <th>Roll</th>
                    <th>Status</th>
                    <th>Registrerad</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($registrations as $registration): ?>
                    <tr>
                        <td><?= h($registration['username']) ?></td>
                        <td><?= h($registration['full_name']) ?></td>
                        <td><?= h(role_label($registration['role'])) ?></td>
                        <td>
                            <span class="status-pill status-<?= h($registration['approval_status']) ?>">
                                <?= h(approval_status_label($registration['approval_status'])) ?>
                            </span>
                        </td>
                        <td><?= h(format_date($registration['created_at'])) ?></td>
                        <td>
                            <?php if ($registration['approval_status'] === 'pending'): ?>
                                <form class="inline-actions" method="post" action="dashboard_school_admin.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review_registration">
                                    <input type="hidden" name="user_id" value="<?= (int) $registration['id'] ?>">
                                    <button class="button button-primary" type="submit" name="decision" value="approve">Godkänn</button>
                                    <button class="button button-secondary" type="submit" name="decision" value="reject">Avvisa</button>
                                </form>
                            <?php else: ?>
                                <span class="muted"><?= h(format_date($registration['reviewed_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

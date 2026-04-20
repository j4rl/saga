<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('super_admin');

$errors = [];
$schools = fetch_schools($conn);
$currentUser = current_user();

if (is_post()) {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_school') {
        $schoolName = trim((string) ($_POST['school_name'] ?? ''));
        $adminUsername = trim((string) ($_POST['school_admin_username'] ?? ''));
        $adminEmail = normalize_email((string) ($_POST['school_admin_email'] ?? ''));
        $adminFullName = trim((string) ($_POST['school_admin_full_name'] ?? ''));
        $adminPassword = (string) ($_POST['school_admin_password'] ?? '');

        if ($schoolName === '' || mb_strlen($schoolName) > 160) {
            $errors[] = 'Skolnamnet är obligatoriskt och får vara högst 160 tecken.';
        }
        if ($adminUsername === '' || mb_strlen($adminUsername) > 80) {
            $errors[] = 'Skoladministratörens användarnamn är obligatoriskt och får vara högst 80 tecken.';
        }
        if (trim((string) ($_POST['school_admin_email'] ?? '')) !== '' && !$adminEmail) {
            $errors[] = 'Skoladministratörens e-postadress är ogiltig.';
        }
        if ($adminFullName === '' || mb_strlen($adminFullName) > 160) {
            $errors[] = 'Skoladministratörens namn är obligatoriskt och får vara högst 160 tecken.';
        }
        if (mb_strlen($adminPassword) < 8) {
            $errors[] = 'Skoladministratörens lösenord måste vara minst 8 tecken.';
        }

        if (!$errors) {
            try {
                $conn->begin_transaction();

                execute_prepared($conn, 'INSERT INTO schools (school_name) VALUES (?)', 's', [$schoolName]);
                $schoolId = (int) $conn->insert_id;

                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = execute_prepared(
                    $conn,
                    'INSERT INTO users
                     (username, email, password_hash, full_name, role, school_id, approval_status, reviewed_by, reviewed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    'sssssisi',
                    [$adminUsername, $adminEmail, $passwordHash, $adminFullName, 'school_admin', $schoolId, 'approved', (int) $currentUser['id']]
                );

                log_event($conn, (int) $currentUser['id'], 'school_create', 'school', $schoolId);
                log_event($conn, (int) $currentUser['id'], 'user_create', 'user', (int) $stmt->insert_id);
                $conn->commit();

                set_flash('success', 'Skolan och skoladministratören har skapats.');
                redirect('dashboard_admin.php');
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $errors[] = 'Kunde inte skapa skolan. Kontrollera att skolnamn och användarnamn är unika.';
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
        } elseif (review_registration($conn, $registrationId, $status, $currentUser)) {
            set_flash('success', $status === 'approved' ? 'Registreringen har godkänts.' : 'Registreringen har avvisats.');
            redirect('dashboard_admin.php');
        } else {
            $errors[] = 'Registreringen kunde inte uppdateras.';
        }
    }

    if ($action === 'create_user') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'student');
        $schoolId = (int) ($_POST['school_id'] ?? 0);

        if ($username === '' || mb_strlen($username) > 80) {
            $errors[] = 'Användarnamn är obligatoriskt och får vara högst 80 tecken.';
        }
        if (trim((string) ($_POST['email'] ?? '')) !== '' && !$email) {
            $errors[] = 'Ange en giltig e-postadress eller lämna fältet tomt.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 160) {
            $errors[] = 'Namn är obligatoriskt och får vara högst 160 tecken.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Lösenordet måste vara minst 8 tecken.';
        }
        if (!in_array($role, ['student', 'teacher', 'school_admin', 'super_admin'], true)) {
            $errors[] = 'Ogiltig roll.';
        }
        if ($schoolId <= 0) {
            $errors[] = 'Välj skola.';
        }

        if (!$errors) {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = execute_prepared(
                    $conn,
                    'INSERT INTO users
                     (username, email, password_hash, full_name, role, school_id, approval_status, reviewed_by, reviewed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    'sssssisi',
                    [$username, $email, $passwordHash, $fullName, $role, $schoolId, 'approved', (int) $currentUser['id']]
                );
                log_event($conn, (int) $currentUser['id'], 'user_create', 'user', (int) $stmt->insert_id);
                set_flash('success', 'Användaren har skapats och är godkänd.');
                redirect('dashboard_admin.php');
            } catch (mysqli_sql_exception $exception) {
                $errors[] = 'Kunde inte skapa användaren. Kontrollera att användarnamnet är unikt.';
            }
        }
    }

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = (string) ($_POST['role'] ?? 'student');
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $status = (string) ($_POST['approval_status'] ?? 'pending');
        $newPassword = (string) ($_POST['new_password'] ?? '');

        if ($userId <= 0) {
            $errors[] = 'Ogiltig användare.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 160) {
            $errors[] = 'Namn är obligatoriskt och får vara högst 160 tecken.';
        }
        if (trim((string) ($_POST['email'] ?? '')) !== '' && !$email) {
            $errors[] = 'Ange en giltig e-postadress eller lämna fältet tomt.';
        }
        if (!in_array($role, ['student', 'teacher', 'school_admin', 'super_admin'], true)) {
            $errors[] = 'Ogiltig roll.';
        }
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $errors[] = 'Ogiltig status.';
        }
        if ($schoolId <= 0 || !fetch_school($conn, $schoolId)) {
            $errors[] = 'Välj en giltig skola.';
        }
        if ($newPassword !== '' && mb_strlen($newPassword) < 8) {
            $errors[] = 'Nytt lösenord måste vara minst 8 tecken.';
        }

        $editedUser = $userId > 0 ? fetch_one_prepared($conn, 'SELECT role FROM users WHERE id = ? LIMIT 1', 'i', [$userId]) : null;
        $superAdminCount = fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM users WHERE role = \'super_admin\' AND approval_status = \'approved\'');
        if (
            $editedUser
            && $editedUser['role'] === 'super_admin'
            && ($role !== 'super_admin' || $status !== 'approved')
            && (int) ($superAdminCount['total'] ?? 0) <= 1
        ) {
            $errors[] = 'Det måste finnas minst en godkänd superadmin.';
        }

        if (!$errors) {
            try {
                if ($newPassword !== '') {
                    execute_prepared(
                        $conn,
                        'UPDATE users
                         SET email = ?, full_name = ?, role = ?, school_id = ?, approval_status = ?,
                             password_hash = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
                         WHERE id = ?',
                        'sssissii',
                        [$email, $fullName, $role, $schoolId, $status, password_hash($newPassword, PASSWORD_DEFAULT), (int) $currentUser['id'], $userId]
                    );
                } else {
                    execute_prepared(
                        $conn,
                        'UPDATE users
                         SET email = ?, full_name = ?, role = ?, school_id = ?, approval_status = ?,
                             reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
                         WHERE id = ?',
                        'sssisii',
                        [$email, $fullName, $role, $schoolId, $status, (int) $currentUser['id'], $userId]
                    );
                }

                log_event($conn, (int) $currentUser['id'], 'user_update', 'user', $userId);
                set_flash('success', 'Användaren har uppdaterats.');
                redirect('dashboard_admin.php');
            } catch (mysqli_sql_exception $exception) {
                $errors[] = 'Kunde inte uppdatera användaren.';
            }
        }
    }

    if ($action === 'update_school') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $schoolName = trim((string) ($_POST['school_name'] ?? ''));

        if ($schoolId <= 0 || !fetch_school($conn, $schoolId)) {
            $errors[] = 'Ogiltig skola.';
        }
        if ($schoolName === '' || mb_strlen($schoolName) > 160) {
            $errors[] = 'Skolnamnet är obligatoriskt och får vara högst 160 tecken.';
        }

        if (!$errors) {
            try {
                execute_prepared($conn, 'UPDATE schools SET school_name = ? WHERE id = ?', 'si', [$schoolName, $schoolId]);
                log_event($conn, (int) $currentUser['id'], 'school_update', 'school', $schoolId);
                set_flash('success', 'Skolan har uppdaterats.');
                redirect('dashboard_admin.php');
            } catch (mysqli_sql_exception $exception) {
                $errors[] = 'Kunde inte uppdatera skolan. Kontrollera att namnet är unikt.';
            }
        }
    }

    $schools = fetch_schools($conn);
}

$users = fetch_admin_users($conn);

$projectCount = fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM projects');
$recentNotifications = fetch_recent_email_notifications($conn, 10);
$pageTitle = 'Superadminpanel';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Superadminpanel</p>
    <h1>Hantera skolor och användare</h1>
    <p class="muted">Superadmin kan skapa skolor, skoladministratörer och hantera alla användare.</p>
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

<section class="admin-grid">
    <form class="form-card" method="post" action="dashboard_admin.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_school">
        <h2>Ny skola</h2>
        <div class="field">
            <label for="school_name">Skolnamn</label>
            <input id="school_name" name="school_name" type="text" maxlength="160" required>
        </div>
        <div class="field">
            <label for="school_admin_username">Skoladministratörens användarnamn</label>
            <input id="school_admin_username" name="school_admin_username" type="text" maxlength="80" required>
        </div>
        <div class="field">
            <label for="school_admin_email">Skoladministratörens e-post</label>
            <input id="school_admin_email" name="school_admin_email" type="email" maxlength="190">
        </div>
        <div class="field">
            <label for="school_admin_full_name">Skoladministratörens namn</label>
            <input id="school_admin_full_name" name="school_admin_full_name" type="text" maxlength="160" required>
        </div>
        <div class="field">
            <label for="school_admin_password">Tillfälligt lösenord</label>
            <input id="school_admin_password" name="school_admin_password" type="password" minlength="8" required>
        </div>
        <button class="button button-primary" type="submit">Skapa skola</button>
    </form>

    <form class="form-card" method="post" action="dashboard_admin.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">
        <h2>Ny användare</h2>
        <div class="field">
            <label for="username">Användarnamn</label>
            <input id="username" name="username" type="text" maxlength="80" required>
        </div>
        <div class="field">
            <label for="email">E-post</label>
            <input id="email" name="email" type="email" maxlength="190">
        </div>
        <div class="field">
            <label for="full_name">Namn</label>
            <input id="full_name" name="full_name" type="text" maxlength="160" required>
        </div>
        <div class="field">
            <label for="password">Tillfälligt lösenord</label>
            <input id="password" name="password" type="password" minlength="8" required>
        </div>
        <div class="field two-col">
            <label for="role">Roll</label>
            <select id="role" name="role" required>
                <option value="student">Elev</option>
                <option value="teacher">Lärare</option>
                <option value="school_admin">Skoladministratör</option>
                <option value="super_admin">Superadmin</option>
            </select>
        </div>
        <div class="field two-col">
            <label for="school_id">Skola</label>
            <select id="school_id" name="school_id" required>
                <?php foreach ($schools as $school): ?>
                    <option value="<?= (int) $school['id'] ?>"><?= h($school['school_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button-primary" type="submit">Skapa användare</button>
    </form>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Skolor</h2>
        <span><?= (int) count($schools) ?> skolor</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Skolnamn</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($schools as $school): ?>
                <tr>
                    <td colspan="2">
                        <form class="inline-actions" method="post" action="dashboard_admin.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_school">
                            <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
                            <input name="school_name" type="text" maxlength="160" value="<?= h($school['school_name']) ?>" required>
                            <button class="button button-secondary" type="submit">Spara</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Användare</h2>
        <span><?= (int) count($users) ?> användare · <?= (int) ($projectCount['total'] ?? 0) ?> arbeten</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Användarnamn</th>
                <th>E-post</th>
                <th>Namn</th>
                <th>Roll</th>
                <th>Status</th>
                <th>Skola</th>
                <th>Skapad</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <?php $userFormId = 'admin-user-form-' . (int) $row['id']; ?>
                <tr>
                    <td><?= h($row['username']) ?></td>
                    <td>
                        <input form="<?= h($userFormId) ?>" name="email" type="email" maxlength="190" value="<?= h($row['email']) ?>" placeholder="e-post">
                    </td>
                    <td><input form="<?= h($userFormId) ?>" name="full_name" type="text" maxlength="160" value="<?= h($row['full_name']) ?>" required></td>
                    <td>
                        <select form="<?= h($userFormId) ?>" name="role" required>
                            <?php foreach (['student', 'teacher', 'school_admin', 'super_admin'] as $role): ?>
                                <option value="<?= h($role) ?>" <?= $row['role'] === $role ? 'selected' : '' ?>><?= h(role_label($role)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select form="<?= h($userFormId) ?>" name="approval_status" required>
                            <?php foreach (['pending', 'approved', 'rejected'] as $status): ?>
                                <option value="<?= h($status) ?>" <?= $row['approval_status'] === $status ? 'selected' : '' ?>><?= h(approval_status_label($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select form="<?= h($userFormId) ?>" name="school_id" required>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= (int) $school['id'] ?>" <?= (int) $row['school_id'] === (int) $school['id'] ? 'selected' : '' ?>>
                                    <?= h($school['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><?= h(format_date($row['created_at'])) ?></td>
                    <td>
                        <input form="<?= h($userFormId) ?>" name="new_password" type="password" minlength="8" placeholder="nytt lösenord">
                        <form id="<?= h($userFormId) ?>" class="inline-actions admin-user-form" method="post" action="dashboard_admin.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                            <button class="button button-secondary" type="submit">Spara</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>E-postnotiser</h2>
        <span><?= (int) count($recentNotifications) ?> senaste</span>
    </div>
    <?php if (!$recentNotifications): ?>
        <p class="empty-state">Inga e-postnotiser har loggats ännu.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Mottagare</th>
                    <th>Ämne</th>
                    <th>Status</th>
                    <th>Fel</th>
                    <th>Skapad</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentNotifications as $notification): ?>
                    <tr>
                        <td><?= h($notification['recipient_email']) ?></td>
                        <td><?= h($notification['subject']) ?></td>
                        <td><?= h($notification['status']) ?></td>
                        <td><?= h($notification['error_message'] ?? '') ?></td>
                        <td><?= h(format_date($notification['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



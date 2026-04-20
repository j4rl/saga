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
        $adminFullName = trim((string) ($_POST['school_admin_full_name'] ?? ''));
        $adminPassword = (string) ($_POST['school_admin_password'] ?? '');

        if ($schoolName === '' || mb_strlen($schoolName) > 160) {
            $errors[] = 'Skolnamnet är obligatoriskt och får vara högst 160 tecken.';
        }
        if ($adminUsername === '' || mb_strlen($adminUsername) > 80) {
            $errors[] = 'Skoladministratörens användarnamn är obligatoriskt och får vara högst 80 tecken.';
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
                     (username, password_hash, full_name, role, school_id, approval_status, reviewed_by, reviewed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    'ssssisi',
                    [$adminUsername, $passwordHash, $adminFullName, 'school_admin', $schoolId, 'approved', (int) $currentUser['id']]
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
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'student');
        $schoolId = (int) ($_POST['school_id'] ?? 0);

        if ($username === '' || mb_strlen($username) > 80) {
            $errors[] = 'Användarnamn är obligatoriskt och får vara högst 80 tecken.';
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
                     (username, password_hash, full_name, role, school_id, approval_status, reviewed_by, reviewed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                    'ssssisi',
                    [$username, $passwordHash, $fullName, $role, $schoolId, 'approved', (int) $currentUser['id']]
                );
                log_event($conn, (int) $currentUser['id'], 'user_create', 'user', (int) $stmt->insert_id);
                set_flash('success', 'Användaren har skapats och är godkänd.');
                redirect('dashboard_admin.php');
            } catch (mysqli_sql_exception $exception) {
                $errors[] = 'Kunde inte skapa användaren. Kontrollera att användarnamnet är unikt.';
            }
        }
    }

    $schools = fetch_schools($conn);
}

$users = fetch_all_prepared(
    $conn,
    'SELECT u.id, u.username, u.full_name, u.role, u.approval_status, u.created_at, s.school_name
     FROM users u
     INNER JOIN schools s ON s.id = u.school_id
     ORDER BY u.created_at DESC, u.id DESC'
);

$projectCount = fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM projects');
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
        <h2>Användare</h2>
        <span><?= (int) count($users) ?> användare · <?= (int) ($projectCount['total'] ?? 0) ?> arbeten</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Användarnamn</th>
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
                <tr>
                    <td><?= h($row['username']) ?></td>
                    <td><?= h($row['full_name']) ?></td>
                    <td><?= h(role_label($row['role'])) ?></td>
                    <td>
                        <span class="status-pill status-<?= h($row['approval_status']) ?>">
                            <?= h(approval_status_label($row['approval_status'])) ?>
                        </span>
                    </td>
                    <td><?= h($row['school_name']) ?></td>
                    <td><?= h(format_date($row['created_at'])) ?></td>
                    <td>
                        <?php if ($row['approval_status'] === 'pending' && in_array($row['role'], ['student', 'teacher'], true)): ?>
                            <form class="inline-actions" method="post" action="dashboard_admin.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="review_registration">
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <button class="button button-primary" type="submit" name="decision" value="approve">Godkänn</button>
                                <button class="button button-secondary" type="submit" name="decision" value="reject">Avvisa</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



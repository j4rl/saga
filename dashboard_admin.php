<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin');

$errors = [];
$schools = fetch_schools($conn);

if (is_post()) {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_school') {
        $schoolName = trim((string) ($_POST['school_name'] ?? ''));
        if ($schoolName === '' || mb_strlen($schoolName) > 160) {
            $errors[] = 'Skolnamnet är obligatoriskt och får vara högst 160 tecken.';
        } else {
            execute_prepared($conn, 'INSERT INTO schools (school_name) VALUES (?)', 's', [$schoolName]);
            log_event($conn, current_user()['id'], 'school_create', 'school', (int) $conn->insert_id);
            set_flash('success', 'Skolan har skapats.');
            redirect('dashboard_admin.php');
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
        if (!in_array($role, ['student', 'teacher', 'admin'], true)) {
            $errors[] = 'Ogiltig roll.';
        }
        if ($schoolId <= 0) {
            $errors[] = 'Välj skola.';
        }

        if (!$errors) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = execute_prepared(
                $conn,
                'INSERT INTO users (username, password_hash, full_name, role, school_id) VALUES (?, ?, ?, ?, ?)',
                'ssssi',
                [$username, $passwordHash, $fullName, $role, $schoolId]
            );
            log_event($conn, current_user()['id'], 'user_create', 'user', (int) $stmt->insert_id);
            set_flash('success', 'Användaren har skapats.');
            redirect('dashboard_admin.php');
        }
    }

    $schools = fetch_schools($conn);
}

$users = fetch_all_prepared(
    $conn,
    'SELECT u.id, u.username, u.full_name, u.role, u.created_at, s.school_name
     FROM users u
     INNER JOIN schools s ON s.id = u.school_id
     ORDER BY u.created_at DESC, u.id DESC'
);

$projectCount = fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM projects');
$pageTitle = 'Adminpanel';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Adminpanel</p>
    <h1>Hantera skolor och användare</h1>
    <p class="muted">Admin kan även se alla arbeten via den vanliga sökningen.</p>
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
                <option value="admin">Admin</option>
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
                <th>Skola</th>
                <th>Skapad</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= h($row['username']) ?></td>
                    <td><?= h($row['full_name']) ?></td>
                    <td><?= h($row['role']) ?></td>
                    <td><?= h($row['school_name']) ?></td>
                    <td><?= h(format_date($row['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



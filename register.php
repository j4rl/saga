<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user()['role']));
}

$errors = [];
$schools = fetch_schools($conn);
$formData = [
    'username' => '',
    'full_name' => '',
    'role' => 'student',
    'school_id' => '',
];

if (is_post()) {
    verify_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');
    $schoolId = (int) ($_POST['school_id'] ?? 0);

    $formData = [
        'username' => $username,
        'full_name' => $fullName,
        'role' => $role,
        'school_id' => (string) $schoolId,
    ];

    if ($username === '' || mb_strlen($username) > 80) {
        $errors[] = 'Användarnamn är obligatoriskt och får vara högst 80 tecken.';
    }
    if ($fullName === '' || mb_strlen($fullName) > 160) {
        $errors[] = 'Namn är obligatoriskt och får vara högst 160 tecken.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Lösenordet måste vara minst 8 tecken.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Lösenorden matchar inte.';
    }
    if (!in_array($role, ['student', 'teacher'], true)) {
        $errors[] = 'Du kan bara registrera dig som elev eller lärare.';
    }
    if ($schoolId <= 0) {
        $errors[] = 'Välj skola.';
    }

    if (!$errors) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = execute_prepared(
                $conn,
                'INSERT INTO users (username, password_hash, full_name, role, school_id, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?)',
                'ssssis',
                [$username, $passwordHash, $fullName, $role, $schoolId, 'pending']
            );

            log_event($conn, null, 'registration_create', 'user', (int) $stmt->insert_id);
            set_flash('success', 'Registreringen är skickad och väntar på godkännande av skoladministratören.');
            redirect('login.php');
        } catch (mysqli_sql_exception $exception) {
            $errors[] = 'Kunde inte skapa registreringen. Kontrollera att användarnamnet är ledigt.';
        }
    }
}

$pageTitle = 'Registrera konto';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="register.php" autocomplete="on">
        <?= csrf_field() ?>
        <h1>Registrera konto</h1>

        <?php if ($errors): ?>
            <div class="notice notice-error">
                <?php foreach ($errors as $error): ?>
                    <div><?= h($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="field">
            <label for="username">Användarnamn</label>
            <input id="username" name="username" type="text" maxlength="80" required autocomplete="username" value="<?= h($formData['username']) ?>">
        </div>

        <div class="field">
            <label for="full_name">Namn</label>
            <input id="full_name" name="full_name" type="text" maxlength="160" required autocomplete="name" value="<?= h($formData['full_name']) ?>">
        </div>

        <div class="field">
            <label for="role">Roll</label>
            <select id="role" name="role" required>
                <option value="student" <?= $formData['role'] === 'student' ? 'selected' : '' ?>>Elev</option>
                <option value="teacher" <?= $formData['role'] === 'teacher' ? 'selected' : '' ?>>Lärare</option>
            </select>
        </div>

        <div class="field">
            <label for="school_id">Skola</label>
            <select id="school_id" name="school_id" required>
                <option value="">Välj skola</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?= (int) $school['id'] ?>" <?= (int) $formData['school_id'] === (int) $school['id'] ? 'selected' : '' ?>>
                        <?= h($school['school_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="password">Lösenord</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirm">Bekräfta lösenord</label>
            <input id="password_confirm" name="password_confirm" type="password" minlength="8" required autocomplete="new-password">
        </div>

        <button class="button button-primary button-full" type="submit">Skicka registrering</button>

        <p class="muted small-text">
            Har du redan konto? <a href="login.php">Logga in här</a>.
        </p>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

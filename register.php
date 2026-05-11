<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$schools = fetch_schools($conn);
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => 'student',
    'school_id' => '',
    'processing_consent' => false,
];

if (is_post()) {
    verify_csrf();
    ensure_privacy_consent_columns($conn);

    $username = trim((string) ($_POST['username'] ?? ''));
    $email = normalize_email((string) ($_POST['email'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');
    $schoolId = (int) ($_POST['school_id'] ?? 0);
    $processingConsent = isset($_POST['processing_consent']);

    $formData = [
        'username' => $username,
        'email' => (string) $email,
        'full_name' => $fullName,
        'role' => $role,
        'school_id' => (string) $schoolId,
        'processing_consent' => $processingConsent,
    ];

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
    if ($password !== $passwordConfirm) {
        $errors[] = 'Lösenorden matchar inte.';
    }
    if (!in_array($role, ['student', 'teacher'], true)) {
        $errors[] = 'Du kan bara registrera dig som elev eller lärare.';
    }
    if ($schoolId <= 0) {
        $errors[] = 'Välj skola.';
    }
    if (!$processingConsent) {
        $errors[] = 'Du behöver samtycka till att SAGA behandlar dina kontouppgifter i relation till din skola.';
    }

    if (!$errors) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = execute_prepared(
                $conn,
                'INSERT INTO users
                 (username, email, password_hash, full_name, role, school_id, approval_status, privacy_consent_at, privacy_consent_version)
                 VALUES (?, ?, ?, ?, ?, ?, \'pending\', NOW(), ?)',
                'sssssis',
                [$username, $email, $passwordHash, $fullName, $role, $schoolId, privacy_consent_version()]
            );

            log_event($conn, null, 'registration_create', 'user', (int) $stmt->insert_id);
            notify_school_admins(
                $conn,
                $schoolId,
                'Ny registrering i SAGA',
                $fullName . ' har registrerat sig som ' . role_label($role) . ' och väntar på godkännande.'
            );
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
            <label for="email">E-post</label>
            <input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= h($formData['email']) ?>">
            <p class="field-help">Används för notifieringar om registreringen och arbetet.</p>
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

        <div class="consent-box">
            <label class="check-option">
                <input type="checkbox" name="processing_consent" value="1" <?= $formData['processing_consent'] ? 'checked' : '' ?> required>
                <span>
                    Jag samtycker till att SAGA behandlar mitt namn, mitt användarkonto, min roll och min koppling till vald skola för att skolan ska kunna administrera registrering, behörighet, handledning och arkivering av gymnasiearbeten.
                </span>
            </label>
            <p class="field-help">
                Om du är elev kan ditt gymnasiearbete senare hanteras i SAGA. Publicering är frivillig och kräver ett separat val: om du väljer att publicera samtycker du då till att ditt namn och arbetet blir sökbart i SAGA.
            </p>
            <p class="field-help">
                Du kan begära radering av ditt konto och dina personuppgifter via profilsidan. Läs mer i <a href="privacy.php" target="_blank" rel="noopener">Integritet och kakor</a>.
            </p>
        </div>

        <button class="button button-primary button-full" type="submit">Skicka registrering</button>

        <p class="muted small-text">
            Har du redan konto? <a href="login.php">Logga in här</a>.
        </p>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

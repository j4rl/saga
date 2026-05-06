<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if (is_post()) {
    verify_csrf();

    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $loginResult = login_user($conn, $username, $password);

    if ($loginResult['ok']) {
        $user = current_user();
        set_flash('success', 'Du är inloggad.');
        redirect(dashboard_url_for_role($user['role']));
    }

    $error = $loginResult['error'];
}

$pageTitle = 'Logga in';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="login.php" autocomplete="on">
        <?= csrf_field() ?>
        <h1>Logga in</h1>

        <?php if ($error): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="field">
            <label for="username">Användarnamn</label>
            <input id="username" name="username" type="text" required autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Lösenord</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>

        <button class="button button-primary button-full" type="submit">Logga in</button>

        <p class="muted small-text">
            Saknar du konto? <a href="register.php">Registrera dig här</a>.
        </p>
        <p class="muted small-text">
            <a href="forgot_password.php">Glömt lösenord?</a>
        </p>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;

if (is_post()) {
    verify_csrf();
    $result = reset_password_with_token(
        $conn,
        $token,
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['password_confirm'] ?? '')
    );

    if ($result['ok']) {
        set_flash('success', 'Lösenordet har återställts. Logga in med det nya lösenordet.');
        redirect('login.php');
    }

    $error = $result['error'];
}

$pageTitle = 'Återställ lösenord';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="reset_password.php">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <h1>Återställ lösenord</h1>

        <?php if ($error): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="field">
            <label for="password">Nytt lösenord</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        </div>
        <div class="field">
            <label for="password_confirm">Bekräfta nytt lösenord</label>
            <input id="password_confirm" name="password_confirm" type="password" minlength="8" required autocomplete="new-password">
        </div>
        <button class="button button-primary button-full" type="submit">Spara nytt lösenord</button>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

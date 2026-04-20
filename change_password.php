<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$error = null;

if (is_post()) {
    verify_csrf();

    $result = change_current_user_password(
        $conn,
        $user,
        (string) ($_POST['current_password'] ?? ''),
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['new_password_confirm'] ?? '')
    );

    if ($result['ok']) {
        set_flash('success', 'Lösenordet har ändrats.');
        redirect(dashboard_url_for_role($user['role']));
    }

    $error = $result['error'];
}

$pageTitle = 'Byt lösenord';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="change_password.php" autocomplete="on">
        <?= csrf_field() ?>
        <h1>Byt lösenord</h1>

        <?php if ($error): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="field">
            <label for="current_password">Nuvarande lösenord</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        </div>

        <div class="field">
            <label for="new_password">Nytt lösenord</label>
            <input id="new_password" name="new_password" type="password" minlength="8" required autocomplete="new-password">
        </div>

        <div class="field">
            <label for="new_password_confirm">Bekräfta nytt lösenord</label>
            <input id="new_password_confirm" name="new_password_confirm" type="password" minlength="8" required autocomplete="new-password">
        </div>

        <button class="button button-primary button-full" type="submit">Spara nytt lösenord</button>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

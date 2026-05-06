<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

if (is_post()) {
    verify_csrf();
    create_password_reset_request($conn, (string) ($_POST['identifier'] ?? ''));
    set_flash('success', 'Om kontot finns och har e-post skickas en återställningslänk.');
    redirect('login.php');
}

$pageTitle = 'Glömt lösenord';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-layout">
    <form class="form-card" method="post" action="forgot_password.php">
        <?= csrf_field() ?>
        <h1>Glömt lösenord</h1>
        <p class="muted">Ange användarnamn eller e-post. Om kontot finns skickas en återställningslänk.</p>
        <div class="field">
            <label for="identifier">Användarnamn eller e-post</label>
            <input id="identifier" name="identifier" type="text" required autocomplete="username">
        </div>
        <button class="button button-primary button-full" type="submit">Skicka länk</button>
        <p class="muted small-text"><a href="login.php">Tillbaka till inloggning</a></p>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

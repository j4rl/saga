<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$canEditName = can_edit_own_profile_name($user);
$cookieConsentAccepted = cookie_consent_accepted();
$profileError = null;
$passwordError = null;

if (is_post()) {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $result = update_current_user_profile(
            $conn,
            $user,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['full_name'] ?? '')
        );

        if ($result['ok']) {
            set_flash('success', 'Profilen har sparats.');
            redirect('profile.php');
        }

        $profileError = $result['error'];
    } elseif ($action === 'change_password') {
        $result = change_current_user_password(
            $conn,
            $user,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['new_password_confirm'] ?? '')
        );

        if ($result['ok']) {
            set_flash('success', 'Lösenordet har ändrats.');
            redirect('profile.php');
        }

        $passwordError = $result['error'];
    } elseif ($action === 'clear_cookie_consent') {
        clear_cookie_consent();
        set_flash('success', 'Godkännandet av kakor har tagits bort.');
        redirect('profile.php');
    }
}

$user = current_user();
$cookieConsentAccepted = cookie_consent_accepted();
$pageTitle = 'Profil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Konto</p>
    <h1>Profil</h1>
    <p class="muted">Här ser du vilken roll och skola kontot hör till.</p>
</section>

<section class="profile-layout">
    <aside class="profile-summary detail-card">
        <div>
            <span class="profile-avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
            <h2><?= h($user['full_name']) ?></h2>
            <p class="muted"><?= h($user['username']) ?></p>
        </div>

        <dl class="definition-grid profile-definition-grid">
            <div>
                <dt>Roll</dt>
                <dd><?= h(role_label($user['role'])) ?></dd>
            </div>
            <div>
                <dt>Skola</dt>
                <dd><?= h($user['school_name']) ?></dd>
            </div>
            <div>
                <dt>E-post</dt>
                <dd><?= $user['email'] ? h($user['email']) : 'Ingen e-post angiven' ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= h(approval_status_label($user['approval_status'])) ?></dd>
            </div>
        </dl>
    </aside>

    <div class="profile-forms">
        <form class="form-card profile-form-card" method="post" action="profile.php" autocomplete="on">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <h2>Kontouppgifter</h2>

            <?php if ($profileError): ?>
                <div class="notice notice-error"><?= h($profileError) ?></div>
            <?php endif; ?>

            <div class="field">
                <label for="full_name">Namn</label>
                <input
                    id="full_name"
                    name="full_name"
                    type="text"
                    maxlength="160"
                    value="<?= h($user['full_name']) ?>"
                    <?= $canEditName ? 'required autocomplete="name"' : 'disabled' ?>
                >
                <?php if (!$canEditName): ?>
                    <p class="field-help">Elever och lärare kan inte ändra namn själva.</p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">E-post</label>
                <input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= h($user['email']) ?>">
            </div>

            <button class="button button-primary" type="submit">Spara profil</button>
        </form>

        <form class="form-card profile-form-card" method="post" action="profile.php" autocomplete="on">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <h2>Byt lösenord</h2>

            <?php if ($passwordError): ?>
                <div class="notice notice-error"><?= h($passwordError) ?></div>
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

            <button class="button button-primary" type="submit">Spara nytt lösenord</button>
        </form>

        <form class="form-card profile-form-card" method="post" action="profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_cookie_consent">
            <h2>Kakor</h2>

            <p class="cookie-status">
                <span class="status-pill <?= $cookieConsentAccepted ? 'status-approved' : 'status-pending' ?>">
                    <?= $cookieConsentAccepted ? 'Godkända' : 'Inte godkända' ?>
                </span>
            </p>

            <p class="muted">
                SAGA använder nödvändiga kakor för att hålla dig inloggad, skydda formulär och komma ihåg lokala inställningar som tema.
                Samtyckeskakan sparar bara att du har godkänt kakor, så att frågan inte behöver visas varje gång.
            </p>

            <?php if ($cookieConsentAccepted): ?>
                <button class="button button-secondary" type="submit">Ta bort godkännande</button>
            <?php else: ?>
                <p class="field-help">Du kan godkänna kakor igen via meddelandet längst ner på sidan.</p>
            <?php endif; ?>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

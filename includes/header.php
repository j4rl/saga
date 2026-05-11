<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$userFirstName = '';
if ($user) {
    $nameParts = preg_split('/\s+/', trim((string) $user['full_name']));
    $userFirstName = $nameParts && $nameParts[0] !== '' ? $nameParts[0] : (string) $user['username'];
}
$schoolProfile = $user ? fetch_school_profile($conn, (int) $user['school_id']) : null;
$themeMode = current_theme_mode();
$themeCss = $schoolProfile ? school_theme_css_vars($schoolProfile) : '';
?>
<!doctype html>
<html lang="sv" data-theme="<?= h($themeMode) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
    <script>
        (() => {
            try {
                const theme = window.localStorage.getItem('saga.themeMode');
                if (['light', 'auto', 'dark'].includes(theme)) {
                    document.documentElement.dataset.theme = theme;
                }
            } catch (error) {
            }
        })();
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if ($themeCss): ?>
        <style><?= $themeCss ?></style>
    <?php endif; ?>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Hoppa till innehåll</a>
<header class="site-header">
    <a class="brand" href="index.php" aria-label="Startsida">
        <?php if ($schoolProfile && !empty($schoolProfile['logo_filename'])): ?>
            <span class="brand-logo-frame">
                <img class="brand-logo" src="school_logo.php?id=<?= (int) $schoolProfile['id'] ?>" alt="Logotyp för <?= h($schoolProfile['school_name']) ?>">
            </span>
        <?php else: ?>
            <span class="brand-mark">S</span>
        <?php endif; ?>
        <span>
            <strong><?= h(APP_NAME) ?></strong>
            <small>Gymnasiearbeten</small>
        </span>
    </a>
    <nav class="main-nav" aria-label="Huvudnavigation">
        <a class="nav-icon-link" href="search.php" aria-label="Sök" title="Sök">
            <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                <path d="M10.8 4.5a6.3 6.3 0 1 1 0 12.6 6.3 6.3 0 0 1 0-12.6Zm0 2a4.3 4.3 0 1 0 0 8.6 4.3 4.3 0 0 0 0-8.6Zm4.8 9.1 4.1 4.1-1.4 1.4-4.1-4.1 1.4-1.4Z"/>
            </svg>
        </a>
        <div class="theme-picker" data-theme-picker role="radiogroup" aria-label="Tema">
            <button class="theme-icon-button" type="button" data-theme-option="light" role="radio" aria-checked="<?= $themeMode === 'light' ? 'true' : 'false' ?>" title="Ljust tema" aria-label="Ljust tema">
                <span aria-hidden="true">☀</span>
            </button>
            <button class="theme-icon-button" type="button" data-theme-option="auto" role="radio" aria-checked="<?= $themeMode === 'auto' ? 'true' : 'false' ?>" title="Automatiskt tema" aria-label="Automatiskt tema">
                <span aria-hidden="true">A</span>
            </button>
            <button class="theme-icon-button" type="button" data-theme-option="dark" role="radio" aria-checked="<?= $themeMode === 'dark' ? 'true' : 'false' ?>" title="Mörkt tema" aria-label="Mörkt tema">
                <span aria-hidden="true">☾</span>
            </button>
        </div>
        <?php if ($user): ?>
            <a class="nav-action" href="<?= h(dashboard_url_for_role($user['role'])) ?>" title="Översikt">
                <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M4 11.2 12 4l8 7.2v8.3a.5.5 0 0 1-.5.5h-5.2v-5.7H9.7V20H4.5a.5.5 0 0 1-.5-.5v-8.3Zm2 1V18h1.7v-5.7h8.6V18H18v-5.8l-6-5.4-6 5.4Z"/>
                </svg>
                <span>Översikt</span>
            </a>
            <a class="nav-user" href="profile.php" title="Profilinställningar">
                <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M12 4.5a4.3 4.3 0 1 1 0 8.6 4.3 4.3 0 0 1 0-8.6Zm0 2a2.3 2.3 0 1 0 0 4.6 2.3 2.3 0 0 0 0-4.6Zm0 8.1c4 0 7 2 7 4.8V20H5v-.6c0-2.8 3-4.8 7-4.8Zm0 2c-2.3 0-4.1.8-4.7 1.4h9.4c-.6-.6-2.4-1.4-4.7-1.4Z"/>
                </svg>
                <span><?= h($userFirstName) ?></span>
            </a>
            <form class="nav-logout-form" method="post" action="logout.php">
                <?= csrf_field() ?>
                <button class="nav-icon-link nav-logout" type="submit" aria-label="Logga ut" title="Logga ut">
                    <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                        <path d="M5 4h7v2H7v12h5v2H5V4Zm10.6 4.4 4.1 4.1-4.1 4.1-1.4-1.4 1.7-1.7H10v-2h5.9l-1.7-1.7 1.4-1.4Z"/>
                    </svg>
                </button>
            </form>
        <?php else: ?>
            <a class="button button-primary nav-login" href="login.php">
                <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                    <path d="M10 4h9v16h-9v-2h7V6h-7V4Zm.4 4.4 4.1 4.1-4.1 4.1L9 15.2l1.7-1.7H4v-2h6.7L9 9.8l1.4-1.4Z"/>
                </svg>
                <span>Logga in</span>
            </a>
        <?php endif; ?>
    </nav>
</header>

<main id="main-content" class="page-shell" tabindex="-1">
    <?php foreach (get_flash_messages() as $message): ?>
        <div class="notice notice-<?= h($message['type']) ?>" role="status">
            <?= h($message['message']) ?>
        </div>
    <?php endforeach; ?>



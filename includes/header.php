<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
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
<header class="site-header">
    <a class="brand" href="index.php" aria-label="Startsida">
        <span class="brand-mark">S</span>
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
            <a href="<?= h(dashboard_url_for_role($user['role'])) ?>">Översikt</a>
            <a class="nav-user" href="profile.php"><?= h($user['full_name']) ?></a>
            <a class="button button-ghost" href="logout.php">Logga ut</a>
        <?php else: ?>
            <a href="register.php">Registrera</a>
            <a class="button button-primary" href="login.php">Logga in</a>
        <?php endif; ?>
    </nav>
</header>

<main class="page-shell">
    <?php foreach (get_flash_messages() as $message): ?>
        <div class="notice notice-<?= h($message['type']) ?>" role="status">
            <?= h($message['message']) ?>
        </div>
    <?php endforeach; ?>



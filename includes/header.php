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
        <style><?= h($themeCss) ?></style>
    <?php endif; ?>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php" aria-label="Startsida">
        <?php if ($schoolProfile && $schoolProfile['logo_filename']): ?>
            <img class="brand-logo" src="school_logo.php?id=<?= (int) $schoolProfile['id'] ?>" alt="">
        <?php else: ?>
            <span class="brand-mark">S</span>
        <?php endif; ?>
        <span>
            <strong><?= h($schoolProfile['school_name'] ?? APP_NAME) ?></strong>
            <small><?= $schoolProfile ? h(APP_NAME . ' · Gymnasiearbeten') : 'Gymnasiearbeten' ?></small>
        </span>
    </a>
    <nav class="main-nav" aria-label="Huvudnavigation">
        <a href="search.php">Sök</a>
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
            <a href="<?= h(dashboard_url_for_role($user['role'])) ?>">Panel</a>
            <span class="school-badge"><?= h($user['school_name']) ?></span>
            <span class="nav-user"><?= h($user['full_name']) ?></span>
            <a href="change_password.php">Lösenord</a>
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



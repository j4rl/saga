<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        <a href="search.php">Sök</a>
        <?php if ($user): ?>
            <a href="<?= h(dashboard_url_for_role($user['role'])) ?>">Panel</a>
            <span class="nav-user"><?= h($user['full_name']) ?></span>
            <a class="button button-ghost" href="logout.php">Logga ut</a>
        <?php else: ?>
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



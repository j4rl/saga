<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('teacher');

$user = current_user();
$view = (string) ($_GET['view'] ?? 'own');
$query = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'student_asc');
$results = teacher_dashboard_projects($conn, $user, $view, $query, $sort, 1, 500);
$view = $results['view'];
$viewLabels = [
    'own' => 'Mina handledningar',
    'supervised' => 'Alla handledningar',
    'school_submitted' => 'Inlämnade på skolan',
];
$format = (string) ($_GET['format'] ?? 'html');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gymnasiearbeten-lista.csv"');

    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Elev', 'Titel', 'Undertitel', 'Kategori', 'Handledare', 'Status', 'Inlämnad'], ';');

    foreach ($results['rows'] as $project) {
        fputcsv(
            $output,
            [
                $project['student_name'],
                $project['title'],
                $project['subtitle'],
                $project['category_name'],
                $project['supervisor_name'],
                (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast',
                (int) $project['is_submitted'] === 1 ? format_date($project['submitted_at']) : '',
            ],
            ';'
        );
    }

    fclose($output);
    exit;
}

if ($format === 'html' && isset($_GET['download'])) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="gymnasiearbeten-lista.html"');
}
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($viewLabels[$view]) ?> - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="print-page">
<main class="print-shell">
    <header class="print-header">
        <div>
            <p class="eyebrow"><?= h($user['school_name']) ?></p>
            <h1><?= h($viewLabels[$view]) ?></h1>
            <p class="muted">Lärare: <?= h($user['full_name']) ?> · Skapad <?= h(date('Y-m-d H:i')) ?></p>
        </div>
        <button class="button button-primary print-hide" type="button" onclick="window.print()">Skriv ut / spara PDF</button>
    </header>

    <?php if ($query !== ''): ?>
        <p class="muted print-filter">Filter: <?= h($query) ?></p>
    <?php endif; ?>

    <table class="data-table print-table">
        <thead>
        <tr>
            <th>Elev</th>
            <th>Titel</th>
            <th>Kategori</th>
            <th>Handledare</th>
            <th>Status</th>
            <th>Inlämnad</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$results['rows']): ?>
            <tr>
                <td colspan="6">Inga arbeten hittades.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($results['rows'] as $project): ?>
            <tr>
                <td><?= h($project['student_name']) ?></td>
                <td>
                    <strong><?= h($project['title']) ?></strong>
                    <?php if ($project['subtitle']): ?>
                        <span class="table-subtitle"><?= h($project['subtitle']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= h($project['category_name']) ?></td>
                <td><?= h($project['supervisor_name']) ?></td>
                <td><?= (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast' ?></td>
                <td><?= (int) $project['is_submitted'] === 1 ? h(format_date($project['submitted_at'])) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>

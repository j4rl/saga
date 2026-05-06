<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('super_admin');

$filters = [
    'action' => trim((string) ($_GET['action'] ?? '')),
    'entity_type' => trim((string) ($_GET['entity_type'] ?? '')),
    'user' => trim((string) ($_GET['user'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$export = (string) ($_GET['export'] ?? '');

if ($export === 'audit') {
    $rows = fetch_audit_log_entries($conn, $filters, 1, 10000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditlogg.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Datum', 'Användare', 'Åtgärd', 'Entitet', 'Entitets-ID', 'IP-prefix'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['created_at'],
            $row['full_name'] ?: $row['username'] ?: '',
            $row['action'],
            $row['entity_type'],
            $row['entity_id'],
            $row['ip_address'],
        ], ';');
    }
    fclose($out);
    exit;
}

if ($export === 'email') {
    $rows = fetch_email_notifications($conn, 10000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="epostnotiser.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Datum', 'Mottagare', 'Ämne', 'Status', 'Fel'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [$row['created_at'], $row['recipient_email'], $row['subject'], $row['status'], $row['error_message']], ';');
    }
    fclose($out);
    exit;
}

$total = count_audit_log_entries($conn, $filters);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$entries = fetch_audit_log_entries($conn, $filters, $page, $perPage);
$notifications = fetch_email_notifications($conn, 25);
$pageTitle = 'Auditlogg';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Superadmin</p>
    <h1>Auditlogg och export</h1>
    <p class="muted">Händelser sparas med anonymiserad IP-prefixinformation.</p>
</section>

<section class="section">
    <form class="search-panel search-panel-compact" method="get" action="audit.php">
        <div class="field">
            <label for="action">Åtgärd</label>
            <input id="action" name="action" type="search" value="<?= h($filters['action']) ?>" placeholder="login, user_update">
        </div>
        <div class="field">
            <label for="entity_type">Entitet</label>
            <input id="entity_type" name="entity_type" type="search" value="<?= h($filters['entity_type']) ?>" placeholder="user, project">
        </div>
        <div class="field">
            <label for="user">Användare</label>
            <input id="user" name="user" type="search" value="<?= h($filters['user']) ?>">
        </div>
        <div class="field">
            <label for="date_from">Från</label>
            <input id="date_from" name="date_from" type="date" value="<?= h($filters['date_from']) ?>">
        </div>
        <div class="field">
            <label for="date_to">Till</label>
            <input id="date_to" name="date_to" type="date" value="<?= h($filters['date_to']) ?>">
        </div>
        <button class="button button-primary" type="submit">Filtrera</button>
        <a class="button button-secondary" href="audit.php">Rensa</a>
    </form>

    <div class="action-row">
        <?php $exportParams = array_merge($filters, ['export' => 'audit']); ?>
        <a class="button button-secondary" href="audit.php?<?= h(http_build_query($exportParams)) ?>">Exportera auditlogg CSV</a>
        <a class="button button-secondary" href="audit.php?export=email">Exportera e-postnotiser CSV</a>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Auditlogg</h2>
        <span><?= (int) $total ?> händelser</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Datum</th>
                <th>Användare</th>
                <th>Åtgärd</th>
                <th>Entitet</th>
                <th>IP-prefix</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= h(format_date($entry['created_at'])) ?></td>
                    <td><?= h($entry['full_name'] ?: $entry['username'] ?: 'System/okänd') ?></td>
                    <td><?= h($entry['action']) ?></td>
                    <td><?= h($entry['entity_type']) ?> #<?= h((string) $entry['entity_id']) ?></td>
                    <td><?= h($entry['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Paginering för auditlogg">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php $params = array_merge($filters, ['page' => $i]); ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="audit.php?<?= h(http_build_query($params)) ?>"><?= (int) $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-heading">
        <h2>E-postnotiser</h2>
        <span><?= (int) count($notifications) ?> senaste</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Datum</th>
                <th>Mottagare</th>
                <th>Ämne</th>
                <th>Status</th>
                <th>Fel</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($notifications as $notification): ?>
                <tr>
                    <td><?= h(format_date($notification['created_at'])) ?></td>
                    <td><?= h($notification['recipient_email']) ?></td>
                    <td><?= h($notification['subject']) ?></td>
                    <td><?= h($notification['status']) ?></td>
                    <td><?= h($notification['error_message'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

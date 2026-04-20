<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('teacher');

$user = current_user();
$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$results = search_projects($conn, ['q' => $query, 'school_id' => (int) $user['school_id']], $user, $page, 10);
$pageTitle = 'Lärarpanel';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Lärarpanel</p>
    <h1>Arbeten på <?= h($user['school_name']) ?></h1>

    <form class="search-panel search-panel-compact" method="get" action="dashboard_teacher.php">
        <div class="field field-grow">
            <label for="q">Sök bland skolans arbeten</label>
            <input id="q" name="q" type="search" value="<?= h($query) ?>" placeholder="Rubrik, abstract eller sammanfattning">
        </div>
        <button class="button button-primary" type="submit">Sök</button>
    </form>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Resultat</h2>
        <span><?= (int) $results['total'] ?> arbeten</span>
    </div>

    <?php if (!$results['rows']): ?>
        <p class="empty-state">Inga arbeten hittades.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Rubrik</th>
                    <th>Elev</th>
                    <th>Handledare</th>
                    <th>Status</th>
                    <th>Uppdaterad</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results['rows'] as $project): ?>
                    <tr>
                        <td>
                            <strong><?= h($project['title']) ?></strong>
                            <?php if ($project['subtitle']): ?>
                                <span class="table-subtitle"><?= h($project['subtitle']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($project['student_name']) ?></td>
                        <td><?= h($project['supervisor']) ?></td>
                        <td>
                            <span class="status-pill <?= (int) $project['is_submitted'] === 1 ? 'status-submitted' : 'status-draft' ?>">
                                <?= (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast' ?>
                            </span>
                        </td>
                        <td><?= h(format_date($project['updated_at'])) ?></td>
                        <td><a href="project_view.php?id=<?= (int) $project['id'] ?>">Visa</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('teacher');

$user = current_user();
$view = (string) ($_GET['view'] ?? 'own');
$query = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'updated_desc');
$page = max(1, (int) ($_GET['page'] ?? 1));
$results = teacher_dashboard_projects($conn, $user, $view, $query, $sort, $page, 10);
$counts = teacher_dashboard_counts($conn, $user);
$view = $results['view'];
$sort = $results['sort'];
$viewLabels = [
    'own' => 'Mina handledningar',
    'supervised' => 'Alla handledningar',
    'school_submitted' => 'Inlämnade på skolan',
];
$pageTitle = 'Lärarpanel';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Lärarpanel</p>
    <h1>Gymnasiearbeten</h1>
    <p class="muted">Standardvyn visar arbeten där du är angiven som handledare på <?= h($user['school_name']) ?>.</p>

    <nav class="filter-tabs" aria-label="Välj arbetslista">
        <a class="<?= $view === 'own' ? 'active' : '' ?>" href="dashboard_teacher.php?<?= h(http_build_query(['view' => 'own', 'sort' => $sort])) ?>">
            Mina handledningar <span><?= (int) $counts['own'] ?></span>
        </a>
        <a class="<?= $view === 'supervised' ? 'active' : '' ?>" href="dashboard_teacher.php?<?= h(http_build_query(['view' => 'supervised', 'sort' => $sort])) ?>">
            Alla handledningar <span><?= (int) $counts['supervised'] ?></span>
        </a>
        <a class="<?= $view === 'school_submitted' ? 'active' : '' ?>" href="dashboard_teacher.php?<?= h(http_build_query(['view' => 'school_submitted', 'sort' => $sort])) ?>">
            Inlämnade på skolan <span><?= (int) $counts['school_submitted'] ?></span>
        </a>
    </nav>

    <form class="search-panel search-panel-compact" method="get" action="dashboard_teacher.php">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <div class="field field-grow">
            <label for="q">Sök i aktuell lista</label>
            <input id="q" name="q" type="search" value="<?= h($query) ?>" placeholder="Rubrik, elev, handledare eller sammanfattning">
        </div>
        <div class="field">
            <label for="sort">Sortera</label>
            <select id="sort" name="sort">
                <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Senast uppdaterad</option>
                <option value="submitted_desc" <?= $sort === 'submitted_desc' ? 'selected' : '' ?>>Senast inlämnad</option>
                <option value="status_desc" <?= $sort === 'status_desc' ? 'selected' : '' ?>>Status</option>
                <option value="student_asc" <?= $sort === 'student_asc' ? 'selected' : '' ?>>Elev A-Ö</option>
                <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Rubrik A-Ö</option>
            </select>
        </div>
        <button class="button button-primary" type="submit">Sök</button>
        <button class="button button-secondary" type="submit" formaction="search.php" formmethod="get" name="advanced" value="1">Avancerad sökning</button>
    </form>

    <div class="action-row teacher-export-actions">
        <a class="button button-secondary" href="teacher_project_list.php?<?= h(http_build_query(['view' => $view, 'q' => $query, 'sort' => $sort])) ?>" target="_blank">Skriv ut / spara PDF</a>
        <a class="button button-secondary" href="teacher_project_list.php?<?= h(http_build_query(['view' => $view, 'q' => $query, 'sort' => $sort, 'format' => 'csv'])) ?>">Exportera CSV</a>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2><?= h($viewLabels[$view]) ?></h2>
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
                    <th>Kategori</th>
                    <th>Skola</th>
                    <th>Status</th>
                    <th>Inlämnad</th>
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
                        <td><?= h($project['supervisor_name']) ?></td>
                        <td><?= h($project['category_name']) ?></td>
                        <td><?= h($project['school_name']) ?></td>
                        <td>
                            <span class="status-pill <?= (int) $project['is_submitted'] === 1 ? 'status-submitted' : 'status-draft' ?>">
                                <?= (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast' ?>
                            </span>
                        </td>
                        <td><?= (int) $project['is_submitted'] === 1 ? h(format_date($project['submitted_at'])) : '-' ?></td>
                        <td><?= h(format_date($project['updated_at'])) ?></td>
                        <td>
                            <a href="project_view.php?id=<?= (int) $project['id'] ?>">Visa</a>
                            <?php if (can_unlock_project_submission($project, $user)): ?>
                                <span aria-hidden="true"> · </span>
                                <a href="project_edit.php?id=<?= (int) $project['id'] ?>">Hantera</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($results['pages'] > 1): ?>
            <nav class="pagination" aria-label="Paginering">
                <?php for ($i = 1; $i <= $results['pages']; $i++): ?>
                    <?php
                    $params = [
                        'view' => $view,
                        'q' => $query,
                        'sort' => $sort,
                        'page' => $i,
                    ];
                    ?>
                    <a class="<?= $i === $results['page'] ? 'active' : '' ?>" href="dashboard_teacher.php?<?= h(http_build_query($params)) ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('teacher');

$user = current_user();
$view = (string) ($_GET['view'] ?? 'own');
$query = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'updated_desc');
$page = max(1, (int) ($_GET['page'] ?? 1));

if (is_post()) {
    verify_csrf();

    $returnParams = [
        'view' => (string) ($_POST['view'] ?? $view),
        'q' => trim((string) ($_POST['q'] ?? $query)),
        'sort' => (string) ($_POST['sort'] ?? $sort),
        'page' => max(1, (int) ($_POST['page'] ?? $page)),
    ];

    if ((string) ($_POST['action'] ?? '') === 'set_approval') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = $projectId > 0 ? get_project_by_id($conn, $projectId) : null;

        if ($project) {
            $result = set_project_approval($conn, $project, $user, isset($_POST['is_approved']));
            set_flash(
                $result['ok'] ? 'success' : 'error',
                $result['ok']
                    ? (!empty($result['approved']) ? 'Arbetet har godkänts.' : 'Godkännandet har tagits bort.')
                    : (string) ($result['error'] ?? 'Godkännandet kunde inte sparas.')
            );
        } else {
            set_flash('error', 'Arbetet kunde inte hittas.');
        }
    }

    if ((string) ($_POST['action'] ?? '') === 'review_registration') {
        $registrationId = (int) ($_POST['user_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        $status = match ($decision) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => '',
        };

        if ($registrationId <= 0 || $status === '') {
            set_flash('error', 'Ogiltig elevregistrering.');
        } elseif (review_registration($conn, $registrationId, $status, $user)) {
            set_flash('success', $status === 'approved' ? 'Elevregistreringen har godkänts.' : 'Elevregistreringen har avvisats.');
        } else {
            set_flash('error', 'Elevregistreringen kunde inte uppdateras.');
        }
    }

    redirect('dashboard_teacher.php?' . http_build_query($returnParams));
}

$assignedRegistrations = fetch_teacher_registration_requests($conn, $user);
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
    <p class="muted">Standardvyn visar arbeten där du är angiven som handledare på <?= h($user['school_name']) ?>. Arbeten med tidigare handledare läggs på läraren som har flest handledda arbeten i samma kategori.</p>

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
            <input id="q" name="q" type="search" value="<?= h($query) ?>" placeholder="Titel, elev, handledare eller sammanfattning">
        </div>
        <div class="field">
            <label for="sort">Sortera</label>
            <select id="sort" name="sort">
                <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Senast uppdaterad</option>
                <option value="submitted_desc" <?= $sort === 'submitted_desc' ? 'selected' : '' ?>>Senast inlämnad</option>
                <option value="status_desc" <?= $sort === 'status_desc' ? 'selected' : '' ?>>Status</option>
                <option value="student_asc" <?= $sort === 'student_asc' ? 'selected' : '' ?>>Elev A-Ö</option>
                <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Titel A-Ö</option>
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

<?php if ($assignedRegistrations): ?>
    <section class="section">
        <div class="section-heading">
            <div>
                <h2>Elevregistreringar att godkänna</h2>
                <p class="muted">Skoladministratören har tilldelat dessa registreringar till dig.</p>
            </div>
            <span><?= (int) count($assignedRegistrations) ?> väntar</span>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Namn</th>
                    <th>Användarnamn</th>
                    <th>E-post</th>
                    <th>Registrerad</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($assignedRegistrations as $registration): ?>
                    <tr>
                        <td><?= h($registration['full_name']) ?></td>
                        <td><?= h($registration['username']) ?></td>
                        <td><?= h($registration['email'] ?? '') ?></td>
                        <td><?= h(format_date($registration['created_at'])) ?></td>
                        <td>
                            <form class="inline-actions" method="post" action="dashboard_teacher.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="review_registration">
                                <input type="hidden" name="user_id" value="<?= (int) $registration['id'] ?>">
                                <input type="hidden" name="view" value="<?= h($view) ?>">
                                <input type="hidden" name="q" value="<?= h($query) ?>">
                                <input type="hidden" name="sort" value="<?= h($sort) ?>">
                                <input type="hidden" name="page" value="<?= (int) $results['page'] ?>">
                                <button class="button button-primary" type="submit" name="decision" value="approve">Godkänn</button>
                                <button class="button button-secondary" type="submit" name="decision" value="reject">Avvisa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="section-heading">
        <h2><?= h($viewLabels[$view]) ?></h2>
        <span><?= (int) $results['total'] ?> arbeten</span>
    </div>

    <?php if (!$results['rows']): ?>
        <p class="empty-state">Inga arbeten hittades.</p>
    <?php else: ?>
        <div class="project-list">
            <?php foreach ($results['rows'] as $project): ?>
                <details class="project-list-item">
                    <summary>
                        <?php $isEffectivelyPublic = project_is_publicly_visible($project); ?>
                        <span class="project-list-main">
                            <strong><?= h($project['title']) ?></strong>
                            <?php if ($project['subtitle']): ?>
                                <span><?= h($project['subtitle']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="project-list-names">
                            <span>Elev: <?= h($project['student_name']) ?></span>
                            <span>Handledare: <?= h($project['supervisor_name']) ?></span>
                        </span>
                        <span class="project-status-icons" aria-label="Status">
                            <?php if ((int) ($project['is_approved'] ?? 0) === 1): ?>
                                <span class="project-status-icon status-icon-approved" title="Godkänd" aria-label="Godkänd">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.2 16.6 4.8 12.2l-1.9 1.9 6.3 6.3L21.4 8.2l-1.9-1.9z"/></svg>
                                </span>
                            <?php endif; ?>
                            <?php if ($isEffectivelyPublic): ?>
                                <span class="project-status-icon status-icon-visible" title="Visas" aria-label="Visas">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5C6.6 5 2.3 8.4 1 12c1.3 3.6 5.6 7 11 7s9.7-3.4 11-7c-1.3-3.6-5.6-7-11-7Zm0 11.2A4.2 4.2 0 1 1 12 7.8a4.2 4.2 0 0 1 0 8.4Zm0-2.4a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z"/></svg>
                                </span>
                            <?php else: ?>
                                <span class="project-status-icon status-icon-hidden" title="Visas ej" aria-label="Visas ej">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.3 2 18.7 18.7-1.6 1.6-3.3-3.3A12.3 12.3 0 0 1 12 20C6.6 20 2.3 16.6 1 13a12.7 12.7 0 0 1 4.1-5.4L1.7 4.2 3.3 2Zm5.5 8.7a4.2 4.2 0 0 0 5.5 5.5l-1.9-1.9a1.8 1.8 0 0 1-1.7-1.7l-1.9-1.9ZM12 6c5.4 0 9.7 3.4 11 7a12.3 12.3 0 0 1-2.9 4.2l-3-3A4.2 4.2 0 0 0 11 8.1L8.8 5.9A12 12 0 0 1 12 6Z"/></svg>
                                </span>
                            <?php endif; ?>
                            <?php if ((int) $project['is_submitted'] !== 1): ?>
                                <span class="project-status-icon status-icon-draft" title="Utkast" aria-label="Utkast"></span>
                            <?php endif; ?>
                        </span>
                    </summary>

                    <div class="project-list-details">
                        <dl class="project-list-facts">
                            <div>
                                <dt>Kategori</dt>
                                <dd><?= h($project['category_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Skola</dt>
                                <dd><?= h($project['school_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>
                                    <span class="status-pill <?= (int) $project['is_submitted'] === 1 ? 'status-submitted' : 'status-draft' ?>">
                                        <?= (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast' ?>
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt>Godkänt</dt>
                                <dd>
                                    <?php if (can_approve_project_for_teacher($conn, $project, $user)): ?>
                                        <form class="approval-form" method="post" action="dashboard_teacher.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set_approval">
                                            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                            <input type="hidden" name="view" value="<?= h($view) ?>">
                                            <input type="hidden" name="q" value="<?= h($query) ?>">
                                            <input type="hidden" name="sort" value="<?= h($sort) ?>">
                                            <input type="hidden" name="page" value="<?= (int) $results['page'] ?>">
                                            <label class="approval-toggle">
                                                <input
                                                    type="checkbox"
                                                    name="is_approved"
                                                    value="1"
                                                    <?= (int) ($project['is_approved'] ?? 0) === 1 ? 'checked' : '' ?>
                                                    onchange="this.form.submit()"
                                                >
                                                <span><?= (int) ($project['is_approved'] ?? 0) === 1 ? 'Godkänt' : 'Godkänn' ?></span>
                                            </label>
                                            <noscript><button class="button button-secondary button-small" type="submit">Spara</button></noscript>
                                        </form>
                                    <?php elseif ((int) $project['is_submitted'] === 1 && (int) ($project['is_approved'] ?? 0) === 1): ?>
                                        <span class="status-pill status-approved">Godkänt</span>
                                    <?php elseif ((int) $project['is_submitted'] === 1): ?>
                                        <span class="status-pill status-pending">Väntar</span>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Inlämnad</dt>
                                <dd><?= (int) $project['is_submitted'] === 1 ? h(format_date($project['submitted_at'])) : '-' ?></dd>
                            </div>
                            <div>
                                <dt>Uppdaterad</dt>
                                <dd><?= h(format_date($project['updated_at'])) ?></dd>
                            </div>
                        </dl>

                        <div class="project-actions">
                            <a class="button button-secondary" href="project_view.php?id=<?= (int) $project['id'] ?>">Visa</a>
                            <?php if (can_comment_project($project, $user)): ?>
                                <a class="button button-secondary" href="project_view.php?id=<?= (int) $project['id'] ?>#feedback">Ge återkoppling</a>
                            <?php endif; ?>
                            <?php if (can_unlock_project_submission($project, $user)): ?>
                                <a class="button button-secondary" href="project_edit.php?id=<?= (int) $project['id'] ?>">Hantera</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
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

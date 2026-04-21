<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$projectId = (int) ($_GET['id'] ?? 0);
$project = $projectId > 0 ? get_project_by_id($conn, $projectId) : null;
$viewer = current_user();

if ($project && !can_view_project($project, $viewer)) {
    set_flash('error', 'Du har inte behörighet att visa arbetet.');
    redirect('index.php');
}

if (!$project) {
    http_response_code(404);
    $pageTitle = 'Arbete saknas';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section section-tight">
        <h1>Arbetet kunde inte visas</h1>
        <p class="empty-state">Arbetet finns inte.</p>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $project['title'];
$versions = fetch_project_versions($conn, (int) $project['id']);
require_once __DIR__ . '/includes/header.php';
?>

<article class="project-detail">
    <header class="project-header">
        <div>
            <p class="eyebrow"><?= h($project['school_name']) ?></p>
            <h1><?= h($project['title']) ?></h1>
            <?php if ($project['subtitle']): ?>
                <p class="lead"><?= h($project['subtitle']) ?></p>
            <?php endif; ?>
        </div>
        <div class="status-stack">
            <span class="status-pill <?= (int) $project['is_public'] === 1 ? 'status-public' : 'status-private' ?>">
                <?= (int) $project['is_public'] === 1 ? 'Publik' : 'Inte publik' ?>
            </span>
            <span class="status-pill <?= (int) $project['is_submitted'] === 1 ? 'status-submitted' : 'status-draft' ?>">
                <?= (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast' ?>
            </span>
        </div>
    </header>

    <section class="detail-card">
        <dl class="definition-grid">
            <div>
                <dt>Elev</dt>
                <dd><?= h($project['student_name']) ?></dd>
            </div>
            <div>
                <dt>Handledare</dt>
                <dd><?= h($project['supervisor_name']) ?></dd>
            </div>
            <div>
                <dt>Kategori</dt>
                <dd><?= h($project['category_name']) ?></dd>
            </div>
            <div>
                <dt>Skola</dt>
                <dd><?= h($project['school_name']) ?></dd>
            </div>
            <div>
                <dt>Senast uppdaterad</dt>
                <dd><?= h(format_date($project['updated_at'])) ?></dd>
            </div>
            <div>
                <dt>Inlämnad</dt>
                <dd><?= (int) $project['is_submitted'] === 1 ? h(format_date($project['submitted_at'])) : 'Nej' ?></dd>
            </div>
            <div>
                <dt>PDF</dt>
                <dd>
                    <?php if ($project['pdf_filename']): ?>
                        <a href="download.php?id=<?= (int) $project['id'] ?>">Öppna</a>
                        <span aria-hidden="true"> · </span>
                        <a href="download.php?id=<?= (int) $project['id'] ?>&download=1">Ladda ned</a>
                    <?php else: ?>
                        Saknas
                    <?php endif; ?>
                </dd>
            </div>
        </dl>

        <?php if ($viewer && can_edit_project($project, $viewer)): ?>
            <div class="action-row">
                <a class="button button-primary" href="project_edit.php?id=<?= (int) $project['id'] ?>">
                    <?= can_edit_project_content($project, $viewer) ? 'Redigera arbete' : 'Hantera inlämning' ?>
                </a>
            </div>
        <?php endif; ?>
    </section>

    <section class="content-block">
        <h2>Abstract</h2>
        <p><?= nl2br(h($project['abstract_text'])) ?></p>
    </section>

    <section class="content-block">
        <h2>Sammanfattning</h2>
        <p><?= nl2br(h($project['summary_text'])) ?></p>
    </section>

    <section class="content-block">
        <h2>PDF-historik</h2>
        <?php if (!$versions): ?>
            <p class="empty-state">Ingen PDF-version har sparats ännu.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Fil</th>
                        <th>Uppladdad av</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($versions as $version): ?>
                        <tr>
                            <td><?= h($version['original_name']) ?></td>
                            <td><?= h($version['uploaded_by_name']) ?></td>
                            <td><?= h(format_date($version['created_at'])) ?></td>
                            <td>
                                <a href="download.php?id=<?= (int) $project['id'] ?>&version=<?= (int) $version['id'] ?>">Öppna</a>
                                <span aria-hidden="true"> · </span>
                                <a href="download.php?id=<?= (int) $project['id'] ?>&version=<?= (int) $version['id'] ?>&download=1">Ladda ned</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



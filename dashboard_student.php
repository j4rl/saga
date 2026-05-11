<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('student');

$user = current_user();
$project = get_project_for_student($conn, (int) $user['id']);
$pageTitle = 'Elevpanel';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Elevpanel</p>
            <h1>Mitt gymnasiearbete</h1>
        </div>
        <?php if (!$project): ?>
            <a class="button button-primary" href="upload_project.php">Ladda upp arbete</a>
        <?php elseif ((int) $project['is_submitted'] !== 1): ?>
            <a class="button button-primary" href="project_edit.php">Redigera arbete</a>
        <?php endif; ?>
    </div>

    <?php if (!$project): ?>
        <div class="empty-state">
            Du har inte lagt in något arbete ännu.
        </div>
    <?php else: ?>
        <article class="detail-card">
            <div class="result-title-row">
                <div>
                    <h2><?= h($project['title']) ?></h2>
                    <?php if ($project['subtitle']): ?>
                        <p class="subtitle"><?= h($project['subtitle']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="status-stack">
                    <?php $isEffectivelyPublic = project_is_publicly_visible($project); ?>
                    <span class="status-pill <?= $isEffectivelyPublic ? 'status-public' : 'status-private' ?>">
                        <?= $isEffectivelyPublic ? 'Publik' : 'Inte publik' ?>
                    </span>
                    <span class="status-pill <?= (int) $project['is_submitted'] === 1 ? 'status-submitted' : 'status-draft' ?>">
                        <?= (int) $project['is_submitted'] === 1 ? 'Inlämnat' : 'Utkast' ?>
                    </span>
                    <?php if ((int) $project['is_submitted'] === 1): ?>
                        <span class="status-pill <?= (int) ($project['is_approved'] ?? 0) === 1 ? 'status-approved' : 'status-pending' ?>">
                            <?= (int) ($project['is_approved'] ?? 0) === 1 ? 'Godkänt' : 'Väntar på godkännande' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="definition-grid">
                <div>
                    <dt>Skola</dt>
                    <dd><?= h($project['school_name']) ?></dd>
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
                    <dt>Uppdaterad</dt>
                    <dd><?= h(format_date($project['updated_at'])) ?></dd>
                </div>
                <div>
                    <dt>PDF</dt>
                    <dd>
                        <?php if ($project['pdf_filename']): ?>
                            <a href="download.php?id=<?= (int) $project['id'] ?>">Öppna PDF</a>
                        <?php else: ?>
                            Saknas
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>

            <div class="action-row">
                <a class="button button-secondary" href="project_view.php?id=<?= (int) $project['id'] ?>">Visa</a>
                <?php if ((int) $project['is_submitted'] !== 1): ?>
                    <a class="button button-primary" href="project_edit.php">Redigera</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('student');

$user = current_user();
$project = get_project_for_student($conn, (int) $user['id']);
$feedbackError = null;

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_feedback' && $project) {
        $result = add_project_feedback($conn, $project, $user, (string) ($_POST['comment_text'] ?? ''));
        if ($result['ok']) {
            set_flash('success', 'Kommentaren har sparats.');
            redirect('dashboard_student.php#feedback');
        }

        $feedbackError = $result['error'] ?? 'Kommentaren kunde inte sparas.';
    }
}

$canViewFeedback = $project && can_view_project_feedback($project, $user);
$feedback = $canViewFeedback ? fetch_project_feedback($conn, (int) $project['id']) : [];
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

        <?php if ($canViewFeedback): ?>
            <section id="feedback" class="section">
                <div class="section-heading">
                    <div>
                        <h2>Återkoppling</h2>
                        <p class="muted">Kommentarer mellan dig och handledaren.</p>
                    </div>
                </div>

                <?php if (!$feedback): ?>
                    <p class="empty-state">Ingen återkoppling har skrivits ännu.</p>
                <?php else: ?>
                    <div class="feedback-list">
                        <?php foreach ($feedback as $comment): ?>
                            <article class="feedback-item">
                                <header>
                                    <strong><?= h($comment['full_name']) ?></strong>
                                    <span><?= h(role_label($comment['role'])) ?> · <?= h(format_date($comment['created_at'])) ?></span>
                                </header>
                                <p><?= nl2br(h($comment['comment_text'])) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (can_comment_project($project, $user)): ?>
                    <form class="feedback-form" method="post" action="dashboard_student.php#feedback">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_feedback">
                        <?php if ($feedbackError): ?>
                            <div class="notice notice-error"><?= h($feedbackError) ?></div>
                        <?php endif; ?>
                        <div class="field">
                            <label for="comment_text">Svara</label>
                            <textarea id="comment_text" name="comment_text" rows="5" maxlength="2000" required></textarea>
                        </div>
                        <button class="button button-primary" type="submit">Skicka svar</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



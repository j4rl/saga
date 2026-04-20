<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/project_form_handler.php';
require_role('student');

$user = current_user();
$project = get_project_for_student($conn, (int) $user['id']);
$errors = [];
$formData = [
    'title' => $project['title'] ?? '',
    'subtitle' => $project['subtitle'] ?? '',
    'supervisor' => $project['supervisor'] ?? '',
    'abstractText' => $project['abstract_text'] ?? '',
    'summaryText' => $project['summary_text'] ?? '',
    'isPublic' => (int) ($project['is_public'] ?? 0),
    'isSubmitted' => (int) ($project['is_submitted'] ?? 0),
];

if (is_post()) {
    $result = handle_project_submission($conn, $user, $project);

    if ($result['ok']) {
        set_flash('success', 'Arbetet har sparats.');
        redirect('project_view.php?id=' . (int) $result['project_id']);
    }

    $errors = $result['errors'] ?? ['Kunde inte spara arbetet.'];
    if (!empty($result['data'])) {
        $formData = $result['data'];
    }
}

$pageTitle = $project ? 'Redigera arbete' : 'Ladda upp arbete';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Elevpanel</p>
    <h1><?= $project ? 'Redigera gymnasiearbete' : 'Ladda upp gymnasiearbete' ?></h1>
    <p class="muted">PDF-filen sparas med slumpat filnamn och kan bara hämtas via behörighetskontrollerad länk.</p>
</section>

<?php if ($errors): ?>
    <section class="section section-tight">
        <div class="notice notice-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <form class="project-form" method="post" action="project_edit.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="field">
                <label for="title">Rubrik</label>
                <input id="title" name="title" type="text" maxlength="180" required value="<?= h($formData['title']) ?>">
            </div>

            <div class="field">
                <label for="subtitle">Underrubrik</label>
                <input id="subtitle" name="subtitle" type="text" maxlength="180" value="<?= h($formData['subtitle']) ?>">
            </div>

            <div class="field">
                <label for="supervisor">Handledare</label>
                <input id="supervisor" name="supervisor" type="text" maxlength="120" required value="<?= h($formData['supervisor']) ?>">
            </div>

            <div class="field">
                <label for="pdf_file"><?= $project && $project['pdf_filename'] ? 'Ersätt PDF' : 'PDF-fil' ?></label>
                <input id="pdf_file" name="pdf_file" type="file" accept="application/pdf,.pdf" <?= $project ? '' : 'required' ?> data-max-bytes="<?= (int) MAX_UPLOAD_BYTES ?>">
                <?php if ($project && $project['pdf_original_name']): ?>
                    <p class="field-help">Nuvarande fil: <?= h($project['pdf_original_name']) ?></p>
                <?php else: ?>
                    <p class="field-help">Max 15 MB. Endast PDF.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label for="abstract_text">Abstract</label>
            <textarea id="abstract_text" name="abstract_text" rows="8" required data-counter="abstract-count"><?= h($formData['abstractText']) ?></textarea>
            <p class="field-help"><span id="abstract-count">0</span> tecken</p>
        </div>

        <div class="field">
            <label for="summary_text">Sammanfattning</label>
            <textarea id="summary_text" name="summary_text" rows="10" required data-counter="summary-count"><?= h($formData['summaryText']) ?></textarea>
            <p class="field-help"><span id="summary-count">0</span> tecken</p>
        </div>

        <div class="toggle-row">
            <label class="check-option">
                <input type="checkbox" name="is_public" value="1" <?= (int) $formData['isPublic'] === 1 ? 'checked' : '' ?>>
                <span>Gör arbetet sökbart och synligt för andra</span>
            </label>

            <label class="check-option">
                <input type="checkbox" name="is_submitted" value="1" <?= (int) $formData['isSubmitted'] === 1 ? 'checked' : '' ?>>
                <span>Markera arbetet som inlämnat</span>
            </label>
        </div>

        <div class="action-row">
            <a class="button button-secondary" href="dashboard_student.php">Avbryt</a>
            <button class="button button-primary" type="submit">Spara arbete</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



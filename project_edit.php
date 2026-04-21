<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/project_form_handler.php';
require_role(['student', 'teacher', 'school_admin', 'super_admin']);

$user = current_user();
$projectId = (int) ($_GET['id'] ?? 0);
$project = $projectId > 0 ? get_project_by_id($conn, $projectId) : null;

if (!$project && $user['role'] === 'student' && $projectId <= 0) {
    $project = get_project_for_student($conn, (int) $user['id']);
}

if (!$project && $projectId > 0) {
    http_response_code(404);
    $pageTitle = 'Arbete saknas';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section section-tight">
        <h1>Arbetet kunde inte redigeras</h1>
        <p class="empty-state">Arbetet finns inte.</p>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (!$project && $user['role'] !== 'student') {
    set_flash('error', 'Du har inte behörighet att ändra arbetet.');
    redirect('index.php');
}

if ($project && !can_edit_project($project, $user)) {
    set_flash('error', 'Du har inte behörighet att ändra arbetet.');
    redirect('index.php');
}

$canEditContent = $project ? can_edit_project_content($project, $user) : true;
$canUnlockSubmission = $project ? can_unlock_project_submission($project, $user) : false;
$projectOwner = $project ? [
    'id' => (int) $project['user_id'],
    'full_name' => (string) $project['student_name'],
    'school_id' => (int) $project['school_id'],
    'school_name' => (string) $project['school_name'],
] : $user;
$formAction = 'project_edit.php' . ($project ? '?id=' . (int) $project['id'] : '');
$cancelUrl = $project ? 'project_view.php?id=' . (int) $project['id'] : 'dashboard_student.php';

$teachers = fetch_school_teachers($conn, (int) $projectOwner['school_id']);
$categories = fetch_project_categories($conn);
$errors = [];
$formData = [
    'title' => $project['title'] ?? '',
    'subtitle' => $project['subtitle'] ?? '',
    'supervisorUserId' => (int) ($project['supervisor_user_id'] ?? 0),
    'categoryName' => $project['category_name'] ?? '',
    'abstractText' => $project['abstract_text'] ?? '',
    'summaryText' => $project['summary_text'] ?? '',
    'isPublic' => (int) ($project['is_public'] ?? 0),
    'isSubmitted' => (int) ($project['is_submitted'] ?? 0),
];

if (is_post()) {
    $result = handle_project_submission($conn, $user, $project);

    if ($result['ok']) {
        if ($canUnlockSubmission && !$canEditContent) {
            set_flash('success', !empty($result['unlocked']) ? 'Inlämningen har låsts upp.' : 'Inlämningsstatusen har sparats.');
        } else {
            set_flash('success', 'Arbetet har sparats.');
        }
        redirect('project_view.php?id=' . (int) $result['project_id']);
    }

    $errors = $result['errors'] ?? ['Kunde inte spara arbetet.'];
    if (!empty($result['data'])) {
        $formData = $result['data'];
    }
}

$pageTitle = $project ? ($canEditContent ? 'Redigera arbete' : 'Hantera inlämning') : 'Ladda upp arbete';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow"><?= h(role_label($user['role'])) ?></p>
    <h1><?= $project ? ($canEditContent ? 'Redigera gymnasiearbete' : 'Hantera inlämning') : 'Ladda upp gymnasiearbete' ?></h1>
    <?php if ($canEditContent): ?>
        <p class="muted">När arbetet markeras som slutgiltigt inlämnat låses det för fortsatt elevredigering.</p>
    <?php else: ?>
        <p class="muted">Avmarkera slutlig inlämning för att låta eleven fortsätta redigera arbetet.</p>
    <?php endif; ?>
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
    <form class="project-form" method="post" action="<?= h($formAction) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="field">
                <label for="student_name">Elev</label>
                <input id="student_name" type="text" value="<?= h($projectOwner['full_name']) ?>" disabled>
                <p class="field-help"><?= $project ? 'Eleven är kopplad till arbetet.' : 'Eleven hämtas från ditt konto.' ?></p>
            </div>

            <div class="field">
                <label for="title">Rubrik</label>
                <input id="title" name="title" type="text" maxlength="180" required value="<?= h($formData['title']) ?>" <?= $canEditContent ? '' : 'disabled' ?>>
            </div>

            <div class="field">
                <label for="subtitle">Underrubrik</label>
                <input id="subtitle" name="subtitle" type="text" maxlength="180" value="<?= h($formData['subtitle']) ?>" <?= $canEditContent ? '' : 'disabled' ?>>
            </div>

            <div class="field">
                <label for="school">Skola</label>
                <input id="school" type="text" value="<?= h($projectOwner['school_name']) ?>" disabled>
                <p class="field-help">Skolan hämtas från ditt konto.</p>
            </div>

            <div class="field">
                <label for="supervisor_user_id">Handledare</label>
                <select id="supervisor_user_id" name="supervisor_user_id" required <?= $canEditContent ? '' : 'disabled' ?>>
                    <option value="">Välj handledare</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= (int) $teacher['id'] ?>" <?= (int) $formData['supervisorUserId'] === (int) $teacher['id'] ? 'selected' : '' ?>>
                            <?= h($teacher['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$teachers): ?>
                    <p class="field-help">Det finns inga godkända lärare registrerade på din skola ännu.</p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="category_name">Kategori</label>
                <input
                    id="category_name"
                    name="category_name"
                    type="text"
                    maxlength="120"
                    required
                    autocomplete="off"
                    list="category_suggestions"
                    value="<?= h($formData['categoryName']) ?>"
                    data-category-autocomplete
                    <?= $canEditContent ? '' : 'disabled' ?>
                >
                <datalist id="category_suggestions">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= h($category['category_name']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="field-help">Välj en befintlig kategori eller skriv en ny.</p>
            </div>

            <div class="field">
                <label for="pdf_file"><?= $project && $project['pdf_filename'] ? 'Ersätt PDF' : 'PDF-fil' ?></label>
                <input id="pdf_file" name="pdf_file" type="file" accept="application/pdf,.pdf" <?= $project ? '' : 'required' ?> data-max-bytes="<?= (int) MAX_UPLOAD_BYTES ?>" <?= $canEditContent ? '' : 'disabled' ?>>
                <?php if ($project && $project['pdf_original_name']): ?>
                    <p class="field-help">Nuvarande fil: <?= h($project['pdf_original_name']) ?></p>
                <?php else: ?>
                    <p class="field-help">Max 15 MB. Endast PDF.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label for="abstract_text">Abstract</label>
            <textarea id="abstract_text" name="abstract_text" rows="8" required data-counter="abstract-count" <?= $canEditContent ? '' : 'disabled' ?>><?= h($formData['abstractText']) ?></textarea>
            <p class="field-help"><span id="abstract-count">0</span> tecken</p>
        </div>

        <div class="field">
            <label for="summary_text">Sammanfattning</label>
            <textarea id="summary_text" name="summary_text" rows="10" required data-counter="summary-count" <?= $canEditContent ? '' : 'disabled' ?>><?= h($formData['summaryText']) ?></textarea>
            <p class="field-help"><span id="summary-count">0</span> tecken</p>
        </div>

        <div class="toggle-row">
            <label class="check-option">
                <input type="checkbox" name="is_public" value="1" <?= (int) $formData['isPublic'] === 1 ? 'checked' : '' ?> <?= $canEditContent ? '' : 'disabled' ?>>
                <span>Gör arbetet sökbart och synligt för andra</span>
            </label>

            <label class="check-option">
                <input type="checkbox" name="is_submitted" value="1" <?= (int) $formData['isSubmitted'] === 1 ? 'checked' : '' ?>>
                <span><?= $canEditContent ? 'Lämna in slutgiltigt. När detta är sparat kan eleven inte ändra arbetet.' : 'Slutlig inlämning. Avmarkera för att låsa upp elevredigering.' ?></span>
            </label>
        </div>

        <div class="action-row">
            <a class="button button-secondary" href="<?= h($cancelUrl) ?>">Avbryt</a>
            <button class="button button-primary" type="submit"><?= $canEditContent ? 'Spara arbete' : 'Spara status' ?></button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if (!app_is_installed()) {
    require_once __DIR__ . '/includes/installer.php';
    render_installer();
}

require_once __DIR__ . '/includes/bootstrap.php';

$schools = fetch_schools($conn);
$latestProjects = latest_public_projects($conn, 5);
$pageTitle = 'Startsida';

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">Svenskt arkiv för gymnasiearbeten</p>
        <h1>Sök, läs och hantera gymnasiearbeten.</h1>
        <p class="lead">Hitta publika arbeten från flera skolor eller logga in för att lämna in och granska arbeten.</p>

        <form class="search-panel" action="search.php" method="get">
            <div class="field field-grow">
                <label for="q">Sökord</label>
                <input id="q" name="q" type="search" placeholder="Rubrik, abstract eller sammanfattning">
            </div>
            <div class="field">
                <label for="school_id">Skola</label>
                <select id="school_id" name="school_id">
                    <option value="">Alla skolor</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= (int) $school['id'] ?>"><?= h($school['school_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="button button-primary" type="submit">Sök</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Senast uppladdade publika arbeten</h2>
        <a href="search.php">Visa alla</a>
    </div>

    <?php if (!$latestProjects): ?>
        <p class="muted">Det finns inga publika arbeten ännu.</p>
    <?php else: ?>
        <div class="result-list">
            <?php foreach ($latestProjects as $project): ?>
                <article class="result-card">
                    <div>
                        <h3><a href="project_view.php?id=<?= (int) $project['id'] ?>"><?= h($project['title']) ?></a></h3>
                        <?php if ($project['subtitle']): ?>
                            <p class="subtitle"><?= h($project['subtitle']) ?></p>
                        <?php endif; ?>
                    </div>
                    <p class="meta"><?= h($project['school_name']) ?> · <?= h($project['category_name']) ?> · Handledare: <?= h($project['supervisor_name']) ?></p>
                    <p><?= h(excerpt($project['abstract_text'] ?: $project['summary_text'])) ?></p>
                    <a class="text-link" href="project_view.php?id=<?= (int) $project['id'] ?>">Läs mer</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



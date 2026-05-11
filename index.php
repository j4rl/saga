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

$viewer = current_user();
$schoolId = (int) ($viewer['school_id'] ?? 0);
$categoryId = 0;
$sort = 'relevance';
$schools = fetch_schools($conn);
$categories = fetch_project_categories($conn);
$canChooseSchool = !$viewer || !in_array($viewer['role'], ['teacher', 'school_admin'], true);
$latestProjects = latest_public_projects($conn, 5);
$pageTitle = 'Startsida';

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">Svenskt arkiv för gymnasiearbeten</p>
        <h1>Sök, läs och hantera gymnasiearbeten.</h1>
        <p class="lead">Hitta publika arbeten från flera skolor eller logga in för att lämna in och granska arbeten.</p>

        <form class="search-panel search-panel-advanced" action="search.php" method="get">
            <div class="search-main-row">
                <div class="field field-grow">
                    <label for="q">Sök smart i titel, kategori, handledare, abstract och sammanfattning</label>
                    <input id="q" name="q" type="search" placeholder="Exempel: Spel med Unity">
                </div>

                <button class="button button-primary" type="submit">Sök</button>
            </div>

            <details class="search-filters">
                <summary>Avancerad sökning</summary>
                <div class="filter-grid">
                    <div class="field">
                        <label for="sort">Sortering</label>
                        <select id="sort" name="sort">
                            <option value="relevance" selected>Relevans</option>
                            <option value="updated_desc">Senast uppdaterad</option>
                            <option value="submitted_desc">Senast inlämnad</option>
                            <option value="title_asc">Titel A-Ö</option>
                            <option value="school_asc">Skola A-Ö</option>
                            <option value="category_asc">Kategori A-Ö</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="category_id">Kategori</label>
                        <select id="category_id" name="category_id">
                            <option value="">Alla kategorier</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= h($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($canChooseSchool): ?>
                        <div class="field">
                            <label for="school_id">Skola</label>
                            <select id="school_id" name="school_id">
                                <option value="">Alla skolor</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?= (int) $school['id'] ?>" <?= $schoolId === (int) $school['id'] ? 'selected' : '' ?>>
                                        <?= h($school['school_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="school_id" value="<?= (int) $viewer['school_id'] ?>">
                    <?php endif; ?>
                </div>
            </details>
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
        <div class="project-list">
            <?php foreach ($latestProjects as $project): ?>
                <details class="project-list-item project-list-item-no-status">
                    <summary>
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
                    </summary>

                    <div class="project-list-details">
                        <dl class="project-list-facts">
                            <div>
                                <dt>Skola</dt>
                                <dd><?= h($project['school_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Kategori</dt>
                                <dd><?= h($project['category_name']) ?></dd>
                            </div>
                        </dl>
                        <div class="project-list-texts">
                            <div>
                                <h3>Sammanfattning</h3>
                                <p><?= h(excerpt($project['summary_text'])) ?></p>
                            </div>
                            <div>
                                <h3>Abstract</h3>
                                <p><?= h(excerpt($project['abstract_text'])) ?></p>
                            </div>
                        </div>
                        <div class="project-actions">
                            <a class="button button-secondary" href="project_view.php?id=<?= (int) $project['id'] ?>">Visa</a>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



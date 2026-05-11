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
                                <dt>Skola</dt>
                                <dd><?= h($project['school_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Kategori</dt>
                                <dd><?= h($project['category_name']) ?></dd>
                            </div>
                        </dl>
                        <p><?= h(excerpt($project['abstract_text'] ?: $project['summary_text'])) ?></p>
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



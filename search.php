<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$viewer = current_user();
$query = trim((string) ($_GET['q'] ?? ''));
$hasSchoolFilter = array_key_exists('school_id', $_GET);
$schoolId = $hasSchoolFilter ? (int) ($_GET['school_id'] ?? 0) : (int) ($viewer['school_id'] ?? 0);
$categoryId = (int) ($_GET['category_id'] ?? 0);
$sort = (string) ($_GET['sort'] ?? 'relevance');
$allowedSorts = ['relevance', 'updated_desc', 'submitted_desc', 'title_asc', 'school_asc', 'category_asc'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'relevance';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$schools = fetch_schools($conn);
$categories = fetch_project_categories($conn);
$results = search_projects($conn, ['q' => $query, 'school_id' => $schoolId, 'category_id' => $categoryId, 'sort' => $sort], $viewer, $page, 10);
$pageTitle = 'Sök arbeten';
$canChooseSchool = !$viewer || !in_array($viewer['role'], ['teacher', 'school_admin'], true);
$hasAdvancedFilters = ($canChooseSchool && $hasSchoolFilter) || $categoryId > 0 || $sort !== 'relevance';
$openAdvancedSearch = $hasAdvancedFilters || (string) ($_GET['advanced'] ?? '') === '1';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <h1>Sök gymnasiearbeten</h1>

    <form class="search-panel search-panel-compact search-panel-advanced" action="search.php" method="get">
        <div class="search-main-row">
            <div class="field field-grow">
                <label for="q">Sök smart i titel, kategori, handledare, abstract och sammanfattning</label>
                <input id="q" name="q" type="search" value="<?= h($query) ?>" placeholder="Exempel: Spel med Unity">
            </div>

            <button class="button button-primary" type="submit">Sök</button>
        </div>

        <details class="search-filters" <?= $openAdvancedSearch ? 'open' : '' ?>>
            <summary>Avancerad sökning</summary>
            <div class="filter-grid">
                <div class="field">
                    <label for="sort">Sortering</label>
                    <select id="sort" name="sort">
                        <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Relevans</option>
                        <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Senast uppdaterad</option>
                        <option value="submitted_desc" <?= $sort === 'submitted_desc' ? 'selected' : '' ?>>Senast inlämnad</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Titel A-Ö</option>
                        <option value="school_asc" <?= $sort === 'school_asc' ? 'selected' : '' ?>>Skola A-Ö</option>
                        <option value="category_asc" <?= $sort === 'category_asc' ? 'selected' : '' ?>>Kategori A-Ö</option>
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

    <?php if ($viewer && in_array($viewer['role'], ['teacher', 'school_admin'], true)): ?>
        <p class="muted">Du ser alla arbeten på <?= h($viewer['school_name']) ?>, inklusive icke-publika.</p>
    <?php elseif ($viewer && $viewer['role'] === 'super_admin'): ?>
        <p class="muted"><?= $schoolId > 0 ? 'Sökningen är filtrerad på vald skola.' : 'Superadmin ser alla arbeten i systemet.' ?></p>
    <?php elseif ($viewer && $schoolId > 0): ?>
        <p class="muted">Sökningen är som standard filtrerad på <?= h($viewer['school_name']) ?>. Ändra skola under Avancerad sökning.</p>
    <?php else: ?>
        <p class="muted">Endast arbeten som eleven har markerat som publika visas.</p>
    <?php endif; ?>

    <?php if ($query !== '' && !empty($results['suggestions'])): ?>
        <div class="search-suggestions" aria-label="Relaterade sökningar">
            <span>Menade du?</span>
            <?php foreach ($results['suggestions'] as $suggestion): ?>
                <?php
                $suggestionParams = [
                    'q' => $suggestion,
                    'school_id' => $schoolId,
                    'category_id' => $categoryId,
                    'sort' => $sort,
                ];
                ?>
                <a href="search.php?<?= h(http_build_query($suggestionParams)) ?>"><?= h($suggestion) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Resultat</h2>
        <span><?= (int) $results['total'] ?> träffar</span>
    </div>

    <?php if (!$results['rows']): ?>
        <p class="empty-state">Inga arbeten matchade sökningen.</p>
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
                                <dt>Skola</dt>
                                <dd><?= h($project['school_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Kategori</dt>
                                <dd><?= h($project['category_name']) ?></dd>
                            </div>
                            <div>
                                <dt>Synlighet</dt>
                                <dd>
                                    <span class="status-pill <?= $isEffectivelyPublic ? 'status-public' : 'status-private' ?>">
                                        <?= $isEffectivelyPublic ? 'Publik' : 'Intern' ?>
                                    </span>
                                </dd>
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
                            <?php if ($viewer && can_edit_project($project, $viewer)): ?>
                                <a class="button button-secondary" href="project_edit.php?id=<?= (int) $project['id'] ?>">
                                    <?= can_edit_project_content($project, $viewer) ? 'Redigera' : 'Hantera' ?>
                                </a>
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
                        'q' => $query,
                        'school_id' => $schoolId,
                        'category_id' => $categoryId,
                        'sort' => $sort,
                        'page' => $i,
                    ];
                    ?>
                    <a class="<?= $i === $results['page'] ? 'active' : '' ?>" href="search.php?<?= h(http_build_query($params)) ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



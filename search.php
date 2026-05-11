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
        <div class="result-list">
            <?php foreach ($results['rows'] as $project): ?>
                <article class="result-card">
                    <div class="result-title-row">
                        <div>
                            <h3><a href="project_view.php?id=<?= (int) $project['id'] ?>"><?= h($project['title']) ?></a></h3>
                            <?php if ($project['subtitle']): ?>
                                <p class="subtitle"><?= h($project['subtitle']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php $isEffectivelyPublic = project_is_publicly_visible($project); ?>
                        <span class="status-pill <?= $isEffectivelyPublic ? 'status-public' : 'status-private' ?>">
                            <?= $isEffectivelyPublic ? 'Publik' : 'Intern' ?>
                        </span>
                    </div>
                    <p class="meta">
                        <?= h($project['school_name']) ?> · <?= h($project['category_name']) ?> · Handledare: <?= h($project['supervisor_name']) ?>
                    </p>
                    <p><?= h(excerpt($project['abstract_text'] ?: $project['summary_text'])) ?></p>
                    <a class="text-link" href="project_view.php?id=<?= (int) $project['id'] ?>">Visa arbete</a>
                    <?php if ($viewer && can_edit_project($project, $viewer)): ?>
                        <span aria-hidden="true"> · </span>
                        <a class="text-link" href="project_edit.php?id=<?= (int) $project['id'] ?>">
                            <?= can_edit_project_content($project, $viewer) ? 'Redigera arbete' : 'Hantera inlämning' ?>
                        </a>
                    <?php endif; ?>
                </article>
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



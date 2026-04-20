<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$viewer = current_user();
$query = trim((string) ($_GET['q'] ?? ''));
$schoolId = (int) ($_GET['school_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$schools = fetch_schools($conn);
$results = search_projects($conn, ['q' => $query, 'school_id' => $schoolId], $viewer, $page, 10);
$pageTitle = 'Sök arbeten';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <h1>Sök gymnasiearbeten</h1>

    <form class="search-panel search-panel-compact" action="search.php" method="get">
        <div class="field field-grow">
            <label for="q">Sök i rubrik, underrubrik, abstract och sammanfattning</label>
            <input id="q" name="q" type="search" value="<?= h($query) ?>" placeholder="Skriv sökord">
        </div>

        <?php if (!$viewer || $viewer['role'] !== 'teacher'): ?>
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

        <button class="button button-primary" type="submit">Sök</button>
    </form>

    <?php if ($viewer && $viewer['role'] === 'teacher'): ?>
        <p class="muted">Du ser alla arbeten på <?= h($viewer['school_name']) ?>, inklusive icke-publika.</p>
    <?php elseif ($viewer && $viewer['role'] === 'admin'): ?>
        <p class="muted">Admin ser alla arbeten i systemet.</p>
    <?php else: ?>
        <p class="muted">Endast arbeten som eleven har markerat som publika visas.</p>
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
                        <span class="status-pill <?= (int) $project['is_public'] === 1 ? 'status-public' : 'status-private' ?>">
                            <?= (int) $project['is_public'] === 1 ? 'Publik' : 'Intern' ?>
                        </span>
                    </div>
                    <p class="meta">
                        <?= h($project['school_name']) ?> · Handledare: <?= h($project['supervisor']) ?>
                    </p>
                    <p><?= h(excerpt($project['abstract_text'] ?: $project['summary_text'])) ?></p>
                    <a class="text-link" href="project_view.php?id=<?= (int) $project['id'] ?>">Visa arbete</a>
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



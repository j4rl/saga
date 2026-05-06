<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('super_admin');

$errors = [];

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'rename') {
        $result = rename_project_category($conn, (int) ($_POST['category_id'] ?? 0), (string) ($_POST['category_name'] ?? ''));
        if ($result['ok']) {
            set_flash('success', 'Kategorin har uppdaterats.');
            redirect('categories.php');
        }
        $errors[] = $result['error'];
    }

    if ($action === 'merge') {
        $result = merge_project_categories($conn, (int) ($_POST['source_category_id'] ?? 0), (int) ($_POST['target_category_id'] ?? 0));
        if ($result['ok']) {
            set_flash('success', 'Kategorierna har slagits ihop.');
            redirect('categories.php');
        }
        $errors[] = $result['error'];
    }
}

$categories = fetch_categories_with_counts($conn);
$pageTitle = 'Kategorier';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Superadmin</p>
    <h1>Kategorier</h1>
    <p class="muted">Byt namn på kategorier och slå ihop dubbletter som skapats via fritext.</p>
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
    <div class="section-heading">
        <h2>Slå ihop kategorier</h2>
        <span><?= (int) count($categories) ?> kategorier</span>
    </div>
    <form class="search-panel search-panel-compact" method="post" action="categories.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="merge">
        <div class="field">
            <label for="source_category_id">Från</label>
            <select id="source_category_id" name="source_category_id" required>
                <option value="">Välj kategori</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= h($category['category_name']) ?> (<?= (int) $category['project_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="target_category_id">Till</label>
            <select id="target_category_id" name="target_category_id" required>
                <option value="">Välj kategori</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= h($category['category_name']) ?> (<?= (int) $category['project_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button-primary" type="submit">Slå ihop</button>
    </form>
</section>

<section class="section">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kategori</th>
                <th>Arbeten</th>
                <th>Skapad</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td colspan="4">
                        <form class="inline-actions" method="post" action="categories.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                            <input name="category_name" type="text" maxlength="120" value="<?= h($category['category_name']) ?>" required>
                            <span class="muted"><?= (int) $category['project_count'] ?> arbeten · <?= h(format_date($category['created_at'])) ?></span>
                            <button class="button button-secondary" type="submit">Spara</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

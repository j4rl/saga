<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_role('super_admin');

$checks = fetch_environment_checks($conn);
$criticalCount = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'critical'));
$warningCount = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warning'));
$pageTitle = 'Driftkontroll';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Superadmin</p>
    <h1>Driftkontroll</h1>
    <p class="muted">Kontrollerar installation, filskydd, uppladdningsmapp och grundläggande produktionskrav.</p>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Status</h2>
        <span><?= (int) $criticalCount ?> kritiska · <?= (int) $warningCount ?> varningar</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kontroll</th>
                <th>Status</th>
                <th>Detalj</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?= h($check['label']) ?></td>
                    <td>
                        <span class="status-pill status-<?= h($check['status']) ?>">
                            <?= h(match ($check['status']) {
                                'ok' => 'OK',
                                'warning' => 'Varning',
                                'critical' => 'Kritisk',
                                default => $check['status'],
                            }) ?>
                        </span>
                    </td>
                    <td><?= h($check['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section section-tight">
    <h2>Driftchecklista</h2>
    <ul class="checklist">
        <li>Aktivera HTTPS innan riktig användardata hanteras.</li>
        <li>Sätt <code>APP_BASE_URL</code> till den publika adressen innan lösenordsåterställning används i produktion.</li>
        <li>Flytta helst uppladdningar utanför webbroten, eller säkra motsvarande serverregel för Apache/Nginx/IIS.</li>
        <li>Lås `config/` efter installation så webbservern inte kan skriva där.</li>
        <li>Sätt upp backup för databas och uppladdade filer.</li>
        <li>Byt testlösenord och använd inte konton från `database/schema.sql` i produktion.</li>
        <li>Kör `php tools/cleanup_storage.php` regelbundet och använd `--apply` efter granskning.</li>
        <li>Kör `php tools/migrate.php` efter uppdateringar som innehåller nya migreringar.</li>
    </ul>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

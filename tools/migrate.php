<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = require __DIR__ . '/../config/database.php';
$migrationsDir = __DIR__ . '/../database/migrations';
$prefix = db_table_prefix();

execute_prepared(
    $conn,
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(40) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci'
);

$appliedRows = fetch_all_prepared($conn, 'SELECT version FROM schema_migrations');
$applied = array_fill_keys(array_column($appliedRows, 'version'), true);
$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (isset($applied[$version])) {
        continue;
    }

    $sql = str_replace('{{prefix}}', $prefix, (string) file_get_contents($file));
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []));

    $conn->begin_transaction();
    try {
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $conn->query($statement);
            }
        }
        execute_prepared($conn, 'INSERT INTO schema_migrations (version) VALUES (?)', 's', [$version]);
        $conn->commit();
        echo "Applied $version\n";
    } catch (Throwable $exception) {
        $conn->rollback();
        fwrite(STDERR, "Migration $version failed: " . $exception->getMessage() . "\n");
        exit(1);
    }
}

echo "Migrations complete\n";

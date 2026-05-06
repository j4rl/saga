<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$apply = in_array('--apply', $argv, true);
$conn = require __DIR__ . '/../config/database.php';

$auditCutoff = date('Y-m-d H:i:s', time() - AUDIT_LOG_RETENTION_DAYS * 86400);
$emailCutoff = date('Y-m-d H:i:s', time() - EMAIL_NOTIFICATION_RETENTION_DAYS * 86400);
$versionCutoff = date('Y-m-d H:i:s', time() - UPLOAD_VERSION_RETENTION_DAYS * 86400);

$auditCount = (int) (fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM audit_log WHERE created_at < ?', 's', [$auditCutoff])['total'] ?? 0);
$emailCount = (int) (fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM email_notifications WHERE created_at < ?', 's', [$emailCutoff])['total'] ?? 0);
$oldVersions = fetch_all_prepared(
    $conn,
    'SELECT uv.id, uv.stored_filename
     FROM upload_versions uv
     INNER JOIN projects p ON p.id = uv.project_id
     WHERE uv.created_at < ? AND uv.stored_filename <> COALESCE(p.pdf_filename, \'\')',
    's',
    [$versionCutoff]
);

echo ($apply ? "Applying cleanup\n" : "Dry run\n");
echo "Audit rows older than " . AUDIT_LOG_RETENTION_DAYS . " days: $auditCount\n";
echo "Email notification rows older than " . EMAIL_NOTIFICATION_RETENTION_DAYS . " days: $emailCount\n";
echo "Old non-current PDF versions older than " . UPLOAD_VERSION_RETENTION_DAYS . " days: " . count($oldVersions) . "\n";

if (!$apply) {
    echo "Run with --apply to delete matching rows/files.\n";
    exit(0);
}

$conn->begin_transaction();
try {
    execute_prepared($conn, 'DELETE FROM audit_log WHERE created_at < ?', 's', [$auditCutoff]);
    execute_prepared($conn, 'DELETE FROM email_notifications WHERE created_at < ?', 's', [$emailCutoff]);

    foreach ($oldVersions as $version) {
        execute_prepared($conn, 'DELETE FROM upload_versions WHERE id = ?', 'i', [(int) $version['id']]);
        $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . basename((string) $version['stored_filename']);
        if (is_file($path)) {
            unlink($path);
        }
    }

    $conn->commit();
    echo "Cleanup complete\n";
} catch (Throwable $exception) {
    $conn->rollback();
    fwrite(STDERR, "Cleanup failed: " . $exception->getMessage() . "\n");
    exit(1);
}

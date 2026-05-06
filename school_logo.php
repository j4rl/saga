<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
send_security_headers();

$conn = require __DIR__ . '/config/database.php';

$schoolId = (int) ($_GET['id'] ?? 0);
$school = $schoolId > 0 ? fetch_school_profile($conn, $schoolId) : null;

if (!$school || empty($school['logo_filename']) || empty($school['logo_mime'])) {
    http_response_code(404);
    exit('Logotypen kunde inte hittas.');
}

$uploadRoot = realpath(UPLOAD_DIR);
$filePath = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . basename((string) $school['logo_filename']));

if (!$uploadRoot || !$filePath || !str_starts_with($filePath, $uploadRoot) || !is_file($filePath)) {
    http_response_code(404);
    exit('Logotypen kunde inte hittas.');
}

header('Content-Type: ' . $school['logo_mime']);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string) ($school['logo_original_name'] ?: 'logotyp')) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');

readfile($filePath);
exit;

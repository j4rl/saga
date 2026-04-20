<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$projectId = (int) ($_GET['id'] ?? 0);
$project = $projectId > 0 ? get_project_by_id($conn, $projectId) : null;
$viewer = current_user();

if (!$project || !can_view_project($project, $viewer) || empty($project['pdf_filename'])) {
    http_response_code(404);
    exit('Filen kunde inte hittas.');
}

$uploadRoot = realpath(UPLOAD_DIR);
$filePath = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . basename((string) $project['pdf_filename']));

if (!$uploadRoot || !$filePath || !str_starts_with($filePath, $uploadRoot) || !is_file($filePath)) {
    http_response_code(404);
    exit('Filen kunde inte hittas.');
}

$download = isset($_GET['download']);
$originalName = $project['pdf_original_name'] ?: 'gymnasiearbete.pdf';
$safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string) $originalName);

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;



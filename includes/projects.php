<?php
declare(strict_types=1);

function get_project_by_id(mysqli $conn, int $projectId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT p.*, s.school_name, u.full_name AS student_name, u.username AS student_username
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.id = ?
         LIMIT 1',
        'i',
        [$projectId]
    );
}

function get_project_for_student(mysqli $conn, int $userId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT p.*, s.school_name, u.full_name AS student_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.user_id = ?
         LIMIT 1',
        'i',
        [$userId]
    );
}

function can_view_project(array $project, ?array $viewer): bool
{
    if ((int) $project['is_public'] === 1) {
        return true;
    }

    if (!$viewer) {
        return false;
    }

    if ($viewer['role'] === 'admin') {
        return true;
    }

    if ($viewer['role'] === 'teacher') {
        return (int) $project['school_id'] === (int) $viewer['school_id'];
    }

    if ($viewer['role'] === 'student') {
        return (int) $project['user_id'] === (int) $viewer['id'];
    }

    return false;
}

function can_edit_project(array $project, array $viewer): bool
{
    return $viewer['role'] === 'student' && (int) $project['user_id'] === (int) $viewer['id'];
}

function add_visibility_sql(?array $viewer, array &$where, string &$types, array &$params, bool $teacherLimitedSearch = true): void
{
    if (!$viewer) {
        $where[] = 'p.is_public = 1';
        return;
    }

    if ($viewer['role'] === 'admin') {
        return;
    }

    if ($viewer['role'] === 'teacher' && $teacherLimitedSearch) {
        $where[] = 'p.school_id = ?';
        $types .= 'i';
        $params[] = (int) $viewer['school_id'];
        return;
    }

    $where[] = 'p.is_public = 1';
}

function search_projects(mysqli $conn, array $filters, ?array $viewer, int $page = 1, int $perPage = 10): array
{
    $page = max(1, $page);
    $perPage = min(50, max(1, $perPage));
    $offset = ($page - 1) * $perPage;

    $query = trim((string) ($filters['q'] ?? ''));
    $schoolId = (int) ($filters['school_id'] ?? 0);

    $where = [];
    $types = '';
    $params = [];

    add_visibility_sql($viewer, $where, $types, $params, true);

    if ($schoolId > 0 && (!$viewer || $viewer['role'] !== 'teacher')) {
        $where[] = 'p.school_id = ?';
        $types .= 'i';
        $params[] = $schoolId;
    }

    if ($query !== '') {
        $like = '%' . $query . '%';
        $where[] = '(p.title LIKE ? OR p.subtitle LIKE ? OR p.abstract_text LIKE ? OR p.summary_text LIKE ?)';
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countRow = fetch_one_prepared(
        $conn,
        "SELECT COUNT(*) AS total
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         $whereSql",
        $types,
        $params
    );

    $total = (int) ($countRow['total'] ?? 0);
    $pages = max(1, (int) ceil($total / $perPage));

    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$perPage, $offset]);

    $rows = fetch_all_prepared(
        $conn,
        "SELECT p.*, s.school_name, u.full_name AS student_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         $whereSql
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT ? OFFSET ?",
        $listTypes,
        $listParams
    );

    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ];
}

function latest_public_projects(mysqli $conn, int $limit = 5): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT p.*, s.school_name, u.full_name AS student_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.is_public = 1
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT ?',
        'i',
        [$limit]
    );
}

function validate_pdf_upload(array $file, bool $required): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required
            ? ['ok' => false, 'error' => 'Du måste välja en PDF-fil.']
            : ['ok' => true, 'file' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Uppladdningen misslyckades. Försök igen.'];
    }

    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'PDF-filen får vara högst 15 MB.'];
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension !== 'pdf') {
        return ['ok' => false, 'error' => 'Endast filer med filändelsen .pdf tillåts.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $allowedMimeTypes = ['application/pdf', 'application/x-pdf'];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return ['ok' => false, 'error' => 'Filen verkar inte vara en giltig PDF.'];
    }

    $handle = fopen($tmpName, 'rb');
    $signature = $handle ? fread($handle, 4) : '';
    if ($handle) {
        fclose($handle);
    }

    if ($signature !== '%PDF') {
        return ['ok' => false, 'error' => 'PDF-signaturen kunde inte verifieras.'];
    }

    return [
        'ok' => true,
        'file' => [
            'tmp_name' => $tmpName,
            'original_name' => mb_substr(basename($originalName), 0, 180),
            'stored_name' => bin2hex(random_bytes(24)) . '.pdf',
        ],
    ];
}

function store_uploaded_pdf(array $validatedFile): array
{
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $targetPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $validatedFile['stored_name'];

    if (!move_uploaded_file($validatedFile['tmp_name'], $targetPath)) {
        throw new RuntimeException('Kunde inte spara PDF-filen.');
    }

    chmod($targetPath, 0640);

    return [
        'stored_name' => $validatedFile['stored_name'],
        'original_name' => $validatedFile['original_name'],
    ];
}



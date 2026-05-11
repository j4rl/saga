<?php
declare(strict_types=1);

function get_project_by_id(mysqli $conn, int $projectId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT p.*, s.school_name, u.full_name AS student_name, u.username AS student_username,
                c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
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
        'SELECT p.*, s.school_name, u.full_name AS student_name,
                c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
         WHERE p.user_id = ?
         LIMIT 1',
        'i',
        [$userId]
    );
}

function can_view_project(array $project, ?array $viewer): bool
{
    if (project_is_publicly_visible($project)) {
        return true;
    }

    if (!$viewer) {
        return false;
    }

    if ($viewer['role'] === 'super_admin') {
        return true;
    }

    if ($viewer['role'] === 'teacher') {
        return teacher_is_project_supervisor($project, $viewer)
            || (
                (int) $project['school_id'] === (int) $viewer['school_id']
                && (int) $project['is_submitted'] === 1
            );
    }

    if ($viewer['role'] === 'school_admin') {
        return (int) $project['school_id'] === (int) $viewer['school_id'];
    }

    if ($viewer['role'] === 'student') {
        return (int) $project['user_id'] === (int) $viewer['id'];
    }

    return false;
}

function project_is_publicly_visible(array $project): bool
{
    return (int) ($project['is_public'] ?? 0) === 1
        && (int) ($project['is_submitted'] ?? 0) === 1
        && (int) ($project['is_approved'] ?? 0) === 1;
}

function teacher_is_project_supervisor(array $project, array $teacher): bool
{
    if (($teacher['role'] ?? '') !== 'teacher') {
        return false;
    }

    if ((int) ($project['supervisor_user_id'] ?? 0) === (int) $teacher['id']) {
        return true;
    }

    return (int) $project['school_id'] === (int) $teacher['school_id']
        && empty($project['supervisor_user_id'])
        && trim((string) $project['supervisor']) === trim((string) $teacher['full_name']);
}

function can_edit_project(array $project, array $viewer): bool
{
    return can_edit_project_content($project, $viewer)
        || can_unlock_project_submission($project, $viewer);
}

function can_edit_project_content(array $project, array $viewer): bool
{
    if ($viewer['role'] === 'super_admin') {
        return true;
    }

    if ($viewer['role'] === 'school_admin') {
        return (int) $project['school_id'] === (int) $viewer['school_id'];
    }

    return $viewer['role'] === 'student'
        && (int) $project['user_id'] === (int) $viewer['id']
        && (int) $project['is_submitted'] !== 1;
}

function can_unlock_project_submission(array $project, array $viewer): bool
{
    return $viewer['role'] === 'teacher'
        && teacher_is_project_supervisor($project, $viewer)
        && (int) $project['is_submitted'] === 1;
}

function can_approve_project(array $project, array $viewer): bool
{
    return $viewer['role'] === 'teacher'
        && teacher_is_project_supervisor($project, $viewer)
        && (int) $project['is_submitted'] === 1;
}

function set_project_approval(mysqli $conn, array $project, array $teacher, bool $approved): array
{
    if (!can_approve_project($project, $teacher)) {
        return ['ok' => false, 'error' => 'Du kan bara godkänna slutligt inlämnade arbeten där du är handledare.'];
    }

    if ($approved) {
        execute_prepared(
            $conn,
            'UPDATE projects
             SET is_approved = 1, approved_at = NOW(), approved_by = ?, updated_at = NOW()
             WHERE id = ? AND is_submitted = 1',
            'ii',
            [(int) $teacher['id'], (int) $project['id']]
        );
        log_event($conn, (int) $teacher['id'], 'project_approve', 'project', (int) $project['id']);

        return ['ok' => true, 'approved' => true];
    }

    execute_prepared(
        $conn,
        'UPDATE projects
         SET is_approved = 0, approved_at = NULL, approved_by = NULL, updated_at = NOW()
         WHERE id = ?',
        'i',
        [(int) $project['id']]
    );
    log_event($conn, (int) $teacher['id'], 'project_unapprove', 'project', (int) $project['id']);

    return ['ok' => true, 'approved' => false];
}

function fetch_project_versions(mysqli $conn, int $projectId): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT uv.id, uv.stored_filename, uv.original_name, uv.created_at, u.full_name AS uploaded_by_name
         FROM upload_versions uv
         INNER JOIN users u ON u.id = uv.uploaded_by
         WHERE uv.project_id = ?
         ORDER BY uv.created_at DESC, uv.id DESC',
        'i',
        [$projectId]
    );
}

function get_project_version(mysqli $conn, int $versionId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT uv.*, p.user_id, p.school_id, p.is_public, p.supervisor_user_id, p.supervisor
         FROM upload_versions uv
         INNER JOIN projects p ON p.id = uv.project_id
         WHERE uv.id = ?
         LIMIT 1',
        'i',
        [$versionId]
    );
}

function add_visibility_sql(?array $viewer, array &$where, string &$types, array &$params, bool $teacherLimitedSearch = true): void
{
    if (!$viewer) {
        $where[] = '(p.is_public = 1 AND p.is_submitted = 1 AND p.is_approved = 1)';
        return;
    }

    if ($viewer['role'] === 'super_admin') {
        return;
    }

    if ($viewer['role'] === 'school_admin' && $teacherLimitedSearch) {
        $where[] = 'p.school_id = ?';
        $types .= 'i';
        $params[] = (int) $viewer['school_id'];
        return;
    }

    if ($viewer['role'] === 'teacher' && $teacherLimitedSearch) {
        $where[] = '(
            (p.school_id = ? AND p.is_submitted = 1)
            OR p.supervisor_user_id = ?
            OR (p.school_id = ? AND p.supervisor_user_id IS NULL AND TRIM(p.supervisor) = ?)
        )';
        $types .= 'iiis';
        array_push($params, (int) $viewer['school_id'], (int) $viewer['id'], (int) $viewer['school_id'], trim((string) $viewer['full_name']));
        return;
    }

    $where[] = '(p.is_public = 1 AND p.is_submitted = 1 AND p.is_approved = 1)';
}

function normalize_search_text(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}#+]+/u', ' ', $text);

    return trim(preg_replace('/\s+/', ' ', (string) $text));
}

function search_stop_words(): array
{
    return ['a', 'an', 'and', 'att', 'av', 'de', 'det', 'en', 'ett', 'for', 'för', 'i', 'med', 'och', 'om', 'på', 'som', 'the', 'to'];
}

function search_synonym_groups(): array
{
    return [
        ['spel', 'spelutveckling', 'spelprogrammering', 'programmering av spel', 'speldesign', 'unity', 'unity3d', 'game', 'game development', 'interaktiv', 'interaktiva'],
        ['programmering', 'programmera', 'kod', 'koda', 'mjukvara', 'software', 'app', 'applikation', 'webb', 'javascript', 'php', 'python', 'java', 'c#', 'csharp'],
        ['energi', 'energianvändning', 'elanvändning', 'el', 'hållbar', 'hållbarhet', 'klimat', 'miljö'],
        ['elektronik', 'elkrets', 'krets', 'arduino', 'sensor', 'robot', 'automation'],
        ['konstruktion', 'cad', '3d', 'modellering', 'produktutveckling', 'teknik', 'design'],
        ['fysik', 'mekanik', 'kraft', 'rörelse', 'ljus', 'optik', 'elektricitet'],
        ['filosofi', 'etik', 'moral', 'existens', 'kunskap', 'argumentation'],
        ['svenska', 'litteratur', 'språk', 'retorik', 'textanalys', 'skrivande'],
    ];
}

function search_query_tokens(string $query): array
{
    $normalized = normalize_search_text($query);
    if ($normalized === '') {
        return [];
    }

    $stopWords = array_flip(search_stop_words());
    $tokens = [];

    foreach (explode(' ', $normalized) as $token) {
        if (mb_strlen($token, 'UTF-8') < 2 || isset($stopWords[$token])) {
            continue;
        }

        $tokens[$token] = $token;
    }

    return array_values($tokens);
}

function smart_search_terms(string $query): array
{
    $tokens = search_query_tokens($query);
    $terms = [];

    foreach ($tokens as $token) {
        $terms[$token] = $token;
    }

    foreach (search_synonym_groups() as $group) {
        $normalizedGroup = array_map('normalize_search_text', $group);
        $matchesGroup = false;

        foreach ($tokens as $token) {
            foreach ($normalizedGroup as $term) {
                $tokenLength = mb_strlen($token, 'UTF-8');
                $termLength = mb_strlen($term, 'UTF-8');

                if (
                    $token === $term
                    || ($tokenLength >= 4 && $termLength >= 4 && (str_contains($term, $token) || str_contains($token, $term)))
                ) {
                    $matchesGroup = true;
                    break 2;
                }
            }
        }

        if ($matchesGroup) {
            foreach ($normalizedGroup as $term) {
                if ($term !== '') {
                    $terms[$term] = $term;
                }
            }
        }
    }

    return array_values($terms);
}

function smart_search_suggestions(string $query, array $terms): array
{
    $tokens = search_query_tokens($query);
    $tokenMap = array_flip($tokens);
    $suggestions = [];

    foreach ($terms as $term) {
        if (isset($tokenMap[$term]) || mb_strlen($term, 'UTF-8') < 4) {
            continue;
        }

        $suggestions[$term] = $term;
    }

    return array_slice(array_values($suggestions), 0, 6);
}

function smart_search_document_text(array $project): string
{
    return normalize_search_text(implode(' ', [
        $project['title'] ?? '',
        $project['subtitle'] ?? '',
        $project['category_name'] ?? '',
        $project['student_name'] ?? '',
        $project['supervisor_name'] ?? $project['supervisor'] ?? '',
        $project['abstract_text'] ?? '',
        $project['summary_text'] ?? '',
    ]));
}

function smart_search_project_score(array $project, string $query, array $terms): int
{
    $document = smart_search_document_text($project);
    if ($document === '') {
        return 0;
    }

    $score = 0;
    $normalizedQuery = normalize_search_text($query);
    $tokens = search_query_tokens($query);
    $words = explode(' ', $document);

    if ($normalizedQuery !== '' && str_contains($document, $normalizedQuery)) {
        $score += 80;
    }

    $score += (int) round(((float) ($project['_fulltext_score'] ?? 0)) * 25);

    foreach ($tokens as $token) {
        if (str_contains($document, $token)) {
            $score += 24;
            continue;
        }

        foreach ($words as $word) {
            if (mb_strlen($token, 'UTF-8') >= 4 && mb_strlen($word, 'UTF-8') >= 4) {
                $distance = levenshtein($token, $word);
                $allowedDistance = max(1, (int) floor(mb_strlen($token, 'UTF-8') / 4));

                if ($distance <= $allowedDistance) {
                    $score += 10;
                    break;
                }
            }
        }
    }

    foreach ($terms as $term) {
        if (str_contains($document, $term)) {
            $score += str_contains($term, ' ') ? 18 : 12;
        }
    }

    return $score;
}

function search_projects(mysqli $conn, array $filters, ?array $viewer, int $page = 1, int $perPage = 10): array
{
    $page = max(1, $page);
    $perPage = min(50, max(1, $perPage));
    $offset = ($page - 1) * $perPage;

    $query = trim((string) ($filters['q'] ?? ''));
    $schoolId = (int) ($filters['school_id'] ?? 0);
    $categoryId = (int) ($filters['category_id'] ?? 0);
    $sort = (string) ($filters['sort'] ?? 'relevance');
    $allowedSorts = ['relevance', 'updated_desc', 'submitted_desc', 'title_asc', 'school_asc', 'category_asc'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'relevance';
    }

    $where = [];
    $types = '';
    $params = [];

    add_visibility_sql($viewer, $where, $types, $params, true);

    if ($schoolId > 0 && (!$viewer || !in_array($viewer['role'], ['teacher', 'school_admin'], true))) {
        $where[] = 'p.school_id = ?';
        $types .= 'i';
        $params[] = $schoolId;
    }

    if ($categoryId > 0) {
        $where[] = 'p.category_id = ?';
        $types .= 'i';
        $params[] = $categoryId;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if ($query !== '') {
        $fulltextQuery = implode(' ', array_slice(search_query_tokens($query), 0, 10));
        $candidateWhereSql = $whereSql;
        $candidateTypes = $types;
        $candidateParams = $params;
        $candidateLikeParts = [];
        $candidateLikeTypes = '';
        $candidateLikeParams = [];
        foreach (array_slice(smart_search_terms($query), 0, 12) as $term) {
            $candidateLikeParts[] = '(p.title LIKE ? OR p.subtitle LIKE ? OR p.supervisor LIKE ? OR p.abstract_text LIKE ? OR p.summary_text LIKE ? OR c.category_name LIKE ? OR u.full_name LIKE ? OR su.full_name LIKE ?)';
            $candidateLikeTypes .= 'ssssssss';
            $like = '%' . $term . '%';
            array_push($candidateLikeParams, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        if ($candidateLikeParts) {
            $candidateClause = '(MATCH(p.title, p.subtitle, p.supervisor, p.abstract_text, p.summary_text)
                    AGAINST (? IN NATURAL LANGUAGE MODE) > 0 OR ' . implode(' OR ', $candidateLikeParts) . ')';
            $candidateTypes .= 's' . $candidateLikeTypes;
            $candidateParams[] = $fulltextQuery ?: $query;
            $candidateParams = array_merge($candidateParams, $candidateLikeParams);
            $candidateWhereSql .= $candidateWhereSql ? ' AND ' . $candidateClause : 'WHERE ' . $candidateClause;
        }

        $candidateRows = fetch_all_prepared(
            $conn,
            "SELECT p.*, s.school_name, u.full_name AS student_name,
                    MATCH(p.title, p.subtitle, p.supervisor, p.abstract_text, p.summary_text)
                        AGAINST (? IN NATURAL LANGUAGE MODE) AS _fulltext_score,
                    c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
             FROM projects p
             INNER JOIN schools s ON s.id = p.school_id
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN users su ON su.id = p.supervisor_user_id
             $candidateWhereSql
             ORDER BY _fulltext_score DESC, p.updated_at DESC
             LIMIT 1000",
            's' . $candidateTypes,
            array_merge([$fulltextQuery ?: $query], $candidateParams)
        );

        $terms = smart_search_terms($query);
        $scoredRows = [];

        foreach ($candidateRows as $row) {
            $score = smart_search_project_score($row, $query, $terms);
            if ($score > 0) {
                $row['_search_score'] = $score;
                $scoredRows[] = $row;
            }
        }

        usort($scoredRows, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'relevance' && $a['_search_score'] !== $b['_search_score']) {
                return $b['_search_score'] <=> $a['_search_score'];
            }

            return match ($sort) {
                'submitted_desc' => strtotime((string) ($b['submitted_at'] ?? '')) <=> strtotime((string) ($a['submitted_at'] ?? '')),
                'title_asc' => strnatcasecmp((string) $a['title'], (string) $b['title']),
                'school_asc' => strnatcasecmp((string) $a['school_name'], (string) $b['school_name']),
                'category_asc' => strnatcasecmp((string) $a['category_name'], (string) $b['category_name']),
                default => strtotime((string) $b['updated_at']) <=> strtotime((string) $a['updated_at']),
            };
        });

        $total = count($scoredRows);
        $pages = max(1, (int) ceil($total / $perPage));

        return [
            'rows' => array_slice($scoredRows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'suggestions' => smart_search_suggestions($query, $terms),
            'expanded_terms' => $terms,
        ];
    }

    $countRow = fetch_one_prepared(
        $conn,
        "SELECT COUNT(*) AS total
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
         $whereSql",
        $types,
        $params
    );

    $total = (int) ($countRow['total'] ?? 0);
    $pages = max(1, (int) ceil($total / $perPage));

    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$perPage, $offset]);
    $orderBy = match ($sort) {
        'submitted_desc' => 'p.submitted_at DESC, p.updated_at DESC, p.id DESC',
        'title_asc' => 'p.title ASC, p.id DESC',
        'school_asc' => 's.school_name ASC, p.updated_at DESC, p.id DESC',
        'category_asc' => 'c.category_name ASC, p.updated_at DESC, p.id DESC',
        default => 'p.updated_at DESC, p.id DESC',
    };

    $rows = fetch_all_prepared(
        $conn,
        "SELECT p.*, s.school_name, u.full_name AS student_name,
                c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
         $whereSql
         ORDER BY $orderBy
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
        'suggestions' => [],
        'expanded_terms' => [],
    ];
}

function teacher_dashboard_projects(mysqli $conn, array $teacher, string $view, string $query, string $sort, int $page = 1, int $perPage = 10): array
{
    $allowedViews = ['own', 'supervised', 'school_submitted'];
    if (!in_array($view, $allowedViews, true)) {
        $view = 'own';
    }

    $page = max(1, $page);
    $perPage = min(500, max(1, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $types = '';
    $params = [];
    $teacherId = (int) $teacher['id'];
    $teacherName = trim((string) $teacher['full_name']);

    if ($view === 'own') {
        $where[] = 'p.school_id = ?';
        $types .= 'i';
        $params[] = (int) $teacher['school_id'];
        $where[] = '(p.supervisor_user_id = ? OR (p.supervisor_user_id IS NULL AND TRIM(p.supervisor) = ?))';
        $types .= 'is';
        array_push($params, $teacherId, $teacherName);
    } elseif ($view === 'supervised') {
        $where[] = '(p.supervisor_user_id = ? OR (p.school_id = ? AND p.supervisor_user_id IS NULL AND TRIM(p.supervisor) = ?))';
        $types .= 'iis';
        array_push($params, $teacherId, (int) $teacher['school_id'], $teacherName);
    } else {
        $where[] = 'p.school_id = ?';
        $types .= 'i';
        $params[] = (int) $teacher['school_id'];
        $where[] = 'p.is_submitted = 1';
    }

    $query = trim($query);
    if ($query !== '') {
        $like = '%' . $query . '%';
        $where[] = '(p.title LIKE ? OR p.subtitle LIKE ? OR p.abstract_text LIKE ? OR p.summary_text LIKE ? OR u.full_name LIKE ? OR COALESCE(su.full_name, p.supervisor) LIKE ? OR c.category_name LIKE ?)';
        $types .= 'sssssss';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    $orderBy = match ($sort) {
        'title_asc' => 'p.title ASC, p.id DESC',
        'student_asc' => 'u.full_name ASC, p.title ASC, p.id DESC',
        'submitted_desc' => 'p.submitted_at DESC, p.updated_at DESC, p.id DESC',
        'status_desc' => 'p.is_submitted DESC, p.is_approved ASC, p.updated_at DESC, p.id DESC',
        default => 'p.updated_at DESC, p.id DESC',
    };

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countRow = fetch_one_prepared(
        $conn,
        "SELECT COUNT(*) AS total
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
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
        "SELECT p.*, s.school_name, u.full_name AS student_name,
                c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
         $whereSql
         ORDER BY $orderBy
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
        'view' => $view,
        'sort' => $sort,
    ];
}

function teacher_dashboard_counts(mysqli $conn, array $teacher): array
{
    $teacherId = (int) $teacher['id'];
    $teacherName = trim((string) $teacher['full_name']);

    $row = fetch_one_prepared(
        $conn,
        'SELECT
            SUM(p.school_id = ? AND (p.supervisor_user_id = ? OR (p.supervisor_user_id IS NULL AND TRIM(p.supervisor) = ?))) AS own_total,
            SUM(p.supervisor_user_id = ? OR (p.school_id = ? AND p.supervisor_user_id IS NULL AND TRIM(p.supervisor) = ?)) AS supervised_total,
            SUM(p.school_id = ? AND p.is_submitted = 1) AS school_submitted_total
         FROM projects p',
        'iisiisi',
        [(int) $teacher['school_id'], $teacherId, $teacherName, $teacherId, (int) $teacher['school_id'], $teacherName, (int) $teacher['school_id']]
    );

    return [
        'own' => (int) ($row['own_total'] ?? 0),
        'supervised' => (int) ($row['supervised_total'] ?? 0),
        'school_submitted' => (int) ($row['school_submitted_total'] ?? 0),
    ];
}

function latest_public_projects(mysqli $conn, int $limit = 5): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT p.*, s.school_name, u.full_name AS student_name,
                c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
         WHERE p.is_public = 1 AND p.is_submitted = 1 AND p.is_approved = 1
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

function can_comment_project(array $project, array $viewer): bool
{
    if (!can_view_project($project, $viewer)) {
        return false;
    }

    if (in_array($viewer['role'], ['super_admin', 'school_admin'], true)) {
        return $viewer['role'] === 'super_admin' || (int) $project['school_id'] === (int) $viewer['school_id'];
    }

    if ($viewer['role'] === 'teacher') {
        return teacher_is_project_supervisor($project, $viewer);
    }

    return $viewer['role'] === 'student' && (int) $project['user_id'] === (int) $viewer['id'];
}

function fetch_project_feedback(mysqli $conn, int $projectId): array
{
    ensure_project_feedback_table($conn);

    return fetch_all_prepared(
        $conn,
        'SELECT pf.id, pf.comment_text, pf.created_at, u.full_name, u.role
         FROM project_feedback pf
         INNER JOIN users u ON u.id = pf.user_id
         WHERE pf.project_id = ?
         ORDER BY pf.created_at ASC, pf.id ASC',
        'i',
        [$projectId]
    );
}

function add_project_feedback(mysqli $conn, array $project, array $viewer, string $commentText): array
{
    ensure_project_feedback_table($conn);

    if (!can_comment_project($project, $viewer)) {
        return ['ok' => false, 'error' => 'Du har inte behörighet att kommentera arbetet.'];
    }

    $commentText = trim($commentText);
    if ($commentText === '' || mb_strlen($commentText, 'UTF-8') > 2000) {
        return ['ok' => false, 'error' => 'Kommentaren måste vara 1-2000 tecken.'];
    }

    execute_prepared(
        $conn,
        'INSERT INTO project_feedback (project_id, user_id, comment_text) VALUES (?, ?, ?)',
        'iis',
        [(int) $project['id'], (int) $viewer['id'], $commentText]
    );
    log_event($conn, (int) $viewer['id'], 'project_feedback_create', 'project', (int) $project['id']);

    return ['ok' => true];
}

function ensure_project_feedback_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    execute_prepared(
        $conn,
        'CREATE TABLE IF NOT EXISTS project_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            comment_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_project_feedback_project
                FOREIGN KEY (project_id) REFERENCES projects(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_project_feedback_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            INDEX idx_project_feedback_project (project_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci'
    );

    $done = true;
}



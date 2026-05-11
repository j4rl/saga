<?php
declare(strict_types=1);

function find_user_by_username(mysqli $conn, string $username): ?array
{
    ensure_user_security_columns($conn);

    return fetch_one_prepared(
        $conn,
        'SELECT u.id, u.username, u.email, u.password_hash, u.must_change_password, u.full_name, u.role, u.school_id, u.approval_status, s.school_name
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         WHERE u.username = ?
         LIMIT 1',
        's',
        [$username]
    );
}

function find_user_by_id(mysqli $conn, int $userId): ?array
{
    ensure_user_security_columns($conn);

    return fetch_one_prepared(
        $conn,
        'SELECT u.id, u.username, u.email, u.password_hash, u.must_change_password, u.full_name, u.role, u.school_id, u.approval_status, s.school_name
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         WHERE u.id = ?
         LIMIT 1',
        'i',
        [$userId]
    );
}

function ensure_user_security_columns(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        execute_prepared($conn, 'ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
        execute_prepared($conn, 'ALTER TABLE users ADD COLUMN IF NOT EXISTS registration_reviewer_id INT UNSIGNED NULL AFTER reviewed_at');
    } catch (Throwable $exception) {
        log_app_error('Kunde inte säkerställa användarnas säkerhetskolumner.', $exception);
    }

    $done = true;
}

function ensure_password_resets_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    execute_prepared(
        $conn,
        'CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_password_resets_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            INDEX idx_password_resets_user (user_id),
            INDEX idx_password_resets_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci'
    );

    $done = true;
}

function ensure_password_reset_attempts_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    execute_prepared(
        $conn,
        'CREATE TABLE IF NOT EXISTS password_reset_attempts (
            attempt_key CHAR(64) NOT NULL PRIMARY KEY,
            scope VARCHAR(24) NOT NULL,
            request_count INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_requested_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_password_reset_attempts_locked_until (locked_until),
            INDEX idx_password_reset_attempts_scope_updated (scope, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci'
    );

    $done = true;
}

function app_base_url(): string
{
    $configuredBaseUrl = configured_app_base_url();
    if ($configuredBaseUrl) {
        return $configuredBaseUrl;
    }

    $isHttps = request_is_https();
    $host = safe_request_host();
    $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($path === '.' || $path === '/') {
        $path = '';
    }

    return ($isHttps ? 'https://' : 'http://') . $host . ($path === '' ? '' : $path);
}

function password_reset_attempt_keys(string $identifier): array
{
    $normalizedIdentifier = normalize_email($identifier) ?: mb_strtolower(trim($identifier), 'UTF-8');
    $ipAddress = login_client_ip();

    return [
        'identifier_ip' => hash('sha256', 'password_reset_identifier_ip|' . $normalizedIdentifier . '|' . $ipAddress),
        'ip' => hash('sha256', 'password_reset_ip|' . $ipAddress),
    ];
}

function password_reset_is_rate_limited(mysqli $conn, string $identifier): bool
{
    ensure_password_reset_attempts_table($conn);
    $keys = password_reset_attempt_keys($identifier);

    $row = fetch_one_prepared(
        $conn,
        'SELECT attempt_key FROM password_reset_attempts
         WHERE attempt_key IN (?, ?) AND locked_until IS NOT NULL AND locked_until > NOW()
         LIMIT 1',
        'ss',
        [$keys['identifier_ip'], $keys['ip']]
    );

    return $row !== null;
}

function password_reset_record_request(mysqli $conn, string $identifier): void
{
    ensure_password_reset_attempts_table($conn);
    $keys = password_reset_attempt_keys($identifier);
    $windowStart = time() - 15 * 60;

    execute_prepared($conn, 'DELETE FROM password_reset_attempts WHERE updated_at < (NOW() - INTERVAL 1 DAY)');

    foreach ($keys as $scope => $key) {
        $threshold = $scope === 'ip' ? 10 : 3;
        $row = fetch_one_prepared(
            $conn,
            'SELECT request_count, last_requested_at FROM password_reset_attempts WHERE attempt_key = ? LIMIT 1',
            's',
            [$key]
        );

        $lastRequestedAt = $row && !empty($row['last_requested_at']) ? strtotime((string) $row['last_requested_at']) : 0;
        $requestCount = $lastRequestedAt >= $windowStart ? (int) ($row['request_count'] ?? 0) + 1 : 1;
        $lockedUntil = $requestCount >= $threshold ? date('Y-m-d H:i:s', time() + 30 * 60) : null;

        execute_prepared(
            $conn,
            'INSERT INTO password_reset_attempts (attempt_key, scope, request_count, locked_until, last_requested_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                scope = VALUES(scope),
                request_count = VALUES(request_count),
                locked_until = VALUES(locked_until),
                last_requested_at = NOW()',
            'ssis',
            [$key, $scope, $requestCount, $lockedUntil]
        );
    }
}

function create_password_reset_request(mysqli $conn, string $identifier): void
{
    ensure_password_resets_table($conn);
    ensure_password_reset_attempts_table($conn);
    $identifier = trim($identifier);
    if ($identifier === '') {
        return;
    }

    if (password_reset_is_rate_limited($conn, $identifier)) {
        log_event($conn, null, 'password_reset_rate_limited', 'user', null);
        return;
    }

    password_reset_record_request($conn, $identifier);

    $user = fetch_one_prepared(
        $conn,
        'SELECT id, email, full_name FROM users
         WHERE approval_status = \'approved\' AND (username = ? OR email = ?)
         LIMIT 1',
        'ss',
        [$identifier, normalize_email($identifier) ?: $identifier]
    );

    if (!$user || empty($user['email'])) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60);

    execute_prepared($conn, 'DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()', 'i', [(int) $user['id']]);
    execute_prepared(
        $conn,
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        'iss',
        [(int) $user['id'], $tokenHash, $expiresAt]
    );

    $link = app_base_url() . '/reset_password.php?token=' . urlencode($token);
    send_email_notification(
        $conn,
        (string) $user['email'],
        'Återställ lösenord i SAGA',
        "Hej!\n\nAnvänd länken för att välja ett nytt lösenord. Länken gäller i 60 minuter:\n$link\n\nOm du inte begärt detta kan du bortse från meddelandet."
    );
    log_event($conn, (int) $user['id'], 'password_reset_request', 'user', (int) $user['id']);
}

function reset_password_with_token(mysqli $conn, string $token, string $password, string $confirmPassword): array
{
    ensure_password_resets_table($conn);

    if (mb_strlen($password, 'UTF-8') < 8) {
        return ['ok' => false, 'error' => 'Det nya lösenordet måste vara minst 8 tecken.'];
    }
    if ($password !== $confirmPassword) {
        return ['ok' => false, 'error' => 'De nya lösenorden matchar inte.'];
    }

    $tokenHash = hash('sha256', $token);
    $reset = fetch_one_prepared(
        $conn,
        'SELECT pr.id, pr.user_id
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.approval_status = \'approved\'
         LIMIT 1',
        's',
        [$tokenHash]
    );

    if (!$reset) {
        return ['ok' => false, 'error' => 'Länken är ogiltig eller har gått ut.'];
    }

    $conn->begin_transaction();
    try {
        execute_prepared(
            $conn,
            'UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?',
            'si',
            [password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]
        );
        execute_prepared($conn, 'UPDATE password_resets SET used_at = NOW() WHERE id = ?', 'i', [(int) $reset['id']]);
        execute_prepared($conn, 'DELETE FROM password_resets WHERE user_id = ? AND id <> ?', 'ii', [(int) $reset['user_id'], (int) $reset['id']]);
        log_event($conn, (int) $reset['user_id'], 'password_reset_complete', 'user', (int) $reset['user_id']);
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        log_app_error('Lösenordsåterställning misslyckades.', $exception);
        return ['ok' => false, 'error' => 'Lösenordet kunde inte återställas. Försök igen.'];
    }

    return ['ok' => true];
}

function sync_current_user_session(mysqli $conn, int $userId): void
{
    $user = find_user_by_id($conn, $userId);
    if (!$user) {
        return;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'school_id' => (int) $user['school_id'],
        'school_name' => $user['school_name'],
        'approval_status' => $user['approval_status'],
        'must_change_password' => (int) ($user['must_change_password'] ?? 0),
    ];
}

function enforce_current_user_session(mysqli $conn): void
{
    $sessionUser = current_user();
    if (!$sessionUser) {
        return;
    }

    $user = find_user_by_id($conn, (int) $sessionUser['id']);
    if (!$user || ($user['approval_status'] ?? 'pending') !== 'approved') {
        logout_user();
        start_secure_session();
        set_flash('error', 'Sessionen har avslutats eftersom kontot inte längre är aktivt.');
        redirect('index.php');
    }

    sync_current_user_session($conn, (int) $sessionUser['id']);

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (
        (int) ($user['must_change_password'] ?? 0) === 1
        && !in_array($script, ['profile.php', 'logout.php'], true)
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    ) {
        set_flash('error', 'Du behöver byta ditt tillfälliga lösenord innan du fortsätter.');
        redirect('profile.php');
    }
}

function login_penalty_key(string $username): string
{
    return hash('sha256', mb_strtolower(trim($username), 'UTF-8'));
}

function login_has_forced_failure(string $username): bool
{
    $key = login_penalty_key($username);

    return !empty($_SESSION['forced_login_failures'][$key]);
}

function login_arm_forced_failure(string $username): void
{
    $key = login_penalty_key($username);
    $_SESSION['forced_login_failures'][$key] = true;
}

function login_consume_forced_failure(string $username): bool
{
    if (!login_has_forced_failure($username)) {
        return false;
    }

    $key = login_penalty_key($username);
    unset($_SESSION['forced_login_failures'][$key]);

    return true;
}

function ensure_login_attempts_table(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    execute_prepared(
        $conn,
        'CREATE TABLE IF NOT EXISTS login_attempts (
            attempt_key CHAR(64) NOT NULL PRIMARY KEY,
            scope VARCHAR(20) NOT NULL,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_failed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_login_attempts_locked_until (locked_until),
            INDEX idx_login_attempts_scope_updated (scope, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci'
    );

    $done = true;
}

function login_client_ip(): string
{
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    return mb_substr($ipAddress !== '' ? $ipAddress : 'unknown', 0, 45, 'UTF-8');
}

function login_attempt_keys(string $username): array
{
    $normalizedUsername = mb_strtolower(trim($username), 'UTF-8');
    $ipAddress = login_client_ip();

    return [
        'user_ip' => hash('sha256', 'user_ip|' . $normalizedUsername . '|' . $ipAddress),
        'ip' => hash('sha256', 'ip|' . $ipAddress),
    ];
}

function login_is_rate_limited(mysqli $conn, string $username): bool
{
    ensure_login_attempts_table($conn);
    $keys = login_attempt_keys($username);

    $row = fetch_one_prepared(
        $conn,
        'SELECT attempt_key FROM login_attempts
         WHERE attempt_key IN (?, ?) AND locked_until IS NOT NULL AND locked_until > NOW()
         LIMIT 1',
        'ss',
        [$keys['user_ip'], $keys['ip']]
    );

    return $row !== null;
}

function login_record_failed_attempt(mysqli $conn, string $username): void
{
    ensure_login_attempts_table($conn);
    $keys = login_attempt_keys($username);
    $windowStart = time() - 15 * 60;

    foreach ($keys as $scope => $key) {
        $threshold = $scope === 'ip' ? 30 : 5;
        $row = fetch_one_prepared(
            $conn,
            'SELECT failed_count, last_failed_at FROM login_attempts WHERE attempt_key = ? LIMIT 1',
            's',
            [$key]
        );

        $lastFailedAt = $row && !empty($row['last_failed_at']) ? strtotime((string) $row['last_failed_at']) : 0;
        $failedCount = $lastFailedAt >= $windowStart ? (int) ($row['failed_count'] ?? 0) + 1 : 1;
        $lockedUntil = $failedCount >= $threshold ? date('Y-m-d H:i:s', time() + 15 * 60) : null;

        execute_prepared(
            $conn,
            'INSERT INTO login_attempts (attempt_key, scope, failed_count, locked_until, last_failed_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                scope = VALUES(scope),
                failed_count = VALUES(failed_count),
                locked_until = VALUES(locked_until),
                last_failed_at = NOW()',
            'ssis',
            [$key, $scope, $failedCount, $lockedUntil]
        );
    }
}

function login_clear_failed_attempts(mysqli $conn, string $username): void
{
    ensure_login_attempts_table($conn);
    $keys = login_attempt_keys($username);

    execute_prepared(
        $conn,
        'DELETE FROM login_attempts WHERE attempt_key IN (?, ?)',
        'ss',
        [$keys['user_ip'], $keys['ip']]
    );
}

function login_user(mysqli $conn, string $username, string $password): array
{
    $username = trim($username);

    if (login_is_rate_limited($conn, $username)) {
        return [
            'ok' => false,
            'error' => 'För många misslyckade inloggningsförsök. Vänta en stund och försök igen.',
        ];
    }

    $user = find_user_by_username($conn, $username);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        login_record_failed_attempt($conn, $username);
        log_event($conn, null, 'login_failed', 'user', null);

        return [
            'ok' => false,
            'error' => 'Fel användarnamn eller lösenord.',
        ];
    }

    if (($user['approval_status'] ?? 'pending') !== 'approved') {
        return [
            'ok' => false,
            'error' => $user['approval_status'] === 'rejected'
                ? 'Kontot har inte godkänts. Kontakta skoladministratören.'
                : 'Kontot väntar på godkännande av skoladministratören.',
        ];
    }

    session_regenerate_id(true);

    sync_current_user_session($conn, (int) $user['id']);
    login_clear_failed_attempts($conn, $username);

    log_event($conn, (int) $user['id'], 'login', 'user', (int) $user['id']);

    return ['ok' => true];
}

function can_edit_own_profile_name(array $user): bool
{
    return in_array($user['role'], ['school_admin', 'super_admin'], true);
}

function update_current_user_profile(mysqli $conn, array $user, string $emailInput, string $fullNameInput): array
{
    $email = normalize_email($emailInput);
    $emailWasProvided = trim($emailInput) !== '';
    $canEditName = can_edit_own_profile_name($user);
    $fullName = $canEditName ? trim($fullNameInput) : (string) $user['full_name'];

    if ($emailWasProvided && !$email) {
        return ['ok' => false, 'error' => 'Ange en giltig e-postadress eller lämna fältet tomt.'];
    }

    if ($canEditName && ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 160)) {
        return ['ok' => false, 'error' => 'Namnet måste vara 1-160 tecken.'];
    }

    execute_prepared(
        $conn,
        'UPDATE users SET email = ?, full_name = ?, updated_at = NOW() WHERE id = ?',
        'ssi',
        [$email, $fullName, (int) $user['id']]
    );

    sync_current_user_session($conn, (int) $user['id']);
    log_event($conn, (int) $user['id'], 'profile_update', 'user', (int) $user['id']);

    return ['ok' => true];
}

function change_current_user_password(mysqli $conn, array $user, string $currentPassword, string $newPassword, string $confirmPassword): array
{
    if (mb_strlen($newPassword, 'UTF-8') < 8) {
        return ['ok' => false, 'error' => 'Det nya lösenordet måste vara minst 8 tecken.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'De nya lösenorden matchar inte.'];
    }

    $row = fetch_one_prepared($conn, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1', 'i', [(int) $user['id']]);
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Nuvarande lösenord stämmer inte.'];
    }

    execute_prepared(
        $conn,
        'UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?',
        'si',
        [password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]
    );
    log_event($conn, (int) $user['id'], 'password_change', 'user', (int) $user['id']);

    return ['ok' => true];
}

function export_upload_file(?string $storedFilename, ?string $originalName): ?array
{
    if (!$storedFilename) {
        return null;
    }

    $safeName = basename($storedFilename);
    $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
    if (!is_file($path)) {
        return [
            'stored_filename' => $safeName,
            'original_name' => $originalName,
            'missing' => true,
        ];
    }

    return [
        'stored_filename' => $safeName,
        'original_name' => $originalName,
        'mime_type' => 'application/pdf',
        'size_bytes' => filesize($path) ?: 0,
        'content_base64' => base64_encode((string) file_get_contents($path)),
    ];
}

function build_personal_data_export(mysqli $conn, array $user): array
{
    ensure_privacy_consent_columns($conn);

    $userId = (int) $user['id'];
    $account = fetch_one_prepared(
        $conn,
        'SELECT u.id, u.username, u.email, u.full_name, u.role, u.approval_status,
                u.privacy_consent_at, u.privacy_consent_version,
                u.created_at, u.updated_at, s.school_name
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         WHERE u.id = ?
         LIMIT 1',
        'i',
        [$userId]
    ) ?? [];

    $projects = fetch_all_prepared(
        $conn,
        'SELECT p.*, s.school_name, c.category_name, COALESCE(su.full_name, p.supervisor) AS supervisor_name
         FROM projects p
         INNER JOIN schools s ON s.id = p.school_id
         INNER JOIN categories c ON c.id = p.category_id
         LEFT JOIN users su ON su.id = p.supervisor_user_id
         WHERE p.user_id = ?
         ORDER BY p.created_at ASC, p.id ASC',
        'i',
        [$userId]
    );

    foreach ($projects as &$project) {
        $projectId = (int) $project['id'];
        $project['current_pdf_file'] = export_upload_file($project['pdf_filename'] ?? null, $project['pdf_original_name'] ?? null);
        unset($project['pdf_filename'], $project['pdf_original_name']);

        $versions = fetch_all_prepared(
            $conn,
            'SELECT uv.id, uv.stored_filename, uv.original_name, uv.created_at, u.full_name AS uploaded_by_name
             FROM upload_versions uv
             INNER JOIN users u ON u.id = uv.uploaded_by
             WHERE uv.project_id = ?
             ORDER BY uv.created_at ASC, uv.id ASC',
            'i',
            [$projectId]
        );
        foreach ($versions as &$version) {
            $version['file'] = export_upload_file($version['stored_filename'] ?? null, $version['original_name'] ?? null);
            unset($version['stored_filename']);
        }
        unset($version);
        $project['upload_versions'] = $versions;

        $project['feedback'] = fetch_all_prepared(
            $conn,
            'SELECT pf.comment_text, pf.created_at, u.full_name, u.role
             FROM project_feedback pf
             INNER JOIN users u ON u.id = pf.user_id
             WHERE pf.project_id = ?
             ORDER BY pf.created_at ASC, pf.id ASC',
            'i',
            [$projectId]
        );
    }
    unset($project);

    $commentsWritten = fetch_all_prepared(
        $conn,
        'SELECT pf.comment_text, pf.created_at, p.title AS project_title
         FROM project_feedback pf
         INNER JOIN projects p ON p.id = pf.project_id
         WHERE pf.user_id = ?
         ORDER BY pf.created_at ASC, pf.id ASC',
        'i',
        [$userId]
    );

    $auditEntries = fetch_all_prepared(
        $conn,
        'SELECT action, entity_type, entity_id, created_at
         FROM audit_log
         WHERE user_id = ? OR (entity_type = \'user\' AND entity_id = ?)
         ORDER BY created_at ASC, id ASC',
        'ii',
        [$userId, $userId]
    );

    $emailNotifications = [];
    if (!empty($account['email'])) {
        $emailNotifications = fetch_all_prepared(
            $conn,
            'SELECT recipient_email, subject, status, error_message, created_at
             FROM email_notifications
             WHERE recipient_email = ?
             ORDER BY created_at ASC, id ASC',
            's',
            [(string) $account['email']]
        );
    }

    return [
        'exported_at' => date(DATE_ATOM),
        'format' => 'SAGA personal data export v1',
        'account' => $account,
        'projects' => $projects,
        'comments_written' => $commentsWritten,
        'audit_entries' => $auditEntries,
        'email_notifications' => $emailNotifications,
    ];
}

function collect_owned_project_files(mysqli $conn, int $userId): array
{
    $files = [];
    $projects = fetch_all_prepared(
        $conn,
        'SELECT id, pdf_filename FROM projects WHERE user_id = ?',
        'i',
        [$userId]
    );

    $projectIds = [];
    foreach ($projects as $project) {
        $projectIds[] = (int) $project['id'];
        if (!empty($project['pdf_filename'])) {
            $files[] = basename((string) $project['pdf_filename']);
        }
    }

    if ($projectIds) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $versions = fetch_all_prepared(
            $conn,
            "SELECT stored_filename FROM upload_versions WHERE project_id IN ($placeholders)",
            str_repeat('i', count($projectIds)),
            $projectIds
        );
        foreach ($versions as $version) {
            if (!empty($version['stored_filename'])) {
                $files[] = basename((string) $version['stored_filename']);
            }
        }
    }

    return array_values(array_unique(array_filter($files)));
}

function delete_current_user_account(mysqli $conn, array $user, string $currentPassword, string $confirmation): array
{
    $userId = (int) $user['id'];
    if ($confirmation !== 'RADERA') {
        return ['ok' => false, 'error' => 'Skriv RADERA för att bekräfta att kontot och personuppgifterna ska tas bort.'];
    }

    $row = fetch_one_prepared($conn, 'SELECT password_hash, role, email, full_name FROM users WHERE id = ? LIMIT 1', 'i', [$userId]);
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Nuvarande lösenord stämmer inte.'];
    }

    if (($row['role'] ?? '') === 'super_admin') {
        $superAdminCount = fetch_one_prepared($conn, 'SELECT COUNT(*) AS total FROM users WHERE role = \'super_admin\' AND approval_status = \'approved\'');
        if ((int) ($superAdminCount['total'] ?? 0) <= 1) {
            return ['ok' => false, 'error' => 'Den sista godkända superadminen kan inte radera sitt konto. Skapa eller godkänn en annan superadmin först.'];
        }
    }

    $ownedFiles = collect_owned_project_files($conn, $userId);
    $email = (string) ($row['email'] ?? '');
    $fullName = (string) ($row['full_name'] ?? '');

    try {
        $conn->begin_transaction();

        execute_prepared($conn, 'DELETE FROM upload_versions WHERE uploaded_by = ?', 'i', [$userId]);
        execute_prepared($conn, 'DELETE FROM projects WHERE user_id = ?', 'i', [$userId]);
        execute_prepared($conn, 'DELETE FROM project_feedback WHERE user_id = ?', 'i', [$userId]);
        execute_prepared($conn, 'DELETE FROM password_resets WHERE user_id = ?', 'i', [$userId]);
        execute_prepared($conn, 'UPDATE audit_log SET user_id = NULL, entity_id = NULL WHERE user_id = ? OR (entity_type = \'user\' AND entity_id = ?)', 'ii', [$userId, $userId]);

        if ($email !== '') {
            execute_prepared($conn, 'DELETE FROM email_notifications WHERE recipient_email = ?', 's', [$email]);
        }
        if ($fullName !== '') {
            $like = '%' . $fullName . '%';
            execute_prepared($conn, 'DELETE FROM email_notifications WHERE subject LIKE ? OR body LIKE ?', 'ss', [$like, $like]);
        }

        execute_prepared($conn, 'DELETE FROM users WHERE id = ?', 'i', [$userId]);
        log_event($conn, null, 'account_delete', 'user', null);

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        log_app_error('Kunde inte radera konto.', $exception);

        return ['ok' => false, 'error' => 'Kontot kunde inte raderas just nu. Försök igen eller kontakta administratör.'];
    }

    foreach ($ownedFiles as $filename) {
        $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    return ['ok' => true];
}

function fetch_registration_requests(mysqli $conn, ?int $schoolId = null): array
{
    ensure_user_security_columns($conn);

    $sql = 'SELECT u.id, u.username, u.email, u.full_name, u.role, u.approval_status,
                   u.created_at, u.reviewed_at, u.registration_reviewer_id,
                   s.school_name, reviewer.full_name AS registration_reviewer_name
            FROM users u
            INNER JOIN schools s ON s.id = u.school_id
            LEFT JOIN users reviewer ON reviewer.id = u.registration_reviewer_id
            WHERE u.role IN (\'student\', \'teacher\')';
    $types = '';
    $params = [];

    if ($schoolId !== null) {
        $sql .= ' AND u.school_id = ?';
        $types .= 'i';
        $params[] = $schoolId;
    }

    $sql .= ' ORDER BY FIELD(u.approval_status, \'pending\', \'rejected\', \'approved\'), u.created_at DESC, u.id DESC';

    return fetch_all_prepared($conn, $sql, $types, $params);
}

function fetch_teacher_registration_requests(mysqli $conn, array $teacher): array
{
    ensure_user_security_columns($conn);

    if (($teacher['role'] ?? '') !== 'teacher') {
        return [];
    }

    return fetch_all_prepared(
        $conn,
        'SELECT id, username, email, full_name, created_at
         FROM users
         WHERE role = \'student\'
           AND approval_status = \'pending\'
           AND school_id = ?
           AND registration_reviewer_id = ?
         ORDER BY created_at ASC, id ASC',
        'ii',
        [(int) $teacher['school_id'], (int) $teacher['id']]
    );
}

function assign_registration_to_teacher(mysqli $conn, int $studentId, int $teacherId, array $reviewer): bool
{
    ensure_user_security_columns($conn);

    if (($reviewer['role'] ?? '') !== 'school_admin') {
        return false;
    }

    $teacher = fetch_one_prepared(
        $conn,
        'SELECT id, email, full_name
         FROM users
         WHERE id = ? AND role = \'teacher\' AND approval_status = \'approved\' AND school_id = ?
         LIMIT 1',
        'ii',
        [$teacherId, (int) $reviewer['school_id']]
    );
    if (!$teacher) {
        return false;
    }

    $stmt = execute_prepared(
        $conn,
        'UPDATE users
         SET registration_reviewer_id = ?, updated_at = NOW()
         WHERE id = ? AND role = \'student\' AND approval_status = \'pending\' AND school_id = ?',
        'iii',
        [$teacherId, $studentId, (int) $reviewer['school_id']]
    );
    if ($stmt->affected_rows < 1) {
        return false;
    }

    log_event($conn, (int) $reviewer['id'], 'registration_assign_teacher', 'user', $studentId);
    if (!empty($teacher['email'])) {
        notify_user(
            $conn,
            (int) $teacher['id'],
            'Elevregistrering att granska i SAGA',
            'En elevregistrering har tilldelats dig av skoladministratören. Logga in i SAGA för att godkänna eller avvisa kontot.'
        );
    }

    return true;
}

function review_registration(mysqli $conn, int $userId, string $status, array $reviewer, ?int $schoolId = null): bool
{
    ensure_user_security_columns($conn);

    if (!in_array($status, ['approved', 'rejected'], true)) {
        return false;
    }

    $reviewerRole = (string) ($reviewer['role'] ?? '');
    if (!in_array($reviewerRole, ['school_admin', 'super_admin', 'teacher'], true)) {
        return false;
    }

    if ($reviewerRole === 'school_admin') {
        if ($schoolId === null || (int) $schoolId !== (int) $reviewer['school_id']) {
            return false;
        }
    }

    if ($reviewerRole === 'teacher') {
        $stmt = execute_prepared(
            $conn,
            'UPDATE users
             SET approval_status = ?, reviewed_by = ?, reviewed_at = NOW(), registration_reviewer_id = NULL
             WHERE id = ?
               AND role = \'student\'
               AND approval_status = \'pending\'
               AND school_id = ?
               AND registration_reviewer_id = ?',
            'siiii',
            [$status, (int) $reviewer['id'], $userId, (int) $reviewer['school_id'], (int) $reviewer['id']]
        );

        if ($stmt->affected_rows < 1) {
            return false;
        }

        log_event($conn, (int) $reviewer['id'], 'registration_' . $status, 'user', $userId);
        notify_user(
            $conn,
            $userId,
            $status === 'approved' ? 'Din SAGA-registrering är godkänd' : 'Din SAGA-registrering har avvisats',
            $status === 'approved'
                ? 'Ditt konto är nu godkänt och du kan logga in i SAGA.'
                : 'Din registrering har avvisats. Kontakta skoladministratören om du har frågor.'
        );

        return true;
    }

    $sql = 'UPDATE users
            SET approval_status = ?, reviewed_by = ?, reviewed_at = NOW(), registration_reviewer_id = NULL
            WHERE id = ? AND role IN (\'student\', \'teacher\')';
    $types = 'sii';
    $params = [$status, (int) $reviewer['id'], $userId];

    if ($schoolId !== null) {
        $sql .= ' AND school_id = ?';
        $types .= 'i';
        $params[] = $schoolId;
    }

    $stmt = execute_prepared($conn, $sql, $types, $params);

    if ($stmt->affected_rows < 1) {
        return false;
    }

    log_event($conn, (int) $reviewer['id'], 'registration_' . $status, 'user', $userId);
    notify_user(
        $conn,
        $userId,
        $status === 'approved' ? 'Din SAGA-registrering är godkänd' : 'Din SAGA-registrering har avvisats',
        $status === 'approved'
            ? 'Ditt konto är nu godkänt och du kan logga in i SAGA.'
            : 'Din registrering har avvisats. Kontakta skoladministratören om du har frågor.'
    );

    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}



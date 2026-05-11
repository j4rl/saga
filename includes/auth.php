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

function fetch_registration_requests(mysqli $conn, ?int $schoolId = null): array
{
    $sql = 'SELECT u.id, u.username, u.email, u.full_name, u.role, u.approval_status, u.created_at, u.reviewed_at, s.school_name
            FROM users u
            INNER JOIN schools s ON s.id = u.school_id
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

function review_registration(mysqli $conn, int $userId, string $status, array $reviewer, ?int $schoolId = null): bool
{
    if (!in_array($status, ['approved', 'rejected'], true)) {
        return false;
    }

    if (!in_array($reviewer['role'] ?? '', ['school_admin', 'super_admin'], true)) {
        return false;
    }

    if (($reviewer['role'] ?? '') === 'school_admin') {
        if ($schoolId === null || (int) $schoolId !== (int) $reviewer['school_id']) {
            return false;
        }
    }

    $sql = 'UPDATE users
            SET approval_status = ?, reviewed_by = ?, reviewed_at = NOW()
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



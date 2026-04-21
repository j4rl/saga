<?php
declare(strict_types=1);

function find_user_by_username(mysqli $conn, string $username): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT u.id, u.username, u.email, u.password_hash, u.full_name, u.role, u.school_id, u.approval_status, s.school_name
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
    return fetch_one_prepared(
        $conn,
        'SELECT u.id, u.username, u.email, u.password_hash, u.full_name, u.role, u.school_id, u.approval_status, s.school_name
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         WHERE u.id = ?
         LIMIT 1',
        'i',
        [$userId]
    );
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
    ];
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

function login_user(mysqli $conn, string $username, string $password): array
{
    $username = trim($username);

    if (login_consume_forced_failure($username)) {
        return [
            'ok' => false,
            'error' => 'Fel användarnamn eller lösenord.',
        ];
    }

    $user = find_user_by_username($conn, $username);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        login_arm_forced_failure($username);

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
        'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?',
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



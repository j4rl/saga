<?php
declare(strict_types=1);

function find_user_by_username(mysqli $conn, string $username): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT u.id, u.username, u.password_hash, u.full_name, u.role, u.school_id, s.school_name
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         WHERE u.username = ?
         LIMIT 1',
        's',
        [$username]
    );
}

function login_user(mysqli $conn, string $username, string $password): bool
{
    $user = find_user_by_username($conn, trim($username));

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'school_id' => (int) $user['school_id'],
        'school_name' => $user['school_name'],
    ];

    log_event($conn, (int) $user['id'], 'login', 'user', (int) $user['id']);

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



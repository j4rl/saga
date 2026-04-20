<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sentToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($sentToken) || !hash_equals((string) $sessionToken, $sentToken)) {
        http_response_code(400);
        exit('Ogiltig säkerhetstoken. Ladda om sidan och försök igen.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Du måste logga in för att komma åt sidan.');
        redirect('login.php');
    }
}

function require_role(array|string $roles): void
{
    require_login();

    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $user = current_user();

    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('Du har inte behörighet att visa sidan.');
    }
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return date('Y-m-d H:i', strtotime($date));
}

function excerpt(?string $text, int $length = 220): string
{
    $clean = trim(preg_replace('/\s+/', ' ', (string) $text));

    if (mb_strlen($clean) <= $length) {
        return $clean;
    }

    return mb_substr($clean, 0, $length - 3) . '...';
}

function bind_and_execute(mysqli_stmt $stmt, string $types = '', array $params = []): void
{
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
}

function fetch_all_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    bind_and_execute($stmt, $types, $params);

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_one_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $conn->prepare($sql);
    bind_and_execute($stmt, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function execute_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    bind_and_execute($stmt, $types, $params);

    return $stmt;
}

function fetch_schools(mysqli $conn): array
{
    return fetch_all_prepared($conn, 'SELECT id, school_name FROM schools ORDER BY school_name');
}

function log_event(mysqli $conn, ?int $userId, string $action, string $entityType, ?int $entityId = null): void
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    execute_prepared(
        $conn,
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)',
        'issis',
        [$userId, $action, $entityType, $entityId, $ipAddress]
    );
}

function dashboard_url_for_role(string $role): string
{
    return match ($role) {
        'student' => 'dashboard_student.php',
        'teacher' => 'dashboard_teacher.php',
        'admin' => 'dashboard_admin.php',
        default => 'index.php',
    };
}



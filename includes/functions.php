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

    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);
    if ($lastActivity > 0 && $now - $lastActivity > SESSION_IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity_at'] = $now;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        defined('SAGA_ALLOW_SELF_FRAME') && SAGA_ALLOW_SELF_FRAME ? "frame-ancestors 'self'" : "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline'",
        "connect-src 'self'",
    ];

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: ' . (defined('SAGA_ALLOW_SELF_FRAME') && SAGA_ALLOW_SELF_FRAME ? 'SAMEORIGIN' : 'DENY'));
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Content-Security-Policy: ' . implode('; ', $csp));

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function request_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function configured_app_base_url(): ?string
{
    if (!defined('APP_BASE_URL')) {
        return null;
    }

    $url = rtrim((string) APP_BASE_URL, '/');
    $parts = parse_url($url);
    if (
        !$parts
        || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || empty($parts['host'])
    ) {
        return null;
    }

    return $url;
}

function safe_request_host(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === '' || str_contains($host, "\r") || str_contains($host, "\n")) {
        return 'localhost';
    }

    if (preg_match('/^(localhost|[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])(:[0-9]{1,5})?$/', $host) !== 1) {
        return 'localhost';
    }

    return mb_substr($host, 0, 255, 'UTF-8');
}

function mail_from_domain(): string
{
    $baseUrl = configured_app_base_url();
    $host = $baseUrl ? (string) (parse_url($baseUrl, PHP_URL_HOST) ?: '') : safe_request_host();
    $host = preg_replace('/:\d+$/', '', $host) ?? '';
    $host = trim($host, '[]');
    $host = preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?? '';

    return $host !== '' ? $host : 'localhost';
}

function sanitize_mail_header_value(string $value, int $maxLength = 190): string
{
    $value = trim(str_replace(["\r", "\n"], ' ', $value));

    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function app_is_installed(): bool
{
    return is_file(INSTALL_LOCK_FILE);
}

function cookie_consent_accepted(): bool
{
    return ($_COOKIE['saga_cookie_consent'] ?? '') === 'accepted';
}

function clear_cookie_consent(): void
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie('saga_cookie_consent', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['saga_cookie_consent']);
}

function render_cookie_notice(): void
{
    if (cookie_consent_accepted()) {
        return;
    }
    ?>
    <div class="cookie-banner" data-cookie-banner role="region" aria-label="Information om kakor">
        <div>
            <strong>Kakor krävs</strong>
            <p>SAGA använder nödvändiga kakor för inloggning, säkerhet och dina lokala inställningar. Du behöver godkänna kakor för att använda tjänsten. <a href="privacy.php">Läs mer</a>.</p>
        </div>
        <button class="button button-primary" type="button" data-cookie-accept>Godkänn kakor</button>
    </div>
    <?php
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
        redirect('index.php');
    }
}

function require_role(array|string $roles): void
{
    require_login();

    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $user = current_user();

    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        set_flash('error', 'Du har inte behörighet att visa sidan.');
        redirect('index.php');
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

function db_table_prefix(): string
{
    return defined('DB_TABLE_PREFIX') ? (string) DB_TABLE_PREFIX : '';
}

function prefix_sql_tables(string $sql): string
{
    $prefix = db_table_prefix();
    if ($prefix === '') {
        return $sql;
    }

    static $tables = ['schools', 'categories', 'users', 'projects', 'upload_versions', 'audit_log', 'email_notifications', 'login_attempts', 'schema_migrations', 'project_feedback', 'password_resets', 'password_reset_attempts'];
    $pattern = '/(?<![A-Za-z0-9_`])(' . implode('|', $tables) . ')(?![A-Za-z0-9_`])/';

    return preg_replace_callback(
        $pattern,
        static fn (array $match): string => '`' . $prefix . $match[1] . '`',
        $sql
    ) ?? $sql;
}

function fetch_all_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $sql = prefix_sql_tables($sql);
    $stmt = $conn->prepare($sql);
    bind_and_execute($stmt, $types, $params);

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetch_one_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $sql = prefix_sql_tables($sql);
    $stmt = $conn->prepare($sql);
    bind_and_execute($stmt, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function execute_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $sql = prefix_sql_tables($sql);
    $stmt = $conn->prepare($sql);
    bind_and_execute($stmt, $types, $params);

    return $stmt;
}

function fetch_schools(mysqli $conn): array
{
    return fetch_all_prepared($conn, 'SELECT id, school_name FROM schools ORDER BY school_name');
}

function fetch_school(mysqli $conn, int $schoolId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT id, school_name FROM schools WHERE id = ? LIMIT 1',
        'i',
        [$schoolId]
    );
}

function fetch_school_profile(mysqli $conn, int $schoolId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT id, school_name, theme_custom_enabled, theme_primary, theme_secondary,
                theme_bg, theme_surface, theme_text, logo_filename, logo_original_name, logo_mime
         FROM schools
         WHERE id = ?
         LIMIT 1',
        'i',
        [$schoolId]
    );
}

function default_theme_colors(): array
{
    return [
        'theme_primary' => '#235b4e',
        'theme_secondary' => '#24527a',
        'theme_bg' => '#f6f7f9',
        'theme_surface' => '#ffffff',
        'theme_text' => '#20242a',
    ];
}

function default_theme_seed_colors(): array
{
    $defaults = default_theme_colors();

    return [
        'theme_primary' => $defaults['theme_primary'],
        'theme_secondary' => $defaults['theme_secondary'],
    ];
}

function is_hex_color(?string $value): bool
{
    return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
}

function normalize_hex_color(?string $value): ?string
{
    $value = trim((string) $value);

    return is_hex_color($value) ? strtolower($value) : null;
}

function hex_color_luminance(string $color): float
{
    $color = ltrim($color, '#');
    $channels = [
        hexdec(substr($color, 0, 2)) / 255,
        hexdec(substr($color, 2, 2)) / 255,
        hexdec(substr($color, 4, 2)) / 255,
    ];

    foreach ($channels as &$channel) {
        $channel = $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
    unset($channel);

    return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
}

function hex_color_contrast_ratio(string $foreground, string $background): float
{
    $foregroundLuminance = hex_color_luminance($foreground);
    $backgroundLuminance = hex_color_luminance($background);
    $lighter = max($foregroundLuminance, $backgroundLuminance);
    $darker = min($foregroundLuminance, $backgroundLuminance);

    return ($lighter + 0.05) / ($darker + 0.05);
}

function readable_text_color(string $background, string $light = '#ffffff', string $dark = '#0d211a'): string
{
    return hex_color_contrast_ratio($light, $background) >= hex_color_contrast_ratio($dark, $background)
        ? $light
        : $dark;
}

function hex_to_rgb(string $color): array
{
    $color = ltrim($color, '#');

    return [
        hexdec(substr($color, 0, 2)),
        hexdec(substr($color, 2, 2)),
        hexdec(substr($color, 4, 2)),
    ];
}

function rgb_to_hex(array $rgb): string
{
    return sprintf(
        '#%02x%02x%02x',
        max(0, min(255, (int) round($rgb[0]))),
        max(0, min(255, (int) round($rgb[1]))),
        max(0, min(255, (int) round($rgb[2])))
    );
}

function mix_hex_colors(string $color, string $target, float $targetWeight): string
{
    $sourceRgb = hex_to_rgb($color);
    $targetRgb = hex_to_rgb($target);
    $targetWeight = max(0.0, min(1.0, $targetWeight));
    $sourceWeight = 1.0 - $targetWeight;

    return rgb_to_hex([
        ($sourceRgb[0] * $sourceWeight) + ($targetRgb[0] * $targetWeight),
        ($sourceRgb[1] * $sourceWeight) + ($targetRgb[1] * $targetWeight),
        ($sourceRgb[2] * $sourceWeight) + ($targetRgb[2] * $targetWeight),
    ]);
}

function color_contrasts_with_all(string $color, array $backgrounds, float $minimumRatio): bool
{
    foreach ($backgrounds as $background) {
        if (hex_color_contrast_ratio($color, $background) < $minimumRatio) {
            return false;
        }
    }

    return true;
}

function adjust_color_for_contrast(string $color, array $backgrounds, float $minimumRatio = 4.5): string
{
    if (color_contrasts_with_all($color, $backgrounds, $minimumRatio)) {
        return $color;
    }

    foreach (['#ffffff', '#000000'] as $target) {
        for ($step = 1; $step <= 20; $step++) {
            $candidate = mix_hex_colors($color, $target, $step * 0.05);
            if (color_contrasts_with_all($candidate, $backgrounds, $minimumRatio)) {
                return $candidate;
            }
        }
    }

    $whitePasses = color_contrasts_with_all('#ffffff', $backgrounds, $minimumRatio);

    return $whitePasses ? '#ffffff' : '#000000';
}

function derive_school_theme_colors(string $primary, string $secondary): array
{
    $primary = normalize_hex_color($primary) ?? default_theme_colors()['theme_primary'];
    $secondary = normalize_hex_color($secondary) ?? default_theme_colors()['theme_secondary'];

    $lightBg = mix_hex_colors($primary, '#ffffff', 0.96);
    $lightSurface = mix_hex_colors($secondary, '#ffffff', 0.985);
    $lightTextBase = mix_hex_colors($primary, '#111827', 0.86);
    $lightText = adjust_color_for_contrast($lightTextBase, [$lightBg, $lightSurface], 4.5);
    $lightSecondary = adjust_color_for_contrast($secondary, [$lightBg, $lightSurface], 4.5);

    $darkBg = mix_hex_colors($primary, '#05080a', 0.88);
    $darkSurface = mix_hex_colors($secondary, '#111820', 0.84);
    $darkTextBase = mix_hex_colors($secondary, '#f7fbfc', 0.88);
    $darkText = adjust_color_for_contrast($darkTextBase, [$darkBg, $darkSurface], 4.5);
    $darkPrimary = adjust_color_for_contrast($primary, [$darkBg, $darkSurface], 3.0);
    $darkSecondary = adjust_color_for_contrast($secondary, [$darkBg, $darkSurface], 4.5);

    return [
        'light' => [
            'theme_primary' => $primary,
            'theme_secondary' => $lightSecondary,
            'theme_bg' => $lightBg,
            'theme_surface' => $lightSurface,
            'theme_text' => $lightText,
        ],
        'dark' => [
            'theme_primary' => $darkPrimary,
            'theme_secondary' => $darkSecondary,
            'theme_bg' => $darkBg,
            'theme_surface' => $darkSurface,
            'theme_text' => $darkText,
        ],
        'seed' => [
            'theme_primary' => $primary,
            'theme_secondary' => $secondary,
        ],
    ];
}

function validate_school_theme_colors(array $colors): array
{
    $errors = [];
    $required = ['theme_primary', 'theme_secondary'];

    foreach ($required as $field) {
        if (empty($colors[$field]) || !is_hex_color($colors[$field])) {
            return ['Ange två giltiga hex-färger för skolans tema.'];
        }
    }

    $derived = derive_school_theme_colors($colors['theme_primary'], $colors['theme_secondary']);
    $minimumTextContrast = 4.5;
    foreach (['light' => 'ljust', 'dark' => 'mörkt'] as $mode => $label) {
        $palette = $derived[$mode];
        if (!color_contrasts_with_all($palette['theme_text'], [$palette['theme_bg'], $palette['theme_surface']], $minimumTextContrast)) {
            $errors[] = 'Det beräknade temat får inte tillräcklig textkontrast i ' . $label . ' läge.';
        }

        if (!color_contrasts_with_all($palette['theme_secondary'], [$palette['theme_bg'], $palette['theme_surface']], $minimumTextContrast)) {
            $errors[] = 'Det beräknade temat får inte tillräcklig länkkontrast i ' . $label . ' läge.';
        }

        if (hex_color_contrast_ratio(readable_text_color($palette['theme_primary']), $palette['theme_primary']) < $minimumTextContrast) {
            $errors[] = 'Det beräknade temat får inte tillräcklig knappkontrast i ' . $label . ' läge.';
        }
    }

    return $errors;
}

function css_var_block(string $selector, array $vars): string
{
    if (!$vars) {
        return '';
    }

    $declarations = [];
    foreach ($vars as $name => $value) {
        $declarations[] = $name . ': ' . $value;
    }

    return $selector . '{' . implode(';', $declarations) . ';}';
}

function theme_support_vars(string $primary, string $surface, string $text): array
{
    return [
        '--primary-strong' => 'color-mix(in srgb, ' . $primary . ' 88%, ' . $text . ')',
        '--on-primary' => readable_text_color($primary),
        '--surface-strong' => 'color-mix(in srgb, ' . $surface . ' 92%, ' . $text . ')',
        '--line' => 'color-mix(in srgb, ' . $surface . ' 78%, ' . $text . ')',
        '--input-bg' => $surface,
        '--input-border' => 'color-mix(in srgb, ' . $surface . ' 68%, ' . $text . ')',
        '--muted' => 'color-mix(in srgb, ' . $text . ' 68%, ' . $surface . ')',
        '--header-bg' => $surface,
        '--focus-ring' => 'color-mix(in srgb, ' . $primary . ' 28%, transparent)',
    ];
}

function school_theme_css_vars(array $school): string
{
    if ((int) ($school['theme_custom_enabled'] ?? 0) !== 1) {
        return '';
    }

    $primary = normalize_hex_color($school['theme_primary'] ?? null);
    $secondary = normalize_hex_color($school['theme_secondary'] ?? null);
    if (!$primary || !$secondary || validate_school_theme_colors(['theme_primary' => $primary, 'theme_secondary' => $secondary])) {
        return '';
    }

    $derived = derive_school_theme_colors($primary, $secondary);
    $colors = $derived['light'];
    $lightVars = [
        '--primary' => $colors['theme_primary'],
        '--secondary' => $colors['theme_secondary'],
        '--bg' => $colors['theme_bg'],
        '--surface' => $colors['theme_surface'],
        '--text' => $colors['theme_text'],
    ] + theme_support_vars($colors['theme_primary'], $colors['theme_surface'], $colors['theme_text']);

    $darkColors = $derived['dark'];
    $darkBg = $darkColors['theme_bg'];
    $darkSurface = $darkColors['theme_surface'];
    $darkText = $darkColors['theme_text'];
    $darkPrimary = $darkColors['theme_primary'];
    $darkSecondary = $darkColors['theme_secondary'];
    $darkVars = [
        '--primary' => $darkPrimary,
        '--secondary' => $darkSecondary,
        '--bg' => $darkBg,
        '--surface' => $darkSurface,
        '--text' => $darkText,
    ] + theme_support_vars($darkPrimary, $darkSurface, $darkText);

    return css_var_block(':root[data-theme="light"],:root[data-theme="auto"]', $lightVars)
        . '@media (prefers-color-scheme: dark){' . css_var_block(':root[data-theme="auto"]', $darkVars) . '}'
        . css_var_block(':root[data-theme="dark"]', $darkVars);
}

function normalize_theme_mode(?string $mode): string
{
    $mode = (string) $mode;

    return in_array($mode, ['light', 'auto', 'dark'], true) ? $mode : 'auto';
}

function current_theme_mode(): string
{
    return normalize_theme_mode($_COOKIE['saga_theme_mode'] ?? 'auto');
}

function normalize_email(?string $email): ?string
{
    $email = trim((string) $email);
    if ($email === '') {
        return null;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email, 'UTF-8') : null;
}

function send_email_notification(mysqli $conn, string $recipientEmail, string $subject, string $body): void
{
    $recipientEmail = (string) normalize_email($recipientEmail);
    $subject = sanitize_mail_header_value($subject);
    $status = 'skipped';
    $error = null;

    if ($recipientEmail !== '') {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: SAGA <no-reply@' . mail_from_domain() . '>',
        ];

        try {
            $status = mail($recipientEmail, $subject, $body, implode("\r\n", $headers)) ? 'sent' : 'failed';
            if ($status === 'failed') {
                $error = 'mail() returnerade false.';
            }
        } catch (Throwable $exception) {
            $status = 'failed';
            $error = mb_substr($exception->getMessage(), 0, 255);
            log_app_error('E-postutskick misslyckades.', $exception);
        }
    }

    try {
        execute_prepared(
            $conn,
            'INSERT INTO email_notifications (recipient_email, subject, body, status, error_message)
             VALUES (?, ?, ?, ?, ?)',
            'sssss',
            [$recipientEmail ?: '(saknas)', $subject, '[brödtext lagras inte av integritetsskäl]', $status, $error]
        );
    } catch (Throwable $exception) {
        log_app_error('Kunde inte skriva e-postnotislogg.', $exception);
    }
}

function log_app_error(string $message, ?Throwable $exception = null): void
{
    $context = [
        'path' => $_SERVER['SCRIPT_NAME'] ?? '',
        'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? '',
    ];

    if ($exception) {
        $context['exception'] = get_class($exception);
        $context['error'] = $exception->getMessage();
    }

    error_log('[SAGA] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function upload_path_is_inside_webroot(): bool
{
    $base = realpath(BASE_PATH);
    $uploads = realpath(UPLOAD_DIR);

    return $base !== false
        && $uploads !== false
        && (str_starts_with($uploads, $base . DIRECTORY_SEPARATOR) || $uploads === $base);
}

function fetch_environment_checks(mysqli $conn): array
{
    $checks = [];
    $add = static function (string $label, string $status, string $detail) use (&$checks): void {
        $checks[] = [
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
        ];
    };

    try {
        $conn->query('SELECT 1');
        $add('Databasanslutning', 'ok', 'Databasen svarar.');
    } catch (Throwable $exception) {
        $add('Databasanslutning', 'critical', 'Databasen svarar inte.');
        log_app_error('Hälsokontroll kunde inte nå databasen.', $exception);
    }

    $add(
        'PHP mysqli',
        extension_loaded('mysqli') ? 'ok' : 'critical',
        extension_loaded('mysqli') ? 'mysqli är aktiverat.' : 'mysqli saknas och måste aktiveras.'
    );

    $add(
        'Uppladdningsmapp',
        is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR) ? 'ok' : 'critical',
        is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR)
            ? 'Mappen finns och är skrivbar.'
            : 'Mappen saknas eller är inte skrivbar.'
    );

    $uploadHtaccess = UPLOAD_DIR . DIRECTORY_SEPARATOR . '.htaccess';
    $add(
        'Direktåtkomst till uppladdningar',
        upload_path_is_inside_webroot() && !is_file($uploadHtaccess) ? 'warning' : 'ok',
        upload_path_is_inside_webroot()
            ? (is_file($uploadHtaccess)
                ? 'uploads/ ligger i webbroten men Apache-regel finns. Kontrollera motsvarande regel på andra webbservrar.'
                : 'uploads/ ligger i webbroten och saknar Apache-regel.')
            : 'Uppladdningar ligger utanför webbroten.'
    );

    $add(
        'Installeringsfil',
        is_file(INSTALL_LOCK_FILE) ? 'ok' : 'critical',
        is_file(INSTALL_LOCK_FILE) ? 'Installationen är låst.' : 'Installationen är inte låst.'
    );

    $add(
        'config/ skrivbarhet',
        is_writable(CONFIG_DIR) ? 'warning' : 'ok',
        is_writable(CONFIG_DIR)
            ? 'config/ är skrivbar. Efter installation bör den låsas för webbservern.'
            : 'config/ är inte skrivbar.'
    );

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $add(
        'HTTPS',
        $isHttps ? 'ok' : 'warning',
        $isHttps ? 'HTTPS används.' : 'HTTPS verkar inte vara aktivt för denna request.'
    );

    $baseUrl = configured_app_base_url();
    $add(
        'Publik bas-URL',
        $baseUrl ? 'ok' : 'warning',
        $baseUrl
            ? 'APP_BASE_URL är satt till ' . $baseUrl . '.'
            : 'APP_BASE_URL saknas. Lösenordsåterställning bygger då länkar från aktuell Host-header.'
    );

    return $checks;
}

function notify_school_admins(mysqli $conn, int $schoolId, string $subject, string $body): void
{
    $admins = fetch_all_prepared(
        $conn,
        'SELECT email FROM users
         WHERE school_id = ? AND role = \'school_admin\' AND approval_status = \'approved\' AND email IS NOT NULL',
        'i',
        [$schoolId]
    );

    foreach ($admins as $admin) {
        send_email_notification($conn, (string) $admin['email'], $subject, $body);
    }
}

function notify_user(mysqli $conn, int $userId, string $subject, string $body): void
{
    $user = fetch_one_prepared($conn, 'SELECT email FROM users WHERE id = ? LIMIT 1', 'i', [$userId]);
    if ($user && !empty($user['email'])) {
        send_email_notification($conn, (string) $user['email'], $subject, $body);
    }
}

function fetch_admin_users(mysqli $conn, array $filters = [], int $page = 1, int $perPage = 25): array
{
    $where = [];
    $types = '';
    $params = [];

    $query = trim((string) ($filters['q'] ?? ''));
    if ($query !== '') {
        $like = '%' . $query . '%';
        $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    $role = (string) ($filters['role'] ?? '');
    if (in_array($role, ['student', 'teacher', 'school_admin', 'super_admin'], true)) {
        $where[] = 'u.role = ?';
        $types .= 's';
        $params[] = $role;
    }

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'u.approval_status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $schoolId = (int) ($filters['school_id'] ?? 0);
    if ($schoolId > 0) {
        $where[] = 'u.school_id = ?';
        $types .= 'i';
        $params[] = $schoolId;
    }

    $page = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset = ($page - 1) * $perPage;
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    return fetch_all_prepared(
        $conn,
        'SELECT u.id, u.username, u.email, u.full_name, u.role, u.approval_status, u.created_at, s.school_name, s.id AS school_id
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         ' . $whereSql . '
         ORDER BY u.created_at DESC, u.id DESC
         LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );
}

function count_admin_users(mysqli $conn, array $filters = []): int
{
    $where = [];
    $types = '';
    $params = [];

    $query = trim((string) ($filters['q'] ?? ''));
    if ($query !== '') {
        $like = '%' . $query . '%';
        $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    $role = (string) ($filters['role'] ?? '');
    if (in_array($role, ['student', 'teacher', 'school_admin', 'super_admin'], true)) {
        $where[] = 'u.role = ?';
        $types .= 's';
        $params[] = $role;
    }

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'u.approval_status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $schoolId = (int) ($filters['school_id'] ?? 0);
    if ($schoolId > 0) {
        $where[] = 'u.school_id = ?';
        $types .= 'i';
        $params[] = $schoolId;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $row = fetch_one_prepared(
        $conn,
        'SELECT COUNT(*) AS total
         FROM users u
         INNER JOIN schools s ON s.id = u.school_id
         ' . $whereSql,
        $types,
        $params
    );

    return (int) ($row['total'] ?? 0);
}

function fetch_recent_email_notifications(mysqli $conn, int $limit = 20): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT recipient_email, subject, status, error_message, created_at
         FROM email_notifications
         ORDER BY created_at DESC, id DESC
         LIMIT ?',
        'i',
        [$limit]
    );
}

function fetch_audit_log_entries(mysqli $conn, array $filters = [], int $page = 1, int $perPage = 25): array
{
    [$whereSql, $types, $params] = audit_log_filter_sql($filters);
    $page = max(1, $page);
    $perPage = min(100, max(1, $perPage));

    return fetch_all_prepared(
        $conn,
        "SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id, a.ip_address, a.created_at,
                u.username, u.full_name
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.user_id
         $whereSql
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT ? OFFSET ?",
        $types . 'ii',
        array_merge($params, [$perPage, ($page - 1) * $perPage])
    );
}

function count_audit_log_entries(mysqli $conn, array $filters = []): int
{
    [$whereSql, $types, $params] = audit_log_filter_sql($filters);
    $row = fetch_one_prepared($conn, "SELECT COUNT(*) AS total FROM audit_log a LEFT JOIN users u ON u.id = a.user_id $whereSql", $types, $params);

    return (int) ($row['total'] ?? 0);
}

function audit_log_filter_sql(array $filters): array
{
    $where = [];
    $types = '';
    $params = [];

    $action = trim((string) ($filters['action'] ?? ''));
    if ($action !== '') {
        $where[] = 'a.action LIKE ?';
        $types .= 's';
        $params[] = '%' . $action . '%';
    }

    $entityType = trim((string) ($filters['entity_type'] ?? ''));
    if ($entityType !== '') {
        $where[] = 'a.entity_type = ?';
        $types .= 's';
        $params[] = $entityType;
    }

    $userQuery = trim((string) ($filters['user'] ?? ''));
    if ($userQuery !== '') {
        $where[] = '(u.username LIKE ? OR u.full_name LIKE ?)';
        $types .= 'ss';
        $like = '%' . $userQuery . '%';
        array_push($params, $like, $like);
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
        $where[] = 'a.created_at >= ?';
        $types .= 's';
        $params[] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
        $where[] = 'a.created_at <= ?';
        $types .= 's';
        $params[] = $dateTo . ' 23:59:59';
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $types, $params];
}

function fetch_email_notifications(mysqli $conn, int $limit = 500): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT recipient_email, subject, status, error_message, created_at
         FROM email_notifications
         ORDER BY created_at DESC, id DESC
         LIMIT ?',
        'i',
        [$limit]
    );
}

function validate_school_logo_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'file' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Logotypen kunde inte laddas upp.'];
    }

    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Logotypen får vara högst 2 MB.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $extensions = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mimeType]) || @getimagesize($tmpName) === false) {
        return ['ok' => false, 'error' => 'Logotypen måste vara PNG, JPG eller WebP.'];
    }

    return [
        'ok' => true,
        'file' => [
            'tmp_name' => $tmpName,
            'stored_name' => 'school-logo-' . bin2hex(random_bytes(18)) . '.' . $extensions[$mimeType],
            'original_name' => mb_substr(basename((string) ($file['name'] ?? 'logotyp')), 0, 180),
            'mime' => $mimeType,
        ],
    ];
}

function store_school_logo(array $validatedFile): array
{
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $targetPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $validatedFile['stored_name'];

    if (!move_uploaded_file($validatedFile['tmp_name'], $targetPath)) {
        throw new RuntimeException('Kunde inte spara logotypen.');
    }

    chmod($targetPath, 0640);

    return [
        'stored_name' => $validatedFile['stored_name'],
        'original_name' => $validatedFile['original_name'],
        'mime' => $validatedFile['mime'],
    ];
}

function fetch_project_categories(mysqli $conn): array
{
    return fetch_all_prepared($conn, 'SELECT id, category_name FROM categories ORDER BY category_name');
}

function normalize_category_name(string $categoryName): string
{
    return trim(preg_replace('/\s+/', ' ', $categoryName));
}

function find_project_category_by_name(mysqli $conn, string $categoryName): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT id, category_name FROM categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1',
        's',
        [$categoryName]
    );
}

function find_or_create_project_category(mysqli $conn, string $categoryName): ?array
{
    $categoryName = normalize_category_name($categoryName);

    if ($categoryName === '' || mb_strlen($categoryName) > 120) {
        return null;
    }

    $existing = find_project_category_by_name($conn, $categoryName);
    if ($existing) {
        return $existing;
    }

    try {
        $stmt = execute_prepared(
            $conn,
            'INSERT INTO categories (category_name) VALUES (?)',
            's',
            [$categoryName]
        );

        return [
            'id' => (int) $stmt->insert_id,
            'category_name' => $categoryName,
        ];
    } catch (mysqli_sql_exception $exception) {
        return find_project_category_by_name($conn, $categoryName);
    }
}

function fetch_school_teachers(mysqli $conn, int $schoolId): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT id, full_name
         FROM users
         WHERE role = \'teacher\' AND approval_status = \'approved\' AND school_id = ?
         ORDER BY full_name',
        'i',
        [$schoolId]
    );
}

function fetch_school_teacher(mysqli $conn, int $teacherId, int $schoolId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT id, full_name
         FROM users
         WHERE id = ? AND role = \'teacher\' AND approval_status = \'approved\' AND school_id = ?
         LIMIT 1',
        'ii',
        [$teacherId, $schoolId]
    );
}

function fetch_project_category(mysqli $conn, int $categoryId): ?array
{
    return fetch_one_prepared(
        $conn,
        'SELECT id, category_name FROM categories WHERE id = ? LIMIT 1',
        'i',
        [$categoryId]
    );
}

function fetch_categories_with_counts(mysqli $conn): array
{
    return fetch_all_prepared(
        $conn,
        'SELECT c.id, c.category_name, c.created_at,
                COUNT(p.id) AS project_count,
                SUM(CASE WHEN p.is_submitted = 1 THEN 1 ELSE 0 END) AS submitted_project_count
         FROM categories c
         LEFT JOIN projects p ON p.category_id = c.id
         GROUP BY c.id, c.category_name, c.created_at
         ORDER BY c.category_name'
    );
}

function count_projects_for_category(mysqli $conn, int $categoryId): array
{
    $row = fetch_one_prepared(
        $conn,
        'SELECT COUNT(*) AS total,
                SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) AS submitted_total
         FROM projects
         WHERE category_id = ?',
        'i',
        [$categoryId]
    );

    return [
        'total' => (int) ($row['total'] ?? 0),
        'submitted_total' => (int) ($row['submitted_total'] ?? 0),
    ];
}

function create_project_category(mysqli $conn, string $categoryName): array
{
    $categoryName = normalize_category_name($categoryName);
    if ($categoryName === '' || mb_strlen($categoryName, 'UTF-8') > 120) {
        return ['ok' => false, 'error' => 'Kategorinamnet måste vara 1-120 tecken.'];
    }

    if (find_project_category_by_name($conn, $categoryName)) {
        return ['ok' => false, 'error' => 'Kategorin finns redan.'];
    }

    try {
        $stmt = execute_prepared($conn, 'INSERT INTO categories (category_name) VALUES (?)', 's', [$categoryName]);
        return ['ok' => true, 'category_id' => (int) $stmt->insert_id];
    } catch (mysqli_sql_exception $exception) {
        return ['ok' => false, 'error' => 'Kategorin kunde inte skapas. Kontrollera att namnet är unikt.'];
    }
}

function rename_project_category(mysqli $conn, int $categoryId, string $categoryName): array
{
    $categoryName = normalize_category_name($categoryName);
    if ($categoryId <= 0 || $categoryName === '' || mb_strlen($categoryName, 'UTF-8') > 120) {
        return ['ok' => false, 'error' => 'Kategorinamnet måste vara 1-120 tecken.'];
    }

    try {
        execute_prepared($conn, 'UPDATE categories SET category_name = ? WHERE id = ?', 'si', [$categoryName, $categoryId]);
        return ['ok' => true];
    } catch (mysqli_sql_exception $exception) {
        return ['ok' => false, 'error' => 'Kunde inte byta namn. Kontrollera att kategorin inte redan finns.'];
    }
}

function merge_project_categories(mysqli $conn, int $sourceCategoryId, int $targetCategoryId): array
{
    if ($sourceCategoryId <= 0 || $targetCategoryId <= 0 || $sourceCategoryId === $targetCategoryId) {
        return ['ok' => false, 'error' => 'Välj två olika kategorier.'];
    }

    $source = fetch_project_category($conn, $sourceCategoryId);
    $target = fetch_project_category($conn, $targetCategoryId);
    if (!$source || !$target) {
        return ['ok' => false, 'error' => 'Kategorin kunde inte hittas.'];
    }

    try {
        $counts = count_projects_for_category($conn, $sourceCategoryId);
        $conn->begin_transaction();
        $stmt = execute_prepared($conn, 'UPDATE projects SET category_id = ? WHERE category_id = ?', 'ii', [$targetCategoryId, $sourceCategoryId]);
        execute_prepared($conn, 'DELETE FROM categories WHERE id = ?', 'i', [$sourceCategoryId]);
        $conn->commit();
        return [
            'ok' => true,
            'updated_count' => (int) $stmt->affected_rows,
            'submitted_updated_count' => $counts['submitted_total'],
        ];
    } catch (Throwable $exception) {
        $conn->rollback();
        log_app_error('Kunde inte slå ihop kategorier.', $exception);
        return ['ok' => false, 'error' => 'Kategorierna kunde inte slås ihop.'];
    }
}

function delete_project_category(mysqli $conn, int $categoryId): array
{
    if ($categoryId <= 0) {
        return ['ok' => false, 'error' => 'Ogiltig kategori.'];
    }

    $category = fetch_project_category($conn, $categoryId);
    if (!$category) {
        return ['ok' => false, 'error' => 'Kategorin kunde inte hittas.'];
    }

    $counts = count_projects_for_category($conn, $categoryId);
    if ($counts['total'] > 0) {
        return [
            'ok' => false,
            'error' => 'Kategorin används av ' . $counts['total'] . ' arbeten, varav ' . $counts['submitted_total'] . ' inlämnade. Slå ihop kategorin med en annan kategori innan den tas bort.',
        ];
    }

    try {
        execute_prepared($conn, 'DELETE FROM categories WHERE id = ?', 'i', [$categoryId]);
        return ['ok' => true];
    } catch (mysqli_sql_exception $exception) {
        return ['ok' => false, 'error' => 'Kategorin kunde inte tas bort.'];
    }
}

function role_label(string $role): string
{
    return match ($role) {
        'student' => 'Elev',
        'teacher' => 'Lärare',
        'school_admin' => 'Skoladministratör',
        'super_admin' => 'Superadmin',
        default => $role,
    };
}

function approval_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Väntar',
        'approved' => 'Godkänd',
        'rejected' => 'Avvisad',
        default => $status,
    };
}

function log_event(mysqli $conn, ?int $userId, string $action, string $entityType, ?int $entityId = null): void
{
    $ipAddress = anonymize_ip_address($_SERVER['REMOTE_ADDR'] ?? null);

    execute_prepared(
        $conn,
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)',
        'issis',
        [$userId, $action, $entityType, $entityId, $ipAddress]
    );
}

function anonymize_ip_address(?string $ipAddress): ?string
{
    $ipAddress = trim((string) $ipAddress);
    if ($ipAddress === '') {
        return null;
    }

    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ipAddress);
        return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
    }

    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ipAddress);
        if ($packed !== false) {
            return substr(bin2hex($packed), 0, 12) . '::/48';
        }
    }

    return null;
}

function dashboard_url_for_role(string $role): string
{
    return match ($role) {
        'student' => 'dashboard_student.php',
        'teacher' => 'dashboard_teacher.php',
        'school_admin' => 'dashboard_school_admin.php',
        'super_admin' => 'dashboard_admin.php',
        default => 'index.php',
    };
}



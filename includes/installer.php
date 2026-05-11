<?php
declare(strict_types=1);

function installer_valid_identifier(string $value): bool
{
    return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
}

function installer_valid_prefix(string $value): bool
{
    return $value === '' || preg_match('/^[A-Za-z0-9_]{0,32}$/', $value) === 1;
}

function installer_table(string $prefix, string $table): string
{
    return '`' . $prefix . $table . '`';
}

function installer_create_schema(mysqli $conn, string $prefix, string $schoolName, string $adminUsername, string $adminName, string $adminPassword): void
{
    $schools = installer_table($prefix, 'schools');
    $categories = installer_table($prefix, 'categories');
    $users = installer_table($prefix, 'users');
    $projects = installer_table($prefix, 'projects');
    $uploadVersions = installer_table($prefix, 'upload_versions');
    $auditLog = installer_table($prefix, 'audit_log');
    $emailNotifications = installer_table($prefix, 'email_notifications');
    $loginAttempts = installer_table($prefix, 'login_attempts');
    $schemaMigrations = installer_table($prefix, 'schema_migrations');
    $projectFeedback = installer_table($prefix, 'project_feedback');
    $passwordResets = installer_table($prefix, 'password_resets');
    $passwordResetAttempts = installer_table($prefix, 'password_reset_attempts');

    $statements = [
        "CREATE TABLE IF NOT EXISTS $schools (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_name VARCHAR(160) NOT NULL UNIQUE,
            theme_mode ENUM('light', 'auto', 'dark') NOT NULL DEFAULT 'auto',
            theme_custom_enabled TINYINT(1) NOT NULL DEFAULT 0,
            theme_primary CHAR(7) NULL,
            theme_secondary CHAR(7) NULL,
            theme_bg CHAR(7) NULL,
            theme_surface CHAR(7) NULL,
            theme_text CHAR(7) NULL,
            logo_filename VARCHAR(120) NULL,
            logo_original_name VARCHAR(180) NULL,
            logo_mime VARCHAR(80) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(120) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            email VARCHAR(190) NULL,
            password_hash VARCHAR(255) NOT NULL,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            full_name VARCHAR(160) NOT NULL,
            role ENUM('student', 'teacher', 'school_admin', 'super_admin') NOT NULL DEFAULT 'student',
            school_id INT UNSIGNED NOT NULL,
            approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            registration_reviewer_id INT UNSIGNED NULL,
            privacy_consent_at DATETIME NULL,
            privacy_consent_version VARCHAR(40) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_{$prefix}users_school
                FOREIGN KEY (school_id) REFERENCES $schools(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}users_reviewed_by
                FOREIGN KEY (reviewed_by) REFERENCES $users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}users_registration_reviewer
                FOREIGN KEY (registration_reviewer_id) REFERENCES $users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            INDEX idx_users_role_school (role, school_id),
            INDEX idx_users_approval_school (approval_status, school_id),
            INDEX idx_users_registration_reviewer (registration_reviewer_id),
            INDEX idx_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $projects (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            school_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            subtitle VARCHAR(180) NULL,
            supervisor VARCHAR(120) NOT NULL,
            supervisor_user_id INT UNSIGNED NULL,
            abstract_text TEXT NOT NULL,
            summary_text TEXT NOT NULL,
            pdf_filename VARCHAR(120) NULL,
            pdf_original_name VARCHAR(180) NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            is_submitted TINYINT(1) NOT NULL DEFAULT 0,
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            submitted_at DATETIME NULL,
            approved_at DATETIME NULL,
            approved_by INT UNSIGNED NULL,
            publication_consent_at DATETIME NULL,
            publication_consent_version VARCHAR(40) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_{$prefix}projects_user
                FOREIGN KEY (user_id) REFERENCES $users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}projects_school
                FOREIGN KEY (school_id) REFERENCES $schools(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}projects_category
                FOREIGN KEY (category_id) REFERENCES $categories(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}projects_supervisor_user
                FOREIGN KEY (supervisor_user_id) REFERENCES $users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}projects_approved_by
                FOREIGN KEY (approved_by) REFERENCES $users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            UNIQUE KEY uq_projects_user (user_id),
            INDEX idx_projects_supervisor_user (supervisor_user_id),
            INDEX idx_projects_approved_by (approved_by),
            INDEX idx_projects_category (category_id),
            INDEX idx_projects_school_public (school_id, is_public),
            INDEX idx_projects_public_approved (is_public, is_submitted, is_approved),
            INDEX idx_projects_updated (updated_at),
            INDEX idx_projects_title (title),
            FULLTEXT KEY ft_projects_search (title, subtitle, supervisor, abstract_text, summary_text)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $uploadVersions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            stored_filename VARCHAR(120) NOT NULL,
            original_name VARCHAR(180) NOT NULL,
            uploaded_by INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_{$prefix}upload_versions_project
                FOREIGN KEY (project_id) REFERENCES $projects(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}upload_versions_user
                FOREIGN KEY (uploaded_by) REFERENCES $users(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,
            INDEX idx_upload_versions_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $auditLog (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_{$prefix}audit_user
                FOREIGN KEY (user_id) REFERENCES $users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            INDEX idx_audit_user_created (user_id, created_at),
            INDEX idx_audit_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $emailNotifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('sent', 'failed', 'skipped') NOT NULL DEFAULT 'skipped',
            error_message VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_notifications_created (created_at),
            INDEX idx_email_notifications_recipient (recipient_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $loginAttempts (
            attempt_key CHAR(64) NOT NULL PRIMARY KEY,
            scope VARCHAR(20) NOT NULL,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_failed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_login_attempts_locked_until (locked_until),
            INDEX idx_login_attempts_scope_updated (scope, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $schemaMigrations (
            version VARCHAR(40) NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $projectFeedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            comment_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_{$prefix}project_feedback_project
                FOREIGN KEY (project_id) REFERENCES $projects(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_{$prefix}project_feedback_user
                FOREIGN KEY (user_id) REFERENCES $users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            INDEX idx_project_feedback_project (project_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $passwordResets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_{$prefix}password_resets_user
                FOREIGN KEY (user_id) REFERENCES $users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            INDEX idx_password_resets_user (user_id),
            INDEX idx_password_resets_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
        "CREATE TABLE IF NOT EXISTS $passwordResetAttempts (
            attempt_key CHAR(64) NOT NULL PRIMARY KEY,
            scope VARCHAR(24) NOT NULL,
            request_count INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_requested_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_password_reset_attempts_locked_until (locked_until),
            INDEX idx_password_reset_attempts_scope_updated (scope, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
    ];

    foreach ($statements as $sql) {
        $conn->query($sql);
    }

    $stmt = $conn->prepare("INSERT INTO $schools (id, school_name) VALUES (1, ?) ON DUPLICATE KEY UPDATE school_name = VALUES(school_name)");
    $stmt->bind_param('s', $schoolName);
    $stmt->execute();

    $categoryNames = [
        'Svenska',
        'Fysik',
        'Filosofi',
        'Elektronik',
        'Konstruktion',
        'Matematik',
        'Biologi',
        'Kemi',
        'Teknik',
        'Samhällskunskap',
        'Historia',
        'Programmering',
        'Estetiska ämnen',
        'Ekonomi',
        'Annat',
    ];
    $stmt = $conn->prepare("INSERT INTO $categories (id, category_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)");
    foreach ($categoryNames as $index => $categoryName) {
        $id = $index + 1;
        $stmt->bind_param('is', $id, $categoryName);
        $stmt->execute();
    }

    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $role = 'super_admin';
    $status = 'approved';
    $stmt = $conn->prepare(
        "INSERT INTO $users (id, username, email, password_hash, full_name, role, school_id, approval_status, reviewed_at)
         VALUES (1, ?, NULL, ?, ?, ?, 1, ?, NOW())
         ON DUPLICATE KEY UPDATE username = VALUES(username), password_hash = VALUES(password_hash),
             full_name = VALUES(full_name), role = VALUES(role), approval_status = VALUES(approval_status)"
    );
    $stmt->bind_param('sssss', $adminUsername, $passwordHash, $adminName, $role, $status);
    $stmt->execute();
}

function installer_config_contents(array $config): string
{
    $appBaseUrl = rtrim((string) ($config['app_base_url'] ?? ''), '/');

    return "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "define('DB_HOST', " . var_export($config['host'], true) . ");\n"
        . "define('DB_PORT', " . (int) $config['port'] . ");\n"
        . "define('DB_USER', " . var_export($config['user'], true) . ");\n"
        . "define('DB_PASS', " . var_export($config['pass'], true) . ");\n"
        . "define('DB_NAME', " . var_export($config['name'], true) . ");\n"
        . "define('DB_TABLE_PREFIX', " . var_export($config['prefix'], true) . ");\n"
        . ($appBaseUrl !== '' ? "define('APP_BASE_URL', " . var_export($appBaseUrl, true) . ");\n" : '')
        . "define('SAGA_INSTALLED_AT', " . var_export(date(DATE_ATOM), true) . ");\n";
}

function render_installer(): never
{
    send_security_headers();

    if (app_is_installed()) {
        redirect('index.php');
    }

    $errors = [];
    $form = [
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_name' => 'saga',
        'db_user' => '',
        'db_pass' => '',
        'table_prefix' => 'saga_',
        'app_base_url' => '',
        'school_name' => '',
        'admin_username' => 'admin',
        'admin_name' => '',
    ];

    if (is_post()) {
        verify_csrf();

        foreach (array_keys($form) as $field) {
            $form[$field] = trim((string) ($_POST[$field] ?? $form[$field]));
        }

        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

        if ($form['db_host'] === '') {
            $errors[] = 'Ange databasserver.';
        }
        if (!ctype_digit($form['db_port']) || (int) $form['db_port'] < 1 || (int) $form['db_port'] > 65535) {
            $errors[] = 'Ange en giltig databasport.';
        }
        if (!installer_valid_identifier($form['db_name'])) {
            $errors[] = 'Databasnamnet får bara innehålla bokstäver, siffror och understreck.';
        }
        if ($form['db_user'] === '') {
            $errors[] = 'Ange databasanvändare.';
        }
        if (!installer_valid_prefix($form['table_prefix'])) {
            $errors[] = 'Tabellprefix får bara innehålla bokstäver, siffror och understreck.';
        }
        if ($form['app_base_url'] !== '') {
            $form['app_base_url'] = rtrim($form['app_base_url'], '/');
            $appBaseUrlParts = parse_url($form['app_base_url']);
            if (
                !$appBaseUrlParts
                || !in_array($appBaseUrlParts['scheme'] ?? '', ['http', 'https'], true)
                || empty($appBaseUrlParts['host'])
            ) {
                $errors[] = 'Ange en giltig publik adress med http:// eller https://, eller lämna fältet tomt.';
            }
        }
        if ($form['school_name'] === '' || mb_strlen($form['school_name'], 'UTF-8') > 160) {
            $errors[] = 'Ange första skolans namn, högst 160 tecken.';
        }
        if ($form['admin_username'] === '' || mb_strlen($form['admin_username'], 'UTF-8') > 80) {
            $errors[] = 'Ange superadmin-användarnamn, högst 80 tecken.';
        }
        if ($form['admin_name'] === '' || mb_strlen($form['admin_name'], 'UTF-8') > 160) {
            $errors[] = 'Ange superadmin-namn, högst 160 tecken.';
        }
        if (mb_strlen($adminPassword, 'UTF-8') < 8) {
            $errors[] = 'Superadmin-lösenordet måste vara minst 8 tecken.';
        }
        if ($adminPassword !== $adminPasswordConfirm) {
            $errors[] = 'Superadmin-lösenorden matchar inte.';
        }
        if (is_file(INSTALL_LOCK_FILE)) {
            $errors[] = 'Installationen är redan låst.';
        }
        if (!extension_loaded('mysqli')) {
            $errors[] = 'PHP-tillägget mysqli måste vara aktiverat innan installationen kan köras.';
        }
        if (!is_writable(CONFIG_DIR)) {
            $errors[] = 'Mappen config/ måste vara skrivbar under installationen.';
        }

        if (!$errors) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            try {
                $conn = new mysqli(
                    $form['db_host'],
                    $form['db_user'],
                    $form['db_pass'],
                    '',
                    (int) $form['db_port']
                );
                $conn->set_charset('utf8mb4');
                try {
                    $conn->query("CREATE DATABASE IF NOT EXISTS `" . $conn->real_escape_string($form['db_name']) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci");
                } catch (mysqli_sql_exception $createException) {
                    // Webbhotell tillåter ofta användning av befintlig databas men inte CREATE DATABASE.
                }

                try {
                    $conn->select_db($form['db_name']);
                } catch (mysqli_sql_exception $selectException) {
                    throw new RuntimeException('Kunde inte skapa eller välja databasen. Skapa databasen hos webbhotellet först eller kontrollera behörigheterna.');
                }
                $conn->query("SET collation_connection = 'utf8mb4_swedish_ci'");

                installer_create_schema(
                    $conn,
                    $form['table_prefix'],
                    $form['school_name'],
                    $form['admin_username'],
                    $form['admin_name'],
                    $adminPassword
                );

                $config = installer_config_contents([
                    'host' => $form['db_host'],
                    'port' => (int) $form['db_port'],
                    'user' => $form['db_user'],
                    'pass' => $form['db_pass'],
                    'name' => $form['db_name'],
                    'prefix' => $form['table_prefix'],
                    'app_base_url' => $form['app_base_url'],
                ]);

                if (file_put_contents(INSTALL_LOCK_FILE, $config, LOCK_EX) === false) {
                    $errors[] = 'Kunde inte skriva config/installed.php.';
                } else {
                    redirect('index.php');
                }
            } catch (Throwable $exception) {
                $errors[] = 'Installationen misslyckades: ' . $exception->getMessage();
            }
        }
    }

    $pageTitle = 'Installera SAGA';
    ?>
<!doctype html>
<html lang="sv" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<main class="page-shell installer-shell">
    <section class="section">
        <p class="eyebrow">Första start</p>
        <h1>Installera SAGA</h1>
        <p class="lead">Ange databasuppgifter, tabellprefix och första superadmin-kontot. När installationen är klar låses den med <code>config/installed.php</code>.</p>
    </section>

    <?php if ($errors): ?>
        <section class="section section-tight">
            <div class="notice notice-error">
                <?php foreach ($errors as $error): ?>
                    <div><?= h($error) ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <form class="project-form" method="post" action="index.php">
        <?= csrf_field() ?>

        <h2>Databas</h2>
        <div class="form-grid">
            <div class="field">
                <label for="db_host">Databasserver</label>
                <input id="db_host" name="db_host" type="text" value="<?= h($form['db_host']) ?>" required>
            </div>
            <div class="field">
                <label for="db_port">Port</label>
                <input id="db_port" name="db_port" type="text" value="<?= h($form['db_port']) ?>" required>
            </div>
            <div class="field">
                <label for="db_name">Databasnamn</label>
                <input id="db_name" name="db_name" type="text" value="<?= h($form['db_name']) ?>" required>
            </div>
            <div class="field">
                <label for="table_prefix">Tabellprefix</label>
                <input id="table_prefix" name="table_prefix" type="text" value="<?= h($form['table_prefix']) ?>" placeholder="saga_">
                <p class="field-help">Standard är saga_. Lämna tomt om tabellerna inte ska ha prefix.</p>
            </div>
            <div class="field">
                <label for="db_user">Databasanvändare</label>
                <input id="db_user" name="db_user" type="text" value="<?= h($form['db_user']) ?>" required>
            </div>
            <div class="field">
                <label for="db_pass">Databaslösenord</label>
                <input id="db_pass" name="db_pass" type="password" value="<?= h($form['db_pass']) ?>">
            </div>
            <div class="field">
                <label for="app_base_url">Publik adress</label>
                <input id="app_base_url" name="app_base_url" type="url" value="<?= h($form['app_base_url']) ?>" placeholder="https://exempel.se/saga">
                <p class="field-help">Används för säkra länkar i e-post, till exempel lösenordsåterställning.</p>
            </div>
        </div>

        <h2>Första skola och superadmin</h2>
        <div class="form-grid">
            <div class="field">
                <label for="school_name">Skolans namn</label>
                <input id="school_name" name="school_name" type="text" value="<?= h($form['school_name']) ?>" required>
            </div>
            <div class="field">
                <label for="admin_username">Superadmin användarnamn</label>
                <input id="admin_username" name="admin_username" type="text" value="<?= h($form['admin_username']) ?>" required>
            </div>
            <div class="field">
                <label for="admin_name">Superadmin namn</label>
                <input id="admin_name" name="admin_name" type="text" value="<?= h($form['admin_name']) ?>" required>
            </div>
            <div class="field">
                <label for="admin_password">Superadmin lösenord</label>
                <input id="admin_password" name="admin_password" type="password" required minlength="8">
            </div>
            <div class="field">
                <label for="admin_password_confirm">Bekräfta lösenord</label>
                <input id="admin_password_confirm" name="admin_password_confirm" type="password" required minlength="8">
            </div>
        </div>

        <button class="button button-primary" type="submit">Installera</button>
    </form>
</main>
<?php render_cookie_notice(); ?>
</body>
</html>
    <?php
    exit;
}

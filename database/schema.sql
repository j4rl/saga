CREATE DATABASE IF NOT EXISTS saga
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_swedish_ci;

USE saga;

CREATE TABLE IF NOT EXISTS schools (
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
    require_pdf_for_submission TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS users (
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
    CONSTRAINT fk_users_school
        FOREIGN KEY (school_id) REFERENCES schools(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_users_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_users_registration_reviewer
        FOREIGN KEY (registration_reviewer_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX idx_users_role_school (role, school_id),
    INDEX idx_users_approval_school (approval_status, school_id),
    INDEX idx_users_registration_reviewer (registration_reviewer_id),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS projects (
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
    CONSTRAINT fk_projects_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_projects_school
        FOREIGN KEY (school_id) REFERENCES schools(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_projects_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_projects_supervisor_user
        FOREIGN KEY (supervisor_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_projects_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS upload_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    stored_filename VARCHAR(120) NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_upload_versions_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_upload_versions_user
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_upload_versions_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX idx_audit_user_created (user_id, created_at),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS email_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('sent', 'failed', 'skipped') NOT NULL DEFAULT 'skipped',
    error_message VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_notifications_created (created_at),
    INDEX idx_email_notifications_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) NOT NULL PRIMARY KEY,
    scope VARCHAR(20) NOT NULL,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_failed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_locked_until (locked_until),
    INDEX idx_login_attempts_scope_updated (scope, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(40) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS project_feedback (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS password_resets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    attempt_key CHAR(64) NOT NULL PRIMARY KEY,
    scope VARCHAR(24) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_requested_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_password_reset_attempts_locked_until (locked_until),
    INDEX idx_password_reset_attempts_scope_updated (scope, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

INSERT INTO schools (id, school_name) VALUES
    (1, 'Norra Gymnasiet'),
    (2, 'Södra Gymnasiet')
ON DUPLICATE KEY UPDATE school_name = VALUES(school_name);

INSERT INTO categories (id, category_name) VALUES
    (1, 'Svenska'),
    (2, 'Fysik'),
    (3, 'Filosofi'),
    (4, 'Elektronik'),
    (5, 'Konstruktion'),
    (6, 'Matematik'),
    (7, 'Biologi'),
    (8, 'Kemi'),
    (9, 'Teknik'),
    (10, 'Samhällskunskap'),
    (11, 'Historia'),
    (12, 'Programmering'),
    (13, 'Estetiska ämnen'),
    (14, 'Ekonomi'),
    (15, 'Annat')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

INSERT INTO users (id, username, email, password_hash, full_name, role, school_id, approval_status, reviewed_at) VALUES
    (1, 'admin', NULL, '$2y$12$mCiDoX63f4nqBGqaOEZ./.NFT/.drYtE1lWip4TILC.uDeGs0xsKu', 'Superadmin', 'super_admin', 1, 'approved', NOW()),
    (2, 'elev', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Exempel Elev', 'student', 1, 'approved', NOW()),
    (3, 'larare', NULL, '$2y$12$sb.qv0WG12fcpTrumaxxk.NFPPlt5OQuObfqJzB2QspLyYOvnRymW', 'Exempel Lärare', 'teacher', 1, 'approved', NOW()),
    (4, 'skoladmin', NULL, '$2y$10$dKsdvoJdMLUUxiJKG93VVuH//Hi2c6vDnZYszPhLz4f6.zWCEK8ta', 'Skoladministratör Norra', 'school_admin', 1, 'approved', NOW()),
    (5, 'skoladmin_sodra', NULL, '$2y$10$dKsdvoJdMLUUxiJKG93VVuH//Hi2c6vDnZYszPhLz4f6.zWCEK8ta', 'Skoladministratör Södra', 'school_admin', 2, 'approved', NOW()),
    (6, 'elev1', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Maja Andersson', 'student', 1, 'approved', NOW()),
    (7, 'elev2', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Leo Karlsson', 'student', 1, 'approved', NOW()),
    (8, 'elev3', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Busig Elev', 'student', 1, 'approved', NOW()),
    (9, 'elev4', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Sara Nguyen', 'student', 1, 'approved', NOW()),
    (10, 'elev5', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Omar Hassan', 'student', 1, 'approved', NOW()),
    (11, 'elev6', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Elin Berg', 'student', 1, 'approved', NOW()),
    (12, 'elev7', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Nora Lind', 'student', 2, 'approved', NOW()),
    (13, 'elev8', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Adam Svensson', 'student', 2, 'approved', NOW()),
    (14, 'elev9', NULL, '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Lina Persson', 'student', 2, 'approved', NOW()),
    (15, 'larare_sodra', NULL, '$2y$12$sb.qv0WG12fcpTrumaxxk.NFPPlt5OQuObfqJzB2QspLyYOvnRymW', 'Södra Handledare', 'teacher', 2, 'approved', NOW())
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    full_name = VALUES(full_name),
    role = VALUES(role),
    school_id = VALUES(school_id),
    approval_status = VALUES(approval_status);

INSERT INTO projects
    (user_id, school_id, category_id, title, subtitle, supervisor, supervisor_user_id, abstract_text, summary_text, is_public, is_submitted, is_approved, submitted_at, approved_at, approved_by)
VALUES
    (
        (SELECT id FROM users WHERE username = 'elev'),
        1,
        2,
        'Hållbar energianvändning i skolmiljö',
        'En fallstudie av elanvändning',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The study shows that clearer visualization of consumption and changed routines can reduce unnecessary electricity use.',
        'Studien visar att tydligare visualisering av förbrukning och ändrade rutiner kan minska onödig elanvändning.',
        1,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev1'),
        1,
        7,
        'Mikroplaster i dagvatten nära skolområdet',
        'Provtagning och analys av lokala vattenflöden',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The results show that particle levels vary clearly between sampling sites and that surfaces close to traffic produce higher values. The project discusses simple measures such as filters, better cleaning and more deliberate stormwater management.',
        'Resultaten visar att mängden partiklar varierar tydligt mellan provplatserna och att trafiknära ytor ger högre värden. Arbetet diskuterar enkla åtgärder som filter, bättre städning och mer genomtänkt dagvattenhantering.',
        1,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev2'),
        1,
        12,
        'En webbapp för planering av gymnasiestudier',
        'Prototyp med påminnelser och prioritering',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The prototype shows that clear views for week, subject and deadline make planning easier to understand. Test users felt that reminders and fewer choices per screen made the tool easier to use.',
        'Prototypen visar att tydliga vyer för vecka, ämne och deadline gör planeringen mer överskådlig. Testpersoner upplevde att påminnelser och färre val per skärm gjorde verktyget lättare att använda.',
        1,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev3'),
        1,
        15,
        'Varför chips är bättre än matte',
        'En helt ovetenskaplig undersökning',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The conclusion is that crisps feel more fun than mathematics, but the reasoning lacks delimitation, source criticism and analysis. The project is therefore submitted as an example of unserious material that needs feedback before publication.',
        'Slutsatsen är att chips känns roligare än matematik, men resonemanget saknar avgränsning, källkritik och analys. Arbetet är därför inlämnat som exempel på ett oseriöst underlag som behöver återkoppling innan publicering.',
        1,
        1,
        0,
        NOW(),
        NULL,
        NULL
    ),
    (
        (SELECT id FROM users WHERE username = 'elev4'),
        1,
        11,
        'Industrialiseringens spår i den egna kommunen',
        'En lokalhistorisk studie med arkivmaterial',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The study shows that the railway route and the establishment of smaller workshops changed both the labour market and local settlement patterns. The project connects local changes to broader Swedish industrialization processes.',
        'Studien visar att järnvägens dragning och etableringen av mindre verkstäder förändrade både arbetsmarknad och bebyggelse. Arbetet kopplar lokala förändringar till större svenska industrialiseringsprocesser.',
        0,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev5'),
        1,
        14,
        'Unga vuxnas privatekonomi efter studenten',
        'Budget, konsumtion och sparande',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The results show that many young adults underestimate recurring small expenses and lack a margin for unexpected costs. The project proposes a simple budget model that can be used in personal finance education.',
        'Resultaten visar att många underskattar återkommande småkostnader och saknar marginal för oförutsedda utgifter. Arbetet föreslår en enkel budgetmodell som kan användas i undervisning om privatekonomi.',
        1,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev6'),
        1,
        9,
        '3D-printade reservdelar i skolmiljö',
        'Hållfasthet, kostnad och användbarhet',
        'Exempel Lärare',
        (SELECT id FROM users WHERE username = 'larare'),
        'The tests show that 3D printing can work for some low-load parts, but that material choice and print orientation are decisive. The project also highlights documentation and safety limits as important before the parts are used.',
        'Tester visar att 3D-printning kan fungera för vissa låg belastade detaljer, men att materialval och utskriftsriktning är avgörande. Arbetet lyfter även dokumentation och säkerhetsgränser som viktiga innan delarna används.',
        0,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev7'),
        2,
        8,
        'C-vitaminhalt i fruktjuice över tid',
        'En jämförelse mellan färskpressad och pastöriserad juice',
        'Södra Handledare',
        (SELECT id FROM users WHERE username = 'larare_sodra'),
        'The results show that storage temperature and exposure to air clearly affect the vitamin content. Freshly pressed juice had the highest initial value but lost vitamin C faster than pasteurized juice at room temperature.',
        'Resultaten visar att förvaringstemperatur och exponering för luft påverkar halten tydligt. Färskpressad juice hade högst startvärde men tappade snabbare än pastöriserad juice vid rumstemperatur.',
        1,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare_sodra')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev8'),
        2,
        10,
        'Ungas nyhetsvanor och källkritik',
        'Sociala medier som första nyhetskälla',
        'Södra Handledare',
        (SELECT id FROM users WHERE username = 'larare_sodra'),
        'The study shows that many students quickly check sender and date, but less often follow links to the original source. The project proposes clearer teaching about reverse image search, primary sources and the influence of algorithms.',
        'Studien visar att många elever snabbt kontrollerar avsändare och datum, men mer sällan följer länkar till ursprungskällan. Arbetet föreslår tydligare undervisning kring bildsökning, primärkällor och algoritmers påverkan.',
        1,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare_sodra')
    ),
    (
        (SELECT id FROM users WHERE username = 'elev9'),
        2,
        1,
        'Språkbruk i svenska samtalspoddar',
        'Informellt tal, kodväxling och publiknärhet',
        'Södra Handledare',
        (SELECT id FROM users WHERE username = 'larare_sodra'),
        'The analysis shows that spoken-language markers, English expressions and direct address are used to create pace and a sense of community. The project discusses how the format influences norms for public language.',
        'Analysen visar att talspråkliga markörer, engelska uttryck och direkt tilltal används för att skapa tempo och gemenskap. Arbetet diskuterar hur formatet påverkar normer för offentligt språk.',
        0,
        1,
        1,
        NOW(),
        NOW(),
        (SELECT id FROM users WHERE username = 'larare_sodra')
    )
ON DUPLICATE KEY UPDATE
    school_id = VALUES(school_id),
    title = VALUES(title),
    subtitle = VALUES(subtitle),
    supervisor = VALUES(supervisor),
    supervisor_user_id = VALUES(supervisor_user_id),
    category_id = VALUES(category_id),
    abstract_text = VALUES(abstract_text),
    summary_text = VALUES(summary_text),
    is_public = VALUES(is_public),
    is_submitted = VALUES(is_submitted),
    is_approved = VALUES(is_approved),
    submitted_at = VALUES(submitted_at),
    approved_at = VALUES(approved_at),
    approved_by = VALUES(approved_by);



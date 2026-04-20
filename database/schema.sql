CREATE DATABASE IF NOT EXISTS saga_gymnasiearbeten
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_swedish_ci;

USE saga_gymnasiearbeten;

CREATE TABLE IF NOT EXISTS schools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(160) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    school_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_school
        FOREIGN KEY (school_id) REFERENCES schools(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_users_role_school (role, school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    subtitle VARCHAR(180) NULL,
    supervisor VARCHAR(120) NOT NULL,
    abstract_text TEXT NOT NULL,
    summary_text TEXT NOT NULL,
    pdf_filename VARCHAR(120) NULL,
    pdf_original_name VARCHAR(180) NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    is_submitted TINYINT(1) NOT NULL DEFAULT 0,
    submitted_at DATETIME NULL,
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
    UNIQUE KEY uq_projects_user (user_id),
    INDEX idx_projects_school_public (school_id, is_public),
    INDEX idx_projects_updated (updated_at),
    INDEX idx_projects_title (title)
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

INSERT INTO schools (id, school_name) VALUES
    (1, 'Norra Gymnasiet'),
    (2, 'Södra Gymnasiet')
ON DUPLICATE KEY UPDATE school_name = VALUES(school_name);

INSERT INTO users (id, username, password_hash, full_name, role, school_id) VALUES
    (1, 'admin', '$2y$12$mCiDoX63f4nqBGqaOEZ./.NFT/.drYtE1lWip4TILC.uDeGs0xsKu', 'Systemadministratör', 'admin', 1),
    (2, 'elev', '$2y$12$0.DUDRZdUGfuDFBT2c9OhueAJTGkWIFbd.IKyXI5kiGIaYseULKUe', 'Exempel Elev', 'student', 1),
    (3, 'larare', '$2y$12$sb.qv0WG12fcpTrumaxxk.NFPPlt5OQuObfqJzB2QspLyYOvnRymW', 'Exempel Lärare', 'teacher', 1)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    role = VALUES(role),
    school_id = VALUES(school_id);

INSERT INTO projects
    (id, user_id, school_id, title, subtitle, supervisor, abstract_text, summary_text, is_public, is_submitted, submitted_at)
VALUES
    (
        1,
        2,
        1,
        'Hållbar energianvändning i skolmiljö',
        'En fallstudie av elanvändning',
        'Exempel Lärare',
        'Arbetet undersöker hur en gymnasieskola kan kartlägga och minska sin energianvändning med enkla mätmetoder.',
        'Studien visar att tydligare visualisering av förbrukning och ändrade rutiner kan minska onödig elanvändning.',
        1,
        1,
        NOW()
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    subtitle = VALUES(subtitle),
    supervisor = VALUES(supervisor),
    abstract_text = VALUES(abstract_text),
    summary_text = VALUES(summary_text),
    is_public = VALUES(is_public),
    is_submitted = VALUES(is_submitted);



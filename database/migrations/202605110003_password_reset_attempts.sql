CREATE TABLE IF NOT EXISTS `{{prefix}}password_reset_attempts` (
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

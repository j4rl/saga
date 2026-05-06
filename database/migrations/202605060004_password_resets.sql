CREATE TABLE IF NOT EXISTS `{{prefix}}password_resets` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_{{prefix}}password_resets_user
        FOREIGN KEY (user_id) REFERENCES `{{prefix}}users`(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_password_resets_user (user_id),
    INDEX idx_password_resets_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

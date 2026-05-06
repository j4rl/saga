CREATE TABLE IF NOT EXISTS `{{prefix}}project_feedback` (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_{{prefix}}project_feedback_project
        FOREIGN KEY (project_id) REFERENCES `{{prefix}}projects`(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_{{prefix}}project_feedback_user
        FOREIGN KEY (user_id) REFERENCES `{{prefix}}users`(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_project_feedback_project (project_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

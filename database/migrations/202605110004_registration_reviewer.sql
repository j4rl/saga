ALTER TABLE `{{prefix}}users`
    ADD COLUMN IF NOT EXISTS registration_reviewer_id INT UNSIGNED NULL AFTER reviewed_at,
    ADD INDEX IF NOT EXISTS idx_users_registration_reviewer (registration_reviewer_id);

ALTER TABLE `{{prefix}}projects`
    ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER is_submitted,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER submitted_at,
    ADD COLUMN IF NOT EXISTS approved_by INT UNSIGNED NULL AFTER approved_at,
    ADD INDEX IF NOT EXISTS idx_projects_approved_by (approved_by),
    ADD INDEX IF NOT EXISTS idx_projects_public_approved (is_public, is_submitted, is_approved);

UPDATE `{{prefix}}projects`
SET is_approved = 0, approved_at = NULL, approved_by = NULL
WHERE is_submitted = 0;

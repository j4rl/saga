ALTER TABLE `{{prefix}}users`
    ADD COLUMN IF NOT EXISTS privacy_consent_at DATETIME NULL AFTER registration_reviewer_id,
    ADD COLUMN IF NOT EXISTS privacy_consent_version VARCHAR(40) NULL AFTER privacy_consent_at;

ALTER TABLE `{{prefix}}projects`
    ADD COLUMN IF NOT EXISTS publication_consent_at DATETIME NULL AFTER approved_by,
    ADD COLUMN IF NOT EXISTS publication_consent_version VARCHAR(40) NULL AFTER publication_consent_at;

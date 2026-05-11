ALTER TABLE `{{prefix}}schools`
    ADD COLUMN IF NOT EXISTS require_pdf_for_submission TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_mime;

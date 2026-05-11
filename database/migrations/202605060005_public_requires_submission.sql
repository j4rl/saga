UPDATE {{prefix}}projects
SET is_public = 0
WHERE is_submitted = 0;

<?php
declare(strict_types=1);

define('APP_NAME', 'SAGA');
define('APP_FULL_NAME', 'Svenskt Arkiv för GymnasieArbeten');

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_DIR', __DIR__);
define('INSTALL_LOCK_FILE', CONFIG_DIR . DIRECTORY_SEPARATOR . 'installed.php');
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);
define('SESSION_IDLE_TIMEOUT_SECONDS', 30 * 60);
define('AUDIT_LOG_RETENTION_DAYS', 180);
define('EMAIL_NOTIFICATION_RETENTION_DAYS', 90);
define('UPLOAD_VERSION_RETENTION_DAYS', 365);

if (is_file(INSTALL_LOCK_FILE)) {
    require_once INSTALL_LOCK_FILE;
}

$envAppBaseUrl = getenv('SAGA_APP_BASE_URL') ?: getenv('APP_BASE_URL') ?: '';
if (!defined('APP_BASE_URL') && $envAppBaseUrl !== '') {
    define('APP_BASE_URL', rtrim((string) $envAppBaseUrl, '/'));
}

define('SESSION_NAME', 'saga_session');



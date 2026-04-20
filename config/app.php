<?php
declare(strict_types=1);

define('APP_NAME', 'SAGA');
define('APP_FULL_NAME', 'Svenskt Arkiv för GymnasieArbeten');

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_DIR', __DIR__);
define('INSTALL_LOCK_FILE', CONFIG_DIR . DIRECTORY_SEPARATOR . 'installed.php');
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);

if (is_file(INSTALL_LOCK_FILE)) {
    require_once INSTALL_LOCK_FILE;
}

define('SESSION_NAME', 'saga_session');



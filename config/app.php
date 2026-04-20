<?php
declare(strict_types=1);

define('APP_NAME', 'SAGA');
define('APP_FULL_NAME', 'Svenskt Arkiv för GymnasieArbeten');

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'saga_gymnasiearbeten');

define('SESSION_NAME', 'saga_session');



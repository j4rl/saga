<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

start_secure_session();
send_security_headers();

if (!app_is_installed()) {
    redirect('index.php');
}

$conn = require __DIR__ . '/../config/database.php';

require_once __DIR__ . '/auth.php';
enforce_current_user_session($conn);
require_once __DIR__ . '/projects.php';



<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

start_secure_session();

$conn = require __DIR__ . '/../config/database.php';

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/projects.php';



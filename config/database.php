<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (!extension_loaded('mysqli')) {
    http_response_code(500);
    exit('PHP-tillägget mysqli är inte aktiverat. Aktivera mysqli i php.ini eller kör appen via XAMPP:s PHP-installation.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    exit('Kunde inte ansluta till databasen. Kontrollera config/app.php och att databasen är importerad.');
}

return $connection;



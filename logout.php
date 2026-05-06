<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    set_flash('error', 'Utloggning måste göras från formuläret i sidhuvudet.');
    redirect('index.php');
}

verify_csrf();
logout_user();
redirect('index.php');



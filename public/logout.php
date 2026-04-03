<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();
auth_logout_user();
flash_success('You have been signed out.');
redirect(BASE_URL . '/public/login.php');

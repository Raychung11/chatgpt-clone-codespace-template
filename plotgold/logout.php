<?php
require_once __DIR__ . '/inc/bootstrap.php';
csrf_enforce();
auth_logout();
flash_set(FLASH_INFO, 'You have been logged out.');
redirect('login.php');

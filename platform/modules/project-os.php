<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();

// ProjectOS lives at /projects/ — redirect immediately
header('Location: /projects/');
exit;

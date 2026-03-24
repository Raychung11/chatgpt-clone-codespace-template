<?php
require_once __DIR__ . '/functions.php';
logout_member();
header('Location: index.php');
exit;

<?php
/**
 * Application Bootstrap
 * /inc/bootstrap.php
 *
 * Include this file at the top of every entry point.
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

// Autoload core classes
spl_autoload_register(function (string $class) {
    $file = BASE_PATH . '/inc/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load helper functions
require_once BASE_PATH . '/inc/functions.php';

// Initialize application
app_init();

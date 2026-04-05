<?php
/**
 * Application Bootstrap
 * /inc/bootstrap.php
 *
 * Include this file at the top of every entry point.
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

// ─── Load .env file ────────────────────────────────────────────────────────
// Parse /path/to/.env and expose values via getenv() / $_ENV.
// Lines starting with # are comments; blank lines are skipped.
(function () {
    $envFile = BASE_PATH . '/.env';
    if (!file_exists($envFile)) return;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'"); // strip quotes

        if ($key === '') continue;

        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
})();

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

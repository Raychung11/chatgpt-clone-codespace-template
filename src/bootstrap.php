<?php
/**
 * STRate AI — Bootstrap
 * Include this at the top of every entry point (web + cron).
 */
$root = dirname(__DIR__);

require_once $root . '/config/config.php';
require_once $root . '/src/Database.php';
require_once $root . '/src/PricingEngine.php';
require_once $root . '/src/AiExplainer.php';
require_once $root . '/src/MarketData.php';
require_once $root . '/src/Scraper.php';

// Simple error handling
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

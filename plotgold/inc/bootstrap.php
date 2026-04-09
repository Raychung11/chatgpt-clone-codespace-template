<?php
/**
 * PlotGold Malaysia — Bootstrap
 * Include this at the top of every entry-point PHP file.
 */

define('PLOTGOLD', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Start session
pg_session_start();

// Load i18n — must come after session start
require_once __DIR__ . '/lang.php';

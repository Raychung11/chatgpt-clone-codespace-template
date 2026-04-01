<?php
declare(strict_types=1);

// ─── SilverDeals MY — Bootstrap (include at top of every page) ──────────────

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/points.php';

session_start_secure();

<?php
/**
 * Legacy entry-point – forward to canonical admin router.
 * /admin/login.php  →  /admin/login
 *
 * The .htaccess skips rewriting physical .php files, so this file
 * may be hit directly. Just pass through to the router URL.
 */
header('Location: /admin/login', true, 301);
exit;

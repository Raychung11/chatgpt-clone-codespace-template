<?php
// ============================================================
// BizAI Debug — upload to public_html/debug.php
// DELETE THIS FILE after you fix the issue!
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre style="font-family:monospace;font-size:13px;background:#111;color:#0f0;padding:20px">';
echo "=== BizAI Debug ===\n\n";

// 1. PHP Version
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n\n";

// 2. Check config.php loads
echo "--- config.php ---\n";
if (!file_exists(__DIR__ . '/includes/config.php')) {
    echo "ERROR: includes/config.php NOT FOUND\n";
} else {
    try {
        require_once __DIR__ . '/includes/config.php';
        echo "OK: config.php loaded\n";
        echo "SITE_NAME: " . (defined('SITE_NAME') ? SITE_NAME : 'NOT DEFINED') . "\n";
        echo "DB_HOST:   " . (defined('DB_HOST')   ? DB_HOST   : 'NOT DEFINED') . "\n";
        echo "DB_NAME:   " . (defined('DB_NAME')   ? DB_NAME   : 'NOT DEFINED') . "\n";
        echo "DB_USER:   " . (defined('DB_USER')   ? DB_USER   : 'NOT DEFINED') . "\n";
        echo "DB_PASS:   " . (defined('DB_PASS')   ? (DB_PASS === 'your_db_password' ? 'STILL DEFAULT - CHANGE IT!' : '*** set ***') : 'NOT DEFINED') . "\n";
    } catch (Throwable $e) {
        echo "ERROR loading config.php: " . $e->getMessage() . "\n";
    }
}

// 3. Test DB connection
echo "\n--- Database ---\n";
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        echo "OK: Connected to MySQL\n";
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found (" . count($tables) . "): " . implode(', ', $tables) . "\n";

        // Check key tables
        $required = ['users', 'categories', 'products', 'settings'];
        foreach ($required as $t) {
            echo "  " . (in_array($t, $tables) ? "✓" : "✗ MISSING") . " $t\n";
        }
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Hint: Check DB_HOST, DB_NAME, DB_USER, DB_PASS in includes/config.php\n";
    }
} else {
    echo "SKIP: config.php constants not defined\n";
}

// 4. Check .htaccess
echo "\n--- .htaccess ---\n";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "OK: .htaccess exists\n";
    $content = file_get_contents(__DIR__ . '/.htaccess');
    echo substr($content, 0, 300) . "\n";
} else {
    echo "WARNING: .htaccess not found\n";
}

// 5. Check index.php exists and show first error
echo "\n--- index.php ---\n";
if (!file_exists(__DIR__ . '/index.php')) {
    echo "ERROR: index.php NOT FOUND in " . __DIR__ . "\n";
} else {
    echo "OK: index.php exists (" . number_format(filesize(__DIR__ . '/index.php')) . " bytes)\n";
    // Capture any fatal error from loading it
    ob_start();
    try {
        // Reset included files so we can re-check
        $before = get_included_files();
        $source = file_get_contents(__DIR__ . '/index.php');
        // Check for PHP syntax error
        $tmpFile = tempnam(sys_get_temp_dir(), 'biz');
        file_put_contents($tmpFile, $source);
        $output = shell_exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1');
        unlink($tmpFile);
        if (str_contains($output ?? '', 'No syntax errors')) {
            echo "OK: No PHP syntax errors in index.php\n";
        } else {
            echo "SYNTAX ERROR: $output\n";
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    ob_end_clean();
}

// 6. Check includes folder
echo "\n--- includes/ folder ---\n";
$includes = ['config.php','db.php','auth.php','header.php','footer.php'];
foreach ($includes as $f) {
    $path = __DIR__ . '/includes/' . $f;
    echo (file_exists($path) ? "✓" : "✗ MISSING") . " includes/$f\n";
}

// 7. PHP extensions
echo "\n--- PHP Extensions ---\n";
$needed = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
foreach ($needed as $ext) {
    echo (extension_loaded($ext) ? "✓" : "✗ MISSING") . " $ext\n";
}

// 8. Error log (last 20 lines)
echo "\n--- PHP Error Log (last 20 lines) ---\n";
$logPaths = [
    ini_get('error_log'),
    __DIR__ . '/error_log',
    __DIR__ . '/../error_log',
    '/var/log/php_errors.log',
];
$shown = false;
foreach ($logPaths as $log) {
    if ($log && file_exists($log) && is_readable($log)) {
        $lines = array_slice(file($log), -20);
        echo "From: $log\n";
        echo htmlspecialchars(implode('', $lines));
        $shown = true;
        break;
    }
}
if (!$shown) echo "No readable error log found.\n";

echo "\n=== End Debug ===\n";
echo '</pre>';
echo '<p style="color:red;font-weight:bold;font-family:sans-serif">⚠️ DELETE debug.php after fixing the issue!</p>';

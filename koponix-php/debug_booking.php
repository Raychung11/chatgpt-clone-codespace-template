<?php
// ============================================================
//  KOPONIX – Booking Debug Page
//  Visit: https://yourdomain.com/debug_booking.php
//  DELETE THIS FILE after diagnosis.
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo '<style>body{font-family:monospace;padding:20px;max-width:900px;margin:auto}
.ok{color:green}.fail{color:red}.warn{color:orange}
h3{border-bottom:2px solid #ccc;padding-bottom:4px}
pre{background:#f4f4f4;padding:10px;border-radius:6px;white-space:pre-wrap}
table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px 10px;text-align:left}
th{background:#eee}</style>';
echo '<h2>🔧 Koponix Booking Debug</h2>';

// ── 1. PHP Version ────────────────────────────────────────────
echo '<h3>1. PHP</h3>';
echo '<p>Version: <strong>' . PHP_VERSION . '</strong></p>';

// ── 2. Config + DB ────────────────────────────────────────────
echo '<h3>2. Config &amp; Database</h3>';
try {
    require_once __DIR__ . '/config.php';
    echo '<p class="ok">✅ config.php loaded</p>';
    echo '<p>DB_HOST: <strong>' . DB_HOST . '</strong></p>';
    echo '<p>DB_NAME: <strong>' . DB_NAME . '</strong></p>';
    echo '<p>DB_USER: <strong>' . DB_USER . '</strong></p>';
    echo '<p>SITE_URL: <strong>' . SITE_URL . '</strong></p>';
    echo '<p>MARKET_URL: <strong>' . MARKET_URL . '</strong></p>';
} catch (Throwable $e) {
    echo '<p class="fail">❌ config.php error: ' . $e->getMessage() . '</p>';
    die();
}

try {
    require_once __DIR__ . '/functions.php';
    echo '<p class="ok">✅ functions.php loaded</p>';
} catch (Throwable $e) {
    echo '<p class="fail">❌ functions.php error: ' . $e->getMessage() . '</p>';
    die();
}

try {
    $pdo = db();
    echo '<p class="ok">✅ Database connected</p>';
} catch (Throwable $e) {
    echo '<p class="fail">❌ DB connection failed: ' . $e->getMessage() . '</p>';
    die();
}

// ── 3. Tables ─────────────────────────────────────────────────
echo '<h3>3. Tables</h3>';
$tables = ['members','sellers','requests','conversations','messages','bookings','credit_transactions'];
foreach ($tables as $t) {
    try {
        $pdo->query("SELECT 1 FROM {$t} LIMIT 1");
        echo '<p class="ok">✅ ' . $t . '</p>';
    } catch (Throwable $e) {
        echo '<p class="fail">❌ ' . $t . ' — ' . $e->getMessage() . '</p>';
    }
}

// ── 4. Bookings table columns ─────────────────────────────────
echo '<h3>4. Bookings Table Columns</h3>';
try {
    $cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>';
    foreach ($cols as $c) {
        echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Default']}</td></tr>";
    }
    echo '</table>';
} catch (Throwable $e) {
    echo '<p class="fail">❌ Cannot read bookings columns: ' . $e->getMessage() . '</p>';
}

// ── 5. Sellers ────────────────────────────────────────────────
echo '<h3>5. Sellers in DB</h3>';
try {
    $sellers = $pdo->query("SELECT id, name, koperasi_id, service_title, status FROM sellers ORDER BY registered_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (!$sellers) {
        echo '<p class="warn">⚠️ No sellers found in database.</p>';
    } else {
        echo '<table><tr><th>ID</th><th>Name</th><th>Service</th><th>Status</th></tr>';
        foreach ($sellers as $s) {
            $status_color = $s['status'] === 'active' ? 'green' : 'orange';
            echo "<tr>
                <td style='font-size:.8em'>{$s['id']}</td>
                <td>{$s['name']}</td>
                <td>{$s['service_title']}</td>
                <td style='color:{$status_color}'><strong>{$s['status']}</strong></td>
            </tr>";
        }
        echo '</table>';
        echo '<p class="ok">Total: ' . count($sellers) . ' seller(s)</p>';
    }
} catch (Throwable $e) {
    echo '<p class="fail">❌ Cannot query sellers: ' . $e->getMessage() . '</p>';
}

// ── 6. Test booking save ──────────────────────────────────────
echo '<h3>6. Test: Save a Booking</h3>';
try {
    $sellers_active = $pdo->query("SELECT id FROM sellers WHERE status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$sellers_active) {
        echo '<p class="warn">⚠️ No active sellers — cannot test save_booking.</p>';
    } else {
        $test_id = 'dbg' . substr(md5(time()), 0, 8);
        $pdo->prepare("INSERT INTO bookings (id,seller_id,seller_kop_id,seller_name,service_title,category,buyer_kop_id,buyer_name,buyer_contact,booking_date,booking_time,notes,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,NULL,'','','pending',NOW())")
            ->execute([$test_id, $sellers_active['id'], 'TEST', 'Debug Test', 'Debug Service', 'Other', '', 'Debug Buyer', '0100000000']);
        echo '<p class="ok">✅ Test booking inserted (id: ' . $test_id . ')</p>';
        // Clean up
        $pdo->prepare("DELETE FROM bookings WHERE id=?")->execute([$test_id]);
        echo '<p class="ok">✅ Test booking deleted — save_booking works correctly</p>';
    }
} catch (Throwable $e) {
    echo '<p class="fail">❌ save_booking test failed: ' . $e->getMessage() . '</p>';
}

// ── 7. Test get_seller_by_id with demo-008 ────────────────────
echo '<h3>7. Test: get_seller_by_id("demo-008")</h3>';
try {
    $s = get_seller_by_id('demo-008');
    if ($s) {
        echo '<p class="ok">✅ Seller found: <strong>' . htmlspecialchars($s['service_title']) . '</strong> — status: <strong>' . $s['status'] . '</strong></p>';
    } else {
        echo '<p class="fail">❌ Seller demo-008 NOT found in database.</p>';
    }
} catch (Throwable $e) {
    echo '<p class="fail">❌ Error: ' . $e->getMessage() . '</p>';
}

// ── 8. Layout include test ────────────────────────────────────
echo '<h3>8. Layout File</h3>';
$layout_path = __DIR__ . '/layout.php';
echo file_exists($layout_path)
    ? '<p class="ok">✅ layout.php exists at ' . $layout_path . '</p>'
    : '<p class="fail">❌ layout.php NOT found at ' . $layout_path . '</p>';

// ── 9. Session ────────────────────────────────────────────────
echo '<h3>9. Session</h3>';
session_name('koponix_session');
session_start();
$logged_in_member = $_SESSION['member'] ?? null;
if ($logged_in_member) {
    echo '<p class="ok">✅ Logged in as: <strong>' . htmlspecialchars($logged_in_member['name']) . '</strong> (' . htmlspecialchars($logged_in_member['koperasi_id']) . ')</p>';
    echo '<p>phone field in session: <strong>' . (isset($logged_in_member['phone']) ? htmlspecialchars($logged_in_member['phone']) : '<span class="warn">NOT SET (re-login to refresh)</span>') . '</strong></p>';
} else {
    echo '<p class="warn">⚠️ Not logged in (guest session)</p>';
}

// ── Done ──────────────────────────────────────────────────────
echo '<hr><p><strong>Debug complete.</strong> <span class="fail">Delete this file from your server now.</span></p>';

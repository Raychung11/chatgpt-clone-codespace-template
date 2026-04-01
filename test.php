<?php
// Quick environment check – DELETE this file after testing
echo '<h3>PHP is working ✓</h3>';
echo '<p>PHP version: ' . phpversion() . '</p>';
echo '<p>Document root: ' . $_SERVER['DOCUMENT_ROOT'] . '</p>';
echo '<p>Script path: ' . __FILE__ . '</p>';

// Check if public/index.php is reachable
$pubIndex = dirname(__FILE__) . '/public/index.php';
echo '<p>public/index.php exists: ' . (file_exists($pubIndex) ? '<b style="color:green">YES</b>' : '<b style="color:red">NO</b>') . '</p>';

// Check config
$cfg = dirname(__FILE__) . '/config/db.php';
echo '<p>config/db.php exists: ' . (file_exists($cfg) ? '<b style="color:green">YES</b>' : '<b style="color:red">NO</b>') . '</p>';

// Check mod_rewrite
echo '<p>mod_rewrite loaded: ' . (in_array('mod_rewrite', apache_get_modules()) ? '<b style="color:green">YES</b>' : '<b style="color:red">NO – contact Hostinger support</b>') . '</p>';

echo '<hr><p><b>Next steps:</b><br>1. Fix any red items above<br>2. Delete this file<br>3. Visit <a href="/app/">/app/</a></p>';

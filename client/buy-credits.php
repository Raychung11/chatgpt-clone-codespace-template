<?php
declare(strict_types=1);

/**
 * client/buy-credits.php
 * Credit purchase page: select a package, upload bank transfer receipt.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/mailer.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];

// ── Load active packages ──────────────────────────────────────────────────────
$pkgs = db()->query(
    'SELECT * FROM `credit_packages` WHERE `is_active` = 1 ORDER BY `sort_order`, `price`'
)->fetchAll();

// ── Bank info from settings ───────────────────────────────────────────────────
$bankName    = setting('bank_name',           'Maybank');
$bankAcc     = setting('bank_account_number', '—');
$bankHolder  = setting('bank_account_name',   '—');
$currency    = setting('currency',            'MYR');

// ── Handle form submission ────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $pkg_id = (int)($_POST['package_id'] ?? 0);
    $pkg    = null;

    foreach ($pkgs as $p) {
        if ((int)$p['id'] === $pkg_id) { $pkg = $p; break; }
    }

    if (!$pkg) {
        $errors['package'] = 'Please select a valid package.';
    }

    // File upload
    $file = $_FILES['receipt'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['receipt'] = 'Please upload your payment receipt.';
    }

    if (empty($errors)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Create order
            $stmt = $pdo->prepare(
                'INSERT INTO `payment_orders`
                 (`user_id`,`package_id`,`amount`,`currency`,`credits`,`payment_method`)
                 VALUES (?,?,?,?,?,"bank_transfer")'
            );
            $stmt->execute([$uid, $pkg['id'], $pkg['price'], $currency, $pkg['credits']]);
            $order_id = (int)$pdo->lastInsertId();

            // Upload receipt
            $upload = upload_receipt($file, $order_id);
            if (!$upload['ok']) {
                $pdo->rollBack();
                $errors['receipt'] = $upload['error'];
            } else {
                $pdo->prepare(
                    'INSERT INTO `payment_receipts`
                     (`payment_order_id`,`file_path`,`original_name`,`file_size`,`mime_type`)
                     VALUES (?,?,?,?,?)'
                )->execute([
                    $order_id,
                    $upload['path'],
                    $upload['name'],
                    $upload['size'],
                    $upload['mime'],
                ]);

                $pdo->commit();
                log_activity('user', $uid, 'payment_order_created', 'Order #' . $order_id . ' submitted');

                // Send confirmation email
                mail_payment_received($user['email'], $user['name'], (float)$pkg['price'], (float)$pkg['credits'], $order_id);

                flash_success('Payment submitted! Your credits will be added once the admin approves your payment (usually within 1 business day).');
                redirect(BASE_URL . '/client/wallet.php');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[buy-credits] ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

$balance = wallet_balance($uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Credits — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_client_navbar($user, 'wallet'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Buy Credits</h1>
            <p class="page-sub">Top up your wallet to generate videos</p>
        </div>
        <span class="navbar-wallet">⚡ <?= e(format_credits($balance)) ?> credits</span>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert--error"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <!-- Package selection -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Select a Package</span></div>

            <?php if (!empty($errors['package'])): ?>
                <div class="alert alert--error"><?= e($errors['package']) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
                <?php foreach ($pkgs as $pkg): ?>
                    <label style="cursor:pointer">
                        <input type="radio" name="package_id" value="<?= (int)$pkg['id'] ?>"
                               style="display:none" class="pkg-radio"
                               <?= (isset($_POST['package_id']) && (int)$_POST['package_id'] === (int)$pkg['id']) ? 'checked' : '' ?>>
                        <div class="pkg-card card" style="transition:border-color .2s,box-shadow .2s;<?= $pkg['is_popular'] ? 'border-color:var(--color-primary)' : '' ?>">
                            <?php if ($pkg['is_popular']): ?>
                                <div class="badge badge-primary" style="margin-bottom:8px">Most Popular</div>
                            <?php endif; ?>
                            <div style="font-size:1.1rem;font-weight:700"><?= e($pkg['name']) ?></div>
                            <div style="font-size:2rem;font-weight:900;color:var(--color-accent);margin:8px 0">
                                <?= e(format_credits((float)$pkg['credits'])) ?>
                                <span style="font-size:.9rem;color:var(--color-muted);font-weight:400">credits</span>
                            </div>
                            <div style="font-size:1.1rem;font-weight:700;color:var(--color-text)">
                                <?= e(format_currency((float)$pkg['price'], $currency)) ?>
                            </div>
                            <?php if ($pkg['description']): ?>
                                <div class="text-muted text-sm mt-2"><?= e($pkg['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bank transfer info -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Bank Transfer Details</span></div>
            <p class="text-muted text-sm mb-3">Transfer the exact package amount to the account below, then upload your receipt.</p>
            <table style="font-size:.95rem">
                <tr><td style="padding:6px 12px 6px 0;color:var(--color-muted)">Bank</td><td style="font-weight:700"><?= e($bankName) ?></td></tr>
                <tr><td style="padding:6px 12px 6px 0;color:var(--color-muted)">Account Number</td><td style="font-weight:700"><?= e($bankAcc) ?></td></tr>
                <tr><td style="padding:6px 12px 6px 0;color:var(--color-muted)">Account Name</td><td style="font-weight:700"><?= e($bankHolder) ?></td></tr>
            </table>
        </div>

        <!-- Receipt upload -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Upload Payment Receipt</span></div>

            <?php if (!empty($errors['receipt'])): ?>
                <div class="alert alert--error"><?= e($errors['receipt']) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="receipt">Receipt File</label>
                <input type="file" id="receipt" name="receipt" class="form-control"
                       accept=".jpg,.jpeg,.png,.pdf" required>
                <div class="form-hint">Accepted: JPEG, PNG, PDF. Max 5 MB.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">Submit Payment</button>
        </div>

    </form>

</div>

<script>
// Highlight selected package card
document.querySelectorAll('.pkg-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.pkg-card').forEach(c => {
            c.style.borderColor = '';
            c.style.boxShadow   = '';
        });
        const card = radio.nextElementSibling;
        card.style.borderColor = 'var(--color-primary)';
        card.style.boxShadow   = '0 0 0 3px rgba(108,71,255,.3)';
    });
    // Init on page load
    if (radio.checked) {
        const card = radio.nextElementSibling;
        card.style.borderColor = 'var(--color-primary)';
        card.style.boxShadow   = '0 0 0 3px rgba(108,71,255,.3)';
    }
});
</script>
</body>
</html>

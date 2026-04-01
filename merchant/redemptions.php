<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MERCHANT);

$user = auth_user();
$merchant = null;
try {
    $stmt = db()->prepare("SELECT * FROM merchants WHERE user_id=? LIMIT 1");
    $stmt->execute([$user['id']]);
    $merchant = $stmt->fetch();
} catch (PDOException $e) { error_log('[Merchant redemptions] '.$e->getMessage()); }

if (!$merchant) redirect('/merchant/dashboard.php');
$mid = $merchant['id'];

$page_title = 'Validate Vouchers';
$active_nav = 'redemptions';

$validated  = null;
$scan_error = '';

// ─── POST: validate voucher ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_code'])) {
    csrf_abort();
    $code = strtoupper(trim($_POST['voucher_code'] ?? ''));

    if (!$code) {
        $scan_error = 'Please enter a voucher code.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT r.*, d.title AS deal_title, d.deal_price, d.commission_pct,
                       u.name AS member_name, u.phone AS member_phone,
                       mc.tier AS member_tier
                FROM redemptions r
                JOIN deals d ON d.id = r.deal_id
                JOIN users u ON u.id = r.user_id
                LEFT JOIN member_cards mc ON mc.user_id = r.user_id AND mc.is_active=1
                WHERE r.voucher_code = ? AND r.merchant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$code, $mid]);
            $redemption = $stmt->fetch();

            if (!$redemption) {
                $scan_error = 'Voucher code not found or does not belong to this merchant.';
            } elseif ($redemption['status'] === 'used') {
                $scan_error = 'This voucher has already been used on ' . date('d M Y g:ia', strtotime($redemption['redeemed_at'])) . '.';
            } elseif ($redemption['status'] === 'expired') {
                $scan_error = 'This voucher has expired.';
            } elseif ($redemption['status'] === 'cancelled') {
                $scan_error = 'This voucher has been cancelled.';
            } elseif ($redemption['expires_at'] && strtotime($redemption['expires_at']) < time()) {
                // Auto-expire
                $pdo->prepare("UPDATE redemptions SET status='expired' WHERE id=?")->execute([$redemption['id']]);
                $scan_error = 'This voucher expired on ' . date('d M Y', strtotime($redemption['expires_at'])) . '.';
            } elseif ($redemption['status'] === 'active') {
                if (isset($_POST['confirm_validate'])) {
                    // Actually validate
                    $pdo->beginTransaction();

                    $pdo->prepare("UPDATE redemptions SET status='used', redeemed_at=NOW(), verified_by=?, redeemed_at_branch=? WHERE id=?")
                        ->execute([$user['id'], null, $redemption['id']]);

                    // Commission calc
                    if ($redemption['deal_price']) {
                        $commPct = (float)($merchant['commission_pct'] ?? 5.00);
                        $commAmt = round((float)$redemption['deal_price'] * $commPct / 100, 2);
                        $pdo->prepare("INSERT INTO commissions(merchant_id,redemption_id,deal_price,commission_pct,commission_amt) VALUES(?,?,?,?,?)")
                            ->execute([$mid, $redemption['id'], $redemption['deal_price'], $commPct, $commAmt]);
                    }

                    // Award member points for redemption
                    $pdo->prepare("UPDATE deals SET redemption_count = redemption_count+1 WHERE id=?")
                        ->execute([$redemption['deal_id']]);

                    $pdo->commit();

                    // Award redemption points to member
                    require_once __DIR__ . '/../inc/points.php';
                    points_add((int)$redemption['user_id'], POINTS_FIRST_REDEMPTION_BONUS, 'redemption',
                        'Points for redeeming: ' . $redemption['deal_title'], (int)$redemption['id']);

                    notify((int)$redemption['user_id'],
                        'Voucher Used! 🎉',
                        'Your voucher for "' . $redemption['deal_title'] . '" was validated. You earned ' . POINTS_FIRST_REDEMPTION_BONUS . ' SilverPoints!',
                        'reward', (int)$redemption['id'], 'redemption');

                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'merchant.validate_voucher','redemption',?,?)")
                        ->execute([$user['id'], $redemption['id'], $_SERVER['REMOTE_ADDR']??null]);

                    $validated = $redemption;
                    $validated['validated_now'] = true;
                    $scan_error = '';
                } else {
                    // Show confirmation step
                    $validated = $redemption;
                    $validated['validated_now'] = false;
                }
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[Validate voucher] '.$e->getMessage());
            $scan_error = 'Validation failed due to a server error. Please try again.';
        }
    }
}

// ─── Redemption history list ─────────────────────────────────────────────
$per_page = 20; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;
$filter   = in_array($_GET['status']??'',['all','active','used','expired']) ? $_GET['status'] : 'all';
$history  = []; $total = 0;
try {
    $pdo = db();
    $wc  = ["r.merchant_id=?"]; $wp = [$mid];
    if ($filter!=='all') { $wc[]="r.status=?"; $wp[]=$filter; }
    $ws = 'WHERE '.implode(' AND ',$wc);
    $st = $pdo->prepare("SELECT COUNT(*) FROM redemptions r {$ws}"); $st->execute($wp); $total=(int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT r.*,d.title AS deal_title,u.name AS member_name FROM redemptions r JOIN deals d ON d.id=r.deal_id JOIN users u ON u.id=r.user_id {$ws} ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
    $st->execute(array_merge($wp,[$per_page,$offset]));
    $history = $st->fetchAll();
} catch (PDOException $e) { error_log('[Merchant redemption list] '.$e->getMessage()); }
$total_pages = (int)ceil($total/$per_page);

include __DIR__ . '/../inc/merchant_layout.php';
?>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- Scan / validate box -->
  <div>
    <h3 style="margin-bottom:var(--space-md);">🔍 Validate Member Voucher</h3>

    <?php if ($validated && $validated['validated_now']): ?>
      <!-- Success state -->
      <div class="card" style="padding:var(--space-xl);text-align:center;border:3px solid var(--success);">
        <div style="font-size:64px;margin-bottom:var(--space-md);">✅</div>
        <h2 style="color:var(--success);margin-bottom:var(--space-sm);">Voucher Validated!</h2>
        <p style="font-size:18px;margin-bottom:var(--space-lg);">Deal successfully redeemed by:</p>
        <div style="background:var(--success-bg);border-radius:var(--radius-md);padding:var(--space-lg);margin-bottom:var(--space-lg);text-align:left;">
          <div style="font-size:18px;font-weight:700;"><?= e($validated['member_name']) ?></div>
          <div style="font-size:15px;color:var(--text-muted);"><?= e($validated['member_phone']) ?></div>
          <div style="margin-top:var(--space-sm);font-size:15px;">Deal: <strong><?= e($validated['deal_title']) ?></strong></div>
          <div style="font-size:14px;color:var(--text-muted);margin-top:4px;">Code: <?= e($validated['voucher_code']) ?></div>
        </div>
        <button onclick="location.href='/merchant/redemptions.php'" class="btn btn--primary btn--full">Validate Another Voucher</button>
      </div>

    <?php elseif ($validated && !$validated['validated_now']): ?>
      <!-- Confirmation step -->
      <div class="card" style="padding:var(--space-xl);border:3px solid var(--orange-primary);">
        <div style="font-size:48px;text-align:center;margin-bottom:var(--space-md);">🎫</div>
        <h3 style="text-align:center;margin-bottom:var(--space-lg);">Confirm Redemption</h3>
        <div style="background:var(--orange-bg);border-radius:var(--radius-md);padding:var(--space-lg);margin-bottom:var(--space-xl);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-sm);">
            <span style="color:var(--text-muted);">Member</span>
            <span style="font-weight:700;"><?= e($validated['member_name']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-sm);">
            <span style="color:var(--text-muted);">Phone</span>
            <span style="font-weight:700;"><?= e($validated['member_phone']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-sm);">
            <span style="color:var(--text-muted);">Deal</span>
            <span style="font-weight:700;"><?= e($validated['deal_title']) ?></span>
          </div>
          <?php if ($validated['deal_price']): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-sm);">
            <span style="color:var(--text-muted);">Value</span>
            <span style="font-weight:700;color:var(--orange-primary);"><?= format_myr((float)$validated['deal_price']) ?></span>
          </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="color:var(--text-muted);">Code</span>
            <code style="font-size:15px;font-weight:700;"><?= e($validated['voucher_code']) ?></code>
          </div>
        </div>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="voucher_code" value="<?= e($validated['voucher_code']) ?>">
          <input type="hidden" name="confirm_validate" value="1">
          <button type="submit" class="btn btn--primary btn--full btn--lg" style="margin-bottom:var(--space-sm);">✅ Confirm — Mark as Used</button>
        </form>
        <a href="/merchant/redemptions.php" class="btn btn--muted btn--full">Cancel</a>
      </div>

    <?php else: ?>
      <!-- Input form -->
      <div class="card" style="padding:var(--space-xl);">
        <?php if ($scan_error): ?>
          <div class="alert alert--error" style="margin-bottom:var(--space-lg);"><span class="alert__icon">✕</span><span><?= e($scan_error) ?></span></div>
        <?php endif; ?>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label" for="voucher_code" style="font-size:18px;">Enter Voucher Code</label>
            <input type="text" id="voucher_code" name="voucher_code" class="form-control"
                   style="font-size:24px;letter-spacing:.1em;text-align:center;font-weight:700;"
                   placeholder="e.g. SD3F8A2C" autocomplete="off" autocorrect="off" autocapitalize="characters"
                   autofocus required>
            <div class="form-hint">Enter the code from the member's voucher or scan their QR code.</div>
          </div>
          <button type="submit" class="btn btn--primary btn--full btn--lg">🔍 Look Up Voucher</button>
        </form>

        <div style="margin-top:var(--space-xl);padding:var(--space-lg);background:var(--bg-light);border-radius:var(--radius-md);">
          <div style="font-weight:700;margin-bottom:var(--space-sm);">📱 QR Scan (Coming Soon)</div>
          <div style="font-size:14px;color:var(--text-muted);">In a future update, you'll be able to scan member QR codes directly from this page using your device camera.</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Redemption history -->
  <div>
    <div class="flex-between" style="margin-bottom:var(--space-md);">
      <h3>📋 Redemption History</h3>
      <div class="pill-list">
        <?php foreach (['all'=>'All','active'=>'Active','used'=>'Used','expired'=>'Expired'] as $k=>$l): ?>
          <a href="?status=<?= $k ?>" class="pill <?= $filter===$k?'active':'' ?>" style="padding:6px 14px;font-size:13px;"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!empty($history)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Member</th><th>Deal</th><th>Code</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($history as $r): ?>
                <tr>
                  <td style="font-size:14px;font-weight:600;"><?= e($r['member_name']) ?></td>
                  <td style="font-size:13px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['deal_title']) ?></td>
                  <td><code style="font-size:12px;"><?= e($r['voucher_code']) ?></code></td>
                  <td><span class="badge badge--<?= match($r['status']){'active'=>'success','used'=>'muted','expired'=>'error',default=>'muted'} ?>" style="font-size:11px;"><?= ucfirst($r['status']) ?></span></td>
                  <td style="font-size:12px;color:var(--text-muted);"><?= time_ago($r['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top:var(--space-md);justify-content:center;">
          <?php if ($page_num>1): ?><a href="?status=<?= $filter ?>&page=<?= $page_num-1 ?>" class="pagination__btn">← Prev</a><?php endif; ?>
          <?php for($i=max(1,$page_num-2);$i<=min($total_pages,$page_num+2);$i++): ?><a href="?status=<?= $filter ?>&page=<?= $i ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
          <?php if ($page_num<$total_pages): ?><a href="?status=<?= $filter ?>&page=<?= $page_num+1 ?>" class="pagination__btn">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-xl);">
        <div class="empty-state__icon">🎫</div>
        <h4 class="empty-state__title">No redemptions yet</h4>
        <p class="empty-state__text">Once members claim your deals, vouchers will appear here.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../inc/merchant_layout_end.php'; ?>

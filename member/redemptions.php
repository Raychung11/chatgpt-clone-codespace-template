<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'My Vouchers — SilverDeals MY';
$active_nav = 'redemptions';

// ─── Filters ──────────────────────────────────────────────────────────────
$filter   = in_array($_GET['status'] ?? '', ['all','active','used','expired','cancelled']) ? $_GET['status'] : 'all';
$per_page = 12;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

// ─── Single voucher detail view ───────────────────────────────────────────
$view_voucher = null;
if (isset($_GET['code'])) {
    try {
        $stmt = db()->prepare("
            SELECT r.*,d.title AS deal_title,d.short_desc,d.deal_price,d.terms,
                   m.business_name AS merchant_name,m.address AS merchant_address,m.phone AS merchant_phone,
                   dc.name AS category,dc.icon AS cat_icon,
                   di.image_path AS deal_image
            FROM redemptions r
            JOIN deals d ON d.id=r.deal_id
            JOIN merchants m ON m.id=r.merchant_id
            LEFT JOIN deal_categories dc ON dc.id=d.category_id
            LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1
            WHERE r.voucher_code=? AND r.user_id=?
            LIMIT 1
        ");
        $stmt->execute([strtoupper(trim($_GET['code'])), $user['id']]);
        $view_voucher = $stmt->fetch();
    } catch (PDOException $e) { error_log('[Member voucher detail] '.$e->getMessage()); }
}

// ─── Redemptions list ─────────────────────────────────────────────────────
$redemptions = []; $total = 0;
try {
    $pdo = db();
    $wc  = ["r.user_id=?"]; $wp = [$user['id']];
    if ($filter !== 'all') { $wc[] = "r.status=?"; $wp[] = $filter; }
    $ws = 'WHERE ' . implode(' AND ', $wc);

    $st = $pdo->prepare("SELECT COUNT(*) FROM redemptions r {$ws}");
    $st->execute($wp); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT r.*,d.title AS deal_title,d.slug AS deal_slug,d.deal_price,
               m.business_name AS merchant_name,
               dc.icon AS cat_icon,
               di.image_path AS deal_image
        FROM redemptions r
        JOIN deals d ON d.id=r.deal_id
        JOIN merchants m ON m.id=r.merchant_id
        LEFT JOIN deal_categories dc ON dc.id=d.category_id
        LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1
        {$ws} ORDER BY r.created_at DESC LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($wp, [$per_page, $offset]));
    $redemptions = $st->fetchAll();
} catch (PDOException $e) { error_log('[Member redemptions list] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

// Status counts for filter tabs
$status_counts = ['all' => 0, 'active' => 0, 'used' => 0, 'expired' => 0];
try {
    $st = db()->prepare("SELECT status, COUNT(*) AS cnt FROM redemptions WHERE user_id=? GROUP BY status");
    $st->execute([$user['id']]);
    foreach ($st->fetchAll() as $row) {
        $status_counts['all'] += $row['cnt'];
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']] = $row['cnt'];
        }
    }
} catch (PDOException) {}

include __DIR__ . '/../inc/member_layout.php';
?>

<?php if ($view_voucher): ?>
<!-- ─── Single Voucher Detail View ──────────────────────────────────────── -->
<div style="margin-bottom:var(--space-xl);">
  <a href="/member/redemptions.php" style="color:var(--text-muted);font-size:14px;text-decoration:none;">← Back to My Vouchers</a>
</div>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- Voucher card -->
  <div>
    <?php $vc = $view_voucher; ?>
    <div class="card" style="overflow:hidden;border:3px solid <?= match($vc['status']){'active'=>'var(--orange-primary)','used'=>'var(--text-muted)','expired'=>'var(--error)',default=>'var(--border-color)'} ?>;">

      <!-- Header -->
      <div style="padding:var(--space-lg);background:<?= $vc['status']==='active' ? 'linear-gradient(135deg,var(--orange-primary),var(--orange-secondary))' : 'var(--bg-light)' ?>;text-align:center;">
        <?php if ($vc['status'] === 'active'): ?>
          <div style="font-size:48px;margin-bottom:var(--space-sm);">🎫</div>
          <div style="color:#fff;font-size:13px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;margin-bottom:var(--space-xs);">Active Voucher</div>
          <div style="color:#fff;font-size:28px;font-weight:800;letter-spacing:.15em;font-family:monospace;"><?= e($vc['voucher_code']) ?></div>
        <?php elseif ($vc['status'] === 'used'): ?>
          <div style="font-size:48px;margin-bottom:var(--space-sm);">✅</div>
          <div style="color:var(--text-muted);font-size:13px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Used Voucher</div>
          <div style="color:var(--text-dark);font-size:24px;font-weight:700;font-family:monospace;opacity:.6;"><?= e($vc['voucher_code']) ?></div>
        <?php else: ?>
          <div style="font-size:48px;margin-bottom:var(--space-sm);">⏰</div>
          <div style="color:var(--error);font-size:13px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;">Expired Voucher</div>
          <div style="color:var(--text-muted);font-size:24px;font-weight:700;font-family:monospace;text-decoration:line-through;"><?= e($vc['voucher_code']) ?></div>
        <?php endif; ?>
      </div>

      <div style="padding:var(--space-xl);">

        <!-- Deal info -->
        <div style="margin-bottom:var(--space-xl);">
          <?php if ($vc['deal_image']): ?>
            <img src="<?= e($vc['deal_image']) ?>" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:var(--space-md);">
          <?php endif; ?>
          <h3 style="margin-bottom:var(--space-xs);"><?= e($vc['deal_title']) ?></h3>
          <?php if ($vc['short_desc']): ?>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:var(--space-sm);"><?= e($vc['short_desc']) ?></p>
          <?php endif; ?>
          <?php if ($vc['deal_price']): ?>
            <div style="font-size:22px;font-weight:700;color:var(--orange-primary);"><?= format_myr((float)$vc['deal_price']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Merchant info -->
        <div style="background:var(--bg-light);border-radius:var(--radius-md);padding:var(--space-md);margin-bottom:var(--space-xl);">
          <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:var(--space-sm);">Merchant</div>
          <div style="font-weight:700;font-size:16px;"><?= e($vc['merchant_name']) ?></div>
          <?php if ($vc['merchant_address']): ?>
            <div style="font-size:14px;color:var(--text-muted);margin-top:4px;"><?= e($vc['merchant_address']) ?></div>
          <?php endif; ?>
          <?php if ($vc['merchant_phone']): ?>
            <div style="margin-top:var(--space-sm);">
              <a href="tel:<?= e($vc['merchant_phone']) ?>" class="btn btn--muted btn--sm">📞 <?= e($vc['merchant_phone']) ?></a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Dates -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);margin-bottom:var(--space-xl);">
          <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Claimed On</div>
            <div style="font-weight:600;"><?= date('d M Y', strtotime($vc['created_at'])) ?></div>
          </div>
          <?php if ($vc['expires_at']): ?>
          <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Expires</div>
            <div style="font-weight:600;color:<?= $vc['status']==='expired' ? 'var(--error)' : (strtotime($vc['expires_at'])<strtotime('+7 days')&&$vc['status']==='active' ? 'var(--error)' : 'inherit') ?>;">
              <?= date('d M Y', strtotime($vc['expires_at'])) ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($vc['redeemed_at']): ?>
          <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Used On</div>
            <div style="font-weight:600;"><?= date('d M Y g:ia', strtotime($vc['redeemed_at'])) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($vc['points_used'] > 0): ?>
          <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Points Used</div>
            <div style="font-weight:600;color:var(--orange-primary);"><?= format_points((int)$vc['points_used']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($vc['terms']): ?>
          <div style="font-size:13px;color:var(--text-muted);background:var(--bg-light);border-radius:var(--radius-md);padding:var(--space-md);">
            <div style="font-weight:700;margin-bottom:var(--space-xs);">Terms & Conditions</div>
            <div><?= nl2br(e($vc['terms'])) ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- QR code panel -->
  <div>
    <?php if ($vc['status'] === 'active'): ?>
      <div class="card" style="padding:var(--space-xl);text-align:center;">
        <h4 style="margin-bottom:var(--space-sm);">Show to Merchant</h4>
        <p style="font-size:14px;color:var(--text-muted);margin-bottom:var(--space-lg);">Ask the merchant to scan or enter your voucher code.</p>

        <div id="qr-code" style="display:inline-block;padding:var(--space-md);background:#fff;border:2px solid var(--orange-primary);border-radius:var(--radius-md);margin-bottom:var(--space-lg);"></div>

        <div style="font-family:monospace;font-size:22px;font-weight:800;letter-spacing:.1em;background:var(--orange-bg);padding:var(--space-md);border-radius:var(--radius-md);margin-bottom:var(--space-lg);color:var(--orange-dark);">
          <?= e($vc['voucher_code']) ?>
        </div>

        <div style="font-size:13px;color:var(--text-muted);">
          Valid until: <?= $vc['expires_at'] ? date('d M Y', strtotime($vc['expires_at'])) : 'No expiry' ?>
        </div>
      </div>

      <div class="card" style="padding:var(--space-lg);margin-top:var(--space-md);">
        <div style="font-weight:700;margin-bottom:var(--space-sm);">How to Use</div>
        <ol style="font-size:14px;color:var(--text-muted);padding-left:var(--space-lg);line-height:1.8;">
          <li>Visit the merchant location</li>
          <li>Show your QR code or voucher code</li>
          <li>Ask staff to scan or enter the code</li>
          <li>Enjoy your deal!</li>
        </ol>
      </div>

    <?php elseif ($vc['status'] === 'used'): ?>
      <div class="card" style="padding:var(--space-xl);text-align:center;border:2px solid var(--success);">
        <div style="font-size:64px;margin-bottom:var(--space-md);">✅</div>
        <h3 style="color:var(--success);margin-bottom:var(--space-sm);">Voucher Used!</h3>
        <p style="color:var(--text-muted);">This voucher was successfully redeemed<?= $vc['redeemed_at'] ? ' on ' . date('d M Y', strtotime($vc['redeemed_at'])) : '' ?>.</p>
        <div style="margin-top:var(--space-lg);">
          <a href="/member/deals.php" class="btn btn--primary">Browse More Deals</a>
        </div>
      </div>
    <?php else: ?>
      <div class="card" style="padding:var(--space-xl);text-align:center;border:2px solid var(--error);">
        <div style="font-size:64px;margin-bottom:var(--space-md);">⏰</div>
        <h3 style="color:var(--error);margin-bottom:var(--space-sm);">Voucher Expired</h3>
        <p style="color:var(--text-muted);">This voucher expired on <?= $vc['expires_at'] ? date('d M Y', strtotime($vc['expires_at'])) : 'an unknown date' ?>.</p>
        <div style="margin-top:var(--space-lg);">
          <a href="/member/deals.php" class="btn btn--primary">Find New Deals</a>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php if ($vc['status'] === 'active'): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSe2keI06RokmYfcoYWYbGr7AEDn" crossorigin="anonymous"></script>
<script>
new QRCode(document.getElementById('qr-code'), {
    text: '<?= addslashes($vc['voucher_code']) ?>',
    width: 200, height: 200,
    colorDark: '#FF6B00', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
});
</script>
<?php endif; ?>

<?php else: ?>
<!-- ─── Voucher List View ─────────────────────────────────────────────────── -->

<div style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-xs);">🎫 My Vouchers</h2>
  <p style="color:var(--text-muted);font-size:16px;">All deals you've claimed. Show voucher codes to merchants when redeeming.</p>
</div>

<!-- Filter tabs -->
<div class="pill-list" style="margin-bottom:var(--space-xl);">
  <?php foreach (['all' => 'All', 'active' => 'Active', 'used' => 'Used', 'expired' => 'Expired'] as $k => $label): ?>
    <a href="?status=<?= $k ?>" class="pill <?= $filter === $k ? 'active' : '' ?>">
      <?= $label ?>
      <?php if ($status_counts[$k] > 0): ?>
        <span style="background:<?= $k==='active'?'var(--orange-primary)':'var(--text-muted)' ?>;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;"><?= $status_counts[$k] ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!empty($redemptions)): ?>
  <div class="grid grid-3" style="margin-bottom:var(--space-xl);">
    <?php foreach ($redemptions as $r): ?>
      <a href="/member/redemptions.php?code=<?= urlencode($r['voucher_code']) ?>" style="text-decoration:none;color:inherit;">
        <div class="card" style="border:2px solid <?= match($r['status']){'active'=>'var(--orange-primary)','used'=>'var(--border-color)','expired'=>'var(--error)',default=>'var(--border-color)'} ?>;transition:transform .15s;cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">

          <?php if ($r['deal_image']): ?>
            <img src="<?= e($r['deal_image']) ?>" alt="" class="card__image" style="height:100px;object-fit:cover;">
          <?php else: ?>
            <div class="card__image" style="height:80px;display:flex;align-items:center;justify-content:center;background:var(--orange-bg);font-size:32px;">
              <?= e($r['cat_icon'] ?? '🎁') ?>
            </div>
          <?php endif; ?>

          <div class="card__body" style="padding:var(--space-md);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:var(--space-xs);">
              <h5 style="font-size:14px;margin:0;flex:1;padding-right:var(--space-sm);line-height:1.3;"><?= e($r['deal_title']) ?></h5>
              <span class="badge badge--<?= match($r['status']){'active'=>'success','used'=>'muted','expired'=>'error',default=>'muted'} ?>" style="font-size:10px;flex-shrink:0;"><?= ucfirst($r['status']) ?></span>
            </div>

            <div style="font-size:12px;color:var(--text-muted);margin-bottom:var(--space-sm);"><?= e($r['merchant_name']) ?></div>

            <?php if ($r['deal_price']): ?>
              <div style="font-size:15px;font-weight:700;color:var(--orange-primary);margin-bottom:var(--space-sm);"><?= format_myr((float)$r['deal_price']) ?></div>
            <?php endif; ?>

            <div style="display:flex;align-items:center;justify-content:space-between;">
              <code style="font-size:12px;font-weight:700;color:<?= $r['status']==='active'?'var(--orange-primary)':'var(--text-muted)' ?>;"><?= e($r['voucher_code']) ?></code>
              <?php if ($r['status'] === 'active'): ?>
                <span style="font-size:11px;color:var(--orange-primary);font-weight:600;">View →</span>
              <?php endif; ?>
            </div>

            <?php if ($r['expires_at'] && $r['status'] === 'active'): ?>
              <div style="font-size:11px;margin-top:var(--space-xs);color:<?= strtotime($r['expires_at'])<strtotime('+7 days')?'var(--error)':'var(--text-muted)' ?>;">
                <?= strtotime($r['expires_at'])<strtotime('+7 days') ? '⏰ ' : '' ?>Exp <?= date('d M Y', strtotime($r['expires_at'])) ?>
              </div>
            <?php elseif ($r['redeemed_at']): ?>
              <div style="font-size:11px;margin-top:var(--space-xs);color:var(--text-muted);">Used <?= time_ago($r['redeemed_at']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="justify-content:center;">
      <?php if ($page_num > 1): ?><a href="?status=<?= $filter ?>&page=<?= $page_num-1 ?>" class="pagination__btn">← Prev</a><?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?><a href="?status=<?= $filter ?>&page=<?= $i ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
      <?php if ($page_num < $total_pages): ?><a href="?status=<?= $filter ?>&page=<?= $page_num+1 ?>" class="pagination__btn">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">🎫</div>
    <h3 class="empty-state__title">
      <?= $filter === 'all' ? 'No vouchers yet' : 'No ' . $filter . ' vouchers' ?>
    </h3>
    <p class="empty-state__text">
      <?= $filter === 'all' ? 'Browse deals and claim your first voucher to get started!' : 'All clear here.' ?>
    </p>
    <a href="/member/deals.php" class="btn btn--primary">Browse Deals</a>
  </div>
<?php endif; ?>

<?php endif; // end single vs list view ?>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>

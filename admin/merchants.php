<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require_admin();

$user       = auth_user();
$page_title = 'Manage Merchants';
$active_nav = 'merchants';

// ─── POST actions ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action      = $_POST['action'] ?? '';
    $merchant_id = (int)($_POST['merchant_id'] ?? 0);

    if ($merchant_id > 0) {
        try {
            $pdo = db();
            // Fetch merchant for notifications
            $mrow = $pdo->prepare("SELECT m.*,u.id AS user_id FROM merchants m JOIN users u ON u.id=m.user_id WHERE m.id=? LIMIT 1");
            $mrow->execute([$merchant_id]);
            $merchant_row = $mrow->fetch();

            switch ($action) {
                case 'approve':
                    $pdo->prepare("UPDATE merchants SET status='active',updated_at=NOW() WHERE id=?")->execute([$merchant_id]);
                    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$merchant_row['user_id']]);
                    if ($merchant_row) {
                        notify((int)$merchant_row['user_id'],
                            'Merchant Application Approved! ✅',
                            'Welcome to SilverDeals MY! You can now log in and start adding deals.',
                            'system', $merchant_id, 'merchant');
                    }
                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.approve_merchant','merchant',?,?)")
                        ->execute([$user['id'], $merchant_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                    auth_set_flash('success', 'Merchant approved successfully.');
                    break;

                case 'reject':
                    $reason = trim($_POST['reason'] ?? '');
                    $pdo->prepare("UPDATE merchants SET status='rejected',updated_at=NOW() WHERE id=?")->execute([$merchant_id]);
                    if ($merchant_row) {
                        notify((int)$merchant_row['user_id'],
                            'Merchant Application Update',
                            'Your merchant application was not approved.' . ($reason ? ' Reason: '.$reason : ' Please contact support for assistance.'),
                            'system', $merchant_id, 'merchant');
                    }
                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.reject_merchant','merchant',?,?)")
                        ->execute([$user['id'], $merchant_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                    auth_set_flash('warning', 'Merchant application rejected.');
                    break;

                case 'suspend':
                    $pdo->prepare("UPDATE merchants SET status='suspended',updated_at=NOW() WHERE id=?")->execute([$merchant_id]);
                    $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$merchant_row['user_id']]);
                    // Pause all their active deals
                    $pdo->prepare("UPDATE deals SET status='paused' WHERE merchant_id=? AND status='active'")->execute([$merchant_id]);
                    if ($merchant_row) {
                        notify((int)$merchant_row['user_id'], 'Account Suspended', 'Your merchant account has been suspended. Please contact support.', 'system');
                    }
                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.suspend_merchant','merchant',?,?)")
                        ->execute([$user['id'], $merchant_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                    auth_set_flash('warning', 'Merchant suspended. All active deals paused.');
                    break;

                case 'reactivate':
                    $pdo->prepare("UPDATE merchants SET status='active',updated_at=NOW() WHERE id=?")->execute([$merchant_id]);
                    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$merchant_row['user_id']]);
                    if ($merchant_row) {
                        notify((int)$merchant_row['user_id'], 'Account Reactivated', 'Your merchant account has been reactivated. Welcome back!', 'system');
                    }
                    auth_set_flash('success', 'Merchant reactivated.');
                    break;

                case 'update_commission':
                    $commission = max(0, min(50, (float)($_POST['commission_pct'] ?? 5)));
                    $pdo->prepare("UPDATE merchants SET commission_pct=?,updated_at=NOW() WHERE id=?")->execute([$commission, $merchant_id]);
                    auth_set_flash('success', 'Commission rate updated to ' . $commission . '%.');
                    break;
            }
        } catch (PDOException $e) {
            error_log('[Admin merchants action] '.$e->getMessage());
            auth_set_flash('error', 'Action failed. Please try again.');
        }
    }
    redirect('/admin/merchants.php' . (isset($_GET['status']) ? '?status='.$_GET['status'] : ''));
}

// ─── Single merchant detail view ──────────────────────────────────────────
$view_merchant = null;
if (isset($_GET['view'])) {
    $mid = (int)$_GET['view'];
    try {
        $stmt = db()->prepare("
            SELECT m.*,
                   u.email, u.name AS user_name, u.created_at AS user_created_at,
                   (SELECT COUNT(*) FROM deals d WHERE d.merchant_id=m.id AND d.deleted_at IS NULL) AS deal_count,
                   (SELECT COUNT(*) FROM deals d WHERE d.merchant_id=m.id AND d.status='active' AND d.deleted_at IS NULL) AS active_deals,
                   (SELECT COUNT(*) FROM redemptions r WHERE r.merchant_id=m.id AND r.status='used') AS total_redemptions,
                   (SELECT COALESCE(SUM(commission_amt),0) FROM commissions c WHERE c.merchant_id=m.id) AS total_commission
            FROM merchants m JOIN users u ON u.id=m.user_id
            WHERE m.id=? LIMIT 1
        ");
        $stmt->execute([$mid]);
        $view_merchant = $stmt->fetch();
    } catch (PDOException $e) { error_log('[Admin merchant view] '.$e->getMessage()); }

    if (!$view_merchant) redirect('/admin/merchants.php');
}

// ─── Filters for list view ────────────────────────────────────────────────
$status_filter = in_array($_GET['status'] ?? '', ['all','pending','active','suspended','rejected']) ? $_GET['status'] : 'all';
$search        = trim($_GET['q'] ?? '');
$per_page      = 20;
$page_num      = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page_num - 1) * $per_page;

// ─── Status counts ────────────────────────────────────────────────────────
$status_counts = [];
try {
    $st = db()->query("SELECT status, COUNT(*) AS cnt FROM merchants GROUP BY status");
    foreach ($st->fetchAll() as $row) { $status_counts[$row['status']] = $row['cnt']; }
    $status_counts['all'] = array_sum($status_counts);
} catch (PDOException) {}

// ─── Merchant list ────────────────────────────────────────────────────────
$merchants = []; $total = 0;
try {
    $pdo    = db();
    $where  = ["1=1"];
    $params = [];

    if ($status_filter !== 'all') { $where[] = "m.status=?"; $params[] = $status_filter; }
    if ($search) {
        $where[] = "(m.business_name LIKE ? OR u.email LIKE ? OR u.name LIKE ?)";
        $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    $ws = 'WHERE ' . implode(' AND ', $where);

    $st = $pdo->prepare("SELECT COUNT(*) FROM merchants m JOIN users u ON u.id=m.user_id {$ws}");
    $st->execute($params); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT m.id,m.business_name,m.slug,m.status,m.commission_pct,m.created_at,
               u.email,u.name AS user_name,
               (SELECT COUNT(*) FROM deals d WHERE d.merchant_id=m.id AND d.status='active' AND d.deleted_at IS NULL) AS active_deals,
               (SELECT COUNT(*) FROM redemptions r WHERE r.merchant_id=m.id AND r.status='used') AS total_redemptions
        FROM merchants m
        JOIN users u ON u.id=m.user_id
        {$ws}
        ORDER BY CASE m.status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END, m.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($params, [$per_page, $offset]));
    $merchants = $st->fetchAll();
} catch (PDOException $e) { error_log('[Admin merchants list] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

include __DIR__ . '/../inc/admin_layout.php';
?>

<?php if ($view_merchant): ?>
<!-- ─── Single Merchant Detail ───────────────────────────────────────────── -->
<?php $m = $view_merchant; ?>

<div style="margin-bottom:var(--space-xl);">
  <a href="/admin/merchants.php" style="color:var(--text-muted);font-size:14px;text-decoration:none;">← Back to Merchants</a>
</div>

<?= flash_html() ?>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- Left: merchant info + actions -->
  <div>
    <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-lg);">
      <?php if ($m['logo']): ?>
        <img src="<?= e($m['logo']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:var(--space-lg);">
      <?php else: ?>
        <div style="width:80px;height:80px;background:var(--orange-bg);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:32px;margin-bottom:var(--space-lg);">🏪</div>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-sm);">
        <h3 style="margin:0;"><?= e($m['business_name']) ?></h3>
        <span class="badge badge--<?= match($m['status']){'active'=>'success','pending'=>'warning','suspended'=>'error',default=>'muted'} ?>">
          <?= ucfirst($m['status']) ?>
        </span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);margin-bottom:var(--space-xl);">
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Owner</div>
          <div style="font-weight:600;"><?= e($m['user_name']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Email</div>
          <div style="font-size:14px;"><?= e($m['email']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Phone</div>
          <div style="font-size:14px;"><?= e($m['phone'] ?? '—') ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Member Since</div>
          <div style="font-size:14px;"><?= date('d M Y', strtotime($m['created_at'])) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Active Deals</div>
          <div style="font-weight:700;font-size:18px;color:var(--orange-primary);"><?= (int)$m['active_deals'] ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Redemptions</div>
          <div style="font-weight:700;font-size:18px;color:var(--orange-primary);"><?= (int)$m['total_redemptions'] ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Total Commission</div>
          <div style="font-weight:700;font-size:18px;color:var(--success);"><?= format_myr((float)$m['total_commission']) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Commission Rate</div>
          <div style="font-weight:700;font-size:18px;"><?= (float)$m['commission_pct'] ?>%</div>
        </div>
      </div>

      <?php if ($m['description']): ?>
        <div style="font-size:14px;color:var(--text-muted);margin-bottom:var(--space-xl);padding:var(--space-md);background:var(--bg-light);border-radius:var(--radius-md);">
          <?= nl2br(e($m['description'])) ?>
        </div>
      <?php endif; ?>

      <!-- Actions -->
      <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
        <?php if ($m['status'] === 'pending'): ?>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button class="btn btn--primary btn--full">✓ Approve Merchant</button>
          </form>
          <details>
            <summary class="btn btn--danger btn--full" style="cursor:pointer;list-style:none;">✕ Reject Application</summary>
            <form method="POST" style="margin-top:var(--space-sm);">
              <?= csrf_field() ?>
              <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <input type="text" name="reason" class="form-control" style="margin-bottom:var(--space-sm);" placeholder="Rejection reason (optional)">
              <button class="btn btn--danger btn--full">Confirm Reject</button>
            </form>
          </details>
        <?php elseif ($m['status'] === 'active'): ?>
          <form method="POST" onsubmit="return confirm('Suspend this merchant? All active deals will be paused.')">
            <?= csrf_field() ?>
            <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
            <input type="hidden" name="action" value="suspend">
            <button class="btn btn--danger btn--full">⚠ Suspend Merchant</button>
          </form>
        <?php elseif (in_array($m['status'], ['suspended','rejected'])): ?>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
            <input type="hidden" name="action" value="reactivate">
            <button class="btn btn--primary btn--full">▶ Reactivate</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Commission rate -->
    <div class="card" style="padding:var(--space-lg);">
      <h4 style="margin-bottom:var(--space-md);">Commission Rate</h4>
      <form method="POST" style="display:flex;gap:var(--space-sm);align-items:center;">
        <?= csrf_field() ?>
        <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
        <input type="hidden" name="action" value="update_commission">
        <input type="number" name="commission_pct" class="form-control" style="width:100px;" min="0" max="50" step="0.5" value="<?= (float)$m['commission_pct'] ?>">
        <span style="font-size:18px;font-weight:700;">%</span>
        <button class="btn btn--primary btn--sm">Update</button>
      </form>
      <div style="font-size:12px;color:var(--text-muted);margin-top:var(--space-sm);">Commission charged on each successful redemption.</div>
    </div>
  </div>

  <!-- Right: deals list -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">Deals (<?= (int)$m['deal_count'] ?>)</h4>
    <?php
    $merchant_deals = [];
    try {
        $st = db()->prepare("
            SELECT d.id,d.title,d.status,d.is_featured,d.deal_price,d.discount_pct,d.redemption_count,d.valid_until,d.created_at
            FROM deals d WHERE d.merchant_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 20
        ");
        $st->execute([$m['id']]);
        $merchant_deals = $st->fetchAll();
    } catch (PDOException) {}
    ?>

    <?php if (!empty($merchant_deals)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Title</th><th>Price</th><th>Redeemed</th><th>Expires</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($merchant_deals as $d): ?>
                <tr>
                  <td style="font-size:14px;font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <a href="/public/deal.php?slug=<?= urlencode($d['slug'] ?? '') ?>" target="_blank" style="color:var(--text-dark);"><?= e($d['title']) ?></a>
                  </td>
                  <td style="font-size:13px;">
                    <?= $d['deal_price'] ? format_myr((float)$d['deal_price']) : ($d['discount_pct'] ? (int)$d['discount_pct'].'% Off' : '—') ?>
                  </td>
                  <td style="font-size:13px;text-align:center;"><?= (int)$d['redemption_count'] ?></td>
                  <td style="font-size:12px;color:var(--text-muted);"><?= $d['valid_until'] ? date('d M Y', strtotime($d['valid_until'])) : '∞' ?></td>
                  <td><span class="badge badge--<?= match($d['status']){'active'=>'success','pending'=>'warning','paused'=>'muted',default=>'muted'} ?>" style="font-size:11px;"><?= ucfirst($d['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-xl);">
        <div class="empty-state__icon">🎁</div>
        <p class="empty-state__text">No deals created yet.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ─── Merchant List View ────────────────────────────────────────────────── -->

<div class="flex-between" style="margin-bottom:var(--space-xl);">
  <div>
    <h2 style="margin-bottom:var(--space-xs);">Manage Merchants</h2>
    <p style="color:var(--text-muted);">Approve, monitor, and manage merchant partners.</p>
  </div>
</div>

<?= flash_html() ?>

<!-- Status filter tabs -->
<div class="pill-list" style="margin-bottom:var(--space-lg);">
  <?php
  $tabs = ['all'=>'All','pending'=>'Pending','active'=>'Active','suspended'=>'Suspended','rejected'=>'Rejected'];
  foreach ($tabs as $k => $label):
    $cnt = $k === 'all' ? ($status_counts['all'] ?? 0) : ($status_counts[$k] ?? 0);
  ?>
    <a href="?status=<?= $k ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="pill <?= $status_filter===$k?'active':'' ?>">
      <?= $label ?>
      <?php if ($cnt > 0): ?>
        <span style="background:<?= $k==='pending'?'var(--orange-primary)':'var(--text-muted)' ?>;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;"><?= $cnt ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:var(--space-lg);">
  <?php if ($status_filter !== 'all'): ?><input type="hidden" name="status" value="<?= e($status_filter) ?>"><?php endif; ?>
  <div style="display:flex;gap:var(--space-sm);">
    <input type="text" name="q" class="form-control" style="max-width:360px;" value="<?= e($search) ?>" placeholder="Search by name, email…">
    <button class="btn btn--primary btn--sm">Search</button>
    <?php if ($search): ?><a href="?status=<?= $status_filter ?>" class="btn btn--muted btn--sm">✕ Clear</a><?php endif; ?>
  </div>
</form>

<div style="margin-bottom:var(--space-md);font-size:14px;color:var(--text-muted);"><?= $total ?> merchant<?= $total!==1?'s':'' ?> found</div>

<?php if (!empty($merchants)): ?>
  <div class="card" style="overflow:hidden;">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Business</th>
            <th>Owner</th>
            <th>Email</th>
            <th>Commission</th>
            <th>Active Deals</th>
            <th>Redemptions</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($merchants as $m): ?>
            <tr style="<?= $m['status']==='pending' ? 'background:var(--orange-bg);' : '' ?>">
              <td>
                <a href="/admin/merchants.php?view=<?= $m['id'] ?>" style="font-weight:700;color:var(--text-dark);text-decoration:none;">
                  <?= e($m['business_name']) ?>
                </a>
                <div style="font-size:11px;color:var(--text-muted);"><?= date('d M Y', strtotime($m['created_at'])) ?></div>
              </td>
              <td style="font-size:13px;"><?= e($m['user_name']) ?></td>
              <td style="font-size:13px;"><?= e($m['email']) ?></td>
              <td style="font-size:13px;font-weight:600;"><?= (float)$m['commission_pct'] ?>%</td>
              <td style="text-align:center;font-weight:600;"><?= (int)$m['active_deals'] ?></td>
              <td style="text-align:center;font-weight:600;"><?= (int)$m['total_redemptions'] ?></td>
              <td>
                <span class="badge badge--<?= match($m['status']){'active'=>'success','pending'=>'warning','suspended'=>'error',default=>'muted'} ?>">
                  <?= ucfirst($m['status']) ?>
                </span>
              </td>
              <td>
                <div style="display:flex;flex-direction:column;gap:4px;min-width:100px;">
                  <a href="/admin/merchants.php?view=<?= $m['id'] ?>" class="btn btn--muted btn--sm btn--full">View</a>
                  <?php if ($m['status'] === 'pending'): ?>
                    <form method="POST" style="margin:0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button class="btn btn--primary btn--sm btn--full">✓ Approve</button>
                    </form>
                  <?php elseif ($m['status'] === 'active'): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Suspend this merchant?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
                      <input type="hidden" name="action" value="suspend">
                      <button class="btn btn--danger btn--sm btn--full">Suspend</button>
                    </form>
                  <?php elseif (in_array($m['status'], ['suspended','rejected'])): ?>
                    <form method="POST" style="margin:0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="merchant_id" value="<?= $m['id'] ?>">
                      <input type="hidden" name="action" value="reactivate">
                      <button class="btn btn--primary btn--sm btn--full">▶ Reactivate</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:var(--space-lg);justify-content:center;">
      <?php if ($page_num > 1): ?><a href="?status=<?= $status_filter ?>&q=<?= urlencode($search) ?>&page=<?= $page_num-1 ?>" class="pagination__btn">← Prev</a><?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?><a href="?status=<?= $status_filter ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
      <?php if ($page_num < $total_pages): ?><a href="?status=<?= $status_filter ?>&q=<?= urlencode($search) ?>&page=<?= $page_num+1 ?>" class="pagination__btn">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">🏪</div>
    <h3 class="empty-state__title">No merchants found</h3>
    <p class="empty-state__text">No merchants match the current filters.</p>
  </div>
<?php endif; ?>

<?php endif; // end single vs list view ?>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require_admin();

$user       = auth_user();
$page_title = 'Manage Deals';
$active_nav = 'deals';

// ─── POST actions ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action  = $_POST['action'] ?? '';
    $deal_id = (int)($_POST['deal_id'] ?? 0);

    if ($deal_id > 0) {
        try {
            $pdo = db();
            switch ($action) {
                case 'approve':
                    $pdo->prepare("UPDATE deals SET status='active',updated_at=NOW() WHERE id=?")
                        ->execute([$deal_id]);
                    // Notify merchant owner
                    $deal_row = $pdo->prepare("SELECT d.title,m.user_id FROM deals d JOIN merchants m ON m.id=d.merchant_id WHERE d.id=? LIMIT 1");
                    $deal_row->execute([$deal_id]);
                    if ($dr = $deal_row->fetch()) {
                        notify((int)$dr['user_id'], 'Deal Approved ✅', 'Your deal "' . $dr['title'] . '" is now live!', 'system', $deal_id, 'deal');
                    }
                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.approve_deal','deal',?,?)")
                        ->execute([$user['id'], $deal_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                    auth_set_flash('success', 'Deal approved and now live.');
                    break;

                case 'reject':
                    $reason = trim($_POST['reason'] ?? '');
                    $pdo->prepare("UPDATE deals SET status='rejected',updated_at=NOW() WHERE id=?")
                        ->execute([$deal_id]);
                    $deal_row = $pdo->prepare("SELECT d.title,m.user_id FROM deals d JOIN merchants m ON m.id=d.merchant_id WHERE d.id=? LIMIT 1");
                    $deal_row->execute([$deal_id]);
                    if ($dr = $deal_row->fetch()) {
                        notify((int)$dr['user_id'], 'Deal Rejected', 'Your deal "' . $dr['title'] . '" was not approved.' . ($reason ? ' Reason: ' . $reason : ''), 'system', $deal_id, 'deal');
                    }
                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.reject_deal','deal',?,?)")
                        ->execute([$user['id'], $deal_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                    auth_set_flash('warning', 'Deal rejected.');
                    break;

                case 'feature':
                    $pdo->prepare("UPDATE deals SET is_featured=1,updated_at=NOW() WHERE id=?")->execute([$deal_id]);
                    auth_set_flash('success', 'Deal featured on homepage.');
                    break;

                case 'unfeature':
                    $pdo->prepare("UPDATE deals SET is_featured=0,updated_at=NOW() WHERE id=?")->execute([$deal_id]);
                    auth_set_flash('success', 'Deal removed from featured.');
                    break;

                case 'pause':
                    $pdo->prepare("UPDATE deals SET status='paused',updated_at=NOW() WHERE id=?")->execute([$deal_id]);
                    auth_set_flash('info', 'Deal paused.');
                    break;

                case 'activate':
                    $pdo->prepare("UPDATE deals SET status='active',updated_at=NOW() WHERE id=?")->execute([$deal_id]);
                    auth_set_flash('success', 'Deal reactivated.');
                    break;

                case 'delete':
                    $pdo->prepare("UPDATE deals SET deleted_at=NOW(),status='inactive' WHERE id=?")->execute([$deal_id]);
                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.delete_deal','deal',?,?)")
                        ->execute([$user['id'], $deal_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                    auth_set_flash('warning', 'Deal deleted.');
                    break;
            }
        } catch (PDOException $e) {
            error_log('[Admin deals action] '.$e->getMessage());
            auth_set_flash('error', 'Action failed. Please try again.');
        }
    }
    redirect('/admin/deals.php' . (isset($_GET['status']) ? '?status='.$_GET['status'] : ''));
}

// ─── Filters ──────────────────────────────────────────────────────────────
$status_filter = in_array($_GET['status'] ?? '', ['all','pending','active','paused','rejected','expired']) ? $_GET['status'] : 'all';
$search        = trim($_GET['q'] ?? '');
$per_page      = 20;
$page_num      = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page_num - 1) * $per_page;

// ─── Status counts ────────────────────────────────────────────────────────
$status_counts = [];
try {
    $st = db()->query("SELECT status, COUNT(*) AS cnt FROM deals WHERE deleted_at IS NULL GROUP BY status");
    foreach ($st->fetchAll() as $row) { $status_counts[$row['status']] = $row['cnt']; }
    $status_counts['all'] = array_sum($status_counts);
} catch (PDOException) {}

// ─── Deal list ────────────────────────────────────────────────────────────
$deals = []; $total = 0;
try {
    $pdo    = db();
    $where  = ["d.deleted_at IS NULL"];
    $params = [];

    if ($status_filter !== 'all') { $where[] = "d.status=?"; $params[] = $status_filter; }
    if ($search) {
        $where[] = "(d.title LIKE ? OR m.business_name LIKE ?)";
        $params  = array_merge($params, ["%$search%", "%$search%"]);
    }
    $ws = 'WHERE ' . implode(' AND ', $where);

    $st = $pdo->prepare("SELECT COUNT(*) FROM deals d JOIN merchants m ON m.id=d.merchant_id {$ws}");
    $st->execute($params); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT d.id,d.title,d.slug,d.status,d.is_featured,d.is_members_only,
               d.deal_price,d.discount_pct,d.redemption_count,d.max_redemptions,
               d.valid_until,d.updated_at,d.created_at,
               m.business_name AS merchant_name,m.id AS merchant_id,
               dc.name AS category,dc.icon AS cat_icon,
               (SELECT COUNT(*) FROM redemptions r WHERE r.deal_id=d.id AND r.status='active') AS active_vouchers
        FROM deals d
        JOIN merchants m ON m.id=d.merchant_id
        LEFT JOIN deal_categories dc ON dc.id=d.category_id
        {$ws} ORDER BY
            CASE d.status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END,
            d.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($params, [$per_page, $offset]));
    $deals = $st->fetchAll();
} catch (PDOException $e) { error_log('[Admin deals list] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

include __DIR__ . '/../inc/admin_layout.php';
?>

<div class="flex-between" style="margin-bottom:var(--space-xl);">
  <div>
    <h2 style="margin-bottom:var(--space-xs);">Manage Deals</h2>
    <p style="color:var(--text-muted);">Approve, feature, and manage all merchant deals.</p>
  </div>
</div>

<?= flash_html() ?>

<!-- Status filter tabs -->
<div class="pill-list" style="margin-bottom:var(--space-lg);">
  <?php
  $tabs = ['all'=>'All','pending'=>'Pending','active'=>'Active','paused'=>'Paused','rejected'=>'Rejected'];
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
    <input type="text" name="q" class="form-control" style="max-width:360px;" value="<?= e($search) ?>" placeholder="Search deals, merchants…">
    <button class="btn btn--primary btn--sm">Search</button>
    <?php if ($search): ?><a href="?status=<?= $status_filter ?>" class="btn btn--muted btn--sm">✕ Clear</a><?php endif; ?>
  </div>
</form>

<!-- Results -->
<div style="margin-bottom:var(--space-md);font-size:14px;color:var(--text-muted);"><?= $total ?> deal<?= $total!==1?'s':'' ?> found</div>

<?php if (!empty($deals)): ?>
  <div class="card" style="overflow:hidden;">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Deal</th>
            <th>Merchant</th>
            <th>Category</th>
            <th>Price</th>
            <th>Redeemed</th>
            <th>Expires</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($deals as $d): ?>
            <tr style="<?= $d['status']==='pending' ? 'background:var(--orange-bg);' : '' ?>">
              <td>
                <div style="display:flex;align-items:center;gap:var(--space-sm);">
                  <div style="font-size:24px;"><?= e($d['cat_icon'] ?? '🎁') ?></div>
                  <div>
                    <div style="font-weight:600;font-size:14px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <a href="/deal.php?slug=<?= urlencode($d['slug']) ?>" target="_blank" style="color:var(--text-dark);"><?= e($d['title']) ?></a>
                    </div>
                    <div style="display:flex;gap:6px;margin-top:2px;flex-wrap:wrap;">
                      <?php if ($d['is_featured']): ?>
                        <span class="badge badge--orange" style="font-size:10px;">⭐ Featured</span>
                      <?php endif; ?>
                      <?php if ($d['is_members_only']): ?>
                        <span class="badge badge--muted" style="font-size:10px;">🔒 Members</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td style="font-size:13px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['merchant_name']) ?></td>
              <td style="font-size:13px;"><?= e($d['category'] ?? '—') ?></td>
              <td style="font-size:13px;font-weight:600;">
                <?php if ($d['deal_price']): ?>
                  <?= format_myr((float)$d['deal_price']) ?>
                <?php elseif ($d['discount_pct']): ?>
                  <?= (int)$d['discount_pct'] ?>% Off
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td style="font-size:13px;text-align:center;">
                <span style="font-weight:700;"><?= (int)$d['redemption_count'] ?></span>
                <?php if ($d['max_redemptions'] > 0): ?>
                  <span style="color:var(--text-muted);">/ <?= (int)$d['max_redemptions'] ?></span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:<?= $d['valid_until'] && strtotime($d['valid_until'])<strtotime('+7 days') ? 'var(--error)' : 'var(--text-muted)' ?>;">
                <?= $d['valid_until'] ? date('d M Y', strtotime($d['valid_until'])) : '∞' ?>
              </td>
              <td>
                <span class="badge badge--<?= match($d['status']){'active'=>'success','pending'=>'warning','paused'=>'muted','rejected'=>'error',default=>'muted'} ?>">
                  <?= ucfirst($d['status']) ?>
                </span>
              </td>
              <td>
                <div style="display:flex;flex-direction:column;gap:4px;min-width:130px;">
                  <?php if ($d['status'] === 'pending'): ?>
                    <form method="POST" style="margin:0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button class="btn btn--primary btn--sm btn--full">✓ Approve</button>
                    </form>
                    <details style="margin:0;">
                      <summary class="btn btn--muted btn--sm" style="cursor:pointer;list-style:none;">✕ Reject</summary>
                      <form method="POST" style="margin-top:4px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="text" name="reason" class="form-control" style="font-size:12px;margin-bottom:4px;" placeholder="Reason (optional)">
                        <button class="btn btn--danger btn--sm btn--full">Confirm Reject</button>
                      </form>
                    </details>
                  <?php endif; ?>

                  <?php if ($d['status'] === 'active'): ?>
                    <?php if (!$d['is_featured']): ?>
                      <form method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="action" value="feature">
                        <button class="btn btn--muted btn--sm btn--full">⭐ Feature</button>
                      </form>
                    <?php else: ?>
                      <form method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="action" value="unfeature">
                        <button class="btn btn--muted btn--sm btn--full">★ Unfeature</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" style="margin:0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="action" value="pause">
                      <button class="btn btn--muted btn--sm btn--full">⏸ Pause</button>
                    </form>
                  <?php endif; ?>

                  <?php if (in_array($d['status'], ['paused','rejected'])): ?>
                    <form method="POST" style="margin:0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="action" value="activate">
                      <button class="btn btn--primary btn--sm btn--full">▶ Activate</button>
                    </form>
                  <?php endif; ?>

                  <form method="POST" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn--danger btn--sm btn--full" onclick="return confirm('Delete this deal? This cannot be undone.')">🗑 Delete</button>
                  </form>
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
    <div class="empty-state__icon">🎁</div>
    <h3 class="empty-state__title">No deals found</h3>
    <p class="empty-state__text">No deals match the current filters.</p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

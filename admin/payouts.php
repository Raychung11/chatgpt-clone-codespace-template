<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require_admin();

$user       = auth_user();
$page_title = 'Payout Requests';
$active_nav = 'payouts';

// ─── POST actions ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action    = $_POST['action'] ?? '';
    $payout_id = (int)($_POST['payout_id'] ?? 0);

    if ($payout_id > 0) {
        try {
            $pdo = db();
            $pr  = $pdo->prepare("SELECT pr.*,m.user_id AS merchant_user_id,m.business_name FROM payout_requests pr JOIN merchants m ON m.id=pr.merchant_id WHERE pr.id=? LIMIT 1");
            $pr->execute([$payout_id]);
            $payout = $pr->fetch();

            if ($payout) {
                switch ($action) {
                    case 'mark_paid':
                        $ref = trim($_POST['payment_ref'] ?? '');
                        $pdo->prepare("UPDATE payout_requests SET status='paid',processed_at=NOW(),processed_by=?,notes=CONCAT(COALESCE(notes,''),' | Ref: ',?) WHERE id=?")
                            ->execute([$user['id'], $ref, $payout_id]);
                        notify((int)$payout['merchant_user_id'],
                            'Payout Processed ✅',
                            format_myr((float)$payout['amount']) . ' has been transferred to your bank account.' . ($ref ? ' Ref: '.$ref : ''),
                            'reward', $payout_id, 'payout');
                        $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.mark_payout_paid','payout_request',?,?)")
                            ->execute([$user['id'], $payout_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                        auth_set_flash('success', 'Payout marked as paid. Merchant notified.');
                        break;

                    case 'reject':
                        $reason = trim($_POST['reason'] ?? '');
                        $pdo->prepare("UPDATE payout_requests SET status='rejected',processed_at=NOW(),processed_by=? WHERE id=?")
                            ->execute([$user['id'], $payout_id]);
                        notify((int)$payout['merchant_user_id'],
                            'Payout Request Rejected',
                            'Your payout request for ' . format_myr((float)$payout['amount']) . ' was not processed.' . ($reason ? ' Reason: '.$reason : ' Please contact support.'),
                            'system', $payout_id, 'payout');
                        $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'admin.reject_payout','payout_request',?,?)")
                            ->execute([$user['id'], $payout_id, $_SERVER['REMOTE_ADDR'] ?? null]);
                        auth_set_flash('warning', 'Payout rejected. Merchant notified.');
                        break;
                }
            }
        } catch (PDOException $e) {
            error_log('[Admin payouts action] '.$e->getMessage());
            auth_set_flash('error', 'Action failed. Please try again.');
        }
    }
    redirect('/admin/payouts.php' . (isset($_GET['status']) ? '?status='.$_GET['status'] : ''));
}

// ─── Filters ──────────────────────────────────────────────────────────────
$status_filter = in_array($_GET['status'] ?? '', ['all','pending','paid','rejected']) ? $_GET['status'] : 'pending';
$per_page      = 20;
$page_num      = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page_num - 1) * $per_page;

// ─── Summary stats ────────────────────────────────────────────────────────
$payout_summary = [];
try {
    $st = db()->query("SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payout_requests GROUP BY status");
    foreach ($st->fetchAll() as $row) { $payout_summary[$row['status']] = $row; }
} catch (PDOException) {}

$pending_count = (int)($payout_summary['pending']['cnt'] ?? 0);
$pending_amount = (float)($payout_summary['pending']['total'] ?? 0);
$paid_amount    = (float)($payout_summary['paid']['total'] ?? 0);

// ─── Payout list ──────────────────────────────────────────────────────────
$payouts = []; $total = 0;
try {
    $pdo    = db();
    $where  = ["1=1"];
    $params = [];
    if ($status_filter !== 'all') { $where[] = "pr.status=?"; $params[] = $status_filter; }
    $ws = 'WHERE ' . implode(' AND ', $where);

    $st = $pdo->prepare("SELECT COUNT(*) FROM payout_requests pr {$ws}");
    $st->execute($params); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT pr.*,
               m.business_name,m.id AS merchant_id,
               u.email AS merchant_email,
               au.name AS processed_by_name
        FROM payout_requests pr
        JOIN merchants m ON m.id=pr.merchant_id
        JOIN users u ON u.id=m.user_id
        LEFT JOIN users au ON au.id=pr.processed_by
        {$ws}
        ORDER BY CASE pr.status WHEN 'pending' THEN 0 ELSE 1 END, pr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($params, [$per_page, $offset]));
    $payouts = $st->fetchAll();
} catch (PDOException $e) { error_log('[Admin payouts list] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

include __DIR__ . '/../inc/admin_layout.php';
?>

<div class="flex-between" style="margin-bottom:var(--space-xl);">
  <div>
    <h2 style="margin-bottom:var(--space-xs);">Payout Requests</h2>
    <p style="color:var(--text-muted);">Process merchant commission withdrawal requests.</p>
  </div>
</div>

<!-- Summary cards -->
<div class="grid grid-3" style="margin-bottom:var(--space-xl);">
  <div class="stat-card" style="<?= $pending_count > 0 ? 'border-color:var(--orange-primary);' : '' ?>">
    <div class="stat-card__icon">⏳</div>
    <div class="stat-card__value" style="<?= $pending_count > 0 ? 'color:var(--orange-primary);' : '' ?>"><?= $pending_count ?></div>
    <div class="stat-card__label">Pending Requests</div>
    <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-top:4px;"><?= format_myr($pending_amount) ?> total</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">✅</div>
    <div class="stat-card__value"><?= (int)($payout_summary['paid']['cnt'] ?? 0) ?></div>
    <div class="stat-card__label">Paid Out</div>
    <div style="font-size:13px;font-weight:600;color:var(--success);margin-top:4px;"><?= format_myr($paid_amount) ?> total</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">❌</div>
    <div class="stat-card__value"><?= (int)($payout_summary['rejected']['cnt'] ?? 0) ?></div>
    <div class="stat-card__label">Rejected</div>
  </div>
</div>

<?= flash_html() ?>

<!-- Status tabs -->
<div class="pill-list" style="margin-bottom:var(--space-xl);">
  <?php foreach (['pending'=>'Pending','paid'=>'Paid','rejected'=>'Rejected','all'=>'All'] as $k => $label): ?>
    <a href="?status=<?= $k ?>" class="pill <?= $status_filter===$k?'active':'' ?>">
      <?= $label ?>
      <?php if ($k === 'pending' && $pending_count > 0): ?>
        <span style="background:var(--orange-primary);color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;"><?= $pending_count ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!empty($payouts)): ?>
  <div style="display:flex;flex-direction:column;gap:var(--space-md);">
    <?php foreach ($payouts as $p): ?>
      <div class="card" style="padding:var(--space-xl);border-left:4px solid <?= match($p['status']){'pending'=>'var(--orange-primary)','paid'=>'var(--success)',default=>'var(--text-muted)'} ?>;">
        <div class="grid grid-2" style="gap:var(--space-xl);align-items:start;">

          <!-- Left: payout details -->
          <div>
            <div style="display:flex;align-items:center;gap:var(--space-md);margin-bottom:var(--space-md);">
              <div>
                <div style="font-size:22px;font-weight:800;color:var(--orange-primary);"><?= format_myr((float)$p['amount']) ?></div>
                <div style="font-size:14px;font-weight:600;"><?= e($p['business_name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= e($p['merchant_email']) ?></div>
              </div>
              <span class="badge badge--<?= match($p['status']){'paid'=>'success','pending'=>'warning','rejected'=>'error',default=>'muted'} ?>" style="margin-left:auto;">
                <?= ucfirst($p['status']) ?>
              </span>
            </div>

            <div style="display:grid;grid-template-columns:auto 1fr;gap:4px var(--space-md);font-size:13px;margin-bottom:var(--space-md);">
              <span style="color:var(--text-muted);">Bank:</span>       <span style="font-weight:600;"><?= e($p['bank_name']) ?></span>
              <span style="color:var(--text-muted);">Account:</span>    <span style="font-family:monospace;font-size:14px;"><?= e($p['account_no']) ?></span>
              <span style="color:var(--text-muted);">Name:</span>       <span><?= e($p['account_name']) ?></span>
              <span style="color:var(--text-muted);">Requested:</span>  <span><?= date('d M Y g:ia', strtotime($p['created_at'])) ?></span>
              <?php if ($p['processed_at']): ?>
                <span style="color:var(--text-muted);">Processed:</span>
                <span><?= date('d M Y', strtotime($p['processed_at'])) ?> by <?= e($p['processed_by_name'] ?? '—') ?></span>
              <?php endif; ?>
            </div>

            <?php if ($p['notes']): ?>
              <div style="font-size:13px;color:var(--text-muted);background:var(--bg-light);border-radius:var(--radius-sm);padding:var(--space-sm) var(--space-md);">
                <?= nl2br(e($p['notes'])) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Right: actions -->
          <?php if ($p['status'] === 'pending'): ?>
            <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
              <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="action" value="mark_paid">
                <div class="form-group" style="margin-bottom:var(--space-sm);">
                  <label class="form-label" style="font-size:13px;">Payment Reference (optional)</label>
                  <input type="text" name="payment_ref" class="form-control" style="font-size:13px;" placeholder="e.g. TT Ref 12345678">
                </div>
                <button class="btn btn--primary btn--full">✓ Mark as Paid</button>
              </form>

              <details>
                <summary class="btn btn--danger btn--full" style="cursor:pointer;list-style:none;">✕ Reject Request</summary>
                <form method="POST" style="margin-top:var(--space-sm);">
                  <?= csrf_field() ?>
                  <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="text" name="reason" class="form-control" style="font-size:13px;margin-bottom:var(--space-sm);" placeholder="Rejection reason">
                  <button class="btn btn--danger btn--full">Confirm Reject</button>
                </form>
              </details>
            </div>
          <?php elseif ($p['status'] === 'paid'): ?>
            <div style="padding:var(--space-lg);background:rgba(var(--success-rgb,16,185,129),.08);border-radius:var(--radius-md);text-align:center;">
              <div style="font-size:32px;margin-bottom:var(--space-sm);">✅</div>
              <div style="font-weight:700;color:var(--success);">Payment Sent</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= date('d M Y', strtotime($p['processed_at'])) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:var(--space-xl);justify-content:center;">
      <?php if ($page_num > 1): ?><a href="?status=<?= $status_filter ?>&page=<?= $page_num-1 ?>" class="pagination__btn">← Prev</a><?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?><a href="?status=<?= $status_filter ?>&page=<?= $i ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
      <?php if ($page_num < $total_pages): ?><a href="?status=<?= $status_filter ?>&page=<?= $page_num+1 ?>" class="pagination__btn">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">💰</div>
    <h3 class="empty-state__title"><?= $status_filter === 'pending' ? 'No pending payout requests' : 'No payouts found' ?></h3>
    <p class="empty-state__text">Merchant payout requests will appear here.</p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

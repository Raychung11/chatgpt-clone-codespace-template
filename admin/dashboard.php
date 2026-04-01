<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require_admin();

$page_title = 'Dashboard';
$active_nav = 'dashboard';

// ─── Stats ────────────────────────────────────────────────────────────────────
$stats = [
    'total_members'    => 0,
    'active_members'   => 0,
    'pending_members'  => 0,
    'total_merchants'  => 0,
    'active_merchants' => 0,
    'total_deals'      => 0,
    'active_deals'     => 0,
    'total_redemptions'=> 0,
    'today_redemptions'=> 0,
    'total_points_issued' => 0,
    'pending_verifications' => 0,
    'pending_merchants' => 0,
    'community_partners' => 0,
    'revenue_total'    => 0.00,
];

$recent_members      = [];
$recent_redemptions  = [];
$pending_merchants   = [];
$recent_audit        = [];

try {
    $pdo = db();

    // Member stats
    $stats['total_members']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
    $stats['active_members']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND status='active'")->fetchColumn();
    $stats['pending_members'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND status='pending'")->fetchColumn();

    // Merchant stats
    $stats['total_merchants']  = (int)$pdo->query("SELECT COUNT(*) FROM merchants")->fetchColumn();
    $stats['active_merchants'] = (int)$pdo->query("SELECT COUNT(*) FROM merchants WHERE status='active'")->fetchColumn();
    $stats['pending_merchants']= (int)$pdo->query("SELECT COUNT(*) FROM merchants WHERE status='pending'")->fetchColumn();

    // Deal stats
    $stats['total_deals']  = (int)$pdo->query("SELECT COUNT(*) FROM deals")->fetchColumn();
    $stats['active_deals'] = (int)$pdo->query("SELECT COUNT(*) FROM deals WHERE status='active'")->fetchColumn();

    // Redemption stats
    $stats['total_redemptions'] = (int)$pdo->query("SELECT COUNT(*) FROM redemptions")->fetchColumn();
    $stats['today_redemptions'] = (int)$pdo->query("SELECT COUNT(*) FROM redemptions WHERE DATE(created_at)=CURDATE()")->fetchColumn();

    // Points
    $stats['total_points_issued'] = (int)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM points_wallets")->fetchColumn();

    // Verifications
    $stats['pending_verifications'] = (int)$pdo->query("SELECT COUNT(*) FROM senior_verifications WHERE status='pending'")->fetchColumn();

    // Community
    $stats['community_partners'] = (int)$pdo->query("SELECT COUNT(*) FROM community_partners WHERE status='active'")->fetchColumn();

    // Revenue (commission approved+paid)
    $stats['revenue_total'] = (float)($pdo->query("SELECT COALESCE(SUM(commission_amt),0) FROM commissions WHERE status IN ('approved','paid')")->fetchColumn() ?: 0);

    // Recent members
    $recent_members = $pdo->query("
        SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at,
               mp.referral_code
        FROM users u
        LEFT JOIN member_profiles mp ON mp.user_id = u.id
        WHERE u.role = 'member'
        ORDER BY u.created_at DESC LIMIT 8
    ")->fetchAll();

    // Recent redemptions
    $recent_redemptions = $pdo->query("
        SELECT r.id, r.voucher_code, r.status, r.created_at,
               u.name AS member_name,
               d.title AS deal_title,
               m.business_name AS merchant_name
        FROM redemptions r
        JOIN users u      ON u.id = r.user_id
        JOIN deals d      ON d.id = r.deal_id
        JOIN merchants m  ON m.id = r.merchant_id
        ORDER BY r.created_at DESC LIMIT 8
    ")->fetchAll();

    // Pending merchant approvals
    $pending_merchants = $pdo->query("
        SELECT me.id, me.business_name, me.email, me.created_at, u.name AS owner_name
        FROM merchants me
        JOIN users u ON u.id = me.user_id
        WHERE me.status = 'pending'
        ORDER BY me.created_at ASC LIMIT 5
    ")->fetchAll();

    // Recent audit logs
    $recent_audit = $pdo->query("
        SELECT al.action, al.created_at, al.ip_address, u.name AS user_name
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id
        ORDER BY al.created_at DESC LIMIT 10
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[Admin Dashboard] ' . $e->getMessage());
}

include __DIR__ . '/../inc/admin_layout.php';
?>

<!-- ─── KPI Cards ─────────────────────────────────────────────────────────── -->
<div class="grid grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card__number"><?= number_format($stats['total_members']) ?></div>
    <div class="stat-card__label">Total Members</div>
    <div style="margin-top:var(--space-sm);font-size:13px;">
      <span style="color:var(--success);">● <?= $stats['active_members'] ?> active</span>
      &nbsp;
      <span style="color:var(--warning);">● <?= $stats['pending_members'] ?> pending</span>
    </div>
  </div>

  <div class="stat-card" style="border-left-color:#3B82F6;">
    <div class="stat-card__number" style="color:#3B82F6;"><?= number_format($stats['total_merchants']) ?></div>
    <div class="stat-card__label">Merchants</div>
    <div style="margin-top:var(--space-sm);font-size:13px;">
      <span style="color:var(--success);">● <?= $stats['active_merchants'] ?> active</span>
      &nbsp;
      <?php if ($stats['pending_merchants'] > 0): ?>
        <a href="/admin/merchants.php?filter=pending" style="color:var(--warning);">● <?= $stats['pending_merchants'] ?> pending</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-card" style="border-left-color:#8B5CF6;">
    <div class="stat-card__number" style="color:#8B5CF6;"><?= number_format($stats['active_deals']) ?></div>
    <div class="stat-card__label">Active Deals</div>
    <div style="margin-top:var(--space-sm);font-size:13px;color:var(--text-muted);">
      <?= $stats['total_deals'] ?> total deals created
    </div>
  </div>

  <div class="stat-card" style="border-left-color:var(--success);">
    <div class="stat-card__number" style="color:var(--success);"><?= format_myr($stats['revenue_total']) ?></div>
    <div class="stat-card__label">Commission Revenue</div>
    <div style="margin-top:var(--space-sm);font-size:13px;color:var(--text-muted);">
      <?= $stats['total_redemptions'] ?> total redemptions
    </div>
  </div>
</div>

<!-- ─── Secondary KPIs ───────────────────────────────────────────────────── -->
<div class="grid grid-4" style="margin-bottom:var(--space-xl);">
  <div class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);">
    <div style="font-size:32px;">🎫</div>
    <div>
      <div style="font-size:22px;font-weight:800;"><?= $stats['today_redemptions'] ?></div>
      <div style="font-size:13px;color:var(--text-muted);">Redemptions Today</div>
    </div>
  </div>

  <div class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);">
    <div style="font-size:32px;">💰</div>
    <div>
      <div style="font-size:22px;font-weight:800;"><?= number_format($stats['total_points_issued']) ?></div>
      <div style="font-size:13px;color:var(--text-muted);">Points in Circulation</div>
    </div>
  </div>

  <?php $total_pend_approval = $stats['pending_verifications'] + $stats['pending_merchants']; ?>
  <a href="/admin/verifications.php" class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);text-decoration:none;<?= $total_pend_approval > 0 ? 'border:2px solid var(--warning);' : '' ?>">
    <div style="font-size:32px;">✅</div>
    <div>
      <div style="font-size:22px;font-weight:800;color:<?= $total_pend_approval > 0 ? 'var(--warning)' : 'var(--text-dark)' ?>;"><?= $stats['pending_verifications'] ?></div>
      <div style="font-size:13px;color:var(--text-muted);">Pending Verifications</div>
    </div>
  </a>

  <div class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);">
    <div style="font-size:32px;">🏢</div>
    <div>
      <div style="font-size:22px;font-weight:800;"><?= $stats['community_partners'] ?></div>
      <div style="font-size:13px;color:var(--text-muted);">Community Partners</div>
    </div>
  </div>
</div>

<!-- ─── Content grid ──────────────────────────────────────────────────────── -->
<div class="grid grid-2" style="align-items:start;margin-bottom:var(--space-xl);">

  <!-- Recent Members -->
  <div>
    <div class="flex-between" style="margin-bottom:var(--space-md);">
      <h3>👥 Recent Members</h3>
      <a href="/admin/members.php" class="btn btn--secondary btn--sm">Manage All</a>
    </div>
    <div class="card" style="overflow:hidden;">
      <?php if (!empty($recent_members)): ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Name</th><th>Phone</th><th>Status</th><th>Joined</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent_members as $m): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;font-size:15px;"><?= e($m['name']) ?></div>
                    <div style="font-size:13px;color:var(--text-muted);"><?= e($m['email']) ?></div>
                  </td>
                  <td><?= e($m['phone']) ?></td>
                  <td>
                    <span class="badge badge--<?= match($m['status']) { 'active'=>'success','pending'=>'warning','suspended','banned'=>'error',default=>'muted' } ?>">
                      <?= ucfirst($m['status']) ?>
                    </span>
                  </td>
                  <td style="font-size:13px;color:var(--text-muted);"><?= time_ago($m['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state" style="padding:var(--space-xl);">
          <div class="empty-state__icon">👥</div>
          <p class="empty-state__text">No members yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pending Approvals + Recent Redemptions -->
  <div style="display:flex;flex-direction:column;gap:var(--space-xl);">

    <!-- Pending Merchant Approvals -->
    <?php if (!empty($pending_merchants)): ?>
    <div>
      <div class="flex-between" style="margin-bottom:var(--space-md);">
        <h3 style="color:var(--warning);">⚠ Pending Merchant Approvals</h3>
        <a href="/admin/merchants.php?filter=pending" class="btn btn--sm" style="background:var(--warning-bg);color:#92400E;border:1px solid #FDE68A;">Review All</a>
      </div>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Business</th><th>Owner</th><th>Applied</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($pending_merchants as $mer): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;"><?= e($mer['business_name']) ?></div>
                    <div style="font-size:13px;color:var(--text-muted);"><?= e($mer['email'] ?? '') ?></div>
                  </td>
                  <td><?= e($mer['owner_name']) ?></td>
                  <td style="font-size:13px;color:var(--text-muted);"><?= time_ago($mer['created_at']) ?></td>
                  <td>
                    <a href="/admin/merchants.php?action=review&id=<?= $mer['id'] ?>" class="btn btn--primary btn--sm">Review</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Redemptions -->
    <div>
      <div class="flex-between" style="margin-bottom:var(--space-md);">
        <h3>🎫 Recent Redemptions</h3>
        <a href="/admin/redemptions.php" class="btn btn--secondary btn--sm">View All</a>
      </div>
      <div class="card" style="overflow:hidden;">
        <?php if (!empty($recent_redemptions)): ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Deal</th><th>Member</th><th>Status</th><th>Time</th></tr></thead>
              <tbody>
                <?php foreach ($recent_redemptions as $r): ?>
                  <tr>
                    <td>
                      <div style="font-weight:600;font-size:14px;"><?= e($r['deal_title']) ?></div>
                      <div style="font-size:12px;color:var(--text-muted);"><?= e($r['merchant_name']) ?></div>
                    </td>
                    <td style="font-size:14px;"><?= e($r['member_name']) ?></td>
                    <td>
                      <span class="badge badge--<?= match($r['status']) { 'active'=>'success','used'=>'muted','expired'=>'error',default=>'muted' } ?>" style="font-size:12px;">
                        <?= ucfirst($r['status']) ?>
                      </span>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= time_ago($r['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state" style="padding:var(--space-xl);">
            <div class="empty-state__icon">🎫</div>
            <p class="empty-state__text">No redemptions yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ─── Recent Audit Log ──────────────────────────────────────────────────── -->
<?php if (!empty($recent_audit)): ?>
<div>
  <div class="flex-between" style="margin-bottom:var(--space-md);">
    <h3>📋 Recent Audit Log</h3>
    <a href="/admin/audit-logs.php" class="btn btn--secondary btn--sm">Full Log</a>
  </div>
  <div class="card" style="overflow:hidden;">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Action</th><th>User</th><th>IP</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($recent_audit as $log): ?>
            <tr>
              <td><code style="font-size:13px;background:var(--bg-light);padding:3px 8px;border-radius:4px;"><?= e($log['action']) ?></code></td>
              <td style="font-size:14px;"><?= e($log['user_name'] ?? 'System') ?></td>
              <td style="font-size:13px;color:var(--text-muted);"><?= e($log['ip_address'] ?? '') ?></td>
              <td style="font-size:13px;color:var(--text-muted);"><?= time_ago($log['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

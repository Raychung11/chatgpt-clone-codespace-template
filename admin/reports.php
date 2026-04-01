<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require_admin();

$page_title = 'Platform Reports';
$active_nav = 'reports';

// ─── Date range filter ────────────────────────────────────────────────────
$range   = in_array($_GET['range'] ?? '', ['7d','30d','90d','365d','all']) ? $_GET['range'] : '30d';
$date_from = match($range) {
    '7d'   => date('Y-m-d', strtotime('-7 days')),
    '30d'  => date('Y-m-d', strtotime('-30 days')),
    '90d'  => date('Y-m-d', strtotime('-90 days')),
    '365d' => date('Y-m-d', strtotime('-365 days')),
    default => '2000-01-01',
};

$pdo = db();

// ─── Platform KPIs ────────────────────────────────────────────────────────
$kpi = [];
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role=? AND created_at>=?"); $st->execute([ROLE_MEMBER,$date_from]); $kpi['new_members']  = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role=? AND status='active'"); $st->execute([ROLE_MEMBER]); $kpi['active_members'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM redemptions WHERE status='used' AND redeemed_at>=?"); $st->execute([$date_from.' 00:00:00']); $kpi['redemptions'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(commission_amt),0) FROM commissions WHERE created_at>=?"); $st->execute([$date_from.' 00:00:00']); $kpi['revenue'] = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM deals WHERE status='active' AND deleted_at IS NULL"); $st->execute([]); $kpi['active_deals'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM merchants WHERE status='active'"); $st->execute([]); $kpi['active_merchants'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COALESCE(SUM(balance),0) FROM points_wallets"); $kpi['points_circulation'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role=? AND status='pending'"); $st->execute([ROLE_MEMBER]); $kpi['pending_verifications'] = (int)$st->fetchColumn();
} catch (PDOException $e) { error_log('[Admin reports KPI] '.$e->getMessage()); }

// ─── Member registrations by day ──────────────────────────────────────────
$member_chart = [];
try {
    $days = match($range) { '7d'=>7,'30d'=>30,'90d'=>90,'365d'=>365,default=>30 };
    // Generate date spine
    for ($i = min($days,90)-1; $i >= 0; $i--) {
        $member_chart[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    }
    $groupBy = $days > 90 ? "DATE_FORMAT(created_at,'%Y-%m')" : "DATE(created_at)";
    $st = $pdo->prepare("SELECT {$groupBy} AS d, COUNT(*) AS cnt FROM users WHERE role=? AND created_at>=? GROUP BY d ORDER BY d");
    $st->execute([ROLE_MEMBER, $date_from.' 00:00:00']);
    foreach ($st->fetchAll() as $row) {
        if (isset($member_chart[$row['d']])) $member_chart[$row['d']] = (int)$row['cnt'];
    }
} catch (PDOException $e) { error_log('[Admin reports chart] '.$e->getMessage()); }

// ─── Revenue by day ───────────────────────────────────────────────────────
$revenue_chart = [];
try {
    $days = min(match($range) { '7d'=>7,'30d'=>30,'90d'=>90,'365d'=>365,default=>30 }, 90);
    for ($i = $days-1; $i >= 0; $i--) {
        $revenue_chart[date('Y-m-d', strtotime("-{$i} days"))] = 0.0;
    }
    $st = $pdo->prepare("SELECT DATE(created_at) AS d, COALESCE(SUM(commission_amt),0) AS total FROM commissions WHERE created_at>=? GROUP BY d ORDER BY d");
    $st->execute([$date_from.' 00:00:00']);
    foreach ($st->fetchAll() as $row) {
        if (isset($revenue_chart[$row['d']])) $revenue_chart[$row['d']] = (float)$row['total'];
    }
} catch (PDOException $e) { error_log('[Admin reports revenue chart] '.$e->getMessage()); }

// ─── Top deals ────────────────────────────────────────────────────────────
$top_deals = [];
try {
    $st = $pdo->prepare("
        SELECT d.title, m.business_name AS merchant, d.redemption_count,
               COALESCE(SUM(c.commission_amt),0) AS revenue
        FROM deals d
        JOIN merchants m ON m.id=d.merchant_id
        LEFT JOIN commissions c ON c.deal_id=d.id AND c.created_at>=?
        WHERE d.deleted_at IS NULL
        GROUP BY d.id ORDER BY d.redemption_count DESC LIMIT 10
    ");
    $st->execute([$date_from.' 00:00:00']);
    $top_deals = $st->fetchAll();
} catch (PDOException $e) { error_log('[Admin reports top deals] '.$e->getMessage()); }

// ─── Top merchants ────────────────────────────────────────────────────────
$top_merchants = [];
try {
    $st = $pdo->prepare("
        SELECT m.business_name,
               COUNT(r.id) AS redemptions,
               COALESCE(SUM(c.commission_amt),0) AS revenue
        FROM merchants m
        LEFT JOIN redemptions r ON r.merchant_id=m.id AND r.status='used' AND r.redeemed_at>=?
        LEFT JOIN commissions c ON c.merchant_id=m.id AND c.created_at>=?
        WHERE m.status='active'
        GROUP BY m.id ORDER BY redemptions DESC LIMIT 10
    ");
    $st->execute([$date_from.' 00:00:00', $date_from.' 00:00:00']);
    $top_merchants = $st->fetchAll();
} catch (PDOException $e) { error_log('[Admin reports top merchants] '.$e->getMessage()); }

// ─── Category breakdown ────────────────────────────────────────────────────
$cat_breakdown = [];
try {
    $st = $pdo->prepare("
        SELECT dc.name, dc.icon, COUNT(r.id) AS redemptions, COALESCE(SUM(c.commission_amt),0) AS revenue
        FROM deal_categories dc
        JOIN deals d ON d.category_id=dc.id AND d.deleted_at IS NULL
        LEFT JOIN redemptions r ON r.deal_id=d.id AND r.status='used' AND r.redeemed_at>=?
        LEFT JOIN commissions c ON c.deal_id=d.id AND c.created_at>=?
        GROUP BY dc.id ORDER BY redemptions DESC
    ");
    $st->execute([$date_from.' 00:00:00', $date_from.' 00:00:00']);
    $cat_breakdown = $st->fetchAll();
} catch (PDOException $e) { error_log('[Admin reports categories] '.$e->getMessage()); }

// ─── Member status breakdown ──────────────────────────────────────────────
$member_status = [];
try {
    $st = $pdo->query("SELECT status, COUNT(*) AS cnt FROM users WHERE role='member' GROUP BY status");
    foreach ($st->fetchAll() as $row) { $member_status[$row['status']] = $row['cnt']; }
} catch (PDOException) {}

include __DIR__ . '/../inc/admin_layout.php';
?>

<div class="flex-between" style="margin-bottom:var(--space-xl);">
  <div>
    <h2 style="margin-bottom:var(--space-xs);">Platform Reports</h2>
    <p style="color:var(--text-muted);">Analytics and performance metrics for SilverDeals MY.</p>
  </div>
  <!-- Date range picker -->
  <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;">
    <?php foreach (['7d'=>'7 Days','30d'=>'30 Days','90d'=>'90 Days','365d'=>'1 Year','all'=>'All Time'] as $k => $label): ?>
      <a href="?range=<?= $k ?>" class="btn btn--<?= $range===$k?'primary':'muted' ?> btn--sm"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- KPI grid -->
<div class="grid grid-4" style="margin-bottom:var(--space-2xl);">
  <div class="stat-card">
    <div class="stat-card__icon">👥</div>
    <div class="stat-card__value"><?= number_format($kpi['new_members'] ?? 0) ?></div>
    <div class="stat-card__label">New Members</div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $range === 'all' ? 'All time' : 'In period' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">✅</div>
    <div class="stat-card__value"><?= number_format($kpi['redemptions'] ?? 0) ?></div>
    <div class="stat-card__label">Redemptions</div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">In period</div>
  </div>
  <div class="stat-card" style="border-color:var(--orange-primary);">
    <div class="stat-card__icon">💰</div>
    <div class="stat-card__value" style="color:var(--orange-primary);"><?= format_myr($kpi['revenue'] ?? 0) ?></div>
    <div class="stat-card__label">Commission Revenue</div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">In period</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">🪙</div>
    <div class="stat-card__value"><?= format_points($kpi['points_circulation'] ?? 0) ?></div>
    <div class="stat-card__label">Points in Circulation</div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Live total</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">🏪</div>
    <div class="stat-card__value"><?= number_format($kpi['active_merchants'] ?? 0) ?></div>
    <div class="stat-card__label">Active Merchants</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">🎁</div>
    <div class="stat-card__value"><?= number_format($kpi['active_deals'] ?? 0) ?></div>
    <div class="stat-card__label">Active Deals</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">👤</div>
    <div class="stat-card__value"><?= number_format($kpi['active_members'] ?? 0) ?></div>
    <div class="stat-card__label">Active Members</div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Total platform</div>
  </div>
  <div class="stat-card" style="<?= ($kpi['pending_verifications']??0)>0?'border-color:var(--orange-primary);':'' ?>">
    <div class="stat-card__icon">⏳</div>
    <div class="stat-card__value" style="<?= ($kpi['pending_verifications']??0)>0?'color:var(--orange-primary);':'' ?>"><?= number_format($kpi['pending_verifications'] ?? 0) ?></div>
    <div class="stat-card__label">Pending Verifications</div>
  </div>
</div>

<!-- Charts row -->
<div class="grid grid-2" style="gap:var(--space-xl);margin-bottom:var(--space-2xl);align-items:start;">

  <!-- Member registrations chart -->
  <div class="card" style="padding:var(--space-xl);">
    <h4 style="margin-bottom:var(--space-lg);">Member Registrations</h4>
    <?php
    $max_m = max(1, max(array_values($member_chart)));
    $chart_dates = array_keys($member_chart);
    $chart_vals  = array_values($member_chart);
    $n = count($chart_dates);
    ?>
    <!-- Bar chart (CSS only) -->
    <div style="display:flex;align-items:flex-end;gap:<?= $n > 30 ? '1px' : '3px' ?>;height:160px;padding-bottom:var(--space-sm);">
      <?php foreach ($chart_vals as $i => $v): ?>
        <?php $h = $max_m > 0 ? max(2, round(($v/$max_m)*140)) : 2; ?>
        <div style="flex:1;background:<?= $v>0?'var(--orange-primary)':'var(--border-color)' ?>;height:<?= $h ?>px;border-radius:2px 2px 0 0;min-width:1px;" title="<?= $chart_dates[$i] ?>: <?= $v ?> registrations"></div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);padding-top:4px;">
      <span><?= reset($chart_dates) ?></span><span><?= end($chart_dates) ?></span>
    </div>
    <div style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:var(--space-sm);">
      Total: <strong><?= array_sum($chart_vals) ?></strong> registrations
    </div>
  </div>

  <!-- Revenue chart -->
  <div class="card" style="padding:var(--space-xl);">
    <h4 style="margin-bottom:var(--space-lg);">Daily Commission Revenue</h4>
    <?php
    $max_r = max(1.0, max(array_values($revenue_chart)));
    $rev_dates = array_keys($revenue_chart);
    $rev_vals  = array_values($revenue_chart);
    ?>
    <div style="display:flex;align-items:flex-end;gap:<?= count($rev_dates)>30?'1px':'3px' ?>;height:160px;padding-bottom:var(--space-sm);">
      <?php foreach ($rev_vals as $i => $v): ?>
        <?php $h = $max_r > 0 ? max(2, round(($v/$max_r)*140)) : 2; ?>
        <div style="flex:1;background:<?= $v>0?'var(--success)':'var(--border-color)' ?>;height:<?= $h ?>px;border-radius:2px 2px 0 0;min-width:1px;" title="<?= $rev_dates[$i] ?>: <?= format_myr($v) ?>"></div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);padding-top:4px;">
      <span><?= reset($rev_dates) ?></span><span><?= end($rev_dates) ?></span>
    </div>
    <div style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:var(--space-sm);">
      Total: <strong style="color:var(--success);"><?= format_myr(array_sum($rev_vals)) ?></strong>
    </div>
  </div>
</div>

<!-- Tables row -->
<div class="grid grid-2" style="gap:var(--space-xl);margin-bottom:var(--space-2xl);align-items:start;">

  <!-- Top deals -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">Top Deals by Redemptions</h4>
    <?php if (!empty($top_deals)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>#</th><th>Deal</th><th>Merchant</th><th>Redeemed</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($top_deals as $i => $d): ?>
                <tr>
                  <td style="font-weight:700;color:var(--orange-primary);"><?= $i+1 ?></td>
                  <td style="font-size:13px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['title']) ?></td>
                  <td style="font-size:12px;color:var(--text-muted);"><?= e($d['merchant']) ?></td>
                  <td style="font-weight:700;text-align:center;"><?= number_format((int)$d['redemption_count']) ?></td>
                  <td style="font-size:13px;color:var(--success);font-weight:600;"><?= format_myr((float)$d['revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="card" style="padding:var(--space-xl);text-align:center;color:var(--text-muted);">No data yet.</div>
    <?php endif; ?>
  </div>

  <!-- Top merchants -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">Top Merchants by Redemptions</h4>
    <?php if (!empty($top_merchants)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>#</th><th>Merchant</th><th>Redemptions</th><th>Commission</th></tr></thead>
            <tbody>
              <?php foreach ($top_merchants as $i => $m): ?>
                <tr>
                  <td style="font-weight:700;color:var(--orange-primary);"><?= $i+1 ?></td>
                  <td style="font-size:13px;font-weight:600;"><?= e($m['business_name']) ?></td>
                  <td style="font-weight:700;text-align:center;"><?= number_format((int)$m['redemptions']) ?></td>
                  <td style="font-size:13px;color:var(--success);font-weight:600;"><?= format_myr((float)$m['revenue']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="card" style="padding:var(--space-xl);text-align:center;color:var(--text-muted);">No data yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Category breakdown + member status -->
<div class="grid grid-2" style="gap:var(--space-xl);align-items:start;">

  <!-- Category breakdown -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">Redemptions by Category</h4>
    <?php if (!empty($cat_breakdown)): ?>
      <div class="card" style="padding:var(--space-xl);">
        <?php $max_cat = max(1, max(array_column($cat_breakdown, 'redemptions'))); ?>
        <?php foreach ($cat_breakdown as $cat): ?>
          <div style="margin-bottom:var(--space-md);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
              <span style="font-size:14px;font-weight:600;"><?= e($cat['icon'].' '.$cat['name']) ?></span>
              <span style="font-size:13px;color:var(--text-muted);"><?= number_format((int)$cat['redemptions']) ?> <?= (int)$cat['redemptions']===1?'redemption':'redemptions' ?></span>
            </div>
            <div style="height:8px;background:var(--border-color);border-radius:4px;overflow:hidden;">
              <div style="height:100%;width:<?= $max_cat>0?round(($cat['redemptions']/$max_cat)*100):0 ?>%;background:var(--orange-primary);border-radius:4px;transition:width .3s;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card" style="padding:var(--space-xl);text-align:center;color:var(--text-muted);">No data yet.</div>
    <?php endif; ?>
  </div>

  <!-- Member status breakdown -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">Member Status Breakdown</h4>
    <div class="card" style="padding:var(--space-xl);">
      <?php
      $status_colors = ['active'=>'var(--success)','pending'=>'var(--orange-primary)','suspended'=>'var(--error)','inactive'=>'var(--text-muted)'];
      $total_members = max(1, array_sum($member_status));
      foreach ($member_status as $status => $cnt): ?>
        <div style="margin-bottom:var(--space-md);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <span style="font-size:14px;font-weight:600;text-transform:capitalize;"><?= ucfirst($status) ?></span>
            <span style="font-size:13px;font-weight:700;color:<?= $status_colors[$status] ?? 'var(--text-muted)' ?>;"><?= number_format($cnt) ?> (<?= round($cnt/$total_members*100) ?>%)</span>
          </div>
          <div style="height:8px;background:var(--border-color);border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:<?= round($cnt/$total_members*100) ?>%;background:<?= $status_colors[$status] ?? 'var(--text-muted)' ?>;border-radius:4px;"></div>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="border-top:1px solid var(--border-color);padding-top:var(--space-md);margin-top:var(--space-sm);display:flex;justify-content:space-between;font-size:14px;">
        <span style="color:var(--text-muted);">Total Members</span>
        <span style="font-weight:800;font-size:18px;color:var(--orange-primary);"><?= number_format($total_members) ?></span>
      </div>
    </div>

    <!-- Points economy -->
    <h4 style="margin-top:var(--space-xl);margin-bottom:var(--space-md);">Points Economy</h4>
    <div class="card" style="padding:var(--space-xl);">
      <?php
      $points_stats = [];
      try {
          $sources = ['welcome_bonus','referral','verification','deal_claim','first_redemption'];
          $st = $pdo->prepare("SELECT source, COALESCE(SUM(amount),0) AS total FROM points_transactions WHERE amount>0 AND source IN (" . implode(',', array_fill(0, count($sources), '?')) . ") GROUP BY source");
          $st->execute($sources);
          foreach ($st->fetchAll() as $row) { $points_stats[$row['source']] = (int)$row['total']; }

          $st = $pdo->query("SELECT COALESCE(SUM(ABS(amount)),0) FROM points_transactions WHERE amount<0");
          $points_stats['_spent'] = (int)$st->fetchColumn();
      } catch (PDOException) {}
      $source_labels = ['welcome_bonus'=>'Welcome Bonuses','referral'=>'Referral Awards','verification'=>'Verification Bonuses','deal_claim'=>'Deal Claims','first_redemption'=>'First Redemption','_spent'=>'Points Spent'];
      ?>
      <?php foreach ($points_stats as $src => $amt): ?>
        <div style="display:flex;justify-content:space-between;padding:var(--space-sm) 0;border-bottom:1px solid var(--border-color);font-size:14px;">
          <span style="color:var(--text-muted);"><?= $source_labels[$src] ?? ucfirst($src) ?></span>
          <span style="font-weight:700;color:<?= $src==='_spent'?'var(--error)':'var(--orange-primary)' ?>;">
            <?= $src==='_spent'?'−':'+' ?><?= format_points($amt) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

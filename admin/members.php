<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/points.php';
auth_require_admin();

$page_title = 'Members';
$active_nav = 'members';
$admin      = auth_user();

// ─── POST: status change / points adjust / add admin note ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action  = $_POST['action'] ?? '';
    $memberId = (int)($_POST['member_id'] ?? 0);

    if ($memberId) {
        try {
            $pdo = db();
            switch ($action) {
                case 'set_status':
                    $newStatus = in_array($_POST['new_status'] ?? '', ['active','pending','suspended','banned'])
                        ? $_POST['new_status'] : null;
                    if ($newStatus) {
                        $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND role = 'member'")
                            ->execute([$newStatus, $memberId]);

                        // If activating manually without verif, also run activate_member
                        if ($newStatus === 'active') {
                            // Check if member card exists first
                            $stmt = $pdo->prepare("SELECT id FROM member_cards WHERE user_id = ? LIMIT 1");
                            $stmt->execute([$memberId]);
                            if (!$stmt->fetch()) activate_member($memberId);
                        }

                        $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address, new_data) VALUES (?, 'admin.set_member_status', 'user', ?, ?, ?)")
                            ->execute([$admin['id'], $memberId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode(['status'=>$newStatus])]);

                        auth_set_flash('success', 'Member status updated to ' . $newStatus . '.');
                    }
                    break;

                case 'adjust_points':
                    $amount = (int)($_POST['points_amount'] ?? 0);
                    $desc   = trim($_POST['points_desc'] ?? 'Admin manual adjustment');
                    if ($amount !== 0) {
                        $ok = points_adjust($memberId, $amount, $desc, (int)$admin['id']);
                        auth_set_flash($ok ? 'success' : 'error', $ok ? "Points adjusted by {$amount}." : 'Points adjustment failed.');
                    }
                    break;

                case 'add_note':
                    $note = trim($_POST['note'] ?? '');
                    if ($note) {
                        $pdo->prepare("INSERT INTO admin_notes (user_id, admin_id, note, created_at) VALUES (?, ?, ?, NOW())")
                            ->execute([$memberId, $admin['id'], $note]);
                        auth_set_flash('success', 'Note added.');
                    }
                    break;
            }
        } catch (PDOException $e) {
            error_log('[Admin Members action] ' . $e->getMessage());
            auth_set_flash('error', 'Action failed. Please try again.');
        }
    }
    redirect('/admin/members.php' . (isset($_GET['view']) ? '?view=' . (int)$_GET['view'] : ''));
}

// ─── Single member view ───────────────────────────────────────────────────────
if (isset($_GET['view'])) {
    $viewId = (int)$_GET['view'];
    $member = $profile = $card = $wallet = $verif = null;
    $txns = $referrals = $redemptions = [];

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'member' LIMIT 1");
        $stmt->execute([$viewId]);
        $member = $stmt->fetch();
        if (!$member) { auth_set_flash('error','Member not found.'); redirect('/admin/members.php'); }

        $stmt = $pdo->prepare("SELECT * FROM member_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$viewId]);
        $profile = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM member_cards WHERE user_id = ? LIMIT 1");
        $stmt->execute([$viewId]);
        $card = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM points_wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$viewId]);
        $wallet = $stmt->fetch() ?: ['balance'=>0,'lifetime_earned'=>0,'lifetime_spent'=>0];

        $stmt = $pdo->prepare("SELECT * FROM senior_verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
        $stmt->execute([$viewId]);
        $verif = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT pt.*, u.name AS referred_name
            FROM referrals pt
            LEFT JOIN users u ON u.id = pt.referred_id
            WHERE pt.referrer_id = ? ORDER BY pt.created_at DESC LIMIT 10
        ");
        $stmt->execute([$viewId]);
        $referrals = $stmt->fetchAll();

        $txns = $pdo->prepare("SELECT * FROM points_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
        $txns->execute([$viewId]);
        $txns = $txns->fetchAll();

        $stmt = $pdo->prepare("
            SELECT r.*, d.title AS deal_title, m.business_name AS merchant
            FROM redemptions r
            JOIN deals d ON d.id = r.deal_id
            JOIN merchants m ON m.id = r.merchant_id
            WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 10
        ");
        $stmt->execute([$viewId]);
        $redemptions = $stmt->fetchAll();

    } catch (PDOException $e) { error_log('[Admin Member view] ' . $e->getMessage()); }

    $page_title = 'Member: ' . ($member['name'] ?? '');
    include __DIR__ . '/../inc/admin_layout.php';
    ?>

    <div style="margin-bottom:var(--space-lg);">
      <a href="/admin/members.php" style="color:var(--text-muted);font-size:15px;">← Back to Members</a>
    </div>

    <!-- Member header -->
    <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-xl);">
      <div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">
        <div style="display:flex;align-items:center;gap:var(--space-lg);">
          <div style="width:72px;height:72px;border-radius:50%;background:var(--orange-bg);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:var(--orange-primary);flex-shrink:0;overflow:hidden;">
            <?php if ($member['avatar']): ?>
              <img src="<?= e($member['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <?= strtoupper(mb_substr($member['name'],0,2)) ?>
            <?php endif; ?>
          </div>
          <div>
            <h2 style="margin-bottom:4px;"><?= e($member['name']) ?></h2>
            <div style="color:var(--text-muted);font-size:15px;"><?= e($member['email']) ?> · <?= e($member['phone']) ?></div>
            <div style="margin-top:var(--space-sm);display:flex;gap:var(--space-sm);flex-wrap:wrap;">
              <span class="badge badge--<?= match($member['status']) { 'active'=>'success','pending'=>'warning',default=>'error' } ?>"><?= ucfirst($member['status']) ?></span>
              <?php if ($profile && $profile['member_number']): ?><span class="badge badge--muted"><?= e($profile['member_number']) ?></span><?php endif; ?>
              <?php if ($profile && $profile['referral_code']): ?><span class="badge badge--orange">Code: <?= e($profile['referral_code']) ?></span><?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Quick stats -->
        <div class="grid grid-2" style="gap:var(--space-md);">
          <div class="stat-card" style="padding:var(--space-md);">
            <div class="stat-card__number" style="font-size:24px;"><?= number_format((int)$wallet['balance']) ?></div>
            <div class="stat-card__label">Points Balance</div>
          </div>
          <div class="stat-card" style="padding:var(--space-md);border-left-color:var(--success);">
            <div class="stat-card__number" style="font-size:24px;color:var(--success);"><?= number_format((int)$wallet['lifetime_earned']) ?></div>
            <div class="stat-card__label">Total Earned</div>
          </div>
          <div class="stat-card" style="padding:var(--space-md);border-left-color:#3B82F6;">
            <div class="stat-card__number" style="font-size:24px;color:#3B82F6;"><?= count($referrals) ?></div>
            <div class="stat-card__label">Referrals</div>
          </div>
          <div class="stat-card" style="padding:var(--space-md);border-left-color:#8B5CF6;">
            <div class="stat-card__number" style="font-size:24px;color:#8B5CF6;"><?= count($redemptions) ?></div>
            <div class="stat-card__label">Redemptions</div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">
      <!-- Left: Details + Actions -->
      <div style="display:flex;flex-direction:column;gap:var(--space-lg);">

        <!-- Status control -->
        <div class="card" style="padding:var(--space-lg);">
          <h4 style="margin-bottom:var(--space-md);">⚙️ Account Actions</h4>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action"    value="set_status">
            <input type="hidden" name="member_id" value="<?= $viewId ?>">
            <div class="form-group">
              <label class="form-label">Set Account Status</label>
              <select name="new_status" class="form-control">
                <?php foreach (['active','pending','suspended','banned'] as $s): ?>
                  <option value="<?= $s ?>" <?= $member['status']===$s ? 'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn--primary btn--sm" data-confirm="Change member status?">Apply Status Change</button>
          </form>
        </div>

        <!-- Points adjust -->
        <div class="card" style="padding:var(--space-lg);">
          <h4 style="margin-bottom:var(--space-md);">💰 Adjust Points</h4>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action"    value="adjust_points">
            <input type="hidden" name="member_id" value="<?= $viewId ?>">
            <div class="form-group">
              <label class="form-label">Amount (negative to deduct)</label>
              <input type="number" name="points_amount" class="form-control" placeholder="e.g. 100 or -50" required>
            </div>
            <div class="form-group">
              <label class="form-label">Reason / Note</label>
              <input type="text" name="points_desc" class="form-control" placeholder="Reason for adjustment">
            </div>
            <button type="submit" class="btn btn--secondary btn--sm" data-confirm="Adjust this member's points?">Apply Adjustment</button>
          </form>
        </div>

        <!-- Verification -->
        <?php if ($verif): ?>
        <div class="card" style="padding:var(--space-lg);">
          <h4 style="margin-bottom:var(--space-md);">✅ Verification</h4>
          <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-md);">
            <span class="badge badge--<?= $verif['status']==='approved'?'success':($verif['status']==='rejected'?'error':'warning') ?>">
              <?= ucfirst($verif['status']) ?>
            </span>
            <span class="badge badge--muted">📄 <?= ucfirst($verif['document_type']) ?></span>
            <?php if ($verif['birth_year']): ?>
              <span class="badge badge--muted">Born <?= $verif['birth_year'] ?></span>
            <?php endif; ?>
          </div>
          <?php if ($verif['status'] === 'pending'): ?>
            <a href="/admin/verifications.php" class="btn btn--primary btn--sm">Review Verification →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Profile details -->
        <?php if ($profile): ?>
        <div class="card" style="padding:var(--space-lg);">
          <h4 style="margin-bottom:var(--space-md);">👤 Profile</h4>
          <table style="width:100%;font-size:14px;border-collapse:collapse;">
            <?php
            $details = [
              'DOB'      => $profile['date_of_birth'] ? date('d M Y', strtotime($profile['date_of_birth'])) : '—',
              'Gender'   => $profile['gender'] ?? '—',
              'Address'  => trim(($profile['address_line1'] ?? '') . ' ' . ($profile['address_line2'] ?? '')),
              'City'     => $profile['city'] ?? '—',
              'State'    => $profile['state'] ?? '—',
              'Postcode' => $profile['postcode'] ?? '—',
              'Referred by' => $profile['referred_by'] ? 'User #' . $profile['referred_by'] : 'Direct signup',
              'Joined'   => date('d M Y', strtotime($member['created_at'])),
              'Last Login' => $member['last_login_at'] ? time_ago($member['last_login_at']) : 'Never',
            ];
            foreach ($details as $label => $val):
            ?>
              <tr>
                <td style="padding:6px 0;color:var(--text-muted);width:120px;"><?= $label ?></td>
                <td style="padding:6px 0;font-weight:500;"><?= e($val) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right: Transactions + Redemptions + Referrals -->
      <div style="display:flex;flex-direction:column;gap:var(--space-lg);">

        <!-- Points transactions -->
        <div>
          <h4 style="margin-bottom:var(--space-md);">💰 Points History</h4>
          <div class="card" style="overflow:hidden;">
            <?php if (!empty($txns)): ?>
              <div class="table-wrap">
                <table class="table">
                  <thead><tr><th>Description</th><th style="text-align:right;">Pts</th><th>Balance</th><th>Date</th></tr></thead>
                  <tbody>
                    <?php foreach ($txns as $tx): ?>
                      <tr>
                        <td style="font-size:13px;"><?= e($tx['description']) ?></td>
                        <td style="text-align:right;font-weight:700;font-size:13px;color:<?= $tx['amount']>0?'var(--success)':'var(--error)' ?>;">
                          <?= $tx['amount']>0?'+':'' ?><?= $tx['amount'] ?>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= number_format($tx['balance_after']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= time_ago($tx['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?><div style="padding:var(--space-lg);text-align:center;color:var(--text-muted);">No transactions.</div><?php endif; ?>
          </div>
        </div>

        <!-- Recent redemptions -->
        <div>
          <h4 style="margin-bottom:var(--space-md);">🎫 Redemptions</h4>
          <div class="card" style="overflow:hidden;">
            <?php if (!empty($redemptions)): ?>
              <div class="table-wrap">
                <table class="table">
                  <thead><tr><th>Deal</th><th>Code</th><th>Status</th><th>Date</th></tr></thead>
                  <tbody>
                    <?php foreach ($redemptions as $r): ?>
                      <tr>
                        <td style="font-size:13px;">
                          <div style="font-weight:600;"><?= e($r['deal_title']) ?></div>
                          <div style="color:var(--text-muted);"><?= e($r['merchant']) ?></div>
                        </td>
                        <td><code style="font-size:12px;"><?= e($r['voucher_code']) ?></code></td>
                        <td><span class="badge badge--<?= match($r['status']){'active'=>'success','used'=>'muted','expired'=>'error',default=>'muted'} ?>" style="font-size:11px;"><?= ucfirst($r['status']) ?></span></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= time_ago($r['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?><div style="padding:var(--space-lg);text-align:center;color:var(--text-muted);">No redemptions.</div><?php endif; ?>
          </div>
        </div>

        <!-- Referrals -->
        <?php if (!empty($referrals)): ?>
        <div>
          <h4 style="margin-bottom:var(--space-md);">🤝 Referrals Made</h4>
          <div class="card" style="overflow:hidden;">
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Referred Member</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                  <?php foreach ($referrals as $ref): ?>
                    <tr>
                      <td style="font-size:14px;"><?= e($ref['referred_name'] ?? 'User #' . $ref['referred_id']) ?></td>
                      <td><span class="badge badge--<?= match($ref['status']){'rewarded'=>'success','pending'=>'warning',default=>'muted'} ?>" style="font-size:12px;"><?= ucfirst($ref['status']) ?></span></td>
                      <td style="font-size:12px;color:var(--text-muted);"><?= time_ago($ref['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php include __DIR__ . '/../inc/admin_layout_end.php'; return; ?>
<?php } // end single view ?>

<!-- ─── List view ─────────────────────────────────────────────────────────── -->
<?php
$filter  = in_array($_GET['status'] ?? '', ['all','active','pending','suspended','banned']) ? $_GET['status'] : 'all';
$page_title = 'Members';
$active_nav = 'members';
$search  = trim($_GET['q'] ?? '');
$per_page = 25;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$members = [];
$total   = 0;
$status_counts = [];

try {
    $pdo = db();

    foreach (['active','pending','suspended','banned'] as $s) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='member' AND status=?");
        $st->execute([$s]);
        $status_counts[$s] = (int)$st->fetchColumn();
    }
    $status_counts['all'] = array_sum($status_counts);

    $where  = ["u.role = 'member'"];
    $params = [];
    if ($filter !== 'all') { $where[] = 'u.status = ?'; $params[] = $filter; }
    if ($search) {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR mp.member_number LIKE ? OR mp.referral_code LIKE ?)';
        $params  = array_merge($params, ["%{$search}%","%{$search}%","%{$search}%","%{$search}%","%{$search}%"]);
    }
    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN member_profiles mp ON mp.user_id = u.id {$whereSQL}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.status, u.last_login_at, u.created_at,
               mp.member_number, mp.referral_code, mp.profile_completed,
               pw.balance AS points,
               sv.status AS verif_status
        FROM users u
        LEFT JOIN member_profiles mp ON mp.user_id = u.id
        LEFT JOIN points_wallets pw  ON pw.user_id  = u.id
        LEFT JOIN senior_verifications sv ON sv.user_id = u.id AND sv.status != 'rejected'
        {$whereSQL}
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $members = $stmt->fetchAll();

} catch (PDOException $e) { error_log('[Admin Members list] ' . $e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

include __DIR__ . '/../inc/admin_layout.php';
?>

<!-- Filters -->
<div class="flex-between" style="margin-bottom:var(--space-lg);flex-wrap:wrap;gap:var(--space-md);">
  <div class="pill-list">
    <?php foreach (['all'=>'All','active'=>'✅ Active','pending'=>'⏳ Pending','suspended'=>'⚠ Suspended','banned'=>'🚫 Banned'] as $key=>$label): ?>
      <a href="?status=<?= $key ?>" class="pill <?= $filter===$key?'active':'' ?>">
        <?= $label ?>
        <span class="badge badge--<?= $key==='pending'?'orange':'muted' ?>" style="font-size:11px;margin-left:4px;"><?= $status_counts[$key] ?? 0 ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <form method="GET" style="display:flex;gap:var(--space-sm);">
    <input type="hidden" name="status" value="<?= e($filter) ?>">
    <input type="text" name="q" class="form-control" style="max-width:260px;min-height:44px;"
           value="<?= e($search) ?>" placeholder="Search name, email, phone, code…">
    <button class="btn btn--muted btn--sm">Search</button>
    <?php if ($search): ?><a href="?status=<?= e($filter) ?>" class="btn btn--muted btn--sm">✕</a><?php endif; ?>
  </form>
</div>

<!-- Table -->
<?php if (!empty($members)): ?>
<div class="card" style="overflow:hidden;">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Member</th>
          <th>Phone</th>
          <th>Member No.</th>
          <th>Status</th>
          <th>Verification</th>
          <th>Points</th>
          <th>Joined</th>
          <th>Last Login</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:15px;"><?= e($m['name']) ?></div>
              <div style="font-size:13px;color:var(--text-muted);"><?= e($m['email']) ?></div>
            </td>
            <td style="font-size:14px;"><?= e($m['phone']) ?></td>
            <td>
              <?php if ($m['member_number']): ?>
                <code style="font-size:12px;background:var(--bg-light);padding:2px 6px;border-radius:4px;"><?= e($m['member_number']) ?></code>
              <?php else: ?>
                <span style="color:var(--text-light);font-size:13px;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge--<?= match($m['status']){'active'=>'success','pending'=>'warning','suspended','banned'=>'error',default=>'muted'} ?>">
                <?= ucfirst($m['status']) ?>
              </span>
            </td>
            <td>
              <span class="badge badge--<?= match($m['verif_status'] ?? ''){'approved'=>'success','pending'=>'warning','rejected'=>'error',default=>'muted'} ?>" style="font-size:12px;">
                <?= $m['verif_status'] ? ucfirst($m['verif_status']) : 'Not submitted' ?>
              </span>
            </td>
            <td style="font-weight:600;color:var(--orange-primary);"><?= number_format((int)($m['points'] ?? 0)) ?></td>
            <td style="font-size:13px;color:var(--text-muted);"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
            <td style="font-size:13px;color:var(--text-muted);"><?= $m['last_login_at'] ? time_ago($m['last_login_at']) : 'Never' ?></td>
            <td>
              <a href="?view=<?= $m['id'] ?>" class="btn btn--secondary btn--sm">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-top:var(--space-md);flex-wrap:wrap;gap:var(--space-md);">
  <div style="font-size:14px;color:var(--text-muted);">
    Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?> members
  </div>
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page_num > 1): ?><a href="?status=<?= $filter ?>&page=<?= $page_num-1 ?>&q=<?= urlencode($search) ?>" class="pagination__btn">← Prev</a><?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
        <a href="?status=<?= $filter ?>&page=<?= $i ?>&q=<?= urlencode($search) ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?><a href="?status=<?= $filter ?>&page=<?= $page_num+1 ?>&q=<?= urlencode($search) ?>" class="pagination__btn">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">👥</div>
    <h3 class="empty-state__title"><?= $search ? 'No results for "' . e($search) . '"' : 'No members found' ?></h3>
    <p class="empty-state__text">Try a different filter or search term.</p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

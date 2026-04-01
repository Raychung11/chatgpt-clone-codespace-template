<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/points.php';
auth_require_admin();

$page_title = 'Senior Verifications';
$active_nav = 'verifications';
$admin      = auth_user();
$errors     = [];

// ─── POST actions: approve / reject ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    $action = $_POST['action'] ?? '';
    $verifId = (int)($_POST['verif_id'] ?? 0);
    $userId  = (int)($_POST['user_id']  ?? 0);

    if ($verifId && $userId && in_array($action, ['approve','reject'])) {
        try {
            $pdo = db();

            if ($action === 'approve') {
                $pdo->prepare("
                    UPDATE senior_verifications
                    SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ")->execute([$admin['id'], $verifId]);

                // Activate the member (points, card, referral rewards)
                activate_member($userId);

                // Audit
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address) VALUES (?, 'admin.approve_verification', 'senior_verification', ?, ?)")
                    ->execute([$admin['id'], $verifId, $_SERVER['REMOTE_ADDR'] ?? null]);

                auth_set_flash('success', 'Member approved and activated. Welcome bonus points have been awarded.');

            } elseif ($action === 'reject') {
                $reason = trim($_POST['reject_reason'] ?? '');
                if (empty($reason)) {
                    auth_set_flash('error', 'Please provide a rejection reason.');
                } else {
                    $pdo->prepare("
                        UPDATE senior_verifications
                        SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), reject_reason = ?
                        WHERE id = ? AND status = 'pending'
                    ")->execute([$admin['id'], $reason, $verifId]);

                    // Notify member
                    notify($userId,
                        'Verification Rejected',
                        'Your verification was not approved. Reason: ' . $reason . '. Please resubmit with correct documents.',
                        'info');

                    // Audit
                    $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address, new_data) VALUES (?, 'admin.reject_verification', 'senior_verification', ?, ?, ?)")
                        ->execute([$admin['id'], $verifId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode(['reason'=>$reason])]);

                    auth_set_flash('warning', 'Verification rejected. Member has been notified.');
                }
            }

        } catch (PDOException $e) {
            error_log('[Admin Verif] ' . $e->getMessage());
            auth_set_flash('error', 'Action failed. Please try again.');
        }
    }
    redirect('/admin/verifications.php');
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$filter  = in_array($_GET['status'] ?? '', ['pending','approved','rejected']) ? $_GET['status'] : 'pending';
$search  = trim($_GET['q'] ?? '');
$per_page = 20;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

// ─── Query ────────────────────────────────────────────────────────────────────
$verifications = [];
$total = 0;
try {
    $pdo = db();

    $where  = ['sv.status = ?'];
    $params = [$filter];
    if ($search) {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $params  = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
    }
    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM senior_verifications sv
        JOIN users u ON u.id = sv.user_id
        {$whereSQL}
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT sv.*,
               u.name   AS member_name,
               u.email  AS member_email,
               u.phone  AS member_phone,
               u.status AS member_status,
               mp.member_number,
               rev.name AS reviewed_by_name
        FROM senior_verifications sv
        JOIN users u ON u.id = sv.user_id
        LEFT JOIN member_profiles mp ON mp.user_id = sv.user_id
        LEFT JOIN users rev ON rev.id = sv.reviewed_by
        {$whereSQL}
        ORDER BY sv.submitted_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $verifications = $stmt->fetchAll();

    // Counts for tab badges
    $counts = [];
    foreach (['pending','approved','rejected'] as $s) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM senior_verifications WHERE status = ?");
        $st->execute([$s]);
        $counts[$s] = (int)$st->fetchColumn();
    }
} catch (PDOException $e) { error_log('[Admin Verif list] ' . $e->getMessage()); $counts = ['pending'=>0,'approved'=>0,'rejected'=>0]; }

$total_pages = (int)ceil($total / $per_page);

include __DIR__ . '/../inc/admin_layout.php';
?>

<!-- ── Filter tabs + search ──────────────────────────────────────────────── -->
<div class="flex-between" style="margin-bottom:var(--space-lg);flex-wrap:wrap;gap:var(--space-md);">
  <div class="pill-list">
    <?php foreach (['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected'] as $key=>$label): ?>
      <a href="?status=<?= $key ?>" class="pill <?= $filter===$key ? 'active' : '' ?>">
        <?= $label ?>
        <?php if ($counts[$key] ?? 0): ?>
          <span class="badge badge--<?= $key==='pending' ? 'orange' : 'muted' ?>" style="font-size:12px;margin-left:4px;"><?= $counts[$key] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <form method="GET" style="display:flex;gap:var(--space-sm);">
    <input type="hidden" name="status" value="<?= e($filter) ?>">
    <input type="text" name="q" class="form-control" style="max-width:240px;min-height:44px;"
           value="<?= e($search) ?>" placeholder="Search name, email, phone…">
    <button class="btn btn--muted btn--sm">Search</button>
    <?php if ($search): ?><a href="?status=<?= e($filter) ?>" class="btn btn--muted btn--sm">✕ Clear</a><?php endif; ?>
  </form>
</div>

<!-- ── Results ───────────────────────────────────────────────────────────── -->
<?php if (!empty($verifications)): ?>
<div style="display:flex;flex-direction:column;gap:var(--space-md);">
  <?php foreach ($verifications as $v): ?>
    <div class="card" style="padding:var(--space-lg);<?= $v['status']==='pending' ? 'border-left:4px solid var(--warning);' : ($v['status']==='approved' ? 'border-left:4px solid var(--success);' : 'border-left:4px solid var(--error);') ?>">
      <div class="grid grid-2" style="gap:var(--space-lg);align-items:start;">

        <!-- Member info -->
        <div>
          <div style="display:flex;align-items:center;gap:var(--space-md);margin-bottom:var(--space-md);">
            <div style="width:52px;height:52px;border-radius:50%;background:var(--orange-bg);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--orange-primary);flex-shrink:0;">
              <?= strtoupper(mb_substr($v['member_name'], 0, 2)) ?>
            </div>
            <div>
              <div style="font-size:18px;font-weight:700;"><?= e($v['member_name']) ?></div>
              <div style="font-size:13px;color:var(--text-muted);"><?= e($v['member_email']) ?> · <?= e($v['member_phone']) ?></div>
              <?php if ($v['member_number']): ?>
                <div style="font-size:12px;color:var(--text-muted);">No: <?= e($v['member_number']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:var(--space-sm);margin-bottom:var(--space-md);">
            <span class="badge badge--muted">
              📄 <?= ucfirst($v['document_type']) ?>
            </span>
            <?php if ($v['birth_year']): ?>
              <span class="badge badge--muted">
                🎂 Born <?= $v['birth_year'] ?> (Age <?= date('Y') - $v['birth_year'] ?>)
              </span>
            <?php endif; ?>
            <span class="badge badge--<?= $v['status']==='pending' ? 'warning' : ($v['status']==='approved' ? 'success' : 'error') ?>">
              <?= ucfirst($v['status']) ?>
            </span>
          </div>

          <div style="font-size:13px;color:var(--text-muted);">
            Submitted <?= date('d M Y, g:ia', strtotime($v['submitted_at'])) ?>
            <?php if ($v['reviewed_at']): ?>
              · Reviewed by <?= e($v['reviewed_by_name'] ?? 'Admin') ?> on <?= date('d M Y', strtotime($v['reviewed_at'])) ?>
            <?php endif; ?>
          </div>

          <?php if ($v['status'] === 'rejected' && $v['reject_reason']): ?>
            <div style="margin-top:var(--space-sm);font-size:14px;background:var(--error-bg);color:#991B1B;padding:var(--space-sm) var(--space-md);border-radius:var(--radius-sm);">
              ❌ Rejection reason: <?= e($v['reject_reason']) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Documents + actions -->
        <div>
          <!-- Document thumbnails -->
          <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-md);">
            <?php
            $docs = [
              'document_front' => 'Front',
              'document_back'  => 'Back',
              'selfie'         => 'Selfie',
            ];
            foreach ($docs as $field => $label):
              if (empty($v[$field])) continue;
            ?>
              <a href="<?= e($v[$field]) ?>" target="_blank" rel="noopener"
                 style="display:block;border-radius:var(--radius-sm);overflow:hidden;border:2px solid var(--border-light);flex-shrink:0;">
                <img src="<?= e($v[$field]) ?>" alt="<?= $label ?>"
                     style="width:90px;height:70px;object-fit:cover;display:block;">
                <div style="font-size:11px;text-align:center;padding:3px;background:var(--bg-light);color:var(--text-muted);"><?= $label ?></div>
              </a>
            <?php endforeach; ?>
            <?php if (!$v['document_front'] && !$v['document_back'] && !$v['selfie']): ?>
              <span style="font-size:14px;color:var(--text-muted);">No documents uploaded</span>
            <?php endif; ?>
          </div>

          <!-- Actions (only for pending) -->
          <?php if ($v['status'] === 'pending'): ?>
            <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
              <!-- Approve -->
              <form method="POST" onsubmit="return confirm('Approve this verification? The member will be activated and receive welcome points.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action"    value="approve">
                <input type="hidden" name="verif_id"  value="<?= $v['id'] ?>">
                <input type="hidden" name="user_id"   value="<?= $v['user_id'] ?>">
                <button type="submit" class="btn btn--primary btn--full">✅ Approve & Activate Member</button>
              </form>

              <!-- Reject (inline form) -->
              <details style="margin-top:var(--space-xs);">
                <summary class="btn btn--muted btn--full" style="cursor:pointer;list-style:none;text-align:center;">❌ Reject</summary>
                <form method="POST" style="margin-top:var(--space-sm);">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action"   value="reject">
                  <input type="hidden" name="verif_id" value="<?= $v['id'] ?>">
                  <input type="hidden" name="user_id"  value="<?= $v['user_id'] ?>">
                  <div class="form-group" style="margin-bottom:var(--space-sm);">
                    <textarea name="reject_reason" class="form-control" rows="3"
                              placeholder="Reason for rejection (member will see this)…" required
                              style="font-size:15px;"></textarea>
                  </div>
                  <button type="submit" class="btn btn--full" style="background:var(--error);color:#fff;border-color:var(--error);">
                    Confirm Rejection
                  </button>
                </form>
              </details>
            </div>
          <?php endif; ?>

          <!-- View member profile link -->
          <div style="margin-top:var(--space-sm);text-align:right;">
            <a href="/admin/members.php?view=<?= $v['user_id'] ?>" style="font-size:13px;color:var(--text-muted);">View full profile →</a>
          </div>
        </div>

      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
  <div class="pagination" style="margin-top:var(--space-xl);justify-content:center;">
    <?php if ($page_num > 1): ?><a href="?status=<?= $filter ?>&page=<?= $page_num-1 ?>&q=<?= urlencode($search) ?>" class="pagination__btn">← Prev</a><?php endif; ?>
    <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
      <a href="?status=<?= $filter ?>&page=<?= $i ?>&q=<?= urlencode($search) ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page_num < $total_pages): ?><a href="?status=<?= $filter ?>&page=<?= $page_num+1 ?>&q=<?= urlencode($search) ?>" class="pagination__btn">Next →</a><?php endif; ?>
  </div>
<?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon"><?= $filter === 'pending' ? '⏳' : '✅' ?></div>
    <h3 class="empty-state__title">
      <?php if ($search): ?>No results for "<?= e($search) ?>"<?php else: ?>No <?= $filter ?> verifications<?php endif; ?>
    </h3>
    <p class="empty-state__text">
      <?= $filter === 'pending' ? 'All caught up! No pending verifications.' : 'Try a different filter.' ?>
    </p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/admin_layout_end.php'; ?>

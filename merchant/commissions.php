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
} catch (PDOException $e) { error_log('[Merchant commissions load] '.$e->getMessage()); }

if (!$merchant) redirect('/merchant/dashboard.php');
$mid = $merchant['id'];

$page_title = 'Commissions & Payouts';
$active_nav = 'commissions';

// ─── POST: Payout request ─────────────────────────────────────────────────
$payout_msg = '';
$payout_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    csrf_abort();
    $bank_name    = trim($_POST['bank_name'] ?? '');
    $account_no   = trim($_POST['account_no'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if (!$bank_name || !$account_no || !$account_name) {
        $payout_err = 'Please fill in all bank details.';
    } else {
        // Check for pending payout
        try {
            $existing = db()->prepare("SELECT id FROM payout_requests WHERE merchant_id=? AND status='pending' LIMIT 1");
            $existing->execute([$mid]);
            if ($existing->fetch()) {
                $payout_err = 'You already have a pending payout request. Please wait for it to be processed.';
            } else {
                // Calculate available balance
                $paid_out = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE merchant_id=? AND status='paid'");
                $paid_out->execute([$mid]);
                $paid_amount = (float)$paid_out->fetchColumn();

                $total_comm = db()->prepare("SELECT COALESCE(SUM(commission_amt),0) FROM commissions WHERE merchant_id=?");
                $total_comm->execute([$mid]);
                $total_commission = (float)$total_comm->fetchColumn();

                $available = round($total_commission - $paid_amount, 2);
                if ($available < 50.00) {
                    $payout_err = 'Minimum payout is RM 50.00. Your available balance is ' . format_myr($available) . '.';
                } else {
                    db()->prepare("
                        INSERT INTO payout_requests(merchant_id,amount,bank_name,account_no,account_name,notes,status,created_at)
                        VALUES(?,?,?,?,?,?,'pending',NOW())
                    ")->execute([$mid, $available, $bank_name, $account_no, $account_name, $notes]);

                    db()->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'merchant.request_payout','merchant',?,?)")
                        ->execute([$user['id'], $mid, $_SERVER['REMOTE_ADDR'] ?? null]);

                    $payout_msg = 'Payout request submitted for ' . format_myr($available) . '. Our team will process it within 3–5 business days.';
                }
            }
        } catch (PDOException $e) {
            error_log('[Merchant payout request] '.$e->getMessage());
            $payout_err = 'Failed to submit payout request. Please try again.';
        }
    }
}

// ─── Summary stats ────────────────────────────────────────────────────────
$summary = ['total_commission' => 0.0, 'paid_out' => 0.0, 'pending_payout' => 0.0, 'available' => 0.0, 'this_month' => 0.0];
try {
    $pdo = db();

    $st = $pdo->prepare("SELECT COALESCE(SUM(commission_amt),0) FROM commissions WHERE merchant_id=?");
    $st->execute([$mid]); $summary['total_commission'] = (float)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE merchant_id=? AND status='paid'");
    $st->execute([$mid]); $summary['paid_out'] = (float)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE merchant_id=? AND status='pending'");
    $st->execute([$mid]); $summary['pending_payout'] = (float)$st->fetchColumn();

    $summary['available'] = round($summary['total_commission'] - $summary['paid_out'] - $summary['pending_payout'], 2);

    $st = $pdo->prepare("SELECT COALESCE(SUM(commission_amt),0) FROM commissions c WHERE c.merchant_id=? AND MONTH(c.created_at)=MONTH(NOW()) AND YEAR(c.created_at)=YEAR(NOW())");
    $st->execute([$mid]); $summary['this_month'] = (float)$st->fetchColumn();
} catch (PDOException $e) { error_log('[Merchant commissions summary] '.$e->getMessage()); }

// ─── Commission history ────────────────────────────────────────────────────
$per_page = 20;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;
$commissions = []; $total = 0;
try {
    $pdo = db();
    $st  = $pdo->prepare("SELECT COUNT(*) FROM commissions WHERE merchant_id=?");
    $st->execute([$mid]); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT c.*,
               r.voucher_code,
               d.title AS deal_title,
               u.name AS member_name
        FROM commissions c
        JOIN redemptions r ON r.id=c.redemption_id
        JOIN deals d ON d.id=r.deal_id
        JOIN users u ON u.id=r.user_id
        WHERE c.merchant_id=?
        ORDER BY c.created_at DESC LIMIT ? OFFSET ?
    ");
    $st->execute([$mid, $per_page, $offset]);
    $commissions = $st->fetchAll();
} catch (PDOException $e) { error_log('[Merchant commissions list] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

// ─── Payout requests ──────────────────────────────────────────────────────
$payout_requests = [];
try {
    $st = db()->prepare("SELECT * FROM payout_requests WHERE merchant_id=? ORDER BY created_at DESC LIMIT 10");
    $st->execute([$mid]);
    $payout_requests = $st->fetchAll();
} catch (PDOException) {}

include __DIR__ . '/../inc/merchant_layout.php';
?>

<div style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-xs);">Commissions & Payouts</h2>
  <p style="color:var(--text-muted);">Track your earnings from member redemptions and request payouts.</p>
</div>

<!-- Summary stats -->
<div class="grid grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card__icon">💰</div>
    <div class="stat-card__value"><?= format_myr($summary['total_commission']) ?></div>
    <div class="stat-card__label">Total Earned</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">📅</div>
    <div class="stat-card__value"><?= format_myr($summary['this_month']) ?></div>
    <div class="stat-card__label">This Month</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon">⏳</div>
    <div class="stat-card__value"><?= format_myr($summary['pending_payout']) ?></div>
    <div class="stat-card__label">Pending Payout</div>
  </div>
  <div class="stat-card" style="border-color:var(--orange-primary);">
    <div class="stat-card__icon">🏦</div>
    <div class="stat-card__value" style="color:var(--orange-primary);"><?= format_myr($summary['available']) ?></div>
    <div class="stat-card__label">Available to Withdraw</div>
  </div>
</div>

<?php if ($payout_msg): ?>
  <div class="alert alert--success" style="margin-bottom:var(--space-xl);"><span class="alert__icon">✓</span><span><?= e($payout_msg) ?></span></div>
<?php endif; ?>
<?php if ($payout_err): ?>
  <div class="alert alert--error" style="margin-bottom:var(--space-xl);"><span class="alert__icon">✕</span><span><?= e($payout_err) ?></span></div>
<?php endif; ?>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- Commission rate info + payout request -->
  <div>
    <!-- Commission rate card -->
    <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-lg);">
      <h4 style="margin-bottom:var(--space-md);">Your Commission Rate</h4>
      <div style="font-size:48px;font-weight:800;color:var(--orange-primary);margin-bottom:var(--space-sm);"><?= (float)$merchant['commission_pct'] ?>%</div>
      <p style="color:var(--text-muted);font-size:14px;">This percentage is deducted from each deal redemption as the platform fee. Contact us to negotiate a custom rate.</p>
      <a href="<?= whatsapp_url('Hi, I\'m '.e($merchant['business_name']).'. I\'d like to discuss my commission rate.') ?>" target="_blank" class="btn btn--muted btn--sm" style="margin-top:var(--space-md);">💬 Contact Support</a>
    </div>

    <!-- Payout request -->
    <div class="card" style="padding:var(--space-xl);">
      <h4 style="margin-bottom:var(--space-xs);">Request Payout</h4>
      <p style="color:var(--text-muted);font-size:14px;margin-bottom:var(--space-lg);">Minimum withdrawal: <strong>RM 50.00</strong>. Available: <strong style="color:var(--orange-primary);"><?= format_myr($summary['available']) ?></strong></p>

      <?php if ($summary['available'] >= 50): ?>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label" for="bank_name">Bank Name <span style="color:var(--error);">*</span></label>
            <select id="bank_name" name="bank_name" class="form-control" required>
              <option value="">— Select bank —</option>
              <?php foreach (['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN','HSBC','Standard Chartered','OCBC','UOB'] as $bank): ?>
                <option value="<?= $bank ?>"><?= $bank ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="account_name">Account Holder Name <span style="color:var(--error);">*</span></label>
            <input type="text" id="account_name" name="account_name" class="form-control" value="<?= e($merchant['business_name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="account_no">Account Number <span style="color:var(--error);">*</span></label>
            <input type="text" id="account_no" name="account_no" class="form-control" placeholder="1234567890" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="notes">Notes (optional)</label>
            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Any special instructions…"></textarea>
          </div>
          <button type="submit" name="request_payout" value="1" class="btn btn--primary btn--full btn--lg">
            Request Payout of <?= format_myr($summary['available']) ?>
          </button>
        </form>
      <?php else: ?>
        <div style="padding:var(--space-lg);background:var(--bg-light);border-radius:var(--radius-md);text-align:center;color:var(--text-muted);font-size:14px;">
          You need at least RM 50.00 available to request a payout.<br>
          Current balance: <strong><?= format_myr($summary['available']) ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <!-- Payout history -->
    <?php if (!empty($payout_requests)): ?>
      <div class="card" style="padding:var(--space-xl);margin-top:var(--space-lg);">
        <h4 style="margin-bottom:var(--space-md);">Payout History</h4>
        <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
          <?php foreach ($payout_requests as $pr): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-sm) 0;border-bottom:1px solid var(--border-color);">
              <div>
                <div style="font-weight:600;"><?= format_myr((float)$pr['amount']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= date('d M Y', strtotime($pr['created_at'])) ?> · <?= e($pr['bank_name']) ?></div>
              </div>
              <span class="badge badge--<?= match($pr['status']){'paid'=>'success','pending'=>'warning','rejected'=>'error',default=>'muted'} ?>"><?= ucfirst($pr['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Commission transaction log -->
  <div>
    <h4 style="margin-bottom:var(--space-md);">Commission Log</h4>
    <?php if (!empty($commissions)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Date</th><th>Deal</th><th>Member</th><th>Deal Price</th><th>Rate</th><th>Commission</th></tr></thead>
            <tbody>
              <?php foreach ($commissions as $c): ?>
                <tr>
                  <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                  <td style="font-size:13px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['deal_title']) ?></td>
                  <td style="font-size:13px;"><?= e($c['member_name']) ?></td>
                  <td style="font-size:13px;"><?= format_myr((float)$c['deal_price']) ?></td>
                  <td style="font-size:12px;text-align:center;"><?= (float)$c['commission_pct'] ?>%</td>
                  <td style="font-size:14px;font-weight:700;color:var(--orange-primary);"><?= format_myr((float)$c['commission_amt']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top:var(--space-md);justify-content:center;">
          <?php if ($page_num > 1): ?><a href="?page=<?= $page_num-1 ?>" class="pagination__btn">← Prev</a><?php endif; ?>
          <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?><a href="?page=<?= $i ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
          <?php if ($page_num < $total_pages): ?><a href="?page=<?= $page_num+1 ?>" class="pagination__btn">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-xl);">
        <div class="empty-state__icon">💰</div>
        <h4 class="empty-state__title">No commissions yet</h4>
        <p class="empty-state__text">Commissions will appear here once members redeem your deals.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../inc/merchant_layout_end.php'; ?>

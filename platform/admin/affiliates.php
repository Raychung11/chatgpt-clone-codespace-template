<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

$pageTitle = 'Affiliate Programme';
require_once '../includes/admin-header.php';

// PRG: handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $affId  = (int)($_POST['affiliate_id'] ?? 0);

    try {
        if ($action === 'approve' && $affId) {
            DB::query(
                "UPDATE affiliates SET status='active', approved_at=NOW() WHERE id=?",
                [$affId]
            );
            $msg = 'Affiliate approved.';
        } elseif ($action === 'suspend' && $affId) {
            DB::query("UPDATE affiliates SET status='suspended' WHERE id=?", [$affId]);
            $msg = 'Affiliate suspended.';
        } elseif ($action === 'reject' && $affId) {
            DB::query("UPDATE affiliates SET status='rejected' WHERE id=?", [$affId]);
            $msg = 'Affiliate rejected.';
        } elseif ($action === 'reactivate' && $affId) {
            DB::query("UPDATE affiliates SET status='active' WHERE id=?", [$affId]);
            $msg = 'Affiliate reactivated.';
        } elseif ($action === 'payout' && $affId) {
            $amount = (float)($_POST['payout_amount'] ?? 0);
            $ref    = trim($_POST['payout_ref'] ?? '');
            if ($amount > 0) {
                DB::insert('affiliate_payouts', [
                    'affiliate_id'  => $affId,
                    'amount'        => $amount,
                    'method'        => 'bank_transfer',
                    'reference'     => $ref ?: null,
                    'status'        => 'paid',
                    'processed_at'  => date('Y-m-d H:i:s'),
                ]);
                DB::query(
                    "UPDATE affiliates SET total_paid = total_paid + ? WHERE id=?",
                    [$amount, $affId]
                );
                $msg = 'Payout of RM ' . number_format($amount, 2) . ' recorded.';
            }
        }
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
    header('Location: affiliates.php?msg=' . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Stats
$totalAffiliates  = 0;
$activeCount      = 0;
$pendingCount     = 0;
$totalEarned      = 0.00;
$totalPaid        = 0.00;

try {
    $totalAffiliates  = (int)(DB::fetch("SELECT COUNT(*) as n FROM affiliates")['n'] ?? 0);
    $activeCount      = (int)(DB::fetch("SELECT COUNT(*) as n FROM affiliates WHERE status='active'")['n'] ?? 0);
    $pendingCount     = (int)(DB::fetch("SELECT COUNT(*) as n FROM affiliates WHERE status='pending'")['n'] ?? 0);
    $totalEarned      = (float)(DB::fetch("SELECT SUM(total_earned) as s FROM affiliates WHERE status='active'")['s'] ?? 0);
    $totalPaid        = (float)(DB::fetch("SELECT SUM(total_paid) as s FROM affiliates")['s'] ?? 0);
} catch (Throwable $e) {}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($statusFilter) { $where[] = "a.status = ?"; $params[] = $statusFilter; }
if ($search)       { $where[] = "(a.name LIKE ? OR a.email LIKE ? OR a.code LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$affiliates = [];
try {
    $affiliates = DB::fetchAll(
        "SELECT a.*, u.name as user_name, u.email as user_email,
                (a.total_earned - a.total_paid) as balance_due
         FROM affiliates a
         LEFT JOIN users u ON a.user_id = u.id
         $whereClause
         ORDER BY a.created_at DESC",
        $params
    );
} catch (Throwable $e) {}

$statusColors = [
    'active'    => 'success',
    'pending'   => 'warning',
    'suspended' => 'secondary',
    'rejected'  => 'danger',
];
?>

<div class="container-fluid px-4 py-4">

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
  <i class="bi bi-check-circle me-2"></i><?= $msg ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
  <div>
    <h4 class="text-white fw-bold mb-1"><i class="bi bi-people-fill me-2 text-primary"></i>Affiliate Programme</h4>
    <p class="text-muted small mb-0">Manage affiliates, approve applications, and record payouts.</p>
  </div>
  <a href="/affiliate.php" target="_blank" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-box-arrow-up-right me-1"></i>View Public Page
  </a>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="glass-card p-3 h-100">
      <div class="text-muted small mb-1">Total Affiliates</div>
      <div class="fs-3 fw-bold text-white"><?= number_format($totalAffiliates) ?></div>
      <div class="text-muted small"><?= $pendingCount ?> pending review</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="glass-card p-3 h-100">
      <div class="text-muted small mb-1">Active Partners</div>
      <div class="fs-3 fw-bold text-success"><?= number_format($activeCount) ?></div>
      <div class="text-muted small">Currently earning</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="glass-card p-3 h-100">
      <div class="text-muted small mb-1">Total Commission Earned</div>
      <div class="fs-3 fw-bold text-primary">RM <?= number_format($totalEarned, 0) ?></div>
      <div class="text-muted small">Across all affiliates</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="glass-card p-3 h-100">
      <div class="text-muted small mb-1">Outstanding Payouts</div>
      <div class="fs-3 fw-bold text-warning">RM <?= number_format($totalEarned - $totalPaid, 0) ?></div>
      <div class="text-muted small">RM <?= number_format($totalPaid, 0) ?> paid to date</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="glass-card p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <input type="text" class="form-control form-control-sm" name="q" placeholder="Search name, email, code…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
      <select class="form-select form-select-sm" name="status">
        <option value="">All Statuses</option>
        <?php foreach (['pending','active','suspended','rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
      <a href="affiliates.php" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
    </div>
    <?php if ($pendingCount > 0): ?>
    <div class="col-auto ms-auto">
      <a href="affiliates.php?status=pending" class="btn btn-sm btn-warning text-dark">
        <i class="bi bi-clock me-1"></i><?= $pendingCount ?> Pending Review
      </a>
    </div>
    <?php endif; ?>
  </form>
</div>

<!-- Affiliates Table -->
<div class="glass-card p-0 overflow-hidden">
  <?php if (empty($affiliates)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people" style="font-size:40px;opacity:.4"></i>
    <p class="mt-3 mb-0">No affiliates found.</p>
    <?php if (!$statusFilter && !$search): ?>
    <p class="small">Share the <a href="/affiliate.php" target="_blank">affiliate page</a> to get applications.</p>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0" style="font-size:13px">
      <thead>
        <tr class="text-muted border-bottom border-secondary border-opacity-25" style="font-size:11px">
          <th class="ps-4 py-3">AFFILIATE</th>
          <th>CODE</th>
          <th>STATUS</th>
          <th class="text-end">CLICKS</th>
          <th class="text-end">REFERRALS</th>
          <th class="text-end">EARNED</th>
          <th class="text-end">BALANCE</th>
          <th class="text-end pe-4">ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($affiliates as $aff):
          $badge = $statusColors[$aff['status']] ?? 'secondary';
          $balance = (float)($aff['balance_due'] ?? 0);
        ?>
        <tr class="border-bottom border-secondary border-opacity-10">
          <td class="ps-4 py-3">
            <div class="fw-semibold text-white"><?= htmlspecialchars($aff['name']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($aff['email']) ?></div>
            <?php if ($aff['website']): ?>
            <a href="<?= htmlspecialchars($aff['website']) ?>" target="_blank" class="text-primary text-decoration-none" style="font-size:11px">
              <i class="bi bi-link-45deg"></i><?= htmlspecialchars(parse_url($aff['website'], PHP_URL_HOST) ?? $aff['website']) ?>
            </a>
            <?php endif; ?>
          </td>
          <td>
            <code class="text-primary" style="font-size:12px"><?= htmlspecialchars($aff['code']) ?></code>
          </td>
          <td>
            <span class="badge bg-<?= $badge ?>-soft text-<?= $badge ?> rounded-pill">
              <?= ucfirst($aff['status']) ?>
            </span>
          </td>
          <td class="text-end text-muted"><?= number_format($aff['total_clicks']) ?></td>
          <td class="text-end text-white"><?= number_format($aff['total_referrals']) ?></td>
          <td class="text-end text-primary">RM <?= number_format($aff['total_earned'], 2) ?></td>
          <td class="text-end <?= $balance > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
            RM <?= number_format($balance, 2) ?>
          </td>
          <td class="text-end pe-4">
            <div class="d-flex gap-1 justify-content-end">
              <?php if ($aff['status'] === 'pending'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="affiliate_id" value="<?= $aff['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-xs btn-success" title="Approve" onclick="return confirm('Approve this affiliate?')">
                  <i class="bi bi-check-lg"></i>
                </button>
              </form>
              <form method="POST" class="d-inline">
                <input type="hidden" name="affiliate_id" value="<?= $aff['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn btn-xs btn-outline-danger" title="Reject" onclick="return confirm('Reject this application?')">
                  <i class="bi bi-x-lg"></i>
                </button>
              </form>
              <?php elseif ($aff['status'] === 'active'): ?>
              <button class="btn btn-xs btn-outline-primary" title="Record Payout"
                      onclick="openPayout(<?= $aff['id'] ?>, '<?= htmlspecialchars($aff['name']) ?>', <?= $balance ?>)">
                <i class="bi bi-cash"></i>
              </button>
              <form method="POST" class="d-inline">
                <input type="hidden" name="affiliate_id" value="<?= $aff['id'] ?>">
                <input type="hidden" name="action" value="suspend">
                <button class="btn btn-xs btn-outline-secondary" title="Suspend" onclick="return confirm('Suspend this affiliate?')">
                  <i class="bi bi-pause"></i>
                </button>
              </form>
              <?php elseif (in_array($aff['status'], ['suspended','rejected'])): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="affiliate_id" value="<?= $aff['id'] ?>">
                <input type="hidden" name="action" value="reactivate">
                <button class="btn btn-xs btn-outline-success" title="Reactivate" onclick="return confirm('Reactivate this affiliate?')">
                  <i class="bi bi-arrow-counterclockwise"></i>
                </button>
              </form>
              <?php endif; ?>
              <button class="btn btn-xs btn-outline-info" title="Details"
                      onclick="showDetails(<?= htmlspecialchars(json_encode($aff)) ?>)">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div>

<!-- Payout Modal -->
<div class="modal fade" id="payoutModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background:#1a1a2e;border:1px solid rgba(99,102,241,0.3)">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white"><i class="bi bi-cash-coin me-2 text-primary"></i>Record Payout</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="payout">
        <input type="hidden" name="affiliate_id" id="payoutAffId">
        <div class="modal-body">
          <p class="text-muted small mb-3">Recording payment to: <strong class="text-white" id="payoutName"></strong></p>
          <div class="mb-3">
            <label class="form-label text-white-50 small">Amount to Pay (RM)</label>
            <input type="number" class="form-control" name="payout_amount" id="payoutAmount" step="0.01" min="0.01" required>
            <div class="form-text text-muted small">Balance due: <span id="payoutBalance" class="text-warning"></span></div>
          </div>
          <div class="mb-3">
            <label class="form-label text-white-50 small">Bank Transfer Reference (optional)</label>
            <input type="text" class="form-control" name="payout_ref" placeholder="e.g. MBBG2024011501">
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-1"></i>Record Payment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="background:#1a1a2e;border:1px solid rgba(99,102,241,0.3)">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white"><i class="bi bi-person-badge me-2 text-primary"></i>Affiliate Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailsBody">
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.btn-xs { padding: 3px 8px; font-size: 11px; }
.bg-success-soft { background: rgba(16,185,129,0.1); }
.bg-warning-soft { background: rgba(245,158,11,0.1); }
.bg-danger-soft  { background: rgba(239,68,68,0.1); }
.bg-secondary-soft { background: rgba(107,114,128,0.1); }
</style>

<script>
function openPayout(id, name, balance) {
  document.getElementById('payoutAffId').value  = id;
  document.getElementById('payoutName').textContent = name;
  document.getElementById('payoutAmount').value = balance.toFixed(2);
  document.getElementById('payoutBalance').textContent = 'RM ' + balance.toFixed(2);
  new bootstrap.Modal(document.getElementById('payoutModal')).show();
}

function showDetails(aff) {
  const html = `
    <div class="row g-3">
      <div class="col-md-6">
        <div class="text-muted small">Name</div>
        <div class="text-white">${aff.name || '—'}</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Email</div>
        <div class="text-white">${aff.email || '—'}</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Affiliate Code</div>
        <code class="text-primary">${aff.code || '—'}</code>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Affiliate Link</div>
        <code class="text-primary small">/register.php?aff=${aff.code}</code>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Website</div>
        <div class="text-white">${aff.website ? '<a href="'+aff.website+'" target="_blank" class="text-primary">'+aff.website+'</a>' : '—'}</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Commission Rate</div>
        <div class="text-white">${aff.commission_rate || 20}%</div>
      </div>
      <div class="col-12">
        <div class="text-muted small">Description / Promotion Plan</div>
        <div class="text-white small mt-1">${aff.description || '—'}</div>
      </div>
      <div class="col-12"><hr class="border-secondary border-opacity-25"></div>
      <div class="col-4 text-center">
        <div class="text-muted small">Total Clicks</div>
        <div class="fs-4 fw-bold text-white">${aff.total_clicks || 0}</div>
      </div>
      <div class="col-4 text-center">
        <div class="text-muted small">Referrals</div>
        <div class="fs-4 fw-bold text-white">${aff.total_referrals || 0}</div>
      </div>
      <div class="col-4 text-center">
        <div class="text-muted small">Commission Earned</div>
        <div class="fs-4 fw-bold text-primary">RM ${parseFloat(aff.total_earned||0).toFixed(2)}</div>
      </div>
      <div class="col-12"><hr class="border-secondary border-opacity-25"></div>
      <div class="col-6">
        <div class="text-muted small">Approved At</div>
        <div class="text-white small">${aff.approved_at || 'Not yet approved'}</div>
      </div>
      <div class="col-6">
        <div class="text-muted small">Applied At</div>
        <div class="text-white small">${aff.created_at || '—'}</div>
      </div>
      ${aff.notes ? '<div class="col-12"><div class="text-muted small">Admin Notes</div><div class="text-white small mt-1">'+aff.notes+'</div></div>' : ''}
    </div>`;
  document.getElementById('detailsBody').innerHTML = html;
  new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>

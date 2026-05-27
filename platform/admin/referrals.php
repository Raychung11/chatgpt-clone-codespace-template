<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/membership.php';

Auth::requireAdmin();

// Ensure tables exist
try { Auth::ensureReferralTables(); } catch (Throwable $e) {}

// Actions
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $refId  = (int)($_POST['ref_id'] ?? 0);

    if ($action === 'convert' && $refId) {
        $reward = max(0, (float)($_POST['reward_amount'] ?? 0));
        $ref = DB::fetch('SELECT referrer_id FROM referrals WHERE id = ?', [$refId]);
        DB::update('referrals', [
            'status'       => 'converted',
            'reward_amount'=> $reward,
            'converted_at' => date('Y-m-d H:i:s'),
            'notes'        => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ], 'id = ?', [$refId]);
        if ($ref) {
            Membership::ensureTables();
            try { Membership::awardPoints((int)$ref['referrer_id'], 300, 'referral_conversion', 'Referred user converted to paid plan'); } catch (Throwable $e) {}
        }
        $flash = 'Referral marked as converted.';
    }
    if ($action === 'reward' && $refId) {
        DB::update('referrals', [
            'status'      => 'rewarded',
            'rewarded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$refId]);
        $flash = 'Referral marked as rewarded.';
    }
    if ($action === 'expire' && $refId) {
        DB::update('referrals', ['status' => 'expired'], 'id = ?', [$refId]);
        $flash = 'Referral marked as expired.';
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$where        = '1';
$params       = [];
if ($statusFilter) { $where .= ' AND r.status = ?'; $params[] = $statusFilter; }
if ($search)       { $where .= ' AND (ref.name LIKE ? OR rfr.name LIKE ? OR r.referred_email LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$referrals = DB::fetchAll(
    "SELECT r.*,
            rfr.name  as referrer_name,
            rfr.email as referrer_email,
            ref.name  as referred_name
     FROM referrals r
     LEFT JOIN users rfr ON r.referrer_id = rfr.id
     LEFT JOIN users ref ON r.referred_id = ref.id
     WHERE $where
     ORDER BY r.created_at DESC",
    $params
);

// Stats
$stats = [
    'total'     => (int)(DB::fetch('SELECT COUNT(*) as n FROM referrals')['n'] ?? 0),
    'pending'   => (int)(DB::fetch("SELECT COUNT(*) as n FROM referrals WHERE status='pending'")['n'] ?? 0),
    'converted' => (int)(DB::fetch("SELECT COUNT(*) as n FROM referrals WHERE status IN ('converted','rewarded')")['n'] ?? 0),
    'rewarded'  => (int)(DB::fetch("SELECT COUNT(*) as n FROM referrals WHERE status='rewarded'")['n'] ?? 0),
    'value'     => (float)(DB::fetch("SELECT SUM(reward_amount) as s FROM referrals WHERE status='rewarded'")['s'] ?? 0),
];

$pageTitle = 'Referrals';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-1"><i class="bi bi-gift me-2 text-success"></i>Referrals</h4>
            <p class="text-muted small mb-0">Track referrals, conversions, and reward payouts</p>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-success alert-dismissible fade show py-2">
        <?= htmlspecialchars($flash) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['Total Referrals',  $stats['total'],     'bi-people',       'text-white'],
            ['Pending',          $stats['pending'],   'bi-hourglass',    'text-warning'],
            ['Converted',        $stats['converted'], 'bi-check-circle', 'text-success'],
            ['Rewards Paid',     APP_CURRENCY . number_format($stats['value'], 0), 'bi-gift', 'text-primary'],
        ];
        foreach ($statCards as [$label, $val, $icon, $color]):
        ?>
        <div class="col-6 col-md-3">
            <div class="glass-card rounded-4 p-3 d-flex align-items-center gap-3">
                <div class="stat-icon <?= str_replace('text-','bg-',$color) ?> bg-opacity-15 <?= $color ?>">
                    <i class="bi <?= $icon ?> fs-5"></i>
                </div>
                <div>
                    <div class="fs-5 fw-bold text-white"><?= $val ?></div>
                    <div class="text-muted small"><?= $label ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="glass-card rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-dark border-secondary text-white"
                           placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select bg-dark border-secondary text-white">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','converted','rewarded','expired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($search || $statusFilter): ?>
                <a href="/admin/referrals.php" class="btn btn-outline-secondary ms-1">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="glass-card rounded-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead style="border-bottom:1px solid rgba(255,255,255,0.08)">
                    <tr class="text-muted" style="font-size:11px;text-transform:uppercase">
                        <th class="px-4 py-3 fw-normal">Referrer</th>
                        <th class="py-3 fw-normal">Referred</th>
                        <th class="py-3 fw-normal">Date</th>
                        <th class="py-3 fw-normal">Status</th>
                        <th class="py-3 fw-normal text-end">Reward</th>
                        <th class="py-3 fw-normal text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($referrals)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No referrals found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($referrals as $r):
                        $badge = match($r['status']) {
                            'converted' => 'bg-success',
                            'rewarded'  => 'bg-primary',
                            'expired'   => 'bg-secondary',
                            default     => 'bg-warning text-dark',
                        };
                    ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-white small fw-semibold"><?= htmlspecialchars($r['referrer_name'] ?? '—') ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($r['referrer_email'] ?? '') ?></div>
                        </td>
                        <td class="py-3">
                            <div class="text-white small"><?= $r['referred_name'] ? htmlspecialchars($r['referred_name']) : '<span class="text-muted">Not registered</span>' ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($r['referred_email']) ?></div>
                        </td>
                        <td class="py-3 text-muted small"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td class="py-3">
                            <span class="badge <?= $badge ?>" style="font-size:10px"><?= ucfirst($r['status']) ?></span>
                            <?php if ($r['notes']): ?>
                            <div class="text-muted" style="font-size:10px;margin-top:2px"><?= htmlspecialchars($r['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-end">
                            <?php if ($r['reward_amount'] > 0): ?>
                            <span class="text-success small fw-semibold"><?= APP_CURRENCY ?><?= number_format($r['reward_amount'], 0) ?></span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <?php if ($r['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                        data-bs-target="#convertModal"
                                        data-id="<?= $r['id'] ?>"
                                        data-email="<?= htmlspecialchars($r['referred_email']) ?>">
                                    Convert
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="expire">
                                    <input type="hidden" name="ref_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Expire</button>
                                </form>
                                <?php elseif ($r['status'] === 'converted'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reward">
                                    <input type="hidden" name="ref_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Mark Rewarded</button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Convert Modal -->
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Mark as Converted</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="convert">
                <input type="hidden" name="ref_id" id="modalRefId">
                <div class="modal-body">
                    <p class="text-muted small" id="modalRefEmail"></p>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Reward Amount (<?= APP_CURRENCY ?>)</label>
                        <input type="number" name="reward_amount" class="form-control bg-dark border-secondary text-white"
                               placeholder="e.g. 500" min="0" step="50">
                        <div class="form-text text-muted">Leave 0 if no monetary reward applies.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control bg-dark border-secondary text-white"
                               placeholder="e.g. Subscribed to Growth plan">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Conversion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('convertModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('modalRefId').value = btn.dataset.id;
    document.getElementById('modalRefEmail').textContent = 'Referral: ' + btn.dataset.email;
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>

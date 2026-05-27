<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/membership.php';

Auth::requireAdmin();
Membership::ensureTables();

$flash = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust_points') {
        $targetId = (int)$_POST['user_id'];
        $delta    = (int)$_POST['delta'];
        $desc     = htmlspecialchars(trim($_POST['description'] ?? 'Admin adjustment'));
        if ($targetId && $delta != 0) {
            Membership::adjustPoints($targetId, $delta, $desc);
            $flash = ($delta > 0 ? 'Added' : 'Deducted') . ' ' . abs($delta) . ' points.';
        }
    }

    if ($action === 'add_wallet') {
        $targetId = (int)$_POST['user_id'];
        $amount   = max(0, (float)$_POST['amount']);
        $desc     = htmlspecialchars(trim($_POST['description'] ?? 'Admin credit'));
        if ($targetId && $amount > 0) {
            Membership::addWalletCredit($targetId, $amount, $desc);
            $flash = APP_CURRENCY . number_format($amount, 0) . ' added to wallet.';
        }
    }

    if ($action === 'save_tier') {
        $tierId   = (int)($_POST['tier_id'] ?? 0);
        $benefits = array_filter(array_map('trim', explode("\n", $_POST['benefits'] ?? '')));
        $data = [
            'name'       => htmlspecialchars(trim($_POST['name'])),
            'min_points' => max(0, (int)$_POST['min_points']),
            'color'      => htmlspecialchars(trim($_POST['color'])),
            'icon'       => htmlspecialchars(trim($_POST['icon'])),
            'benefits'   => json_encode(array_values($benefits)),
        ];
        if ($tierId) {
            DB::update('membership_tiers', $data, 'id = ?', [$tierId]);
            $flash = 'Tier updated.';
        }
    }
}

// Load data
$allTiers = Membership::getAllTiers();

$search      = trim($_GET['q'] ?? '');
$tierFilter  = $_GET['tier'] ?? '';
$params      = [];
$where       = '1';
if ($search)     { $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%"]); }

$users = DB::fetchAll(
    "SELECT u.id, u.name, u.email, u.company,
            COALESCE(u.total_points, 0) as total_points,
            COALESCE(u.wallet_balance, 0) as wallet_balance,
            u.created_at
     FROM users u
     WHERE u.role = 'customer' AND $where
     ORDER BY total_points DESC LIMIT 100",
    $params
);

// Attach tier to each user
foreach ($users as &$u) {
    $u['tier'] = Membership::getTierByPoints((int)$u['total_points']);
}
unset($u);

// If filtering by tier (client-side)
if ($tierFilter) {
    $users = array_filter($users, fn($u) => $u['tier']['slug'] === $tierFilter);
}

// Stats
$tierCounts = [];
foreach ($allTiers as $t) {
    $tierCounts[$t['slug']] = 0;
}
foreach ($users as $u) {
    $tierCounts[$u['tier']['slug']] = ($tierCounts[$u['tier']['slug']] ?? 0) + 1;
}

$pageTitle = 'Membership Management';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-1"><i class="bi bi-trophy me-2 text-warning"></i>Membership</h4>
            <p class="text-muted small mb-0">Manage tiers, award points, and wallet credits</p>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-success alert-dismissible fade show py-2">
        <?= htmlspecialchars($flash) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tier Distribution -->
    <div class="row g-3 mb-4">
        <?php foreach ($allTiers as $t): ?>
        <div class="col">
            <div class="glass-card rounded-4 p-3 text-center" style="border-top:3px solid <?= htmlspecialchars($t['color']) ?>">
                <i class="bi <?= htmlspecialchars($t['icon']) ?> fs-4 mb-1 d-block" style="color:<?= htmlspecialchars($t['color']) ?>"></i>
                <div class="fs-5 fw-bold text-white"><?= $tierCounts[$t['slug']] ?? 0 ?></div>
                <div class="text-muted small"><?= htmlspecialchars($t['name']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Members Table -->
        <div class="col-lg-8">
            <div class="glass-card rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Members</h6>
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control form-control-sm bg-dark border-secondary text-white"
                               placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:160px">
                        <select name="tier" class="form-select form-select-sm bg-dark border-secondary text-white" style="width:110px">
                            <option value="">All tiers</option>
                            <?php foreach ($allTiers as $t): ?>
                            <option value="<?= $t['slug'] ?>" <?= $tierFilter === $t['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Go</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0 align-middle">
                        <thead>
                            <tr class="text-muted" style="font-size:11px;text-transform:uppercase">
                                <th class="fw-normal pb-2">Member</th>
                                <th class="fw-normal pb-2">Tier</th>
                                <th class="fw-normal pb-2 text-end">Points</th>
                                <th class="fw-normal pb-2 text-end">Wallet</th>
                                <th class="fw-normal pb-2 text-center">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): $t = $u['tier']; ?>
                        <tr>
                            <td class="py-2">
                                <div class="text-white small fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td class="py-2">
                                <span class="badge px-2" style="background:<?= htmlspecialchars($t['color']) ?>22;color:<?= htmlspecialchars($t['color']) ?>;border:1px solid <?= htmlspecialchars($t['color']) ?>44;font-size:10px">
                                    <i class="bi <?= htmlspecialchars($t['icon']) ?> me-1"></i><?= htmlspecialchars($t['name']) ?>
                                </span>
                            </td>
                            <td class="py-2 text-end text-white small fw-semibold"><?= number_format($u['total_points']) ?></td>
                            <td class="py-2 text-end text-success small"><?= APP_CURRENCY ?><?= number_format($u['wallet_balance'], 0) ?></td>
                            <td class="py-2 text-center">
                                <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#manageModal"
                                        data-uid="<?= $u['id'] ?>"
                                        data-uname="<?= htmlspecialchars($u['name']) ?>"
                                        data-points="<?= $u['total_points'] ?>"
                                        data-wallet="<?= $u['wallet_balance'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No members found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tier Editor -->
        <div class="col-lg-4">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-sliders me-2"></i>Edit Tiers</h6>
                <div class="accordion accordion-flush" id="tierAccordion">
                <?php foreach ($allTiers as $idx => $t): ?>
                <div class="accordion-item bg-transparent border-secondary border-opacity-25 mb-2 rounded-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-white py-2" type="button"
                                data-bs-toggle="collapse" data-bs-target="#tier<?= $idx ?>">
                            <i class="bi <?= htmlspecialchars($t['icon']) ?> me-2" style="color:<?= htmlspecialchars($t['color']) ?>"></i>
                            <?= htmlspecialchars($t['name']) ?>
                            <span class="text-muted small ms-2">(<?= number_format((int)$t['min_points']) ?> pts)</span>
                        </button>
                    </h2>
                    <div id="tier<?= $idx ?>" class="accordion-collapse collapse">
                        <div class="accordion-body pt-2">
                            <form method="POST">
                                <input type="hidden" name="action" value="save_tier">
                                <input type="hidden" name="tier_id" value="<?= $t['id'] ?? '' ?>">
                                <div class="mb-2">
                                    <label class="form-label text-muted" style="font-size:11px">Name</label>
                                    <input type="text" name="name" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?= htmlspecialchars($t['name']) ?>">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size:11px">Min Points</label>
                                        <input type="number" name="min_points" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?= $t['min_points'] ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-muted" style="font-size:11px">Color</label>
                                        <input type="color" name="color" class="form-control form-control-sm form-control-color bg-dark border-secondary" value="<?= htmlspecialchars($t['color']) ?>">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label text-muted" style="font-size:11px">Icon (Bootstrap icon class)</label>
                                    <input type="text" name="icon" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?= htmlspecialchars($t['icon']) ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label text-muted" style="font-size:11px">Benefits (one per line)</label>
                                    <textarea name="benefits" rows="4" class="form-control form-control-sm bg-dark border-secondary text-white" style="font-size:12px"><?= htmlspecialchars(implode("\n", json_decode($t['benefits'] ?? '[]', true) ?: [])) ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manage Member Modal -->
<div class="modal fade" id="manageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Manage Member: <span id="modalName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 text-center glass-card rounded-3 p-3">
                        <div class="fs-4 fw-bold text-white" id="modalPoints"></div>
                        <div class="text-muted small">Points</div>
                    </div>
                    <div class="col-6 text-center glass-card rounded-3 p-3">
                        <div class="fs-4 fw-bold text-success" id="modalWallet"></div>
                        <div class="text-muted small">Wallet</div>
                    </div>
                </div>

                <!-- Award/Deduct Points -->
                <form method="POST" class="mb-3 p-3 rounded-3" style="background:rgba(255,255,255,0.04)">
                    <input type="hidden" name="action" value="adjust_points">
                    <input type="hidden" name="user_id" id="modalUid1">
                    <label class="form-label text-muted small">Adjust Points (negative to deduct)</label>
                    <div class="input-group mb-2">
                        <input type="number" name="delta" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 200 or -100" required>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                    <input type="text" name="description" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Reason (e.g. Loyalty bonus)">
                </form>

                <!-- Add Wallet Credit -->
                <form method="POST" class="p-3 rounded-3" style="background:rgba(16,185,129,0.06)">
                    <input type="hidden" name="action" value="add_wallet">
                    <input type="hidden" name="user_id" id="modalUid2">
                    <label class="form-label text-muted small">Add Wallet Credit (<?= APP_CURRENCY ?>)</label>
                    <div class="input-group mb-2">
                        <input type="number" name="amount" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 500" min="1" step="50" required>
                        <button type="submit" class="btn btn-success">Add Credit</button>
                    </div>
                    <input type="text" name="description" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Reason (e.g. Referral reward)">
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('manageModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('modalName').textContent   = btn.dataset.uname;
    document.getElementById('modalPoints').textContent = Number(btn.dataset.points).toLocaleString();
    document.getElementById('modalWallet').textContent = '<?= APP_CURRENCY ?>' + Number(btn.dataset.wallet).toLocaleString();
    document.getElementById('modalUid1').value = btn.dataset.uid;
    document.getElementById('modalUid2').value = btn.dataset.uid;
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>

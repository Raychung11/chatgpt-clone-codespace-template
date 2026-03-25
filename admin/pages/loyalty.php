<?php
/**
 * Admin – Loyalty Transactions
 * /admin/pages/loyalty.php
 */

$pageTitle  = 'Loyalty Points';
$activePage = 'loyalty';

$search = sanitize_string($_GET['search'] ?? '');
$type   = sanitize_string($_GET['type']   ?? '');
$page   = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 30;

$where  = '1=1';
$params = [];

if ($search) {
    $where .= ' AND (u.name LIKE ? OR u.phone LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s]);
}
if ($type) {
    $where .= ' AND lt.type = ?';
    $params[] = $type;
}

$total  = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM loyalty_transactions lt JOIN users u ON u.id=lt.user_id WHERE {$where}", $params)['c'];
$pages  = (int) ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$transactions = Database::fetchAll(
    "SELECT lt.*, u.name AS customer_name, u.phone AS customer_phone
     FROM loyalty_transactions lt
     JOIN users u ON u.id = lt.user_id
     WHERE {$where}
     ORDER BY lt.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Summary
$totalEarned  = (int) Database::fetchOne("SELECT COALESCE(SUM(points),0) AS p FROM loyalty_transactions WHERE type='earn' AND DATE(created_at)=CURDATE()")['p'];
$totalRedeemed= (int) abs((int) Database::fetchOne("SELECT COALESCE(SUM(points),0) AS p FROM loyalty_transactions WHERE type='redeem' AND DATE(created_at)=CURDATE()")['p'] ?? 0);

require __DIR__ . '/../layout/header.php';
?>

<!-- Summary -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Earned Today</div>
            <div class="fs-4 fw-bold text-success">+<?= number_format($totalEarned) ?> pts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Redeemed Today</div>
            <div class="fs-4 fw-bold text-danger">-<?= number_format($totalRedeemed) ?> pts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Total Transactions</div>
            <div class="fs-4 fw-bold"><?= number_format($total) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Customer name or phone…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach (['earn','redeem','expire','adjust','referral','bonus'] as $t): ?>
                    <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Date</th><th>Customer</th><th>Type</th><th>Points</th><th>Balance After</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="text-muted"><?= date('d M Y H:i', strtotime($tx['created_at'])) ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($tx['customer_name']) ?></div>
                            <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($tx['customer_phone']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $tx['type']==='earn'||$tx['type']==='bonus'?'success':($tx['type']==='redeem'||$tx['type']==='expire'?'danger':'secondary') ?>">
                                <?= ucfirst($tx['type']) ?>
                            </span>
                        </td>
                        <td class="<?= $tx['points']>0?'text-success':'text-danger' ?> fw-semibold">
                            <?= ($tx['points']>0?'+':'') . number_format($tx['points']) ?>
                        </td>
                        <td><?= number_format($tx['balance_after']) ?> pts</td>
                        <td class="text-muted"><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between px-3 py-2 border-top small">
            <span class="text-muted">Page <?= $page ?> of <?= $pages ?></span>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

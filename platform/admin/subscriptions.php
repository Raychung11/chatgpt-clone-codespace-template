<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$where = '1=1';
$params = [];
if ($search) { $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status) { $where .= ' AND s.status=?'; $params[] = $status; }

$subs = DB::fetchAll("SELECT s.*, u.name as user_name, u.email, p.name as product_name
    FROM subscriptions s JOIN users u ON u.id=s.user_id JOIN products p ON p.id=s.product_id
    WHERE $where ORDER BY s.created_at DESC LIMIT 200", $params);

$stats = [
    'active'   => DB::fetch("SELECT COUNT(*) as n FROM subscriptions WHERE status='active'")['n'],
    'trial'    => DB::fetch("SELECT COUNT(*) as n FROM subscriptions WHERE status='trial'")['n'],
    'cancelled'=> DB::fetch("SELECT COUNT(*) as n FROM subscriptions WHERE status='cancelled'")['n'],
    'mrr'      => DB::fetch("SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status='active' AND plan='monthly'")['n'],
];

$pageTitle = 'Subscriptions';
require_once '../includes/admin-header.php';
?>
<div class="admin-content px-4 py-4">
<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Subscriptions</h4>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Active','active','bg-success',$stats['active'],'bi-check-circle'],
        ['Trial','trial','bg-info',$stats['trial'],'bi-clock'],
        ['Cancelled','cancelled','bg-danger',$stats['cancelled'],'bi-x-circle'],
        ['MRR','mrr','bg-primary',CURRENCY_SYMBOL.number_format($stats['mrr'],2),'bi-cash-stack'],
    ] as [$label,$key,$bg,$val,$icon]): ?>
    <div class="col-6 col-md-3">
        <div class="glass-card p-3 text-center">
            <i class="bi <?= $icon ?> fs-3 <?= $bg === 'bg-primary' ? 'text-primary' : '' ?>" style="<?= $bg !== 'bg-primary' ? 'color:var(--bs-'.str_replace('bg-','',$bg).')' : '' ?>"></i>
            <div class="fs-4 fw-bold mt-1"><?= $val ?></div>
            <div class="text-muted small"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="glass-card p-3 mb-3">
<form class="row g-2" method="get">
    <div class="col-md-5"><input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search name, email or product…" value="<?= htmlspecialchars($search) ?>"></div>
    <div class="col-md-3">
        <select name="status" class="form-select bg-dark text-white border-secondary">
            <option value="">All statuses</option>
            <?php foreach (['active','trial','cancelled','expired','paused'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    <div class="col-auto"><a href="/admin/subscriptions.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
</div>

<div class="glass-card p-0 overflow-hidden">
<table class="table table-dark table-hover mb-0">
<thead><tr>
    <th>Customer</th><th>Product</th><th>Plan</th><th>Amount</th><th>Status</th><th>Next Billing</th><th>Started</th>
</tr></thead>
<tbody>
<?php foreach ($subs as $s): ?>
<tr>
    <td>
        <div class="fw-semibold"><?= htmlspecialchars($s['user_name']) ?></div>
        <div class="text-muted small"><?= htmlspecialchars($s['email']) ?></div>
    </td>
    <td><?= htmlspecialchars($s['product_name']) ?></td>
    <td><span class="badge bg-secondary"><?= ucfirst($s['plan'] ?? 'monthly') ?></span></td>
    <td><?= CURRENCY_SYMBOL.number_format($s['amount'],2) ?></td>
    <td>
        <?php $sc=['active'=>'success','trial'=>'info','cancelled'=>'danger','expired'=>'secondary','paused'=>'warning']; ?>
        <span class="badge bg-<?= $sc[$s['status']] ?? 'secondary' ?>"><?= ucfirst($s['status']) ?></span>
    </td>
    <td class="text-muted small"><?= $s['next_billing_date'] ? date('d M Y', strtotime($s['next_billing_date'])) : '—' ?></td>
    <td class="text-muted small"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($subs)): ?>
<tr><td colspan="7" class="text-center text-muted py-4">No subscriptions found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php require_once '../includes/admin-footer.php'; ?>

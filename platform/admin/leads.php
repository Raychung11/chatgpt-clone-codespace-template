<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $id     = (int)$_POST['id'];
        $status = in_array($_POST['status'], ['new','contacted','qualified','lost']) ? $_POST['status'] : 'new';
        DB::update('leads', ['status' => $status], 'id=?', [$id]);
    }
    if ($action === 'delete') {
        DB::query('DELETE FROM leads WHERE id=?', [(int)$_POST['id']]);
    }
    header('Location: /admin/leads.php');
    exit;
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');
$where = '1=1'; $params = [];
if ($status_filter) { $where .= ' AND status=?'; $params[] = $status_filter; }
if ($search)        { $where .= ' AND (name LIKE ? OR email LIKE ? OR message LIKE ?)'; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }

$leads = DB::fetchAll("SELECT * FROM leads WHERE $where ORDER BY created_at DESC LIMIT 200", $params);
$counts = DB::fetchAll("SELECT status, COUNT(*) as n FROM leads GROUP BY status");
$countMap = [];
foreach ($counts as $c) $countMap[$c['status']] = $c['n'];

$pageTitle = 'Leads';
require_once '../includes/admin-header.php';
?>
<div class="admin-content px-4 py-4">
<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Leads</h4>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([['New','new','warning'],['Contacted','contacted','info'],['Qualified','qualified','success'],['Lost','lost','danger']] as [$lbl,$st,$col]): ?>
    <div class="col-6 col-md-3">
        <div class="glass-card p-3 text-center">
            <div class="fs-3 fw-bold text-<?= $col ?>"><?= $countMap[$st] ?? 0 ?></div>
            <div class="text-muted small"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="glass-card p-3 mb-3">
<form class="row g-2" method="get">
    <div class="col-md-5"><input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search name, email, message…" value="<?= htmlspecialchars($search) ?>"></div>
    <div class="col-md-3">
        <select name="status" class="form-select bg-dark text-white border-secondary">
            <option value="">All</option>
            <?php foreach (['new','contacted','qualified','lost'] as $s): ?>
            <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    <div class="col-auto"><a href="/admin/leads.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
</div>

<div class="glass-card p-0 overflow-hidden">
<table class="table table-dark table-hover mb-0">
<thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Message</th><th>Status</th><th>Date</th><th></th></tr></thead>
<tbody>
<?php foreach ($leads as $lead): ?>
<tr>
    <td class="fw-semibold"><?= htmlspecialchars($lead['name'] ?? '') ?></td>
    <td><a href="mailto:<?= htmlspecialchars($lead['email'] ?? '') ?>" class="text-primary"><?= htmlspecialchars($lead['email'] ?? '') ?></a></td>
    <td><?= htmlspecialchars($lead['company'] ?? '—') ?></td>
    <td class="text-muted small" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($lead['message'] ?? '') ?></td>
    <td>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?= $lead['id'] ?>">
            <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()" style="width:110px">
                <?php foreach (['new','contacted','qualified','lost'] as $s): ?>
                <option value="<?= $s ?>" <?= $lead['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </td>
    <td class="text-muted small"><?= date('d M Y', strtotime($lead['created_at'])) ?></td>
    <td>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this lead?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $lead['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($leads)): ?>
<tr><td colspan="7" class="text-center text-muted py-4">No leads yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php require_once '../includes/admin-footer.php'; ?>

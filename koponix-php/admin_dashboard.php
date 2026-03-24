<?php
require_once __DIR__ . '/layout.php';

// Simple admin auth via session
session_start_safe();
$admin_error = '';
if (isset($_POST['admin_login'])) {
    if (hash_password($_POST['admin_pw'] ?? '') === ADMIN_PASSWORD_HASH) {
        $_SESSION['admin_auth'] = true;
    } else {
        $admin_error = 'Incorrect admin password.';
    }
}
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_auth']);
    redirect('admin_dashboard.php');
}
$is_admin = !empty($_SESSION['admin_auth']);

// Handle actions
if ($is_admin && isset($_POST['action'])) {
    $id = $_POST['id'] ?? '';
    match($_POST['action']) {
        'activate_seller'   => update_seller_status($id, 'active'),
        'deactivate_seller' => update_seller_status($id, 'inactive'),
        'delete_seller'     => delete_seller($id),
        'update_request'    => update_request_status($id, $_POST['status'] ?? 'open'),
        default             => null,
    };
    redirect('admin_dashboard.php?tab=' . ($_POST['tab'] ?? 'sellers'));
}

$tab     = $_GET['tab'] ?? 'overview';
$sellers = $is_admin ? get_sellers() : [];
$requests= $is_admin ? get_requests() : [];
$members = $is_admin ? get_all_members() : [];

html_head('Admin Dashboard');
html_body_open();
?>

<div class="page-title">🛡️ Admin Dashboard</div>

<?php if (!$is_admin): ?>
<div class="card p-4" style="max-width:380px">
    <h5>Admin Login</h5>
    <?php if ($admin_error): ?><div class="alert alert-danger py-2 small"><?= e($admin_error) ?></div><?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Admin Password</label>
            <input type="password" name="admin_pw" class="form-control" required>
        </div>
        <button type="submit" name="admin_login" value="1" class="btn btn-primary">Login</button>
    </form>
</div>
<?php html_footer(); return; ?>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($sellers) ?></div><div class="lbl">Total Listings</div></div></div>
    <div class="col-3"><div class="stat-box"><div class="val"><?= count(array_filter($sellers, fn($s)=>$s['status']==='active')) ?></div><div class="lbl">Active</div></div></div>
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($requests) ?></div><div class="lbl">Requests</div></div></div>
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($members) ?></div><div class="lbl">Members</div></div></div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='sellers'?'active':'' ?>" href="?tab=sellers">Listings</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='requests'?'active':'' ?>" href="?tab=requests">Requests</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='members'?'active':'' ?>" href="?tab=members">Members</a></li>
</ul>

<?php if ($tab === 'sellers'): ?>
<div class="table-responsive">
<table class="table table-sm table-hover small">
<thead class="table-light">
    <tr><th>Name</th><th>Service</th><th>Category</th><th>Area</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($sellers as $s): ?>
<tr>
    <td><?= e($s['name']) ?><br><span class="text-muted" style="font-size:.7rem"><?= e($s['koperasi_id']) ?></span></td>
    <td><?= e($s['service_title']) ?></td>
    <td><?= e($s['category']) ?></td>
    <td><?= e($s['area']) ?></td>
    <td><span class="<?= $s['status']==='active'?'pill-active':'pill-inactive' ?>"><?= e($s['status']) ?></span></td>
    <td>
        <form method="post" class="d-inline">
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <input type="hidden" name="tab" value="sellers">
            <?php if ($s['status']==='active'): ?>
                <button name="action" value="deactivate_seller" class="btn btn-xs btn-outline-warning" style="font-size:.7rem;padding:1px 6px">Deactivate</button>
            <?php else: ?>
                <button name="action" value="activate_seller" class="btn btn-xs btn-outline-success" style="font-size:.7rem;padding:1px 6px">Activate</button>
            <?php endif; ?>
            <button name="action" value="delete_seller" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:1px 6px"
                onclick="return confirm('Delete this listing?')">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php elseif ($tab === 'requests'): ?>
<div class="table-responsive">
<table class="table table-sm table-hover small">
<thead class="table-light">
    <tr><th>Buyer</th><th>Category</th><th>Location</th><th>Budget</th><th>Status</th><th>Change Status</th></tr>
</thead>
<tbody>
<?php foreach ($requests as $r): ?>
<tr>
    <td><?= e($r['buyer_name']) ?><br><span class="text-muted" style="font-size:.7rem"><?= e($r['buyer_contact']) ?></span></td>
    <td><?= e($r['category']) ?></td>
    <td><?= e($r['location']) ?></td>
    <td><?= e($r['budget']) ?></td>
    <td><?= e($r['status']) ?></td>
    <td>
        <form method="post" class="d-flex gap-1">
            <input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <input type="hidden" name="action" value="update_request">
            <input type="hidden" name="tab" value="requests">
            <select name="status" class="form-select form-select-sm" style="width:auto;font-size:.75rem">
                <?php foreach (['open','in progress','matched','closed'] as $st): ?>
                    <option value="<?= $st ?>" <?= $r['status']===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:.73rem;padding:2px 8px">Save</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php elseif ($tab === 'members'): ?>
<div class="table-responsive">
<table class="table table-sm table-hover small">
<thead class="table-light">
    <tr><th>Name</th><th>Member ID</th><th>Email</th><th>Role</th><th>Joined</th></tr>
</thead>
<tbody>
<?php foreach ($members as $m): ?>
<tr>
    <td><?= e($m['name']) ?></td>
    <td><?= e($m['koperasi_id']) ?></td>
    <td><?= e($m['email'] ?: '—') ?></td>
    <td><span class="badge bg-<?= $m['role']==='admin'?'danger':'secondary' ?>"><?= e($m['role']) ?></span></td>
    <td><?= e($m['joined_date']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<div class="mt-3">
    <a href="?admin_logout=1" class="btn btn-sm btn-outline-secondary">Logout Admin</a>
</div>

<?php html_footer(); ?>

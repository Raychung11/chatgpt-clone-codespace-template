<?php
require_once __DIR__ . '/../layout.php';

session_start_safe();

// CSRF check for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') verify_csrf();

$admin_error  = '';
$admin_locked = null;

if (isset($_POST['admin_login'])) {
    $admin_locked = check_admin_rate_limit();
    if ($admin_locked) {
        $admin_error = 'Too many failed attempts. Try again in ' . ceil($admin_locked / 60) . ' minute(s).';
    } else {
        $kop_id = trim($_POST['admin_kop_id'] ?? '');
        $pw     = $_POST['admin_pw'] ?? '';
        $m      = authenticate_member($kop_id, $pw);
        if ($m && ($m['role'] ?? '') === 'admin') {
            $_SESSION['admin_auth'] = true;
            reset_admin_rate_limit();
        } else {
            record_admin_fail();
            $admin_error = 'Invalid Member ID or password.';
        }
    }
}
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['admin_auth']);
    redirect(ADMIN_URL . '/');
}
$is_admin = !empty($_SESSION['admin_auth']);

// ── Handle POST actions ───────────────────────────────────────
if ($is_admin && isset($_POST['action'])) {
    $id  = trim($_POST['id']  ?? '');
    $act = $_POST['action'];
    $tab = $_POST['tab'] ?? 'overview';

    if ($act === 'approve_seller') {
        $seller_row = get_seller_by_id($id);
        approve_seller($id, trim($_POST['note'] ?? ''));
        if ($seller_row) notify_listing_approved($seller_row);
        flash('Listing approved and set to active.');
    } elseif ($act === 'reject_seller') {
        $note = trim($_POST['note'] ?? '');
        if (!$note) { flash('Please enter a rejection reason.', 'error'); }
        else {
            $seller_row = get_seller_by_id($id);
            reject_seller($id, $note);
            if ($seller_row) notify_listing_rejected($seller_row, $note);
            flash('Listing rejected.', 'error');
        }
    } elseif ($act === 'activate_seller') {
        update_seller_status($id, 'active');
        flash('Listing activated.');
    } elseif ($act === 'deactivate_seller') {
        update_seller_status($id, 'inactive');
        flash('Listing deactivated.', 'error');
    } elseif ($act === 'delete_seller') {
        delete_seller($id);
        flash('Listing deleted.', 'error');
    } elseif ($act === 'update_request') {
        update_request_status($id, $_POST['status'] ?? 'open');
        flash('Request status updated.');
    } elseif ($act === 'reset_password') {
        $new_pw = trim($_POST['new_password'] ?? '');
        if (strlen($new_pw) < 6) { flash('Password must be at least 6 characters.', 'error'); }
        else { admin_reset_member_password($id, $new_pw); flash('Password reset for ' . e($id) . '.'); }
    } elseif ($act === 'toggle_member') {
        $cur = get_member_by_kop_id($id);
        $new_status = ($cur && ($cur['status'] ?? 'active') === 'active') ? 'inactive' : 'active';
        set_member_status($id, $new_status);
        flash('Member status updated to ' . $new_status . '.');
    } elseif ($act === 'delete_member') {
        delete_member($id);
        flash('Member deleted.', 'error');
    } elseif ($act === 'add_member') {
        $kop_id = strtoupper(trim($_POST['koperasi_id'] ?? ''));
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pw     = trim($_POST['password'] ?? '');
        if (!$kop_id || !$name || !$pw) {
            flash('Name, Member ID and password are required.', 'error');
        } elseif (!str_starts_with($kop_id, MEMBER_ID_PREFIX)) {
            flash('Member ID must start with ' . MEMBER_ID_PREFIX, 'error');
        } elseif (get_member_by_kop_id($kop_id)) {
            flash('Member ID ' . e($kop_id) . ' already exists.', 'error');
        } else {
            save_member(['name'=>$name,'koperasi_id'=>$kop_id,'email'=>$email,'password_hash'=>hash_password($pw)]);
            flash('Member ' . e($kop_id) . ' added successfully.');
        }
        $tab = 'members';
    } elseif ($act === 'import_csv') {
        $csv = '';
        $csv_file = $_FILES['csv_file'] ?? [];
        $csv_err  = '';
        if (!empty($csv_file['tmp_name']) && $csv_file['error'] === UPLOAD_ERR_OK) {
            if ($csv_file['size'] > 2 * 1024 * 1024) {
                $csv_err = 'CSV file too large (max 2 MB).';
            } else {
                $ext = strtolower(pathinfo($csv_file['name'] ?? '', PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt'], true)) {
                    $csv_err = 'Only .csv files are allowed.';
                } else {
                    $csv = file_get_contents($csv_file['tmp_name']);
                }
            }
        }
        if ($csv_err) {
            flash($csv_err, 'error');
        } elseif (!$csv) {
            flash('No CSV file uploaded.', 'error');
        } else {
            $result = import_members_csv($csv);
            $msg = "Import complete: {$result['imported']} imported, {$result['skipped']} skipped.";
            if ($result['errors']) $msg .= ' Errors: ' . implode('; ', $result['errors']);
            flash($msg, $result['imported'] > 0 ? 'success' : 'error');
        }
        $tab = 'members';
    }
    redirect(ADMIN_URL . '/?tab=' . $tab);
}

$tab      = $_GET['tab'] ?? 'overview';
$sellers  = $is_admin ? get_sellers() : [];
$pending  = $is_admin ? get_pending_sellers() : [];
$requests = $is_admin ? get_requests() : [];
$members  = $is_admin ? get_all_members() : [];

$active_count   = count(array_filter($sellers, fn($s) => $s['status'] === 'active'));
$pending_count  = count($pending);
$open_requests  = count(array_filter($requests, fn($r) => $r['status'] === 'open'));

html_head('Admin Dashboard');
html_body_open();
?>

<div class="page-title">🛡️ Admin Dashboard</div>

<?php if (!$is_admin): ?>
<!-- ── Login ── -->
<div class="card p-4" style="max-width:380px">
    <h5>🛡️ Admin Login</h5>
    <?php if ($admin_error): ?><div class="alert alert-danger py-2 small"><?= e($admin_error) ?></div><?php endif; ?>
    <?php
    $remaining_sec = check_admin_rate_limit();
    if ($remaining_sec):
    ?>
    <div class="alert alert-warning py-2 small">
        ⏱️ Account locked. Try again in <strong><?= ceil($remaining_sec / 60) ?> minute(s)</strong>.
    </div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Admin Member ID</label>
            <input type="text" name="admin_kop_id" class="form-control" placeholder="e.g. ADMIN-001" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="admin_pw" class="form-control" required>
        </div>
        <button type="submit" name="admin_login" value="1" class="btn btn-primary w-100"
            <?= $remaining_sec ? 'disabled' : '' ?>>Login</button>
    </form>
</div>
<?php html_footer(); return; ?>
<?php endif; ?>

<!-- ── Stats Row ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-box">
            <div class="val"><?= count($sellers) ?></div>
            <div class="lbl">Total Listings</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-box" style="<?= $pending_count > 0 ? 'border:2px solid #f39c12' : '' ?>">
            <div class="val" style="color:<?= $pending_count > 0 ? '#e67e22' : 'var(--primary)' ?>"><?= $pending_count ?></div>
            <div class="lbl">Pending Approval</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-box">
            <div class="val"><?= $open_requests ?></div>
            <div class="lbl">Open Requests</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-box">
            <div class="val"><?= count($members) ?></div>
            <div class="lbl">Members</div>
        </div>
    </div>
</div>

<!-- ── Tabs ── -->
<ul class="nav nav-tabs mb-3 flex-wrap">
    <li class="nav-item">
        <a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?tab=overview">📊 Overview</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='pending'?'active':'' ?>" href="?tab=pending">
            ⏳ Pending
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='sellers'?'active':'' ?>" href="?tab=sellers">📋 All Listings</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='requests'?'active':'' ?>" href="?tab=requests">🛒 Requests</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='members'?'active':'' ?>" href="?tab=members">👥 Members</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab==='import'?'active':'' ?>" href="?tab=import">⬆️ Import</a>
    </li>
</ul>

<?php /* ══════════════════ OVERVIEW ══════════════════ */ if ($tab === 'overview'): ?>

<div class="row g-3">
    <!-- Category breakdown -->
    <div class="col-md-6">
        <div class="card p-3">
            <div class="section-head mb-2">Listings by Category</div>
            <?php
            $by_cat = [];
            foreach ($sellers as $s) { $by_cat[$s['category']] = ($by_cat[$s['category']] ?? 0) + 1; }
            arsort($by_cat);
            $icons = cat_icons();
            foreach ($by_cat as $cat => $cnt):
                $pct = count($sellers) ? round($cnt / count($sellers) * 100) : 0;
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= ($icons[$cat]??'⭐') . ' ' . e($cat) ?></span>
                    <span class="fw-semibold"><?= $cnt ?></span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= cat_colors()[$cat]??'#1a5276' ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Recent activity -->
    <div class="col-md-6">
        <div class="card p-3">
            <div class="section-head mb-2">Recent Listings</div>
            <?php foreach (array_slice($sellers, 0, 6) as $s): ?>
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size:.8rem">
                <div>
                    <span class="fw-semibold"><?= e($s['service_title']) ?></span><br>
                    <span class="text-muted"><?= e($s['name']) ?> · <?= e($s['koperasi_id']) ?></span>
                </div>
                <span class="<?= $s['status']==='active'?'pill-active':($s['status']==='pending'?'':'pill-inactive') ?>"
                    style="<?= $s['status']==='pending'?'background:#fff3cd;color:#856404;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600':'' ?>">
                    <?= e($s['status']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php /* ══════════════════ PENDING APPROVAL ══════════════════ */ elseif ($tab === 'pending'): ?>

<?php if (empty($pending)): ?>
    <div class="alert alert-success">✅ No listings pending approval. All clear!</div>
<?php else: ?>
    <p class="text-muted small mb-3">Review each listing below. Approved listings go live immediately. Rejected listings are hidden from the marketplace and the member is notified via their portal.</p>
    <?php foreach ($pending as $s): ?>
    <div class="card mb-3 p-3" style="border-left:4px solid #f39c12">
        <div class="row g-2 align-items-start">
            <div class="col-md-8">
                <?= cat_badge($s['category']) ?>
                <h5 class="mt-1 mb-1"><?= e($s['service_title']) ?></h5>
                <div class="small text-muted mb-1">
                    👤 <?= e($s['name']) ?> &nbsp;|&nbsp;
                    🪪 <?= e($s['koperasi_id']) ?> &nbsp;|&nbsp;
                    📍 <?= e($s['area']) ?> &nbsp;|&nbsp;
                    💰 <?= e($s['price_range']) ?>
                </div>
                <div class="small text-muted mb-1">📅 Submitted: <?= e($s['registered_date']) ?></div>
                <div class="small" style="background:#f8f9fa;padding:.5rem;border-radius:6px;max-height:80px;overflow:auto">
                    <?= nl2br(e(substr($s['description'], 0, 300))) ?>…
                </div>
            </div>
            <div class="col-md-4">
                <?php if ($s['image']): ?>
                    <img src="<?= e(img_url($s['image'])) ?>" class="img-fluid rounded mb-2" style="max-height:100px;object-fit:cover;width:100%">
                <?php endif; ?>
                <!-- Approve -->
                <form method="post" class="mb-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                    <input type="hidden" name="tab" value="pending">
                    <input type="hidden" name="action" value="approve_seller">
                    <input type="text" name="note" class="form-control form-control-sm mb-1" placeholder="Optional note for member (optional)">
                    <button type="submit" class="btn btn-success btn-sm w-100"
                        onclick="return confirm('Approve this listing?')">✅ Approve &amp; Activate</button>
                </form>
                <!-- Reject -->
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                    <input type="hidden" name="tab" value="pending">
                    <input type="hidden" name="action" value="reject_seller">
                    <input type="text" name="note" class="form-control form-control-sm mb-1" placeholder="Rejection reason (required)" required>
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">❌ Reject</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php /* ══════════════════ ALL LISTINGS ══════════════════ */ elseif ($tab === 'sellers'): ?>

<div class="table-responsive">
<table class="table table-sm table-hover small align-middle">
<thead class="table-light">
    <tr><th>Member / ID</th><th>Service</th><th>Category</th><th>Area</th><th>Status</th><th>Admin Note</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($sellers as $s): ?>
<tr>
    <td>
        <?= e($s['name']) ?><br>
        <span class="text-muted" style="font-size:.7rem"><?= e($s['koperasi_id']) ?></span>
    </td>
    <td><?= e($s['service_title']) ?></td>
    <td><?= cat_badge($s['category']) ?></td>
    <td><?= e($s['area']) ?></td>
    <td>
        <?php
        $pill = match($s['status']) {
            'active'   => 'pill-active',
            'pending'  => '',
            'rejected' => 'pill-inactive',
            default    => 'pill-inactive',
        };
        $style = $s['status']==='pending' ? 'background:#fff3cd;color:#856404;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600' : '';
        ?>
        <span class="<?= $pill ?>" style="<?= $style ?>"><?= e($s['status']) ?></span>
    </td>
    <td><span class="text-muted" style="font-size:.7rem"><?= e(substr($s['admin_note']??'',0,50)) ?></span></td>
    <td>
        <form method="post" class="d-flex gap-1 flex-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <input type="hidden" name="tab" value="sellers">
            <?php if ($s['status'] !== 'active'): ?>
                <button name="action" value="activate_seller" class="btn btn-xs btn-outline-success" style="font-size:.7rem;padding:1px 6px">Activate</button>
            <?php else: ?>
                <button name="action" value="deactivate_seller" class="btn btn-xs btn-outline-warning" style="font-size:.7rem;padding:1px 6px">Deactivate</button>
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

<?php /* ══════════════════ REQUESTS ══════════════════ */ elseif ($tab === 'requests'): ?>

<div class="table-responsive">
<table class="table table-sm table-hover small align-middle">
<thead class="table-light">
    <tr><th>Buyer</th><th>Category</th><th>Location</th><th>Budget</th><th>Date</th><th>Status</th><th>Change</th></tr>
</thead>
<tbody>
<?php foreach ($requests as $r): ?>
<tr>
    <td>
        <?= e($r['buyer_name']) ?><br>
        <span class="text-muted" style="font-size:.7rem"><?= e($r['buyer_contact']) ?></span>
    </td>
    <td><?= cat_badge($r['category']) ?></td>
    <td><?= e($r['location']) ?></td>
    <td><?= e($r['budget']) ?></td>
    <td><?= e($r['submitted_date']) ?></td>
    <td><span class="pill-<?= e($r['status'] === 'in progress' ? 'matched' : $r['status']) ?>"><?= e($r['status']) ?></span></td>
    <td>
        <form method="post" class="d-flex gap-1">
            <?= csrf_field() ?>
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

<?php /* ══════════════════ MEMBERS ══════════════════ */ elseif ($tab === 'members'): ?>

<!-- Add member inline form -->
<div class="card p-3 mb-3" style="max-width:700px">
    <div class="section-head">➕ Add New Member</div>
    <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="tab" value="members">
        <div class="col-md-3">
            <input type="text" name="name" class="form-control form-control-sm" placeholder="Full name *" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="koperasi_id" class="form-control form-control-sm"
                placeholder="<?= MEMBER_ID_PREFIX ?>00001 *" required>
        </div>
        <div class="col-md-3">
            <input type="email" name="email" class="form-control form-control-sm" placeholder="Email (optional)">
        </div>
        <div class="col-md-2">
            <input type="text" name="password" class="form-control form-control-sm" placeholder="Password *" required>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
        </div>
    </form>
</div>

<!-- Member table -->
<div class="table-responsive">
<table class="table table-sm table-hover small align-middle">
<thead class="table-light">
    <tr><th>Name</th><th>Member ID</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Reset PW</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($members as $m): ?>
<tr>
    <td><?= e($m['name']) ?></td>
    <td><span class="fw-semibold"><?= e($m['koperasi_id']) ?></span></td>
    <td><?= e($m['email'] ?: '—') ?></td>
    <td><span class="badge bg-<?= $m['role']==='admin'?'danger':'secondary' ?>"><?= e($m['role']) ?></span></td>
    <td>
        <?php $mst = $m['status'] ?? 'active'; ?>
        <span class="<?= $mst==='active'?'pill-active':'pill-inactive' ?>"><?= e($mst) ?></span>
    </td>
    <td><?= e($m['joined_date']) ?></td>
    <!-- Reset password inline -->
    <td>
        <form method="post" class="d-flex gap-1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= e($m['koperasi_id']) ?>">
            <input type="hidden" name="tab" value="members">
            <input type="text" name="new_password" class="form-control form-control-sm"
                placeholder="New PW" style="width:90px;font-size:.72rem" minlength="6">
            <button type="submit" class="btn btn-sm btn-outline-secondary" style="font-size:.7rem;padding:1px 6px"
                onclick="return confirm('Reset password for <?= e(addslashes($m['koperasi_id'])) ?>?')">Reset</button>
        </form>
    </td>
    <td>
        <form method="post" class="d-flex gap-1">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($m['koperasi_id']) ?>">
            <input type="hidden" name="tab" value="members">
            <?php if ($m['role'] !== 'admin'): ?>
            <button name="action" value="toggle_member" class="btn btn-xs btn-outline-warning" style="font-size:.7rem;padding:1px 6px">
                <?= ($m['status']??'active')==='active' ? 'Deactivate' : 'Activate' ?>
            </button>
            <button name="action" value="delete_member" class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:1px 6px"
                onclick="return confirm('Delete member <?= e(addslashes($m['koperasi_id'])) ?>?')">Delete</button>
            <?php else: ?>
                <span class="text-muted small">—</span>
            <?php endif; ?>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php /* ══════════════════ CSV IMPORT ══════════════════ */ elseif ($tab === 'import'): ?>

<div class="card p-4" style="max-width:640px">
    <div class="section-head">⬆️ Bulk Member Import via CSV</div>
    <p class="text-muted small mb-3">Upload a CSV file with member data. Existing Member IDs will be skipped (no overwrite).</p>

    <div class="alert alert-info small">
        <strong>Required CSV format (with header row):</strong><br>
        <code>name,koperasi_id,email,password</code><br><br>
        Example:<br>
        <code>
            name,koperasi_id,email,password<br>
            Ahmad Bin Ali,KKBR-00101,ahmad@email.com,temppass123<br>
            Siti Binti Omar,KKBR-00102,siti@email.com,temppass456
        </code>
        <br><br>
        <strong>Notes:</strong>
        <ul class="mb-0">
            <li>Member ID must start with <code><?= MEMBER_ID_PREFIX ?></code></li>
            <li>Password field is required per row</li>
            <li>Email is optional</li>
            <li>Max 500 rows per upload recommended</li>
        </ul>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_csv">
        <input type="hidden" name="tab" value="members">
        <div class="mb-3">
            <label class="form-label fw-semibold">Select CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary"
            onclick="return confirm('Import members from this CSV? Existing IDs will be skipped.')">
            ⬆️ Upload and Import
        </button>
    </form>

    <hr>
    <div class="section-head mt-2">📥 Download Template</div>
    <p class="small text-muted">Copy this template and fill in your member data:</p>
    <textarea class="form-control font-monospace small" rows="5" readonly
        onclick="this.select()"><?= "name,koperasi_id,email,password\nAhmad Bin Ali,KKBR-00101,ahmad@email.com,temppass123\nSiti Binti Omar,KKBR-00102,siti@email.com,temppass456" ?></textarea>
    <small class="text-muted">Click the box above to select all, then copy (Ctrl+C).</small>
</div>

<?php endif; // end tab switch ?>

<div class="mt-4">
    <a href="?admin_logout=1" class="btn btn-sm btn-outline-secondary">Logout Admin</a>
</div>

<?php html_footer(); ?>

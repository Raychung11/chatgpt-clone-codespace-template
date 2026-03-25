<?php
require_once __DIR__ . '/layout.php';

$login_errors   = [];
$reg_errors     = [];
$pw_errors      = [];
$pw_success     = '';
$prof_success   = '';
$prof_errors    = [];
$tab            = $_GET['tab'] ?? 'login';

// ── Handle Login ──────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $kop_id  = trim($_POST['koperasi_id'] ?? '');
    $pw      = $_POST['password'] ?? '';
    if (!$kop_id || !$pw) {
        $login_errors[] = 'Please enter your Member ID and password.';
    } else {
        $m = authenticate_member($kop_id, $pw);
        if ($m) {
            login_member($m);
            flash('Welcome back, ' . $m['name'] . '!');
            redirect('member_portal.php');
        } else {
            $login_errors[] = 'Invalid Member ID or password.';
        }
    }
}

// ── Handle Register ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $tab    = 'register';
    $name   = trim($_POST['name']           ?? '');
    $kop_id = trim($_POST['koperasi_id']    ?? '');
    $email  = trim($_POST['email']          ?? '');
    $pw     = $_POST['password']            ?? '';
    $pw2    = $_POST['password_confirm']    ?? '';

    if (!$name)            $reg_errors[] = 'Full name is required.';
    if (!$kop_id)          $reg_errors[] = 'Koperasi Member ID is required.';
    if (strlen($pw) < 6)   $reg_errors[] = 'Password must be at least 6 characters.';
    if ($pw !== $pw2)      $reg_errors[] = 'Passwords do not match.';
    if (empty($reg_errors) && get_member_by_kop_id($kop_id)) {
        $reg_errors[] = 'Member ID already registered.';
    }

    if (empty($reg_errors)) {
        $id = save_member(['name' => $name, 'koperasi_id' => $kop_id, 'email' => $email, 'password_hash' => hash_password($pw)]);
        $m  = get_member_by_kop_id($kop_id);
        login_member($m);
        flash('Account created! Welcome, ' . $name . '.');
        redirect('member_portal.php');
    }
}

// ── Handle Profile Update ─────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_profile' && is_logged_in()) {
    $tab    = 'profile';
    $member = current_member();
    $p_name = trim($_POST['profile_name'] ?? '');
    $p_email= trim($_POST['profile_email'] ?? '');
    $p_bio  = trim($_POST['profile_bio']  ?? '');

    if (!$p_name) {
        $prof_errors[] = 'Name cannot be empty.';
    } else {
        $updates = ['name' => $p_name, 'email' => $p_email, 'bio' => $p_bio];
        $avatar  = handle_image_upload('avatar', 'avatars');
        if ($avatar) $updates['avatar'] = $avatar;
        update_member_profile($member['koperasi_id'], $updates);
        $updated = get_member_by_kop_id($member['koperasi_id']);
        login_member($updated);
        $prof_success = 'Profile updated successfully.';
        $member = $updated;
    }
}

// ── Handle Password Change ────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'change_password' && is_logged_in()) {
    $tab    = 'profile';
    $member = current_member();
    $cur    = $_POST['current_password'] ?? '';
    $new    = $_POST['new_password']     ?? '';
    $new2   = $_POST['new_password2']    ?? '';

    if (hash_password($cur) !== $member['password_hash']) {
        $pw_errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $pw_errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $new2) {
        $pw_errors[] = 'New passwords do not match.';
    } else {
        update_member_password($member['koperasi_id'], hash_password($new));
        $updated = get_member_by_kop_id($member['koperasi_id']);
        login_member($updated);
        $pw_success = 'Password updated successfully.';
    }
}

$member = current_member();
html_head('Member Portal');
html_body_open();
?>

<div class="page-title">👤 Member Portal</div>

<?php if (!$member): ?>
<!-- ── Not logged in ─────────────────────────────────────────── -->
<?php if (!empty($_GET['login_required'])): ?>
<div class="alert alert-warning">Please log in to access that feature.</div>
<?php endif; ?>

<div class="row g-4" style="max-width:700px">
    <!-- Login -->
    <div class="col-md-6">
        <div class="card p-4 h-100">
            <h5 class="mb-3">🔑 Member Login</h5>
            <?php if ($login_errors): ?>
                <div class="alert alert-danger py-2 small"><?= e($login_errors[0]) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="mb-3">
                    <label class="form-label">Koperasi Member ID</label>
                    <input type="text" name="koperasi_id" class="form-control"
                        placeholder="e.g. KOP-2024-001"
                        value="<?= e($_POST['koperasi_id'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
    <!-- Register -->
    <div class="col-md-6">
        <div class="card p-4 h-100">
            <h5 class="mb-3">📝 New Member</h5>
            <?php if ($reg_errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0"><?php foreach ($reg_errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="mb-2">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control"
                        value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Koperasi Member ID</label>
                    <input type="text" name="koperasi_id" class="form-control"
                        placeholder="e.g. KOP-2024-099"
                        value="<?= e($_POST['koperasi_id'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Email (optional)</label>
                    <input type="email" name="email" class="form-control"
                        value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">Create Account</button>
            </form>
        </div>
    </div>
</div>

<?php else:
// ── Logged in ────────────────────────────────────────────────
$kop_id      = $member['koperasi_id'];
$my_listings = get_sellers(['koperasi_id' => $kop_id]);
$my_requests = get_requests(['member_kop_id' => $kop_id]);
$active_l    = array_filter($my_listings, fn($s) => $s['status'] === 'active');
$open_r      = array_filter($my_requests, fn($r) => $r['status'] === 'open');
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($my_listings) ?></div><div class="lbl">My Listings</div></div></div>
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($active_l) ?></div><div class="lbl">Active</div></div></div>
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($my_requests) ?></div><div class="lbl">My Requests</div></div></div>
    <div class="col-3"><div class="stat-box"><div class="val"><?= count($open_r) ?></div><div class="lbl">Open</div></div></div>
</div>

<ul class="nav nav-tabs mb-3" id="portalTabs">
    <li class="nav-item"><a class="nav-link <?= $tab==='listings'?'active':'' ?>" href="?tab=listings">My Listings</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='requests'?'active':'' ?>" href="?tab=requests">My Requests</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='coaching'?'active':'' ?>" href="?tab=coaching">AI Coaching</a></li>
    <li class="nav-item"><a class="nav-link <?= ($tab==='profile'||!in_array($tab,['listings','requests','coaching']))?'active':'' ?>" href="?tab=profile">Profile</a></li>
</ul>

<!-- My Listings -->
<?php if ($tab === 'listings'): ?>
<?php if (!$my_listings): ?>
    <div class="alert alert-info">No listings yet. <a href="register_service.php">Add your first service →</a></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($my_listings as $s): ?>
    <div class="col-md-6">
    <div class="seller-card h-100">
        <div class="d-flex gap-2 align-items-start">
            <?php if ($s['image']): ?>
                <img src="<?= e(img_url($s['image'])) ?>"
                    style="width:72px;height:72px;object-fit:cover;border-radius:8px;flex-shrink:0">
            <?php else: ?>
                <div style="width:72px;height:72px;border-radius:8px;flex-shrink:0;background:linear-gradient(135deg,<?= e(cat_colors()[$s['category']] ?? '#607d8b') ?>,#ddd);display:flex;align-items:center;justify-content:center;font-size:1.6rem">
                    <?= cat_icons()[$s['category']] ?? '⭐' ?>
                </div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <?= cat_badge($s['category']) ?>
                <span class="ms-1 <?= $s['status']==='active'?'pill-active':'pill-inactive' ?>"><?= e($s['status']) ?></span>
                <h5 class="mt-1"><?= e($s['service_title']) ?></h5>
                <div class="meta">📍 <?= e($s['area']) ?> &nbsp;|&nbsp; 💰 <?= e($s['price_range']) ?></div>
            </div>
        </div>
        <div class="desc mt-2"><?= e(substr($s['description'],0,120)) ?>…</div>
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <a href="edit_listing.php?id=<?= urlencode($s['id']) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
            <a href="find_services.php?category=<?= urlencode($s['category']) ?>" class="btn btn-sm btn-outline-secondary">View Public</a>
            <a href="ai_assistant.php?prompt=<?= urlencode('Give me tips to improve my listing: '.$s['service_title'].' in '.$s['area']) ?>" class="btn btn-sm btn-outline-info">🤖 AI Tips</a>
        </div>
    </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<a href="register_service.php" class="btn btn-primary mt-3">+ Add New Listing</a>

<!-- My Requests -->
<?php elseif ($tab === 'requests'): ?>
<?php if (!$my_requests): ?>
    <div class="alert alert-info">No requests yet. <a href="request_service.php">Submit a request →</a></div>
<?php else: ?>
<?php
$status_class = ['open'=>'pill-open','in progress'=>'badge bg-warning text-dark','matched'=>'pill-matched','closed'=>'pill-closed'];
foreach ($my_requests as $r): ?>
    <div class="card mb-2 p-3">
        <?= cat_badge($r['category']) ?>
        <span class="ms-1 <?= $status_class[$r['status']] ?? 'badge bg-secondary' ?>"><?= e($r['status']) ?></span>
        <div class="small mt-1 text-muted"><?= e($r['service_description']) ?></div>
        <div class="small text-muted">📍 <?= e($r['location']) ?> | 💰 <?= e($r['budget']) ?> | <?= e($r['submitted_date']) ?></div>
    </div>
<?php endforeach; ?>
<?php endif; ?>
<a href="request_service.php" class="btn btn-primary mt-2">+ New Request</a>

<!-- AI Coaching -->
<?php elseif ($tab === 'coaching'): ?>
<div class="card p-4" style="max-width:640px">
    <h5>🤖 AI Coaching for <?= e($member['name']) ?></h5>
    <p class="text-muted small">Get personalised AI tips based on your listings and requests.</p>
    <button class="btn btn-primary" id="getCoachingBtn">Get My AI Coaching Tips</button>
    <div id="coachingResult" class="mt-3"></div>
</div>
<script>
document.getElementById('getCoachingBtn').addEventListener('click', async () => {
    const btn = document.getElementById('getCoachingBtn');
    btn.disabled = true; btn.textContent = 'Thinking…';
    const res = await fetch('ajax/ai_coaching.php', {method:'POST'});
    const data = await res.json();
    document.getElementById('coachingResult').innerHTML =
        `<div class="alert alert-info"><strong>💡 Your Tips:</strong><br>${data.tips.replace(/\n/g,'<br>')}</div>`;
    btn.disabled = false; btn.textContent = '🔄 Refresh Tips';
});
</script>

<!-- Profile -->
<?php else: ?>
<div class="row g-4" style="max-width:780px">

    <!-- Avatar + info card -->
    <div class="col-md-4">
        <div class="card p-3 text-center">
            <?php if (!empty($member['avatar'])): ?>
                <img src="<?= e(img_url($member['avatar'])) ?>"
                    class="rounded-circle mx-auto mb-2"
                    style="width:90px;height:90px;object-fit:cover;border:3px solid #1a5276">
            <?php else: ?>
                <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                    style="width:90px;height:90px;background:#1a5276;font-size:2rem;color:#fff">
                    <?= mb_strtoupper(mb_substr($member['name'],0,1)) ?>
                </div>
            <?php endif; ?>
            <div class="fw-bold"><?= e($member['name']) ?></div>
            <div class="text-muted small"><?= e($member['koperasi_id']) ?></div>
            <div class="text-muted small mt-1">Joined <?= e($member['joined_date']) ?></div>
            <?php if (!empty($member['bio'])): ?>
                <div class="mt-2 small text-muted fst-italic"><?= e($member['bio']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit profile form -->
    <div class="col-md-8">
        <div class="card p-3 mb-3">
            <h6>✏️ Edit Profile</h6>
            <?php if ($prof_errors): ?>
                <div class="alert alert-danger py-2 small"><?= e($prof_errors[0]) ?></div>
            <?php endif; ?>
            <?php if ($prof_success): ?>
                <div class="alert alert-success py-2 small"><?= e($prof_success) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="profile_name" class="form-control form-control-sm"
                        value="<?= e($member['name']) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="profile_email" class="form-control form-control-sm"
                        value="<?= e($member['email'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Short Bio</label>
                    <textarea name="profile_bio" class="form-control form-control-sm" rows="2"
                        placeholder="Tell others a bit about yourself…"><?= e($member['bio'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Profile Photo</label>
                    <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*"
                        onchange="previewAvatar(this)">
                    <img id="avatarPreview" src="" class="rounded-circle mt-2 d-none"
                        style="width:60px;height:60px;object-fit:cover">
                    <div class="small text-muted mt-1">JPG/PNG/WebP, max 2MB</div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">💾 Save Profile</button>
            </form>
        </div>

        <div class="card p-3">
            <h6>🔒 Change Password</h6>
            <?php if ($pw_errors): ?>
                <div class="alert alert-danger py-2 small"><?= e($pw_errors[0]) ?></div>
            <?php endif; ?>
            <?php if ($pw_success): ?>
                <div class="alert alert-success py-2 small"><?= e($pw_success) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-2">
                    <label class="form-label small">Current Password</label>
                    <input type="password" name="current_password" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">New Password</label>
                    <input type="password" name="new_password" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Confirm New Password</label>
                    <input type="password" name="new_password2" class="form-control form-control-sm" required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Update Password</button>
            </form>
        </div>

        <div class="mt-3">
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sign Out</a>
        </div>
    </div>
</div>
<script>
function previewAvatar(input) {
    const preview = document.getElementById('avatarPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.classList.remove('d-none'); };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php endif; ?>

<?php endif; ?>
<?php html_footer(); ?>

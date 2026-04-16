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
    elseif (!str_starts_with(strtoupper($kop_id), MEMBER_ID_PREFIX))
        $reg_errors[] = 'Member ID must start with ' . MEMBER_ID_PREFIX . ' (e.g. ' . MEMBER_ID_PREFIX . '00123). Contact koperasi admin if you don\'t know your ID.';
    if (strlen($pw) < 6)   $reg_errors[] = 'Password must be at least 6 characters.';
    if ($pw !== $pw2)      $reg_errors[] = 'Passwords do not match.';
    if (empty($reg_errors) && get_member_by_kop_id(strtoupper($kop_id))) {
        $reg_errors[] = 'Member ID already registered.';
    }
    if (empty($reg_errors)) $kop_id = strtoupper($kop_id);

    if (empty($reg_errors)) {
        save_member(['name' => $name, 'koperasi_id' => $kop_id, 'email' => $email, 'password_hash' => hash_password($pw)]);
        // Auto-generate referral code for new member
        ensure_referral_code($kop_id);
        // Give 10 welcome credits to every new member
        add_credits($kop_id, 10, 'Welcome bonus — account created');
        // Apply referral code if provided
        $ref_code = strtoupper(trim($_POST['referral_code'] ?? ''));
        if ($ref_code) apply_referral($kop_id, $ref_code);
        $m = get_member_by_kop_id($kop_id);
        login_member($m);
        flash('Account created! Welcome, ' . $name . '. You have been given 10 credits to get started!');
        redirect('member_portal.php?tab=credits');
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
                        placeholder="e.g. KKBR-00123"
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
                        placeholder="e.g. KKBR-00123"
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
                <div class="mb-2">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Referral Code <span class="text-muted">(optional)</span></label>
                    <input type="text" name="referral_code" class="form-control"
                        placeholder="e.g. KKBR2F8X4A"
                        value="<?= e($_GET['ref'] ?? $_POST['referral_code'] ?? '') ?>">
                    <div class="form-text">🎁 If a friend referred you, enter their code. You'll both get bonus credits!</div>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">Create Account &amp; Get 10 Credits</button>
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
$credits     = get_credits($kop_id);
$ref_code    = ensure_referral_code($kop_id);
$ref_url     = rtrim(defined('SITE_URL') ? SITE_URL : 'https://yourdomain.com', '/')
               . '/member_portal.php?tab=register&ref=' . $ref_code;
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-box"><div class="val"><?= count($my_listings) ?></div><div class="lbl">My Listings</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-box"><div class="val"><?= count($active_l) ?></div><div class="lbl">Active</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-box"><div class="val"><?= count($my_requests) ?></div><div class="lbl">My Requests</div></div></div>
    <div class="col-6 col-md-3">
        <div class="stat-box" style="background:linear-gradient(135deg,#f6d365,#fda085);cursor:pointer"
            onclick="window.location='?tab=credits'">
            <div class="val" style="color:#7d3200">💰 <?= $credits ?></div>
            <div class="lbl" style="color:#7d3200">Credits</div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3 flex-wrap" id="portalTabs">
    <li class="nav-item"><a class="nav-link <?= $tab==='listings'?'active':'' ?>" href="?tab=listings">📋 My Listings</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='requests'?'active':'' ?>" href="?tab=requests">🛒 My Requests</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='credits'?'active':'' ?>" href="?tab=credits">💰 Credits &amp; Referral</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='coaching'?'active':'' ?>" href="?tab=coaching">🤖 AI Coaching</a></li>
    <li class="nav-item"><a class="nav-link <?= ($tab==='profile'||!in_array($tab,['listings','requests','credits','coaching']))?'active':'' ?>" href="?tab=profile">👤 Profile</a></li>
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
                <?php
                $spill = match($s['status']) {
                    'active'   => '<span class="ms-1 pill-active">✅ active</span>',
                    'pending'  => '<span class="ms-1" style="background:#fff3cd;color:#856404;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600">⏳ pending approval</span>',
                    'rejected' => '<span class="ms-1 pill-inactive">❌ rejected</span>',
                    default    => '<span class="ms-1 pill-inactive">' . e($s['status']) . '</span>',
                };
                echo $spill;
                ?>
                <h5 class="mt-1"><?= e($s['service_title']) ?></h5>
                <div class="meta">📍 <?= e($s['area']) ?> &nbsp;|&nbsp; 💰 <?= e($s['price_range']) ?></div>
                <?php if ($s['status']==='pending'): ?>
                    <div class="small text-warning mt-1">⏳ Your listing is under review by the koperasi admin (1–2 working days).</div>
                <?php elseif ($s['status']==='rejected' && !empty($s['admin_note'])): ?>
                    <div class="small text-danger mt-1">Reason: <?= e($s['admin_note']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="desc mt-2"><?= e(substr($s['description'],0,120)) ?>…</div>
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <a href="edit_listing.php?id=<?= urlencode($s['id']) ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
            <?php if ($s['status']==='active'): ?>
            <a href="find_services.php?category=<?= urlencode($s['category']) ?>" class="btn btn-sm btn-outline-secondary">View Public</a>
            <?php endif; ?>
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

<!-- Credits & Referral -->
<?php elseif ($tab === 'credits'):
    $credit_history  = get_credit_history($kop_id);
    $referral_count  = get_referral_count($ref_code);
    $whatsapp_msg    = urlencode("Jom sertai Koponix — marketplace digital Koperasi Kakitangan Bank Rakyat! Guna kod referral saya *{$ref_code}* untuk daftar dan dapatkan kredit percuma. Daftar di: {$ref_url}");
?>
<div class="row g-4">

    <!-- Left: Credit balance + referral -->
    <div class="col-lg-5">

        <!-- Credit Balance Card -->
        <div class="card p-4 mb-3 text-center"
            style="background:linear-gradient(135deg,#f6d365,#fda085);border-radius:16px">
            <div style="font-size:.8rem;font-weight:600;color:#7d3200;margin-bottom:.3rem">
                YOUR CREDIT BALANCE
            </div>
            <div style="font-size:3rem;font-weight:800;color:#7d3200;line-height:1">
                <?= $credits ?>
            </div>
            <div style="font-size:.85rem;color:#7d3200;margin-top:.2rem">
                credits &nbsp;≈&nbsp; RM <?= number_format($credits, 2) ?>
            </div>
            <div style="margin-top:1rem;font-size:.75rem;color:rgba(125,50,0,.7)">
                1 credit = RM 1.00 &nbsp;|&nbsp; Use when ordering services
            </div>
        </div>

        <!-- How to earn credits -->
        <div class="card p-3 mb-3">
            <div class="section-head">How to Earn Credits</div>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex gap-3 align-items-start">
                    <span style="font-size:1.4rem">🎁</span>
                    <div><strong>Welcome Bonus</strong><br>
                    <span class="text-muted small">+10 credits when you first create your account</span></div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <span style="font-size:1.4rem">🔗</span>
                    <div><strong>Refer a Friend</strong><br>
                    <span class="text-muted small">+10 credits for every friend who registers with your code</span></div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <span style="font-size:1.4rem">🌟</span>
                    <div><strong>Join via Referral</strong><br>
                    <span class="text-muted small">+5 bonus credits if you joined using someone's referral code</span></div>
                </div>
            </div>
        </div>

        <!-- How to use credits -->
        <div class="card p-3">
            <div class="section-head">How to Use Credits</div>
            <div class="text-muted small">
                When posting a service request, select <strong>"Apply my credits"</strong> to use
                your credits as a discount on the service. Credits are deducted from your balance
                and the provider receives the full payment.
            </div>
            <a href="request_service.php" class="btn btn-primary btn-sm mt-2">🛒 Post a Request</a>
        </div>

    </div>

    <!-- Right: Referral link + history -->
    <div class="col-lg-7">

        <!-- Referral link -->
        <div class="card p-4 mb-3">
            <div class="section-head">Your Referral Code</div>
            <div class="d-flex gap-2 align-items-center mb-2">
                <div style="font-size:1.6rem;font-weight:800;letter-spacing:.1em;color:var(--primary);
                    background:#eef4fb;padding:.5rem 1.2rem;border-radius:10px;flex:1;text-align:center">
                    <?= e($ref_code) ?>
                </div>
                <button class="btn btn-outline-primary" onclick="copyCode()"
                    id="copyBtn" style="white-space:nowrap">📋 Copy Code</button>
            </div>
            <div class="input-group mb-3">
                <input type="text" id="refUrl" class="form-control form-control-sm"
                    value="<?= e($ref_url) ?>" readonly onclick="this.select()">
                <button class="btn btn-outline-secondary btn-sm" onclick="copyLink()">Copy Link</button>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="https://wa.me/?text=<?= $whatsapp_msg ?>" target="_blank"
                    class="btn btn-success btn-sm">📱 Share via WhatsApp</a>
                <a href="https://t.me/share/url?url=<?= urlencode($ref_url) ?>&text=<?= urlencode("Guna kod referral {$ref_code} untuk dapat kredit percuma di Koponix!") ?>"
                    target="_blank" class="btn btn-outline-secondary btn-sm">✈️ Telegram</a>
            </div>
            <div class="mt-3 d-flex align-items-center gap-2">
                <span style="font-size:1.3rem">👥</span>
                <span class="text-muted small">
                    <strong style="color:var(--primary)"><?= $referral_count ?></strong>
                    <?= $referral_count === 1 ? 'person has' : 'people have' ?> joined using your referral code.
                    That's <strong><?= $referral_count * 10 ?> credits</strong> earned!
                </span>
            </div>
        </div>

        <!-- Credit history -->
        <div class="card p-3">
            <div class="section-head">Credit History</div>
            <?php if (empty($credit_history)): ?>
                <p class="text-muted small">No credit transactions yet.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm small">
            <thead class="table-light">
                <tr><th>Date</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Type</th></tr>
            </thead>
            <tbody>
            <?php foreach ($credit_history as $tx): ?>
            <tr>
                <td class="text-muted"><?= e(substr($tx['created_at'], 0, 10)) ?></td>
                <td><?= e($tx['description']) ?></td>
                <td class="text-end fw-semibold <?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $tx['amount'] > 0 ? '+' : '' ?><?= $tx['amount'] ?>
                </td>
                <td class="text-end">
                    <span class="<?= $tx['type']==='earn'?'pill-active':'pill-inactive' ?>">
                        <?= e($tx['type']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function copyCode() {
    navigator.clipboard.writeText('<?= e($ref_code) ?>').then(() => {
        const btn = document.getElementById('copyBtn');
        btn.textContent = '✅ Copied!'; btn.classList.replace('btn-outline-primary','btn-success');
        setTimeout(() => { btn.textContent = '📋 Copy Code'; btn.classList.replace('btn-success','btn-outline-primary'); }, 2000);
    });
}
function copyLink() {
    document.getElementById('refUrl').select();
    navigator.clipboard.writeText(document.getElementById('refUrl').value);
}
</script>

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

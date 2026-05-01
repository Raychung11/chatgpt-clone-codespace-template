<?php
require_once __DIR__ . '/layout.php';

$errors  = [];
$member  = current_member();
$credits = $member ? get_credits($member['koperasi_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $buyer_name          = trim($_POST['buyer_name']          ?? '');
    $buyer_contact       = trim($_POST['buyer_contact']       ?? '');
    $category            = trim($_POST['category']            ?? '');
    $location            = trim($_POST['location']            ?? '');
    $service_description = trim($_POST['service_description'] ?? '');
    $preferred_date      = trim($_POST['preferred_date']      ?? '');
    $urgency             = trim($_POST['urgency']             ?? '');
    $budget              = trim($_POST['budget']              ?? '');
    $special_notes       = trim($_POST['special_notes']       ?? '');
    $member_kop_id       = $member['koperasi_id'] ?? '';
    $apply_credits       = !empty($_POST['apply_credits']) && $member_kop_id;
    $credits_to_use      = $apply_credits ? min((int)($_POST['credits_amount'] ?? 0), $credits) : 0;

    if (!$buyer_name)          $errors[] = 'Your name is required.';
    if (!$buyer_contact)       $errors[] = 'Contact number is required.';
    if (!$category)            $errors[] = 'Category is required.';
    if (!$location)            $errors[] = 'Location is required.';
    if (!$service_description) $errors[] = 'Service description is required.';

    if (empty($errors)) {
        $req_data = compact('buyer_name','buyer_contact','member_kop_id','category','location',
            'service_description','preferred_date','urgency','budget','special_notes');
        $req_data['credits_used'] = $credits_to_use;
        save_request($req_data);
        // Deduct credits if applied
        if ($credits_to_use > 0) {
            spend_credits($member_kop_id, $credits_to_use,
                'Credits applied to service request: ' . substr($service_description, 0, 60));
        }
        $msg = 'Your service request has been submitted!';
        if ($credits_to_use > 0) $msg .= " {$credits_to_use} credits applied (RM " . number_format($credits_to_use, 2) . " discount).";
        flash($msg);
        redirect('match_engine.php?category=' . urlencode($category) . '&location=' . urlencode($location));
    }
}

// ── Demand stats ──────────────────────────────────────────────
$all_requests = get_requests();
$all_sellers  = get_sellers(['status' => 'active']);

// Requests by category
$req_by_cat = [];
foreach ($all_requests as $r) {
    $req_by_cat[$r['category']] = ($req_by_cat[$r['category']] ?? 0) + 1;
}
arsort($req_by_cat);

// Sellers by category (supply)
$sel_by_cat = [];
foreach ($all_sellers as $s) {
    $sel_by_cat[$s['category']] = ($sel_by_cat[$s['category']] ?? 0) + 1;
}

// Supply-demand gap: high demand, low supply
$gap = [];
foreach ($req_by_cat as $cat => $demand) {
    $supply = $sel_by_cat[$cat] ?? 0;
    if ($demand > 0) {
        $gap[$cat] = ['demand' => $demand, 'supply' => $supply, 'gap' => max(0, $demand - $supply)];
    }
}
$gap_sorted = array_keys($gap);
usort($gap_sorted, fn($a, $b) => $gap[$b]['gap'] - $gap[$a]['gap']);

// Top requested locations
$req_by_loc = [];
foreach ($all_requests as $r) {
    $req_by_loc[$r['location']] = ($req_by_loc[$r['location']] ?? 0) + 1;
}
arsort($req_by_loc);

// Recent open requests (last 6, anonymised)
$recent_open = array_slice(
    array_filter($all_requests, fn($r) => $r['status'] === 'open'),
    0, 6
);

$max_req = $req_by_cat ? max(array_values($req_by_cat)) : 1;
$icons   = cat_icons();
$colors  = cat_colors();
$member  = current_member();

html_head('Request a Service');
html_body_open();
?>

<div class="page-title">🛒 Request a Service</div>
<p class="text-muted small mb-3">Tell us what you need and we'll match you with the right koperasi member.</p>

<div class="row g-4">

<!-- ── LEFT: Demand Insights ─────────────────────────────────── -->
<div class="col-lg-4 order-lg-2">

    <!-- Hot Skills -->
    <div class="card mb-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.08)">
        <div class="card-header text-white fw-bold"
            style="background:linear-gradient(135deg,#1a5276,#2e86c1);border-radius:10px 10px 0 0">
            🔥 Most In-Demand Services
        </div>
        <div class="card-body p-3">
            <?php if (!$req_by_cat): ?>
                <p class="text-muted small mb-0">No requests yet — be the first!</p>
            <?php else: ?>
            <?php $rank = 0; foreach ($req_by_cat as $cat => $count): $rank++;
                $pct   = round($count / $max_req * 100);
                $color = $colors[$cat] ?? '#607d8b';
                $icon  = $icons[$cat]  ?? '⭐';
                $sup   = $sel_by_cat[$cat] ?? 0;
                $hot   = $count >= 3 || ($count > 0 && $sup === 0);
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size:.8rem;font-weight:600;color:#222">
                        <?php if ($rank <= 3): ?>
                            <span style="background:<?= ['#f39c12','#95a5a6','#cd7f32'][$rank-1] ?>;
                                         color:#fff;border-radius:50%;width:18px;height:18px;
                                         display:inline-flex;align-items:center;justify-content:center;
                                         font-size:.65rem;font-weight:800;margin-right:4px"><?= $rank ?></span>
                        <?php endif; ?>
                        <?= $icon ?> <?= e($cat) ?>
                        <?php if ($hot): ?>
                            <span style="background:#fde8e8;color:#c0392b;font-size:.65rem;
                                         padding:1px 6px;border-radius:10px;margin-left:4px">🔥 Hot</span>
                        <?php endif; ?>
                    </span>
                    <span style="font-size:.75rem;color:#888"><?= $count ?> request<?= $count>1?'s':'' ?></span>
                </div>
                <div class="progress" style="height:7px;border-radius:4px">
                    <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>;border-radius:4px"></div>
                </div>
                <div style="font-size:.68rem;color:#aaa;margin-top:2px">
                    <?= $sup ?> provider<?= $sup!=1?'s':'' ?> available
                    <?php if ($sup === 0 && $count > 0): ?>
                        <span style="color:#e74c3c;font-weight:600"> — opportunity gap!</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Opportunity Gap -->
    <?php $gaps = array_filter($gap, fn($g) => $g['gap'] > 0);
    if ($gaps): ?>
    <div class="card mb-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.08)">
        <div class="card-header fw-bold small"
            style="background:#fff9f0;color:#e67e22;border-radius:10px 10px 0 0;border-bottom:2px solid #fde8c8">
            💡 Skill Opportunity Gaps
            <span class="float-end text-muted fw-normal" style="font-size:.7rem">High demand, low supply</span>
        </div>
        <div class="card-body p-3">
            <p class="text-muted" style="font-size:.77rem;margin-bottom:.75rem">
                These categories have more buyer demand than available providers — a chance for members to register and earn.
            </p>
            <?php foreach (array_slice($gap_sorted, 0, 4) as $cat):
                if ($gap[$cat]['gap'] <= 0) continue;
            ?>
            <div class="d-flex align-items-center justify-content-between mb-2 p-2"
                style="background:#fff9f0;border-radius:8px;border-left:3px solid #e67e22">
                <div>
                    <div style="font-size:.8rem;font-weight:600"><?= ($icons[$cat]??'⭐') ?> <?= e($cat) ?></div>
                    <div style="font-size:.7rem;color:#888"><?= $gap[$cat]['demand'] ?> requests · <?= $gap[$cat]['supply'] ?> providers</div>
                </div>
                <a href="register_service.php" style="font-size:.72rem;color:#e67e22;font-weight:700;white-space:nowrap">
                    Register →
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Locations -->
    <?php if ($req_by_loc): ?>
    <div class="card mb-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.08)">
        <div class="card-header fw-bold small"
            style="background:#f0fdf4;color:#27ae60;border-radius:10px 10px 0 0;border-bottom:2px solid #c8f0d8">
            📍 Busiest Areas
        </div>
        <div class="card-body p-3">
            <?php $li=0; foreach ($req_by_loc as $loc => $cnt): if($li++>=5) break; ?>
            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.8rem">
                <span>📍 <?= e($loc) ?></span>
                <span class="badge" style="background:#d5f5e3;color:#1e8449"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- AI Insight button -->
    <div class="card" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.08)">
        <div class="card-body p-3">
            <div class="fw-bold small mb-1">🤖 AI Market Insight</div>
            <p class="text-muted" style="font-size:.77rem;margin-bottom:.75rem">
                Get an AI-generated summary of what skills and services are most needed right now.
            </p>
            <button class="btn btn-outline-primary btn-sm w-100" id="aiInsightBtn">
                ✨ Generate Insight
            </button>
            <div id="aiInsightResult" class="mt-2" style="font-size:.8rem"></div>
        </div>
    </div>

</div><!-- end left col -->

<!-- ── RIGHT: Request Form ────────────────────────────────────── -->
<div class="col-lg-8 order-lg-1">

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Recent open requests board -->
    <?php if ($recent_open): ?>
    <div class="mb-3 p-3" style="background:#f8fafc;border-radius:12px;border:1px solid #e0e7ef">
        <div style="font-size:.8rem;font-weight:700;color:#1a5276;margin-bottom:.6rem">
            📋 Recent Open Requests — <?= count(array_filter($all_requests, fn($r)=>$r['status']==='open')) ?> waiting for providers
        </div>
        <div class="row g-2">
        <?php foreach ($recent_open as $r):
            $col = $colors[$r['category']] ?? '#607d8b';
            $ico = $icons[$r['category']]  ?? '⭐';
        ?>
            <div class="col-md-6">
                <div style="background:#fff;border-radius:8px;padding:.55rem .75rem;border-left:3px solid <?= $col ?>">
                    <div style="font-size:.75rem;font-weight:600;color:#222">
                        <?= $ico ?> <?= e($r['category']) ?>
                        <span style="float:right;font-size:.68rem;color:#aaa"><?= e($r['submitted_date']) ?></span>
                    </div>
                    <div style="font-size:.72rem;color:#555;margin-top:2px">
                        📍 <?= e($r['location']) ?>
                        <?php if ($r['budget']): ?>· 💰 <?= e($r['budget']) ?><?php endif; ?>
                    </div>
                    <div style="font-size:.72rem;color:#777;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        <?= e(substr($r['service_description'], 0, 60)) ?>…
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="mt-2" style="font-size:.72rem;color:#888">
            Are you a provider? <a href="register_service.php">List your service</a> and get matched to these requests.
        </div>
    </div>
    <?php endif; ?>

    <!-- The form -->
    <div class="card p-4">
        <h6 class="mb-3 fw-bold" style="color:#1a5276">📝 Submit Your Request</h6>
        <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Your Name *</label>
                    <input type="text" name="buyer_name" class="form-control"
                        value="<?= e($_POST['buyer_name'] ?? $member['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Contact (WhatsApp/Phone) *</label>
                    <input type="text" name="buyer_contact" class="form-control"
                        value="<?= e($_POST['buyer_contact'] ?? '') ?>"
                        placeholder="e.g. 012-3456789" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Service Category *</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select…</option>
                        <?php foreach (categories() as $c):
                            $req_count = $req_by_cat[$c] ?? 0;
                        ?>
                            <option value="<?= e($c) ?>" <?= ($_POST['category']??'')===$c?'selected':'' ?>>
                                <?= e($c) ?><?= $req_count ? " ({$req_count} requests)" : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Location *</label>
                    <select name="location" class="form-select" required>
                        <option value="">Select…</option>
                        <?php foreach (locations() as $l): ?>
                            <option value="<?= e($l) ?>" <?= ($_POST['location']??'')===$l?'selected':'' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Describe What You Need *</label>
                    <textarea name="service_description" class="form-control" rows="3"
                        placeholder="Describe the job scope, your requirements, etc." required><?= e($_POST['service_description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Preferred Date</label>
                    <input type="date" name="preferred_date" class="form-control"
                        value="<?= e($_POST['preferred_date'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Urgency</label>
                    <select name="urgency" class="form-select">
                        <option value="">Select…</option>
                        <option value="As soon as possible">As soon as possible</option>
                        <option value="Within 2–3 days">Within 2–3 days</option>
                        <option value="Flexible — within 1 week">Flexible — within 1 week</option>
                        <option value="Flexible — within 2 weeks">Flexible — within 2 weeks</option>
                        <option value="No rush — within a month">No rush — within a month</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Budget Range</label>
                    <input type="text" name="budget" class="form-control"
                        value="<?= e($_POST['budget'] ?? '') ?>"
                        placeholder="e.g. RM 100–200">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Special Notes</label>
                    <textarea name="special_notes" class="form-control" rows="2"
                        placeholder="Any special instructions or requirements"><?= e($_POST['special_notes'] ?? '') ?></textarea>
                </div>
                <?php if ($member && $credits > 0): ?>
                <div class="col-12">
                    <div class="card p-3" style="background:#fffbf0;border:1px solid #f6d365">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input class="form-check-input mt-0" type="checkbox" name="apply_credits"
                                id="applyCredits" value="1"
                                onchange="document.getElementById('creditAmtRow').style.display=this.checked?'flex':'none'">
                            <label class="form-check-label fw-semibold" for="applyCredits">
                                💰 Apply my credits as discount
                            </label>
                            <span class="ms-auto text-muted small">Balance: <strong><?= $credits ?> credits</strong> = RM <?= number_format($credits, 2) ?></span>
                        </div>
                        <div id="creditAmtRow" class="d-none align-items-center gap-2">
                            <label class="small fw-semibold" style="white-space:nowrap">Credits to use:</label>
                            <input type="number" name="credits_amount" class="form-control form-control-sm"
                                style="max-width:100px" min="1" max="<?= $credits ?>" value="<?= min($credits, 10) ?>">
                            <span class="text-muted small">max <?= $credits ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">🚀 Submit Request</button>
                    <a href="find_services.php" class="btn btn-outline-secondary ms-2">Browse Services Instead</a>
                </div>
            </div>
        </form>
    </div>
</div><!-- end right col -->
</div><!-- end row -->

<script>
document.getElementById('aiInsightBtn').addEventListener('click', async () => {
    const btn = document.getElementById('aiInsightBtn');
    const res = document.getElementById('aiInsightResult');
    btn.disabled = true; btn.textContent = '⏳ Thinking…';
    res.innerHTML = '';
    const stats = {
        req_by_cat : <?= json_encode($req_by_cat) ?>,
        sel_by_cat : <?= json_encode($sel_by_cat) ?>,
        req_by_loc : <?= json_encode(array_slice($req_by_loc, 0, 5, true)) ?>,
        total_req  : <?= count($all_requests) ?>
    };
    try {
        const r    = await csrfFetch('ajax/ai_demand.php', {
            method : 'POST',
            body   : JSON.stringify(stats)
        });
        const data = await r.json();
        const box  = document.createElement('div');
        box.style.cssText = 'background:#eaf4fb;border-left:3px solid #2e86c1;border-radius:0 8px 8px 0;padding:.6rem .8rem;color:#1a5276;line-height:1.6';
        box.innerHTML = escHtml(data.insight || '').replace(/\n/g,'<br>');
        res.replaceChildren(box);
    } catch(e) {
        res.textContent = 'Could not load insight. Try again.';
    }
    btn.disabled = false; btn.textContent = '✨ Generate Insight';
});
</script>

<?php html_footer(); ?>

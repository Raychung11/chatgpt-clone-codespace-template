<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_auth('/login.php');

$plans = Database::fetchAll('SELECT * FROM planning_profiles WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC', [auth_user_id()]);

// Save new plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_plan') {
    csrf_enforce();
    $profileName = clean($_POST['profile_name'] ?? 'My Plan');
    $targetAmt   = clean_float($_POST['target_amount'] ?? 0);
    $monthlyContr= clean_float($_POST['monthly_contribution'] ?? 0);
    $startDate   = clean($_POST['start_date'] ?? date('Y-m-d'));
    $targetDate  = clean($_POST['target_date'] ?? '');
    $goldGrams   = clean_float($_POST['gold_reference_grams'] ?? 0);
    $accepted    = !empty($_POST['partner_disclaimer_accepted']) ? 1 : 0;

    Database::insert(
        'INSERT INTO planning_profiles (user_id, profile_name, target_amount, monthly_contribution, start_date, target_date, gold_reference_grams, partner_disclaimer_accepted)
         VALUES (?,?,?,?,?,?,?,?)',
        [auth_user_id(), $profileName, $targetAmt, $monthlyContr, $startDate, $targetDate ?: null, $goldGrams ?: null, $accepted]
    );
    flash_set(FLASH_SUCCESS, 'Planning profile saved.');
    redirect('buyer/planner.php');
}

// Get current gold price (from cache or mock)
$goldCache = Database::fetchOne('SELECT rate_value, fetched_at FROM partner_rate_cache WHERE rate_type = ? ORDER BY fetched_at DESC LIMIT 1', ['gold_price_myr_per_gram']);
$goldPrice = $goldCache['rate_value'] ?? 358.00; // Approx MYR/gram placeholder

$page_title = 'Funeral Planning Tool';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="fw-700 text-navy mb-0">Planning Tool</h4>
    </div>

    <!-- Disclaimer -->
    <div class="alert alert-info small mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <?= PARTNER_DISCLAIMER ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <!-- New Plan Form -->
            <div class="pg-card p-4 mb-4">
                <h6 class="fw-600 mb-3">Create a New Planning Profile</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_plan">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Plan Name</label>
                            <input type="text" name="profile_name" class="form-control" value="My Plan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Funeral Amount (RM)</label>
                            <input type="number" name="target_amount" class="form-control" min="0" step="100" placeholder="e.g. 25000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Contribution (RM)</label>
                            <input type="number" name="monthly_contribution" class="form-control" min="0" step="50" placeholder="e.g. 300" id="monthly">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gold Reference (grams)</label>
                            <input type="number" name="gold_reference_grams" class="form-control" step="0.001" placeholder="e.g. 50" id="goldGrams">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Date (optional)</label>
                            <input type="date" name="target_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="partner_disclaimer_accepted" value="1" id="disclaimer" class="form-check-input" required>
                                <label for="disclaimer" class="form-check-label small text-muted">I understand this is an estimation tool only and not a licensed financial product.</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-gold">Save Plan</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Existing Plans -->
            <?php foreach ($plans as $plan):
                $progress = $plan['target_amount'] > 0 ? min(100, round(($plan['current_savings'] / $plan['target_amount']) * 100)) : 0;
                $shortfall = max(0, $plan['target_amount'] - $plan['current_savings']);
                $months    = $plan['monthly_contribution'] > 0 ? ceil($shortfall / $plan['monthly_contribution']) : null;
            ?>
            <div class="pg-card p-4 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="fw-600 mb-0"><?= h($plan['profile_name']) ?></h6>
                        <div class="text-muted small">Target: <?= format_currency($plan['target_amount']) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fw-600 text-navy"><?= $progress ?>%</div>
                        <div class="text-muted" style="font-size:.75rem">Complete</div>
                    </div>
                </div>
                <div class="verification-bar mb-3">
                    <div class="bar-fill <?= $progress >= 90 ? 'verified' : ($progress >= 50 ? 'partial' : 'pending') ?>" style="width:<?= $progress ?>%"></div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="small fw-600 text-navy"><?= format_currency($plan['current_savings']) ?></div>
                        <div class="text-muted" style="font-size:.72rem">Saved</div>
                    </div>
                    <div class="col-4">
                        <div class="small fw-600 text-danger"><?= format_currency($shortfall) ?></div>
                        <div class="text-muted" style="font-size:.72rem">Shortfall</div>
                    </div>
                    <div class="col-4">
                        <div class="small fw-600 text-navy"><?= $months ? $months . ' mo' : '—' ?></div>
                        <div class="text-muted" style="font-size:.72rem">Est. Months</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gold Reference Sidebar -->
        <div class="col-lg-5">
            <div class="pg-card p-4 mb-3" style="border-left:3px solid var(--pg-gold)">
                <h6 class="fw-600 mb-3"><i class="fas fa-coins me-2 text-gold"></i>Gold Price Reference</h6>
                <div class="text-center mb-3">
                    <div class="fs-3 fw-700 text-navy">RM <?= number_format($goldPrice, 2) ?></div>
                    <div class="small text-muted">per gram (indicative)</div>
                    <?php if ($goldCache): ?>
                        <div class="text-muted" style="font-size:.72rem">Updated: <?= time_ago($goldCache['fetched_at']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="row g-2 mb-3">
                    <?php foreach ([10, 50, 100, 200] as $g): ?>
                    <div class="col-6">
                        <div class="text-center p-2 border rounded-2">
                            <div class="small fw-600"><?= $g ?>g</div>
                            <div class="small text-muted">≈ RM <?= number_format($g * $goldPrice) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Calculator -->
                <div class="border-top pt-3">
                    <div class="small fw-600 mb-2">Quick Gold Estimator</div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">Grams</span>
                        <input type="number" id="calcGrams" class="form-control" placeholder="50" oninput="calcGoldValue()">
                    </div>
                    <div id="calcResult" class="text-center fw-600 text-navy small"></div>
                </div>
                <p class="text-muted mt-3 mb-0" style="font-size:.73rem"><?= PARTNER_DISCLAIMER ?></p>
            </div>

            <div class="pg-card p-4">
                <h6 class="fw-600 mb-2">Planning Resources</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2"><a href="<?= pg_url('diy_funeral_planner.php') ?>" class="text-gold"><i class="fas fa-clipboard-list me-2"></i>Build your funeral service plan</a></li>
                    <li class="mb-2"><a href="<?= pg_url('browse_listings.php') ?>" class="text-gold"><i class="fas fa-search me-2"></i>Browse burial plot prices</a></li>
                    <li class="mb-2"><a href="<?= pg_url('faq.php') ?>" class="text-gold"><i class="fas fa-question-circle me-2"></i>FAQ about pre-planning</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>

<?php
$extra_scripts = <<<JS
<script>
function calcGoldValue() {
    const grams = parseFloat(document.getElementById('calcGrams').value) || 0;
    const rate  = <?= (float)$goldPrice ?>;
    const val   = grams * rate;
    const el    = document.getElementById('calcResult');
    el.textContent = grams > 0 ? 'RM ' + val.toLocaleString('en-MY', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
}
</script>
JS;
include INC_PATH . '/footer.php'; ?>

<?php
/**
 * Admin – System Settings
 * /admin/pages/settings.php
 */

$pageTitle  = 'Settings';
$activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $settingGroups = [
            'brand_name'            => 'general',
            'currency'              => 'general',
            'tax_rate'              => 'general',
            'loyalty_points_per_myr'=> 'loyalty',
            'tier_silver_threshold' => 'loyalty',
            'tier_gold_threshold'   => 'loyalty',
            'tier_platinum_threshold'=> 'loyalty',
            'referral_reward_points'=> 'referral',
            'referral_referee_points'=> 'referral',
            'reservation_points'    => 'reservation',
        ];

        foreach ($settingGroups as $key => $group) {
            if (isset($_POST[$key])) {
                set_setting($key, sanitize_string($_POST[$key] ?? '', 200), $group);
            }
        }
        admin_log('update_settings', 'settings');
        flash('success', 'Settings saved.');
        header('Location: /admin/settings'); exit;
    }
}

$s = get_settings();
require __DIR__ . '/../layout/header.php';
?>

<div class="row g-3" style="max-width:700px;">
    <div class="col-12">
        <form method="POST">
            <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">

            <!-- General -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-gear me-2"></i>General</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Brand Name</label>
                            <input type="text" name="brand_name" class="form-control" value="<?= htmlspecialchars($s['brand_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Currency</label>
                            <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($s['currency'] ?? 'MYR') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Tax Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= $s['tax_rate'] ?? '0.06' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loyalty -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-star me-2 text-warning"></i>Loyalty Program</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Points per MYR 1 spent</label>
                            <input type="number" name="loyalty_points_per_myr" class="form-control" min="1" value="<?= $s['loyalty_points_per_myr'] ?? '1' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Silver Tier Threshold (lifetime pts)</label>
                            <input type="number" name="tier_silver_threshold" class="form-control" value="<?= $s['tier_silver_threshold'] ?? '500' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Gold Tier Threshold</label>
                            <input type="number" name="tier_gold_threshold" class="form-control" value="<?= $s['tier_gold_threshold'] ?? '2000' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Platinum Tier Threshold</label>
                            <input type="number" name="tier_platinum_threshold" class="form-control" value="<?= $s['tier_platinum_threshold'] ?? '5000' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referral -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-share me-2 text-info"></i>Referral Program</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Referrer Reward Points</label>
                            <input type="number" name="referral_reward_points" class="form-control" value="<?= $s['referral_reward_points'] ?? '100' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Referee Bonus Points</label>
                            <input type="number" name="referral_referee_points" class="form-control" value="<?= $s['referral_referee_points'] ?? '50' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reservation -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2 text-primary"></i>Reservations</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Points per Completed Reservation</label>
                            <input type="number" name="reservation_points" class="form-control" value="<?= $s['reservation_points'] ?? '10' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary px-4">Save Settings</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

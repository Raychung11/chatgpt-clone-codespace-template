<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/membership.php';

Auth::requireLogin();

$userId = Auth::id();

Membership::ensureTables();

$points      = Membership::getPoints($userId);
$wallet      = Membership::getWallet($userId);
$tier        = Membership::getTierByPoints($points);
$nextTier    = Membership::getNextTier($points);
$history     = Membership::getHistory($userId, 30);
$allTiers    = Membership::getAllTiers();

// Progress to next tier
$progressPct = 100;
$pointsToNext = 0;
if ($nextTier) {
    $tierStart    = (int)$tier['min_points'];
    $tierEnd      = (int)$nextTier['min_points'];
    $progressPct  = $tierEnd > $tierStart
        ? min(99, (int)(($points - $tierStart) / ($tierEnd - $tierStart) * 100))
        : 99;
    $pointsToNext = max(0, $tierEnd - $points);
}

$tierBenefits = json_decode($tier['benefits'] ?? '[]', true) ?: [];

$pageTitle = 'Membership';
require_once 'includes/header.php';
?>

<div class="container py-5">

    <div class="d-flex align-items-center gap-3 mb-5">
        <a href="/dashboard.php" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Membership</h3>
            <p class="text-muted small mb-0">Your tier, points, and rewards wallet</p>
        </div>
    </div>

    <!-- Hero: Current Tier -->
    <div class="rounded-4 p-4 mb-5 position-relative overflow-hidden"
         style="background:linear-gradient(135deg,<?= htmlspecialchars($tier['color']) ?>22,<?= htmlspecialchars($tier['color']) ?>08);border:1px solid <?= htmlspecialchars($tier['color']) ?>44">
        <div class="row align-items-center g-4">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:56px;height:56px;border-radius:16px;background:<?= htmlspecialchars($tier['color']) ?>22;display:flex;align-items:center;justify-content:center">
                        <i class="bi <?= htmlspecialchars($tier['icon']) ?> fs-3" style="color:<?= htmlspecialchars($tier['color']) ?>"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Current Tier</div>
                        <div class="fw-bold fs-4" style="color:<?= htmlspecialchars($tier['color']) ?>"><?= htmlspecialchars($tier['name']) ?></div>
                    </div>
                </div>
                <?php if ($nextTier): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small"><?= htmlspecialchars($tier['name']) ?></span>
                        <span class="small" style="color:<?= htmlspecialchars($nextTier['color'] ?? '#6366f1') ?>"><?= htmlspecialchars($nextTier['name']) ?></span>
                    </div>
                    <div class="progress" style="height:8px;background:rgba(255,255,255,0.08)">
                        <div class="progress-bar" style="width:<?= $progressPct ?>%;background:<?= htmlspecialchars($tier['color']) ?>"></div>
                    </div>
                    <div class="text-muted small mt-1"><?= number_format($pointsToNext) ?> more points to <?= htmlspecialchars($nextTier['name']) ?></div>
                </div>
                <?php else: ?>
                <div class="badge px-3 py-2" style="background:<?= htmlspecialchars($tier['color']) ?>33;color:<?= htmlspecialchars($tier['color']) ?>">
                    <i class="bi bi-trophy-fill me-1"></i>Maximum tier reached!
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="glass-card rounded-3 p-3 text-center">
                            <div class="fs-3 fw-bold text-white"><?= number_format($points) ?></div>
                            <div class="text-muted small">Total Points</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="glass-card rounded-3 p-3 text-center">
                            <div class="fs-3 fw-bold text-success"><?= APP_CURRENCY ?><?= number_format($wallet, 0) ?></div>
                            <div class="text-muted small">Wallet Credit</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Benefits + Tier Map -->
        <div class="col-lg-5">

            <!-- Current Benefits -->
            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3">
                    <i class="bi bi-star-fill me-2 text-warning"></i><?= htmlspecialchars($tier['name']) ?> Benefits
                </h6>
                <?php foreach ($tierBenefits as $b): ?>
                <div class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0" style="font-size:13px"></i>
                    <span class="text-muted small"><?= htmlspecialchars($b) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- How to Earn Points -->
            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-lightning-charge me-2 text-primary"></i>Earn Points</h6>
                <?php
                $ways = [
                    ['bi-magic',       'Use AI tools',              '+2 pts each (max 20/day)'],
                    ['bi-gift',        'Successful referral',       '+300 pts on conversion'],
                    ['bi-repeat',      'Active subscription',       '+500 pts on subscribe'],
                    ['bi-person-check','Complete your profile',     '+100 pts (one-time)'],
                    ['bi-calendar-check','Account anniversary',     '+200 pts per year'],
                ];
                foreach ($ways as [$icon, $label, $value]):
                ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-15">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= $icon ?> text-primary" style="font-size:13px"></i>
                        <span class="text-muted small"><?= $label ?></span>
                    </div>
                    <span class="text-success small fw-semibold"><?= $value ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tier Roadmap -->
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-map me-2"></i>All Tiers</h6>
                <?php foreach ($allTiers as $t):
                    $isActive  = $t['slug'] === $tier['slug'];
                    $isPast    = (int)$t['min_points'] < (int)$tier['min_points'];
                    $benefits  = json_decode($t['benefits'] ?? '[]', true) ?: [];
                ?>
                <div class="d-flex gap-3 mb-3 align-items-start">
                    <div style="width:32px;height:32px;border-radius:50%;background:<?= htmlspecialchars($t['color']) ?><?= $isActive ? 'cc' : ($isPast ? '66' : '22') ?>;
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid <?= htmlspecialchars($t['color']) ?><?= $isActive ? '' : '44' ?>">
                        <i class="bi <?= htmlspecialchars($t['icon']) ?>" style="color:<?= htmlspecialchars($t['color']) ?>;font-size:14px"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold small <?= $isActive ? 'text-white' : 'text-muted' ?>"><?= htmlspecialchars($t['name']) ?></span>
                            <span class="text-muted" style="font-size:11px"><?= number_format((int)$t['min_points']) ?> pts</span>
                        </div>
                        <?php if ($isActive): ?>
                        <div class="text-muted" style="font-size:11px"><?= implode(' · ', array_slice($benefits, 0, 2)) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Points History -->
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">
                    <i class="bi bi-clock-history me-2"></i>Points History
                </h6>

                <?php if (empty($history)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-lightning fs-1 text-muted d-block mb-3" style="opacity:.3"></i>
                    <p class="text-muted small">No points yet. Start using AI tools to earn your first points!</p>
                    <a href="/modules/" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-magic me-1"></i>Open AI Tools
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0 align-middle">
                        <thead>
                            <tr class="text-muted" style="font-size:11px;text-transform:uppercase">
                                <th class="fw-normal pb-2">Activity</th>
                                <th class="fw-normal pb-2">Date</th>
                                <th class="fw-normal pb-2 text-end">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $h):
                            $isEarn = in_array($h['type'], ['earn']);
                        ?>
                        <tr>
                            <td class="py-2">
                                <div class="text-white small"><?= htmlspecialchars($h['description'] ?: $h['source']) ?></div>
                                <div class="text-muted" style="font-size:10px"><?= ucfirst(str_replace('_', ' ', $h['source'])) ?></div>
                            </td>
                            <td class="py-2 text-muted small"><?= date('d M Y', strtotime($h['created_at'])) ?></td>
                            <td class="py-2 text-end fw-semibold small <?= $isEarn ? 'text-success' : 'text-danger' ?>">
                                <?= $isEarn ? '+' : '-' ?><?= number_format(abs($h['points'])) ?>
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
</div>

<?php require_once 'includes/footer.php'; ?>

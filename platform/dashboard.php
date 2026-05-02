<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'My Dashboard';

// Handle trial capsule dismiss / restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_capsule'])) {
    $dismissId = (int)$_POST['dismiss_capsule'];
    $_SESSION['dismissed_capsules']   = $_SESSION['dismissed_capsules'] ?? [];
    $_SESSION['dismissed_capsules'][] = $dismissId;
    $_SESSION['dismissed_capsules']   = array_unique($_SESSION['dismissed_capsules']);
    header('Location: /dashboard.php');
    exit;
}
if (isset($_GET['restore'])) {
    $_SESSION['dismissed_capsules'] = [];
    header('Location: /dashboard.php');
    exit;
}
$dismissedIds = $_SESSION['dismissed_capsules'] ?? [];

// Trial detection
$trialEnd     = strtotime($user['created_at']) + (TRIAL_DAYS * 86400);
$trialDaysLeft = max(0, (int)ceil(($trialEnd - time()) / 86400));
$isInTrial    = $trialDaysLeft > 0;

// Fetch user's subscriptions (join ai_modules for direct tool link)
$moduleJoin = DB::fetch("SHOW TABLES LIKE 'ai_modules'")
    ? 'LEFT JOIN ai_modules m ON m.product_id=p.id AND m.is_active=1'
    : '';
$moduleCol  = $moduleJoin ? ', m.slug as module_slug' : ', NULL as module_slug';

$subscriptions = DB::fetchAll(
    "SELECT s.*, p.name as product_name, p.slug as product_slug, p.tagline,
            c.name as cat_name, c.icon as cat_icon, c.color as cat_color $moduleCol
     FROM subscriptions s
     JOIN products p ON s.product_id = p.id
     LEFT JOIN categories c ON p.category_id = c.id
     $moduleJoin
     WHERE s.user_id = ? ORDER BY s.created_at DESC",
    [$user['id']]
);

// Fetch one-time purchases
$purchases = DB::fetchAll(
    "SELECT pu.*, p.name as product_name, p.slug as product_slug, p.tagline,
            c.name as cat_name, c.icon as cat_icon, c.color as cat_color $moduleCol
     FROM purchases pu
     JOIN products p ON pu.product_id = p.id
     LEFT JOIN categories c ON p.category_id = c.id
     $moduleJoin
     WHERE pu.user_id = ? AND pu.status='completed' ORDER BY pu.created_at DESC",
    [$user['id']]
);

$hasOwned = !empty($subscriptions) || !empty($purchases);

// Trial capsules — show featured products during trial if no paid capsules yet
// Join ai_modules so we can link directly to the tool instead of the product/pricing page
$trialCapsules = [];
if ($isInTrial && !$hasOwned) {
    $moduleTableExists = DB::fetch("SHOW TABLES LIKE 'ai_modules'");
    if ($moduleTableExists) {
        $trialCapsules = DB::fetchAll(
            'SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color,
                    m.slug as module_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id=c.id
             LEFT JOIN ai_modules m ON m.product_id=p.id AND m.is_active=1
             WHERE p.is_active=1 ORDER BY p.is_featured DESC, p.sort_order LIMIT 6'
        );
    } else {
        $trialCapsules = DB::fetchAll(
            'SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color,
                    NULL as module_slug
             FROM products p LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.is_active=1 ORDER BY p.is_featured DESC, p.sort_order LIMIT 6'
        );
    }
}

// Recommended products (not owned) — only when user has paid capsules
$recommended = [];
if ($hasOwned) {
    $ownedIds    = array_merge(array_column($subscriptions, 'product_id'), array_column($purchases, 'product_id'));
    $placeholders = implode(',', array_fill(0, count($ownedIds), '?'));
    $recommended = DB::fetchAll(
        "SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color
         FROM products p LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.id NOT IN ($placeholders) AND p.is_active=1 ORDER BY p.is_featured DESC, p.sort_order LIMIT 3",
        $ownedIds
    );
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-1">My Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($user['name']) ?>!</p>
        </div>
        <a href="/marketplace.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Add Capsule
        </a>
    </div>

    <!-- Trial Banner -->
    <?php if ($isInTrial): ?>
    <div class="rounded-4 p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3"
         style="background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(139,92,246,0.15));border:1px solid rgba(99,102,241,0.4)">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center">
                <i class="bi bi-stars text-primary fs-4"></i>
            </div>
            <div>
                <div class="text-white fw-semibold">Free Trial Active</div>
                <div class="text-muted small">
                    <?= $trialDaysLeft ?> day<?= $trialDaysLeft !== 1 ? 's' : '' ?> remaining &bull; Full access to all Capsules
                </div>
            </div>
        </div>
        <a href="/pricing.php" class="btn btn-primary btn-sm px-4">
            Upgrade to Keep Access <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <?php elseif (!$hasOwned): ?>
    <div class="rounded-4 p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3"
         style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3)">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
            <div>
                <div class="text-white fw-semibold">Your trial has ended</div>
                <div class="text-muted small">Subscribe to a plan to continue using your Capsules.</div>
            </div>
        </div>
        <a href="/pricing.php" class="btn btn-danger btn-sm px-4">Choose a Plan</a>
    </div>
    <?php endif; ?>

    <!-- AI Tools Quick Access -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="glass-card rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white fw-semibold mb-0"><i class="bi bi-magic me-2 text-primary"></i>AI Tools</h5>
                    <a href="/modules/" class="text-primary small text-decoration-none">View all <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-3">
                    <?php
                    $tools = [
                        ['Email Writer',    '/modules/email-writer.php',       'bi-envelope-paper',    '#6366f1', 'rgba(99,102,241,0.15)'],
                        ['Social Posts',    '/modules/social-post.php',        'bi-share',             '#10b981', 'rgba(16,185,129,0.15)'],
                        ['Sales Proposal',  '/modules/sales-proposal.php',     'bi-file-earmark-text', '#f97316', 'rgba(249,115,22,0.15)'],
                        ['Ad Copy',         '/modules/ad-copy.php',            'bi-megaphone',         '#ec4899', 'rgba(236,72,153,0.15)'],
                        ['Invoice',         '/modules/invoice-generator.php',  'bi-receipt',           '#f59e0b', 'rgba(245,158,11,0.15)'],
                        ['Customer Reply',  '/modules/customer-reply.php',     'bi-chat-dots',         '#14b8a6', 'rgba(20,184,166,0.15)'],
                        ['Job Description', '/modules/job-description.php',    'bi-person-badge',      '#06b6d4', 'rgba(6,182,212,0.15)'],
                        ['Meeting Minutes', '/modules/meeting-minutes.php',    'bi-journal-text',      '#8b5cf6', 'rgba(139,92,246,0.15)'],
                    ];
                    foreach ($tools as [$name, $url, $icon, $color, $bg]):
                    ?>
                    <div class="col-6 col-md-3 col-lg-3">
                        <a href="<?= $url ?>" class="text-decoration-none">
                            <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);transition:all .2s" onmouseover="this.style.borderColor='<?= $color ?>50'" onmouseout="this.style.borderColor='rgba(255,255,255,0.07)'">
                                <div style="width:38px;height:38px;border-radius:10px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="bi <?= $icon ?>"></i>
                                </div>
                                <div class="text-white small fw-semibold"><?= $name ?></div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-15 text-primary"><i class="bi bi-grid fs-5"></i></div>
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= count($subscriptions) + count($purchases) ?></div>
                        <div class="text-muted small">Active Capsules</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-15 text-success"><i class="bi bi-repeat fs-5"></i></div>
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= count($subscriptions) ?></div>
                        <div class="text-muted small">Subscriptions</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-15 text-warning"><i class="bi bi-bag-check fs-5"></i></div>
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= count($purchases) ?></div>
                        <div class="text-muted small">Purchases</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-15 text-info"><i class="bi bi-calendar-check fs-5"></i></div>
                    <div>
                        <div class="fs-4 fw-bold text-white">
                            <?php
                            $activeSubs = array_filter($subscriptions, fn($s) => $s['status'] === 'active');
                            echo count($activeSubs);
                            ?>
                        </div>
                        <div class="text-muted small">Active Subs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- My Capsules -->
        <div class="col-lg-8">
            <div class="glass-card rounded-4 p-4">

                <?php if (!$hasOwned && $isInTrial): ?>
                <!-- Trial capsules -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white fw-semibold mb-0"><i class="bi bi-cpu me-2 text-primary"></i>Your Trial Capsules</h5>
                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 px-2 py-1" style="font-size:11px">
                        <i class="bi bi-stars me-1"></i><?= $trialDaysLeft ?>d left
                    </span>
                </div>
                <?php
                $visibleTrialCapsules = array_filter($trialCapsules, fn($tc) => !in_array($tc['id'], $dismissedIds));
                foreach ($visibleTrialCapsules as $tc):
                    $openUrl = $tc['module_slug'] ? '/modules/'.$tc['module_slug'].'.php' : '/modules/';
                ?>
                <div class="rounded-3 mb-2 p-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                    <!-- Row 1: icon + name + dismiss -->
                    <div class="d-flex align-items-start gap-3 mb-2">
                        <div class="cat-icon-sm flex-shrink-0" style="background:<?= $tc['cat_color'] ?? '#6366f1' ?>22;color:<?= $tc['cat_color'] ?? '#6366f1' ?>">
                            <i class="bi <?= $tc['cat_icon'] ?? 'bi-cpu' ?>"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="text-white fw-semibold" style="font-size:14px;line-height:1.3"><?= htmlspecialchars($tc['name']) ?></div>
                            <div class="text-muted" style="font-size:12px;margin-top:2px"><?= htmlspecialchars($tc['tagline'] ?? '') ?></div>
                        </div>
                        <form method="POST" class="flex-shrink-0">
                            <input type="hidden" name="dismiss_capsule" value="<?= $tc['id'] ?>">
                            <button type="submit" class="btn btn-sm p-0" style="width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,0.07);color:#6b7280;border:none;font-size:13px;line-height:1" title="Hide this capsule">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
                    </div>
                    <!-- Row 2: badge + date + open button -->
                    <div class="d-flex align-items-center justify-content-between gap-2" style="padding-left:47px">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-info text-dark" style="font-size:10px">Trial</span>
                            <span class="text-muted" style="font-size:11px"><?= $trialDaysLeft ?> days left</span>
                        </div>
                        <a href="<?= htmlspecialchars($openUrl) ?>" class="btn btn-primary btn-sm px-3" style="font-size:12px;white-space:nowrap">
                            <i class="bi bi-play-fill me-1"></i>Try Now
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($visibleTrialCapsules)): ?>
                <div class="text-center py-3">
                    <p class="text-muted small mb-2">You've hidden all trial capsules.</p>
                    <a href="?restore=1" class="btn btn-outline-secondary btn-sm">Restore All</a>
                </div>
                <?php endif; ?>
                <div class="mt-3 pt-3 border-top border-secondary border-opacity-25 text-center">
                    <a href="/modules/" class="text-muted small text-decoration-none">
                        <i class="bi bi-magic me-1"></i>View all AI Tools
                    </a>
                </div>

                <?php elseif (!$hasOwned): ?>
                <!-- Trial expired, no subscriptions -->
                <h5 class="text-white fw-semibold mb-4"><i class="bi bi-cpu me-2 text-primary"></i>My Capsules</h5>
                <div class="text-center py-5">
                    <i class="bi bi-lock fs-1 text-muted mb-3 d-block"></i>
                    <h6 class="text-white">Trial ended</h6>
                    <p class="text-muted small">Subscribe to a plan to access your Capsules again.</p>
                    <a href="/pricing.php" class="btn btn-primary btn-sm px-4">View Plans</a>
                </div>

                <?php else: ?>
                <!-- Paid subscriptions / purchases -->
                <h5 class="text-white fw-semibold mb-4"><i class="bi bi-cpu me-2 text-primary"></i>My Capsules</h5>

                <?php foreach ($subscriptions as $sub):
                    $subUrl = $sub['module_slug'] ? '/modules/'.$sub['module_slug'].'.php' : '/product.php?slug='.$sub['product_slug'];
                    $badgeClass = match($sub['status']) {
                        'active'   => 'bg-success',
                        'trialing' => 'bg-info text-dark',
                        'past_due' => 'bg-warning text-dark',
                        'canceled' => 'bg-danger',
                        default    => 'bg-secondary',
                    };
                ?>
                <div class="rounded-3 mb-2 p-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                    <div class="d-flex align-items-start gap-3 mb-2">
                        <div class="cat-icon-sm flex-shrink-0" style="background:<?= $sub['cat_color'] ?? '#6366f1' ?>22;color:<?= $sub['cat_color'] ?? '#6366f1' ?>">
                            <i class="bi <?= $sub['cat_icon'] ?? 'bi-cpu' ?>"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="text-white fw-semibold" style="font-size:14px;line-height:1.3"><?= htmlspecialchars($sub['product_name']) ?></div>
                            <div class="text-muted" style="font-size:12px;margin-top:2px"><?= htmlspecialchars($sub['tagline'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2" style="padding-left:47px">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge <?= $badgeClass ?>" style="font-size:10px"><?= ucfirst($sub['status']) ?></span>
                            <span class="text-muted" style="font-size:11px">
                                <?= ucfirst($sub['plan']) ?> &bull; Renews <?= $sub['current_period_end'] ? date('d M', strtotime($sub['current_period_end'])) : 'N/A' ?>
                            </span>
                        </div>
                        <a href="<?= htmlspecialchars($subUrl) ?>" class="btn btn-primary btn-sm px-3" style="font-size:12px;white-space:nowrap">
                            <i class="bi bi-play-fill me-1"></i>Open
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php foreach ($purchases as $pur):
                    $purUrl = $pur['module_slug'] ? '/modules/'.$pur['module_slug'].'.php' : '/product.php?slug='.$pur['product_slug'];
                ?>
                <div class="rounded-3 mb-2 p-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                    <div class="d-flex align-items-start gap-3 mb-2">
                        <div class="cat-icon-sm flex-shrink-0" style="background:<?= $pur['cat_color'] ?? '#6366f1' ?>22;color:<?= $pur['cat_color'] ?? '#6366f1' ?>">
                            <i class="bi <?= $pur['cat_icon'] ?? 'bi-cpu' ?>"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="text-white fw-semibold" style="font-size:14px;line-height:1.3"><?= htmlspecialchars($pur['product_name']) ?></div>
                            <div class="text-muted" style="font-size:12px;margin-top:2px"><?= htmlspecialchars($pur['tagline'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2" style="padding-left:47px">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary" style="font-size:10px">Purchased</span>
                            <span class="text-muted" style="font-size:11px">Lifetime access</span>
                        </div>
                        <a href="<?= htmlspecialchars($purUrl) ?>" class="btn btn-primary btn-sm px-3" style="font-size:12px;white-space:nowrap">
                            <i class="bi bi-play-fill me-1"></i>Open
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">
            <!-- Account Info -->
            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-person-circle me-2"></i>Account</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="avatar-initials lg"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
                    <div>
                        <div class="text-white fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                        <?php if ($user['company']): ?>
                        <div class="text-muted small"><?= htmlspecialchars($user['company']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="/account.php" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-gear me-1"></i>Account Settings
                </a>
            </div>

            <!-- Recommended -->
            <?php if ($recommended): ?>
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-stars me-2 text-warning"></i>Recommended for You</h6>
                <?php foreach ($recommended as $rec): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="cat-icon-sm flex-shrink-0" style="background:<?= $rec['cat_color'] ?? '#6366f1' ?>22;color:<?= $rec['cat_color'] ?? '#6366f1' ?>">
                        <i class="bi <?= $rec['cat_icon'] ?? 'bi-cpu' ?>"></i>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white small fw-semibold"><?= htmlspecialchars($rec['name']) ?></div>
                        <div class="text-muted" style="font-size:12px"><?= APP_CURRENCY ?><?= number_format($rec['price_monthly'], 0) ?>/mo</div>
                    </div>
                    <a href="/product.php?slug=<?= $rec['slug'] ?>" class="btn btn-primary btn-sm flex-shrink-0">View</a>
                </div>
                <?php endforeach; ?>
                <a href="/marketplace.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Browse All</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

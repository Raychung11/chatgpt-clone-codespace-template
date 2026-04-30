<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'My Dashboard';

// Fetch user's subscriptions
$subscriptions = DB::fetchAll(
    'SELECT s.*, p.name as product_name, p.slug as product_slug, p.tagline,
            c.name as cat_name, c.icon as cat_icon, c.color as cat_color
     FROM subscriptions s
     JOIN products p ON s.product_id = p.id
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE s.user_id = ? ORDER BY s.created_at DESC',
    [$user['id']]
);

// Fetch one-time purchases
$purchases = DB::fetchAll(
    'SELECT pu.*, p.name as product_name, p.slug as product_slug, p.tagline,
            c.name as cat_name, c.icon as cat_icon, c.color as cat_color
     FROM purchases pu
     JOIN products p ON pu.product_id = p.id
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE pu.user_id = ? AND pu.status="completed" ORDER BY pu.created_at DESC',
    [$user['id']]
);

// Recommended products (not owned)
$ownedIds = array_merge(
    array_column($subscriptions, 'product_id'),
    array_column($purchases, 'product_id')
);
$placeholders = $ownedIds ? implode(',', array_fill(0, count($ownedIds), '?')) : '0';
$recommended = DB::fetchAll(
    "SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color
     FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.id NOT IN ($placeholders) AND p.is_active=1 ORDER BY p.is_featured DESC, p.sort_order LIMIT 3",
    $ownedIds
);

require_once 'includes/header.php';
?>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="text-white fw-bold mb-1">My Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($user['name']) ?>!</p>
        </div>
        <a href="/marketplace.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Add Capsule
        </a>
    </div>

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
                        ['Email Writer',          '/modules/email-writer.php',     'bi-envelope-paper',   '#6366f1', 'rgba(99,102,241,0.15)'],
                        ['Social Post Generator', '/modules/social-post.php',      'bi-share',            '#10b981', 'rgba(16,185,129,0.15)'],
                        ['Invoice Generator',     '/modules/invoice-generator.php','bi-receipt',          '#f59e0b', 'rgba(245,158,11,0.15)'],
                        ['Leave Request',         '/modules/leave-request.php',    'bi-calendar-check',   '#ef4444', 'rgba(239,68,68,0.15)'],
                    ];
                    foreach ($tools as [$name, $url, $icon, $color, $bg]):
                    ?>
                    <div class="col-6 col-md-3">
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
        <!-- My Agents -->
        <div class="col-lg-8">
            <div class="glass-card rounded-4 p-4">
                <h5 class="text-white fw-semibold mb-4"><i class="bi bi-cpu me-2 text-primary"></i>My Capsules</h5>

                <?php if (empty($subscriptions) && empty($purchases)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box-seam fs-1 text-muted mb-3 d-block"></i>
                    <h6 class="text-white">No capsules yet</h6>
                    <p class="text-muted small">Browse the Capsule Store to deploy your first Capsule.</p>
                    <a href="/marketplace.php" class="btn btn-primary btn-sm">Browse Capsule Store</a>
                </div>
                <?php else: ?>

                <!-- Subscriptions -->
                <?php foreach ($subscriptions as $sub): ?>
                <div class="agent-row d-flex align-items-center gap-3 p-3 rounded-3 mb-2">
                    <div class="cat-icon-sm flex-shrink-0" style="background:<?= $sub['cat_color'] ?? '#6366f1' ?>22;color:<?= $sub['cat_color'] ?? '#6366f1' ?>">
                        <i class="bi <?= $sub['cat_icon'] ?? 'bi-cpu' ?>"></i>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white fw-semibold"><?= htmlspecialchars($sub['product_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($sub['tagline'] ?? '') ?></div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div>
                            <?php
                            $badgeClass = match($sub['status']) {
                                'active'   => 'bg-success',
                                'trialing' => 'bg-info',
                                'past_due' => 'bg-warning text-dark',
                                'canceled' => 'bg-danger',
                                default    => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($sub['status']) ?></span>
                        </div>
                        <div class="text-muted small mt-1">
                            <?= ucfirst($sub['plan']) ?> &bull;
                            Renews <?= $sub['current_period_end'] ? date('d M', strtotime($sub['current_period_end'])) : 'N/A' ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="/product.php?slug=<?= $sub['product_slug'] ?>" class="btn btn-outline-primary btn-sm">Open</a>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- One-time purchases -->
                <?php foreach ($purchases as $pur): ?>
                <div class="agent-row d-flex align-items-center gap-3 p-3 rounded-3 mb-2">
                    <div class="cat-icon-sm flex-shrink-0" style="background:<?= $pur['cat_color'] ?? '#6366f1' ?>22;color:<?= $pur['cat_color'] ?? '#6366f1' ?>">
                        <i class="bi <?= $pur['cat_icon'] ?? 'bi-cpu' ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-white fw-semibold"><?= htmlspecialchars($pur['product_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($pur['tagline'] ?? '') ?></div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary">Purchased</span>
                        <div class="text-muted small mt-1">Lifetime access</div>
                    </div>
                    <div>
                        <a href="/product.php?slug=<?= $pur['product_slug'] ?>" class="btn btn-outline-primary btn-sm">Open</a>
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
                        <div class="text-muted" style="font-size:12px"><?= CURRENCY_SYMBOL ?><?= number_format($rec['price_monthly'], 0) ?>/mo</div>
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

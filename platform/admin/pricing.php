<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

$saved  = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['product_id'] ?? [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if (!$id) continue;
        $monthly  = (float)($_POST['price_monthly'][$id]  ?? 0);
        $yearly   = (float)($_POST['price_yearly'][$id]   ?? 0);
        $badge    = trim($_POST['badge'][$id]              ?? '');
        $featured = isset($_POST['featured'][$id]) ? 1 : 0;
        $active   = isset($_POST['active'][$id])   ? 1 : 0;

        DB::update('products', [
            'price_monthly' => $monthly,
            'price_yearly'  => $yearly,
            'badge'         => $badge ?: null,
            'is_featured'   => $featured,
            'is_active'     => $active,
        ], 'id = ?', [$id]);
    }
    $saved = true;
}

// Load all products grouped by category
$products = DB::fetchAll(
    "SELECT p.*, c.name as cat_name, c.color as cat_color, c.icon as cat_icon
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     ORDER BY c.sort_order, p.sort_order"
);

// Group by category
$grouped = [];
foreach ($products as $p) {
    $grouped[$p['cat_name'] ?? 'Uncategorised'][] = $p;
}

$pageTitle = 'Capsule Pricing';
require_once '../includes/admin-header.php';
?>

<div class="admin-content px-4 py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Capsule Pricing Manager</h4>
            <p class="text-muted small mb-0">Set monthly &amp; yearly prices, badges, and visibility for all <?= count($products) ?> Capsules</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="form-check form-switch mb-0 me-2">
                <input class="form-check-input" type="checkbox" id="autoYearly" checked>
                <label class="form-check-label text-muted small" for="autoYearly">Auto yearly (10× monthly)</label>
            </div>
            <button form="pricingForm" type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Save All Prices
            </button>
        </div>
    </div>

    <?php if ($saved): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <i class="bi bi-check-circle me-2"></i>All prices saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- KPI row -->
    <?php
    $prices = array_column($products, 'price_monthly');
    $avgPrice = $prices ? array_sum($prices) / count($prices) : 0;
    $minPrice = $prices ? min($prices) : 0;
    $maxPrice = $prices ? max($prices) : 0;
    $featured = count(array_filter($products, fn($p) => $p['is_featured']));
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Total Capsules', count($products),        'bi-grid',       'text-primary'],
            ['Featured',       $featured,               'bi-star-fill',  'text-warning'],
            ['Lowest Price',   'RM '.number_format($minPrice,0), 'bi-arrow-down-circle', 'text-success'],
            ['Highest Price',  'RM '.number_format($maxPrice,0), 'bi-arrow-up-circle',   'text-danger'],
        ] as [$label, $val, $icon, $cls]): ?>
        <div class="col-6 col-lg-3">
            <div class="glass-card rounded-3 p-3 d-flex align-items-center gap-3">
                <i class="bi <?= $icon ?> fs-4 <?= $cls ?>"></i>
                <div>
                    <div class="fw-bold text-white"><?= $val ?></div>
                    <div class="text-muted" style="font-size:12px"><?= $label ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <form id="pricingForm" method="POST">
    <?php foreach ($grouped as $catName => $catProducts): ?>
    <?php $catColor = $catProducts[0]['cat_color'] ?? '#6366f1'; $catIcon = $catProducts[0]['cat_icon'] ?? 'bi-cpu'; ?>

    <div class="glass-card rounded-4 mb-4 overflow-hidden">
        <!-- Category header -->
        <div class="px-4 py-3 d-flex align-items-center gap-2" style="background:<?= $catColor ?>14;border-bottom:1px solid <?= $catColor ?>22">
            <i class="bi <?= $catIcon ?>" style="color:<?= $catColor ?>"></i>
            <span class="fw-semibold text-white"><?= htmlspecialchars($catName) ?></span>
            <span class="badge ms-1" style="background:<?= $catColor ?>22;color:<?= $catColor ?>"><?= count($catProducts) ?> capsules</span>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle" style="font-size:13px">
                <thead style="background:rgba(255,255,255,0.03)">
                    <tr>
                        <th class="ps-4" style="width:30%">Capsule</th>
                        <th style="width:16%">Monthly (RM)</th>
                        <th style="width:16%">Yearly (RM)</th>
                        <th style="width:12%">Yearly Saving</th>
                        <th style="width:14%">Badge</th>
                        <th class="text-center" style="width:6%">Featured</th>
                        <th class="text-center" style="width:6%">Active</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($catProducts as $p): ?>
                <tr>
                    <input type="hidden" name="product_id[]" value="<?= $p['id'] ?>">
                    <td class="ps-4">
                        <div class="fw-semibold text-white"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($p['tagline']) ?></div>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-muted">RM</span>
                            <input type="number" step="1" min="0"
                                   name="price_monthly[<?= $p['id'] ?>]"
                                   value="<?= number_format($p['price_monthly'], 0, '.', '') ?>"
                                   class="form-control bg-dark border-secondary text-white monthly-input"
                                   data-id="<?= $p['id'] ?>"
                                   style="max-width:110px">
                        </div>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-muted">RM</span>
                            <input type="number" step="1" min="0"
                                   name="price_yearly[<?= $p['id'] ?>]"
                                   value="<?= number_format($p['price_yearly'], 0, '.', '') ?>"
                                   class="form-control bg-dark border-secondary text-white yearly-input"
                                   data-id="<?= $p['id'] ?>"
                                   style="max-width:110px">
                        </div>
                    </td>
                    <td>
                        <span class="saving-label text-success small fw-semibold"
                              data-id="<?= $p['id'] ?>"
                              data-monthly="<?= $p['price_monthly'] ?>"
                              data-yearly="<?= $p['price_yearly'] ?>">
                            <?php
                            $saving = ($p['price_monthly'] * 12) - $p['price_yearly'];
                            echo $saving > 0 ? 'Save RM '.number_format($saving, 0) : '—';
                            ?>
                        </span>
                    </td>
                    <td>
                        <select name="badge[<?= $p['id'] ?>]" class="form-select form-select-sm bg-dark border-secondary text-white" style="max-width:120px">
                            <option value="" <?= !$p['badge'] ? 'selected' : '' ?>>None</option>
                            <?php foreach (['Popular','Hot','New','Premium','Sale'] as $b): ?>
                            <option value="<?= $b ?>" <?= $p['badge'] === $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center mb-0">
                            <input class="form-check-input" type="checkbox"
                                   name="featured[<?= $p['id'] ?>]"
                                   <?= $p['is_featured'] ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center mb-0">
                            <input class="form-check-input" type="checkbox"
                                   name="active[<?= $p['id'] ?>]"
                                   <?= $p['is_active'] ? 'checked' : '' ?>>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Bottom save -->
    <div class="d-flex justify-content-end gap-2 mt-2 mb-5">
        <a href="/marketplace.php" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-eye me-1"></i>Preview Marketplace
        </a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i>Save All Prices
        </button>
    </div>
    </form>
</div>

<script>
const autoYearlyToggle = document.getElementById('autoYearly');

// Auto-calculate yearly when monthly changes
document.querySelectorAll('.monthly-input').forEach(input => {
    input.addEventListener('input', () => {
        if (!autoYearlyToggle.checked) return;
        const id      = input.dataset.id;
        const monthly = parseFloat(input.value) || 0;
        const yearly  = Math.round(monthly * 10);
        const yInput  = document.querySelector(`.yearly-input[data-id="${id}"]`);
        if (yInput) yInput.value = yearly;
        updateSaving(id, monthly, yearly);
    });
});

// Recalc saving when yearly changes manually
document.querySelectorAll('.yearly-input').forEach(input => {
    input.addEventListener('input', () => {
        const id      = input.dataset.id;
        const mInput  = document.querySelector(`.monthly-input[data-id="${id}"]`);
        const monthly = parseFloat(mInput?.value) || 0;
        const yearly  = parseFloat(input.value) || 0;
        updateSaving(id, monthly, yearly);
    });
});

// When auto-yearly toggled on, recalculate all
autoYearlyToggle.addEventListener('change', () => {
    if (!autoYearlyToggle.checked) return;
    document.querySelectorAll('.monthly-input').forEach(input => {
        const id      = input.dataset.id;
        const monthly = parseFloat(input.value) || 0;
        const yearly  = Math.round(monthly * 10);
        const yInput  = document.querySelector(`.yearly-input[data-id="${id}"]`);
        if (yInput) yInput.value = yearly;
        updateSaving(id, monthly, yearly);
    });
});

function updateSaving(id, monthly, yearly) {
    const label  = document.querySelector(`.saving-label[data-id="${id}"]`);
    if (!label) return;
    const saving = Math.round((monthly * 12) - yearly);
    label.textContent = saving > 0 ? 'Save RM ' + saving.toLocaleString() : '—';
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>

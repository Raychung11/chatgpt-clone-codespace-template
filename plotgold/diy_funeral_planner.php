<?php
require_once __DIR__ . '/inc/bootstrap.php';

$emergency = isset($_GET['mode']) && $_GET['mode'] === 'urgent';

// Load all service categories and their services
$categories = Database::fetchAll(
    'SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order'
);

$servicesByCategory = [];
foreach ($categories as $cat) {
    $servicesByCategory[$cat['id']] = Database::fetchAll(
        'SELECT fs.*, sc.name AS category_name, sc.label_en AS category_label
         FROM funeral_services fs
         JOIN service_categories sc ON sc.id = fs.category_id
         WHERE fs.category_id = ? AND fs.is_active = 1
         ORDER BY fs.sort_order, fs.base_price ASC',
        [$cat['id']]
    );
}

$page_title       = $emergency ? 'Urgent Funeral Help — Get a Quote Now' : 'DIY Funeral Planner';
$meta_description = 'Build your personalised funeral plan with itemised services, compare providers, and request quotes — at your own pace, with no pressure.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<?php if ($emergency): ?>
<div class="emergency-banner">
    <div class="container">
        <h4><i class="fas fa-hands-helping me-2"></i>Urgent Support Available — Our Team is Here For You</h4>
        <p class="mb-0 small opacity-75">If you need immediate assistance, please <a href="<?= whatsapp_link('I need urgent funeral assistance.') ?>" class="text-white fw-600" target="_blank" rel="noopener">WhatsApp us now</a>.</p>
    </div>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item active">DIY Funeral Planner</li>
        </ol>
    </div>
</nav>

<!-- Page Header -->
<div class="bg-white border-bottom py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h3 fw-700 text-navy mb-1">
                    <?= $emergency ? '<i class="fas fa-hands-helping text-danger me-2"></i>Urgent Funeral Planning' : 'DIY Funeral Planner' ?>
                </h1>
                <p class="text-muted mb-0">
                    Select the services you need, see itemised pricing, and request a quote from verified providers.
                    <?php if (!$emergency): ?>No sales calls. No pressure. Build at your own pace.<?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= whatsapp_link('Hi PlotGold, I need help planning a funeral.') ?>" target="_blank" rel="noopener" class="btn btn-whatsapp">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp for Help
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">

        <!-- ── SERVICES CATALOG ──────────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- Category tabs -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <button class="btn btn-sm btn-gold active" onclick="filterCategory('all', this)">All Services</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="filterCategory('<?= (int)$cat['id'] ?>', this)"
                            data-cat="<?= (int)$cat['id'] ?>">
                        <?php if ($cat['icon']): ?><i class="fas <?= h($cat['icon']) ?> me-1"></i><?php endif; ?>
                        <?= h($cat['label_en']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Services Grid -->
            <?php foreach ($categories as $cat): ?>
            <div class="service-section mb-4" data-section="<?= (int)$cat['id'] ?>">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?php if ($cat['icon']): ?>
                        <div class="trust-icon" style="width:36px;height:36px;font-size:1rem"><i class="fas <?= h($cat['icon']) ?>"></i></div>
                    <?php endif; ?>
                    <h5 class="fw-600 mb-0"><?= h($cat['label_en']) ?></h5>
                    <?php if ($cat['label_zh']): ?><span class="text-muted small"><?= h($cat['label_zh']) ?></span><?php endif; ?>
                </div>

                <div class="row g-3">
                    <?php foreach ($servicesByCategory[$cat['id']] ?? [] as $svc): ?>
                    <div class="col-sm-6">
                        <div class="pg-card p-3 service-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-600 mb-0 small"><?= h($svc['name']) ?></h6>
                                    <?php if ($svc['is_recommended']): ?>
                                        <span class="badge bg-warning text-dark" style="font-size:.65rem">Recommended</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <?php if ($svc['base_price']): ?>
                                        <div class="fw-600 text-navy small">
                                            RM <?= number_format($svc['base_price'], 0) ?>
                                            <?php if ($svc['price_type'] !== 'fixed'): ?><span class="text-muted fw-400">/ <?= h($svc['unit_label'] ?? 'unit') ?></span><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small">POQ</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($svc['description']): ?>
                                <p class="text-muted mb-2" style="font-size:.8rem"><?= h($svc['description']) ?></p>
                            <?php endif; ?>
                            <button class="btn btn-outline-gold btn-sm w-100 mt-auto"
                                    onclick="PlannerCart.add({
                                        id: 'svc_<?= (int)$svc['id'] ?>',
                                        name: <?= json_encode($svc['name']) ?>,
                                        price: <?= (float)($svc['base_price'] ?? 0) ?>,
                                        category: <?= json_encode($svc['category_label']) ?>
                                    })">
                                <i class="fas fa-plus me-1"></i>Add to Plan
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Custom add-on -->
                    <?php if ($cat['slug'] === 'custom'): ?>
                    <div class="col-12">
                        <div class="pg-card p-3">
                            <h6 class="fw-600 small mb-2">Add a Custom Item</h6>
                            <div class="row g-2">
                                <div class="col-md-5"><input type="text" id="customName" class="form-control form-control-sm" placeholder="Service name"></div>
                                <div class="col-md-3"><input type="number" id="customPrice" class="form-control form-control-sm" placeholder="Price (RM)"></div>
                                <div class="col-md-4">
                                    <button class="btn btn-outline-gold btn-sm w-100" onclick="addCustomItem()">
                                        <i class="fas fa-plus me-1"></i>Add Custom
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Bundles by Budget -->
            <div class="mt-4">
                <h5 class="fw-600 mb-3"><i class="fas fa-layer-group me-2 text-gold"></i>Recommended Bundles</h5>
                <div class="row g-3">
                    <?php
                    $bundles = [
                        ['label' => 'Essential', 'range' => 'RM 8,000–15,000', 'color' => 'secondary', 'items' => ['Basic coffin or casket', 'Hearse service (local)', 'Funeral director', 'Death cert paperwork', '1 floral wreath']],
                        ['label' => 'Standard', 'range' => 'RM 15,000–30,000', 'color' => 'gold', 'items' => ['Standard coffin', 'Hearse + support vehicle', 'Wake hall (1 day)', 'Clergy / ritual service', 'Floral arrangements', 'Funeral director', 'Obituary notice']],
                        ['label' => 'Premium', 'range' => 'RM 30,000+', 'color' => 'navy', 'items' => ['Premium coffin', 'Full transport coordination', 'Premium wake hall setup', 'Extended ritual ceremony', 'Photography', 'Full admin support', 'Online memorial page']],
                    ];
                    foreach ($bundles as $bundle): ?>
                    <div class="col-md-4">
                        <div class="pg-card p-3 h-100">
                            <div class="badge bg-<?= $bundle['color'] === 'gold' ? 'warning text-dark' : ($bundle['color'] === 'navy' ? 'dark' : 'secondary') ?> mb-2"><?= $bundle['label'] ?></div>
                            <div class="fw-600 text-navy small mb-2"><?= $bundle['range'] ?></div>
                            <ul class="list-unstyled text-muted mb-3" style="font-size:.8rem">
                                <?php foreach ($bundle['items'] as $item): ?>
                                    <li><i class="fas fa-check text-success me-1"></i><?= $item ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="<?= pg_url('request_quote.php?bundle=' . strtolower($bundle['label'])) ?>" class="btn btn-outline-gold btn-sm w-100">
                                Get Bundle Quote
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── CART SIDEBAR ──────────────────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="cart-summary">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h5 class="fw-600 mb-0">
                        <i class="fas fa-clipboard-list me-2 text-gold"></i>My Plan
                        <span id="cartCount" class="badge bg-danger rounded-pill ms-1 d-none" style="font-size:.65rem"></span>
                    </h5>
                    <button class="btn btn-link btn-sm text-muted p-0" onclick="if(confirm('Clear your plan?')) PlannerCart.clear()">Clear</button>
                </div>

                <div id="cartItems">
                    <p class="text-muted text-center py-3 small">Your plan is empty.<br>Add services from the list.</p>
                </div>

                <div class="border-top pt-3 mt-1">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-600">Estimated Total</span>
                        <span class="cart-total" id="cartTotal">RM 0.00</span>
                    </div>
                    <p class="text-muted mb-3" style="font-size:.76rem">
                        <i class="fas fa-info-circle me-1"></i>
                        Prices are indicative. Final quotes are provided by service providers.
                    </p>
                    <a href="<?= pg_url('request_quote.php') ?>" class="btn btn-gold w-100 mb-2" onclick="saveCartForQuote()">
                        <i class="fas fa-file-invoice me-2"></i>Request a Quote
                    </a>
                    <button class="btn btn-outline-secondary w-100 btn-sm" onclick="downloadPlan()">
                        <i class="fas fa-download me-2"></i>Save / Download Plan
                    </button>
                </div>
            </div>

            <!-- Partner planning tool teaser -->
            <div class="pg-card p-3 mt-3" style="border-left:3px solid var(--pg-gold)">
                <div class="small fw-600 mb-1"><i class="fas fa-coins me-2 text-gold"></i>Planning Ahead?</div>
                <p class="text-muted mb-2" style="font-size:.8rem">Use our gold-based planning estimator to track contributions towards your funeral fund.</p>
                <a href="<?= pg_url('buyer/planner.php') ?>" class="btn btn-sm btn-outline-gold">Open Planning Tool</a>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
function filterCategory(catId, btn) {
    document.querySelectorAll('.service-section').forEach(s => {
        s.style.display = catId === 'all' || s.dataset.section === catId ? '' : 'none';
    });
    document.querySelectorAll('[onclick^="filterCategory"]').forEach(b => b.classList.remove('btn-gold', 'btn-outline-secondary', 'active'));
    document.querySelectorAll('[onclick^="filterCategory"]').forEach(b => b.classList.add('btn-outline-secondary'));
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-gold', 'active');
}

function addCustomItem() {
    const name  = document.getElementById('customName').value.trim();
    const price = parseFloat(document.getElementById('customPrice').value) || 0;
    if (!name) { PlotGold.toast('Please enter a service name.', 'warning'); return; }
    PlannerCart.add({ id: 'custom_' + Date.now(), name, price, category: 'Custom' });
    document.getElementById('customName').value  = '';
    document.getElementById('customPrice').value = '';
}

function saveCartForQuote() {
    sessionStorage.setItem('pg_quote_cart', JSON.stringify(PlannerCart.items));
}

function downloadPlan() {
    if (!PlannerCart.items.length) {
        PlotGold.toast('Your plan is empty. Add some services first.', 'warning');
        return;
    }
    let text = 'PlotGold Malaysia — Funeral Plan\n';
    text += '================================\n';
    PlannerCart.items.forEach(i => {
        text += `• ${i.name} (${i.category||''}) — RM ${parseFloat(i.price||0).toFixed(2)} × ${i.qty||1} = RM ${((i.price||0)*(i.qty||1)).toFixed(2)}\n`;
    });
    text += '--------------------------------\n';
    text += `Total: RM ${PlannerCart.total().toFixed(2)}\n`;
    text += '\nVisit plotgold.my to request a formal quote.\n';
    text += '* Prices are indicative. Consult providers for confirmed quotes.\n';
    const blob = new Blob([text], { type: 'text/plain' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href: url, download: 'plotgold-funeral-plan.txt' });
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
}
</script>
JS;
include INC_PATH . '/footer.php';
?>

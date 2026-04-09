<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_PROVIDER, '/register.php');

$provider = Database::fetchOne('SELECT * FROM providers WHERE user_id = ?', [auth_user_id()]);
if (!$provider) redirect('/register.php');
$pid = (int)$provider['id'];

// ── Handle POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'upsert') {
        // Add or update a service offering
        $serviceId   = clean_int($_POST['service_id'] ?? 0);
        $price       = clean_float($_POST['price'] ?? 0);
        $priceType   = clean($_POST['price_type'] ?? 'fixed');
        $leadTime    = clean_int($_POST['lead_time_hours'] ?? 24);
        $desc        = clean($_POST['description'] ?? '');
        $available   = !empty($_POST['is_available']) ? 1 : 0;

        if (!in_array($priceType, ['fixed','per_unit','per_day','quote_required'], true)) {
            $priceType = 'fixed';
        }

        $existing = Database::fetchOne(
            'SELECT id FROM provider_services WHERE provider_id = ? AND service_id = ?',
            [$pid, $serviceId]
        );

        if ($existing) {
            Database::query(
                'UPDATE provider_services SET price=?,price_type=?,lead_time_hours=?,description=?,is_available=?,updated_at=NOW()
                 WHERE provider_id=? AND service_id=?',
                [$price ?: null, $priceType, $leadTime, $desc ?: null, $available, $pid, $serviceId]
            );
            flash_set(FLASH_SUCCESS, 'Service updated.');
        } else {
            Database::insert(
                'INSERT INTO provider_services (provider_id,service_id,price,price_type,lead_time_hours,description,is_available)
                 VALUES (?,?,?,?,?,?,?)',
                [$pid, $serviceId, $price ?: null, $priceType, $leadTime, $desc ?: null, $available]
            );
            flash_set(FLASH_SUCCESS, 'Service added.');
        }
        redirect('provider/services.php');
    }

    if ($action === 'toggle') {
        $serviceId = clean_int($_POST['service_id'] ?? 0);
        Database::query(
            'UPDATE provider_services SET is_available = 1 - is_available WHERE provider_id = ? AND service_id = ?',
            [$pid, $serviceId]
        );
        redirect('provider/services.php');
    }

    if ($action === 'remove') {
        $serviceId = clean_int($_POST['service_id'] ?? 0);
        Database::query(
            'DELETE FROM provider_services WHERE provider_id = ? AND service_id = ?',
            [$pid, $serviceId]
        );
        flash_set(FLASH_SUCCESS, 'Service removed.');
        redirect('provider/services.php');
    }
}

// ── Load data ──────────────────────────────────────────────────────
// Provider's current offerings
$myServices = Database::fetchAll(
    'SELECT ps.*, fs.name AS service_name, fs.base_price AS catalog_price,
            sc.label_en AS category_label, sc.icon AS category_icon
     FROM provider_services ps
     JOIN funeral_services fs ON fs.id = ps.service_id
     JOIN service_categories sc ON sc.id = fs.category_id
     WHERE ps.provider_id = ?
     ORDER BY sc.sort_order, fs.sort_order',
    [$pid]
);
$myServiceIds = array_column($myServices, 'service_id');

// All available services from catalog (for "Add Service" modal)
$catalog = Database::fetchAll(
    'SELECT fs.*, sc.label_en AS category_label, sc.icon AS category_icon, sc.sort_order AS cat_sort
     FROM funeral_services fs
     JOIN service_categories sc ON sc.id = fs.category_id
     WHERE fs.is_active = 1
     ORDER BY sc.sort_order, fs.sort_order'
);
// Group catalog by category
$catalogByCategory = [];
foreach ($catalog as $s) {
    $catalogByCategory[$s['category_label']][] = $s;
}

$page_title = 'My Services';
$body_class = 'portal-layout';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-700 text-navy mb-0">My Services</h4>
            <p class="text-muted small mb-0">Manage the services you offer and your pricing</p>
        </div>
        <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="fas fa-plus me-2"></i>Add Service
        </button>
    </div>

    <?php if ($myServices): ?>
    <!-- ── Services Table ───────────────────────────────────────── -->
    <?php
    // Group by category for display
    $grouped = [];
    foreach ($myServices as $s) {
        $grouped[$s['category_label']][] = $s;
    }
    foreach ($grouped as $catLabel => $services):
    ?>
    <div class="pg-card mb-3">
        <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <?php
            $icon = $services[0]['category_icon'] ?? 'fa-concierge-bell';
            ?>
            <i class="fas <?= h($icon) ?> text-gold"></i>
            <h6 class="fw-600 mb-0"><?= h($catLabel) ?></h6>
        </div>
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Price Type</th>
                        <th>Lead Time</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                <tr class="<?= !$s['is_available'] ? 'opacity-50' : '' ?>">
                    <td>
                        <div class="small fw-500"><?= h($s['service_name']) ?></div>
                        <?php if ($s['description']): ?>
                            <div class="text-muted" style="font-size:.73rem"><?= h(substr($s['description'], 0, 60)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small fw-600">
                        <?php if ($s['price_type'] === 'quote_required'): ?>
                            <span class="text-muted">Quote Required</span>
                        <?php elseif ($s['price']): ?>
                            <?= format_currency($s['price']) ?>
                        <?php else: ?>
                            <span class="text-muted text-decoration-underline" style="cursor:pointer"
                                  onclick="openEdit(<?= (int)$s['service_id'] ?>)">Set price</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= ucfirst(str_replace('_', ' ', $s['price_type'])) ?></td>
                    <td class="small text-muted"><?= (int)$s['lead_time_hours'] ?>h</td>
                    <td>
                        <span class="status-pill <?= $s['is_available'] ? 'active' : 'draft' ?>">
                            <?= $s['is_available'] ? 'Active' : 'Paused' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-gold"
                                    onclick="openEdit(<?= (int)$s['service_id'] ?>, <?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="service_id" value="<?= (int)$s['service_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $s['is_available'] ? 'Pause' : 'Activate' ?>">
                                    <i class="fas <?= $s['is_available'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Remove this service from your profile?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="service_id" value="<?= (int)$s['service_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-concierge-bell fa-3x mb-3 opacity-25"></i>
        <h5>No services added yet</h5>
        <p class="small">Add the services you offer to start receiving quote requests from families.</p>
        <button type="button" class="btn btn-gold mt-2" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="fas fa-plus me-2"></i>Add Your First Service
        </button>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- ── Add / Edit Service Modal ─────────────────────────────────── -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-600" id="modalTitle">Add Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="serviceForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upsert">
                <input type="hidden" name="service_id" id="modalServiceId">

                <div class="modal-body">
                    <!-- Service selector (shown only for new) -->
                    <div id="serviceSelectorWrap" class="mb-3">
                        <label class="form-label">Select Service from Catalog</label>
                        <select name="service_id" id="serviceCatalogSelect" class="form-select" required>
                            <option value="">— Choose a service —</option>
                            <?php foreach ($catalogByCategory as $catLabel => $services): ?>
                            <optgroup label="<?= h($catLabel) ?>">
                                <?php foreach ($services as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"
                                        <?= in_array($s['id'], $myServiceIds) ? 'data-existing="1"' : '' ?>
                                        data-base-price="<?= (float)($s['base_price'] ?? 0) ?>">
                                        <?= h($s['name']) ?>
                                        <?php if ($s['base_price']): ?> — RM <?= number_format($s['base_price'], 0) ?><?php endif; ?>
                                        <?= in_array($s['id'], $myServiceIds) ? ' ✓' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Pricing Type</label>
                            <select name="price_type" id="modalPriceType" class="form-select">
                                <option value="fixed">Fixed Price</option>
                                <option value="per_unit">Per Unit</option>
                                <option value="per_day">Per Day</option>
                                <option value="quote_required">Quote Required</option>
                            </select>
                        </div>
                        <div class="col-sm-6" id="priceFieldWrap">
                            <label class="form-label">Your Price (RM)</label>
                            <div class="input-group">
                                <span class="input-group-text">RM</span>
                                <input type="number" name="price" id="modalPrice" class="form-control"
                                       min="0" step="0.01" placeholder="e.g. 1200.00">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Lead Time (hours)</label>
                            <input type="number" name="lead_time_hours" id="modalLeadTime"
                                   class="form-control" min="1" max="720" value="24">
                        </div>
                        <div class="col-sm-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_available" id="modalAvailable"
                                       class="form-check-input" value="1" checked>
                                <label for="modalAvailable" class="form-check-label fw-500">Available now</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes / Description <span class="text-muted fw-400">(optional)</span></label>
                            <textarea name="description" id="modalDesc" class="form-control" rows="2"
                                      placeholder="Any details about this service offering…"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold" id="modalSaveBtn">Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<JS
<script>
const modalEl = document.getElementById('addServiceModal');
const modal   = new bootstrap.Modal(modalEl);

document.getElementById('modalPriceType').addEventListener('change', function() {
    document.getElementById('priceFieldWrap').style.display =
        this.value === 'quote_required' ? 'none' : '';
});

document.getElementById('serviceCatalogSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const base = parseFloat(opt.dataset.basePrice || 0);
    if (base > 0 && !document.getElementById('modalPrice').value) {
        document.getElementById('modalPrice').value = base.toFixed(2);
    }
});

function openEdit(serviceId, data) {
    document.getElementById('modalTitle').textContent = data ? 'Edit Service' : 'Add Service';
    document.getElementById('modalSaveBtn').textContent = data ? 'Save Changes' : 'Add Service';

    const selWrap = document.getElementById('serviceSelectorWrap');
    const selSvc  = document.getElementById('serviceCatalogSelect');
    const hidId   = document.getElementById('modalServiceId');

    if (data) {
        selWrap.style.display = 'none';
        selSvc.removeAttribute('required');
        hidId.value = serviceId;

        document.getElementById('modalPriceType').value = data.price_type || 'fixed';
        document.getElementById('modalPrice').value     = data.price || '';
        document.getElementById('modalLeadTime').value  = data.lead_time_hours || 24;
        document.getElementById('modalDesc').value      = data.description || '';
        document.getElementById('modalAvailable').checked = data.is_available == 1;
        document.getElementById('priceFieldWrap').style.display =
            data.price_type === 'quote_required' ? 'none' : '';
    } else {
        selWrap.style.display = '';
        selSvc.required = true;
        hidId.value = '';
        // Reset
        document.getElementById('modalPrice').value = '';
        document.getElementById('modalLeadTime').value = 24;
        document.getElementById('modalDesc').value = '';
        document.getElementById('modalAvailable').checked = true;
        document.getElementById('modalPriceType').value = 'fixed';
        document.getElementById('priceFieldWrap').style.display = '';
    }

    modal.show();
}
</script>
JS;
include INC_PATH . '/footer.php';
?>

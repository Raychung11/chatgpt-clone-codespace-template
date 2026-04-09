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

    if ($action === 'save_areas') {
        // Replace all areas with submitted set
        Database::query('DELETE FROM provider_service_areas WHERE provider_id = ?', [$pid]);
        $states = $_POST['states'] ?? [];
        foreach ($states as $state) {
            $state = clean($state);
            if ($state && in_array($state, MY_STATES, true)) {
                Database::insert(
                    'INSERT INTO provider_service_areas (provider_id, state) VALUES (?,?)',
                    [$pid, $state]
                );
            }
        }
        flash_set(FLASH_SUCCESS, 'Service areas updated.');
        redirect('provider/areas.php');
    }

    if ($action === 'add_city') {
        $state = clean($_POST['state'] ?? '');
        $city  = clean($_POST['city']  ?? '');
        if ($state && $city) {
            Database::insert(
                'INSERT IGNORE INTO provider_service_areas (provider_id, state, city) VALUES (?,?,?)',
                [$pid, $state, $city]
            );
            flash_set(FLASH_SUCCESS, 'City added.');
        }
        redirect('provider/areas.php');
    }

    if ($action === 'remove_city') {
        $areaId = clean_int($_POST['area_id'] ?? 0);
        Database::query(
            'DELETE FROM provider_service_areas WHERE id = ? AND provider_id = ?',
            [$areaId, $pid]
        );
        redirect('provider/areas.php');
    }
}

// Current coverage
$selectedStates = Database::fetchAll(
    'SELECT DISTINCT state FROM provider_service_areas WHERE provider_id = ? AND (city IS NULL OR city = "") ORDER BY state',
    [$pid]
);
$selectedStateNames = array_column($selectedStates, 'state');

$cityAreas = Database::fetchAll(
    'SELECT * FROM provider_service_areas WHERE provider_id = ? AND city IS NOT NULL AND city != "" ORDER BY state, city',
    [$pid]
);

$page_title = 'Service Areas';
$body_class = 'portal-layout';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <h4 class="fw-700 text-navy mb-1">Service Areas</h4>
    <p class="text-muted small mb-4">Define which states and cities you can provide services in. This helps match you with relevant quote requests.</p>

    <div class="row g-4">
        <!-- State Coverage -->
        <div class="col-lg-7">
            <div class="pg-card p-4 mb-4">
                <h6 class="fw-600 mb-3 pb-2 border-bottom">State Coverage</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_areas">
                    <p class="text-muted small mb-3">Check all states where you provide services:</p>
                    <div class="row g-2 mb-4">
                        <?php foreach (MY_STATES as $state): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="states[]"
                                       id="state_<?= h(str_replace(' ', '_', $state)) ?>"
                                       value="<?= h($state) ?>"
                                       class="form-check-input"
                                       <?= in_array($state, $selectedStateNames) ? 'checked' : '' ?>>
                                <label for="state_<?= h(str_replace(' ', '_', $state)) ?>"
                                       class="form-check-label small"><?= h($state) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-gold">Save State Coverage</button>
                </form>
            </div>

            <!-- Specific Cities -->
            <div class="pg-card p-4">
                <h6 class="fw-600 mb-3 pb-2 border-bottom">Specific Cities / Townships</h6>
                <p class="text-muted small mb-3">Add specific towns or townships where you operate for more precise matching.</p>

                <form method="POST" class="mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_city">
                    <div class="row g-2">
                        <div class="col-sm-5">
                            <select name="state" class="form-select form-select-sm" required>
                                <option value="">— State —</option>
                                <?php foreach (MY_STATES as $s): ?>
                                    <option value="<?= h($s) ?>"><?= h($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <input type="text" name="city" class="form-control form-control-sm"
                                   placeholder="e.g. Kajang, Subang Jaya" required>
                        </div>
                        <div class="col-sm-2">
                            <button type="submit" class="btn btn-sm btn-outline-gold w-100">Add</button>
                        </div>
                    </div>
                </form>

                <?php if ($cityAreas): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($cityAreas as $a): ?>
                    <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_city">
                        <input type="hidden" name="area_id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="badge rounded-pill border-0 d-flex align-items-center gap-1"
                                style="background:var(--pg-gold-pale);color:var(--pg-gold);font-size:.8rem;padding:6px 12px;cursor:pointer"
                                title="Click to remove">
                            <i class="fas fa-map-marker-alt" style="font-size:.65rem"></i>
                            <?= h($a['city']) ?>, <?= h($a['state']) ?>
                            <i class="fas fa-times" style="font-size:.65rem;opacity:.6"></i>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted small">No specific cities added yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coverage Preview -->
        <div class="col-lg-5">
            <div class="pg-card p-4 mb-3">
                <h6 class="fw-600 mb-3">Current Coverage</h6>
                <?php if ($selectedStateNames): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($selectedStateNames as $s): ?>
                        <span class="badge bg-navy text-white"><?= h($s) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mb-0"><?= count($selectedStateNames) ?> state(s) covered</p>
                <?php else: ?>
                <p class="text-muted small">No states selected yet.</p>
                <?php endif; ?>
            </div>

            <div class="pg-card p-4" style="border-left:3px solid var(--pg-gold)">
                <h6 class="fw-600 mb-3"><i class="fas fa-info-circle text-gold me-2"></i>Why This Matters</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2">Quote requests from families in your covered states/cities are highlighted for you.</li>
                    <li class="mb-2">Families searching for providers in your area will see your profile first.</li>
                    <li>Keeping your areas accurate ensures you only get relevant enquiries.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>

<?php include INC_PATH . '/footer.php'; ?>

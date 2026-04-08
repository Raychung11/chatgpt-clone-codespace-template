<?php
require_once __DIR__ . '/inc/bootstrap.php';

$typeFilter   = clean($_GET['type']  ?? '');
$stateFilter  = clean($_GET['state'] ?? '');
$q            = clean($_GET['q']     ?? '');

$where  = ["p.approval_status = 'approved'"];
$params = [];

if ($typeFilter) { $where[] = 'p.provider_type = ?'; $params[] = $typeFilter; }
if ($stateFilter){ $where[] = 'EXISTS (SELECT 1 FROM provider_service_areas psa WHERE psa.provider_id = p.id AND psa.state = ?)'; $params[] = $stateFilter; }
if ($q)          { $where[] = '(p.business_name LIKE ? OR p.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$providers = Database::fetchAll(
    'SELECT p.*, up.full_name, u.phone, u.email
     FROM providers p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN user_profiles up ON up.user_id = p.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.is_featured DESC, p.rating DESC, p.business_name ASC',
    $params
);

$providerTypes = [
    'funeral_home'   => 'Funeral Home',
    'transport'      => 'Transport / Hearse',
    'florist'        => 'Florist',
    'memorial_park'  => 'Memorial Park',
    'catering'       => 'Catering',
    'clergy'         => 'Clergy / Ritual',
    'admin_support'  => 'Admin Support',
    'multipurpose'   => 'Multipurpose',
];

$page_title       = 'Funeral Service Providers';
$meta_description = 'Find verified funeral service providers in Malaysia — funeral homes, transport, florists, clergy, and more. Compare and request quotes.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item active">Service Providers</li>
        </ol>
    </div>
</nav>

<div class="bg-white border-bottom py-4">
    <div class="container">
        <h1 class="h3 fw-700 text-navy mb-1">Funeral Service Providers</h1>
        <p class="text-muted mb-3">Verified service providers across Malaysia</p>

        <form method="GET" action="" class="d-flex flex-wrap gap-2">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:220px" placeholder="Search providers…" value="<?= h($q) ?>">
            <select name="type" class="form-select form-select-sm" style="max-width:180px">
                <option value="">All Types</option>
                <?php foreach ($providerTypes as $val => $label): ?>
                    <option value="<?= h($val) ?>" <?= $typeFilter === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="state" class="form-select form-select-sm" style="max-width:160px">
                <option value="">Any State</option>
                <?php foreach (MY_STATES as $s): ?>
                    <option value="<?= h($s) ?>" <?= $stateFilter === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-gold btn-sm">Search</button>
        </form>
    </div>
</div>

<div class="container py-4">
    <!-- Type quick filters -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= pg_url('providers.php') ?>" class="btn btn-sm <?= !$typeFilter ? 'btn-gold' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($providerTypes as $val => $label): ?>
            <a href="<?= pg_url('providers.php') ?>?type=<?= $val ?>" class="btn btn-sm <?= $typeFilter === $val ? 'btn-gold' : 'btn-outline-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($providers): ?>
    <div class="row g-3">
        <?php foreach ($providers as $prov): ?>
        <div class="col-sm-6 col-lg-4">
            <div class="pg-card p-4 h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="nav-avatar" style="width:48px;height:48px;font-size:1.2rem;flex-shrink:0">
                        <?php if ($prov['logo_path']): ?>
                            <img src="<?= BASE_URL . '/uploads/providers/' . h($prov['logo_path']) ?>" alt="" class="w-100 h-100 rounded" style="object-fit:cover">
                        <?php else: ?>
                            <i class="fas fa-briefcase"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="fw-600 mb-0"><?= h($prov['business_name']) ?></h6>
                        <span class="badge bg-light text-muted border" style="font-size:.7rem"><?= h($providerTypes[$prov['provider_type']] ?? $prov['provider_type']) ?></span>
                        <?php if ($prov['is_featured']): ?>
                            <span class="badge bg-warning text-dark" style="font-size:.7rem">Featured</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($prov['description']): ?>
                    <p class="text-muted small mb-3"><?= h(substr($prov['description'], 0, 120)) ?>…</p>
                <?php endif; ?>

                <?php if ($prov['rating'] > 0): ?>
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="font-size:.75rem;color:<?= $i <= round($prov['rating']) ? 'var(--pg-gold)' : '#E2E8F0' ?>"></i>
                        <?php endfor; ?>
                        <span class="small text-muted"><?= number_format($prov['rating'], 1) ?> (<?= $prov['total_reviews'] ?>)</span>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-auto pt-2">
                    <a href="<?= pg_url('request_quote.php?provider=' . (int)$prov['id']) ?>" class="btn btn-outline-gold btn-sm flex-fill">Request Quote</a>
                    <?php if ($prov['phone'] || $prov['whatsapp']): ?>
                        <a href="<?= whatsapp_link('Hi, I found you on PlotGold Malaysia. Can I enquire about your services?', preg_replace('/[^0-9]/', '', $prov['whatsapp'] ?: $prov['phone'])) ?>"
                           class="btn btn-whatsapp btn-sm" target="_blank" rel="noopener" title="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No providers found</h5>
        <p class="text-muted small">Try adjusting your filters.</p>
        <a href="<?= pg_url('register.php?type=provider') ?>" class="btn btn-outline-gold mt-2">Join as a Provider</a>
    </div>
    <?php endif; ?>

    <!-- CTA to join as provider -->
    <div class="mt-5 p-4 pg-card text-center" style="border-left:4px solid var(--pg-gold)">
        <h5 class="fw-600">Are you a funeral service provider?</h5>
        <p class="text-muted small">Join our verified network and receive quote requests from families across Malaysia.</p>
        <a href="<?= pg_url('register.php?type=provider') ?>" class="btn btn-gold">Join as a Provider</a>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>

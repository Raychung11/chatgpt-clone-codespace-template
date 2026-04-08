<?php
require_once __DIR__ . '/inc/bootstrap.php';

$slug = clean($_GET['slug'] ?? '');
if (!$slug) redirect('browse_listings.php');

$listing = Database::fetchOne(
    'SELECT l.*, lt.label_en AS type_label, lt.slug AS type_slug,
            mp.name AS park_name, mp.slug AS park_slug, mp.city AS park_city,
            mp.address_line1 AS park_address, mp.phone AS park_phone,
            rc.label_en AS religion_label,
            ps.name AS section_name,
            up.full_name AS seller_name, u.phone AS seller_phone
     FROM listings l
     LEFT JOIN listing_types lt       ON lt.id = l.listing_type_id
     LEFT JOIN memorial_parks mp      ON mp.id = l.park_id
     LEFT JOIN religion_categories rc ON rc.id = l.religion_id
     LEFT JOIN park_sections ps       ON ps.id = l.section_id
     LEFT JOIN sellers s              ON s.id  = l.seller_id
     LEFT JOIN users u                ON u.id  = s.user_id
     LEFT JOIN user_profiles up       ON up.user_id = s.user_id
     WHERE l.slug = ? AND l.status = ?',
    [$slug, LISTING_ACTIVE]
);

if (!$listing) {
    http_response_code(404);
    include INC_PATH . '/errors/404.php';
    exit;
}

// Increment view count
Database::query('UPDATE listings SET view_count = view_count + 1 WHERE id = ?', [$listing['id']]);

// Media
$media = Database::fetchAll(
    'SELECT * FROM listing_media WHERE listing_id = ? AND is_approved = 1 ORDER BY is_primary DESC, sort_order ASC',
    [$listing['id']]
);

// Verification checks
$checks = Database::fetchAll(
    'SELECT * FROM listing_verification_checks WHERE listing_id = ? ORDER BY weight DESC',
    [$listing['id']]
);

// Similar listings
$similar = Database::fetchAll(
    'SELECT l.id, l.title, l.slug, l.asking_price, l.city, l.badge_status,
            lt.label_en AS type_label, lm.file_path AS primary_image
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN listing_media lm ON lm.listing_id = l.id AND lm.is_primary = 1
     WHERE l.status = ? AND l.id != ? AND (l.park_id = ? OR l.state = ?)
     ORDER BY RAND() LIMIT 3',
    [LISTING_ACTIVE, $listing['id'], $listing['park_id'], $listing['state']]
);

$verifyScore = (int)$listing['verification_score'];
$isFav = false;
if (auth_check()) {
    $buyer = Database::fetchOne('SELECT id FROM buyers WHERE user_id = ?', [auth_user_id()]);
    if ($buyer) {
        $isFav = (bool)Database::fetchOne(
            'SELECT id FROM favourites WHERE buyer_id = ? AND listing_id = ?',
            [$buyer['id'], $listing['id']]
        );
    }
}

$whatsappMsg = urlencode("Hi, I'm interested in listing #{$listing['listing_code']} ({$listing['title']}) on PlotGold. Can you share more details?");

$page_title       = $listing['title'];
$meta_description = substr(strip_tags($listing['public_description'] ?? ''), 0, 160);
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= pg_url('browse_listings.php') ?>">Browse</a></li>
            <?php if (!empty($listing['park_slug'])): ?>
                <li class="breadcrumb-item"><a href="<?= park_url($listing['park_slug']) ?>"><?= h($listing['park_name']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= h($listing['listing_code']) ?></li>
        </ol>
    </div>
</nav>

<div class="container py-4">
    <div class="row g-4">

        <!-- ── MAIN COLUMN ──────────────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- Title + badges -->
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <?= listing_badge_html($listing['badge_status']) ?>
                    <?= listing_urgency_html($listing['urgency_level']) ?>
                    <span class="listing-type-tag"><?= h($listing['type_label']) ?></span>
                    <span class="text-muted small ms-auto"><?= h($listing['listing_code']) ?></span>
                </div>
                <h1 class="h3 fw-700 text-navy mb-1"><?= h($listing['title']) ?></h1>
                <p class="text-muted">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    <?= h($listing['city']) ?>, <?= h($listing['state']) ?>
                    <?php if ($listing['park_name']): ?> &mdash; <?= h($listing['park_name']) ?><?php endif; ?>
                </p>
            </div>

            <!-- Gallery -->
            <?php if ($media): ?>
            <div class="listing-detail-gallery mb-4">
                <img id="galleryMain" src="<?= BASE_URL . '/uploads/listings/' . h($media[0]['file_path']) ?>"
                     class="gallery-main-img rounded-3 mb-2"
                     alt="<?= h($listing['title']) ?>">
                <?php if (count($media) > 1): ?>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach ($media as $i => $m): ?>
                        <img src="<?= BASE_URL . '/uploads/listings/' . h($m['file_path']) ?>"
                             class="gallery-thumb rounded-2 <?= $i === 0 ? 'active' : '' ?>"
                             data-full="<?= BASE_URL . '/uploads/listings/' . h($m['file_path']) ?>"
                             alt="Image <?= $i + 1 ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="pg-card-img-placeholder rounded-3 mb-4" style="height:360px">
                <i class="fas fa-mountain fa-4x"></i>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="pg-card p-4 mb-4">
                <h5 class="fw-600 mb-3">About this Listing</h5>
                <p class="text-muted" style="line-height:1.9"><?= nl2br(h($listing['public_description'] ?? 'No description provided.')) ?></p>
            </div>

            <!-- Specifications -->
            <div class="pg-card p-4 mb-4">
                <h5 class="fw-600 mb-3">Listing Details</h5>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table listing-spec-table">
                            <tr><td>Listing Type</td><td><?= h($listing['type_label']) ?></td></tr>
                            <tr><td>Religion / Category</td><td><?= h($listing['religion_label'] ?? '—') ?></td></tr>
                            <tr><td>Memorial Park</td><td><?= $listing['park_name'] ? '<a href="' . park_url($listing['park_slug']) . '">' . h($listing['park_name']) . '</a>' : '—' ?></td></tr>
                            <tr><td>Section</td><td><?= h($listing['section_name'] ?? '—') ?></td></tr>
                            <tr><td>Block / Row / Lot</td><td>
                                <?= implode(' / ', array_filter([h($listing['block_no']), h($listing['row_no']), h($listing['lot_no'])])) ?: '—' ?>
                            </td></tr>
                            <tr><td>City</td><td><?= h($listing['city']) ?>, <?= h($listing['state']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table listing-spec-table">
                            <tr><td>Asking Price</td><td class="fw-600 text-navy"><?= format_currency($listing['asking_price']) ?></td></tr>
                            <tr><td>Transfer Fee</td><td><?= $listing['transfer_fee'] ? format_currency($listing['transfer_fee']) : 'TBC' ?></td></tr>
                            <tr><td>Annual Maintenance</td><td><?= $listing['annual_maintenance_fee'] ? format_currency($listing['annual_maintenance_fee']) : '—' ?></td></tr>
                            <tr><td>Maintenance Status</td><td><?= ucfirst($listing['maintenance_status'] ?? '—') ?></td></tr>
                            <tr><td>Ownership Type</td><td><?= ucfirst(str_replace('_', ' ', $listing['ownership_type'] ?? '—')) ?></td></tr>
                            <tr><td>Transferable</td><td><?= $listing['is_transferable'] ? '<span class="text-success fw-500">Yes</span>' : '<span class="text-warning fw-500">Check Required</span>' ?></td></tr>
                        </table>
                    </div>
                </div>

                <?php if ($listing['orientation_notes'] || $listing['fengshui_notes']): ?>
                <div class="bg-pale-gold p-3 rounded-2 mt-2">
                    <h6 class="fw-600 small mb-1"><i class="fas fa-compass me-2 text-gold"></i>Orientation / Feng Shui Notes</h6>
                    <p class="small text-muted mb-0"><?= h($listing['fengshui_notes'] ?: $listing['orientation_notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Verification Status -->
            <div class="pg-card p-4 mb-4">
                <h5 class="fw-600 mb-3"><i class="fas fa-shield-alt me-2 text-gold"></i>Verification Status</h5>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="flex-grow-1">
                        <div class="verification-bar">
                            <div class="bar-fill <?= $verifyScore >= 90 ? 'verified' : ($verifyScore >= 60 ? 'partial' : 'pending') ?>"
                                 style="width:<?= $verifyScore ?>%"></div>
                        </div>
                    </div>
                    <div class="fw-600 text-navy"><?= $verifyScore ?>%</div>
                    <?= listing_badge_html($listing['badge_status']) ?>
                </div>
                <?php if ($checks): ?>
                <div class="row g-2">
                    <?php foreach ($checks as $c): ?>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center gap-2 small">
                            <?php if ($c['status'] === 'passed'): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php elseif ($c['status'] === 'failed'): ?>
                                <i class="fas fa-times-circle text-danger"></i>
                            <?php else: ?>
                                <i class="fas fa-clock text-warning"></i>
                            <?php endif; ?>
                            <span class="<?= $c['status'] !== 'passed' ? 'text-muted' : '' ?>"><?= h($c['check_label']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ── SIDEBAR / ENQUIRY ─────────────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="sticky-enquiry">
                <!-- Price -->
                <div class="text-center mb-3 pb-3 border-bottom">
                    <?php if ($listing['asking_price']): ?>
                        <div class="listing-price"><?= format_currency($listing['asking_price']) ?></div>
                        <div class="small text-muted">Asking Price</div>
                        <?php if ($listing['market_estimate']): ?>
                            <div class="small text-muted mt-1">Market Est. <?= format_currency($listing['market_estimate']) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="fs-5 fw-600 text-muted">Price on Enquiry</div>
                    <?php endif; ?>
                </div>

                <!-- WhatsApp Enquiry (primary CTA) -->
                <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=<?= $whatsappMsg ?>"
                   target="_blank" rel="noopener" class="btn btn-whatsapp w-100 mb-2 py-3">
                    <i class="fab fa-whatsapp me-2 fs-5"></i>Enquire via WhatsApp
                </a>

                <!-- Online Enquiry Form -->
                <form action="<?= pg_url('api/enquiry.php') ?>" method="POST" id="enquiryForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
                    <input type="hidden" name="enquiry_type" value="listing">
                    <div class="mb-2">
                        <input type="text" name="contact_name" class="form-control form-control-sm"
                               placeholder="Your name" required value="<?= auth_check() ? h(auth_user_name()) : '' ?>">
                    </div>
                    <div class="mb-2">
                        <input type="tel" name="contact_phone" class="form-control form-control-sm"
                               placeholder="Phone / WhatsApp">
                    </div>
                    <div class="mb-2">
                        <textarea name="message" class="form-control form-control-sm" rows="3"
                                  placeholder="Your message or question…" required>I'm interested in this listing (<?= h($listing['listing_code']) ?>). Please contact me.</textarea>
                    </div>
                    <button type="submit" class="btn btn-navy w-100 btn-sm">
                        <i class="fas fa-envelope me-2"></i>Send Enquiry
                    </button>
                </form>

                <!-- Save / Compare -->
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-outline-secondary btn-sm flex-fill <?= $isFav ? 'text-danger' : '' ?>"
                            onclick="toggleFavourite('<?= (int)$listing['id'] ?>', this)">
                        <i class="<?= $isFav ? 'fas' : 'far' ?> fa-heart me-1"></i>
                        <?= $isFav ? 'Saved' : 'Save' ?>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="Compare.toggle('<?= (int)$listing['id'] ?>', this)">
                        <i class="fas fa-balance-scale me-1"></i>Compare
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="navigator.share ? navigator.share({title: '<?= addslashes(h($listing['title'])) ?>', url: window.location.href}) : navigator.clipboard.writeText(window.location.href)" title="Share">
                        <i class="fas fa-share-alt"></i>
                    </button>
                </div>

                <!-- Seller info (minimal) -->
                <div class="border-top mt-3 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="nav-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="small fw-500">Listed by <?= h($listing['seller_name'] ?? 'Verified Seller') ?></div>
                            <div class="small text-muted">
                                <?php if ($listing['seller_phone']): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $listing['seller_phone']) ?>?text=<?= $whatsappMsg ?>"
                                       class="text-muted" target="_blank" rel="noopener">
                                        <i class="fab fa-whatsapp me-1"></i>Contact seller
                                    </a>
                                <?php else: ?>
                                    Contact via PlotGold
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seller intent -->
                <div class="mt-3 p-2 rounded-2 bg-pale-gold text-center">
                    <small class="text-muted">
                        <?php
                        $intentMap = [
                            'direct_sale'     => '<i class="fas fa-tag me-1"></i>Seller is open to direct sale',
                            'open_to_offers'  => '<i class="fas fa-handshake me-1"></i>Seller is open to offers',
                            'inquiry_only'    => '<i class="fas fa-info-circle me-1"></i>Inquiry only — contact for details',
                        ];
                        echo $intentMap[$listing['seller_intent'] ?? 'direct_sale'] ?? '';
                        ?>
                    </small>
                </div>
            </div>

            <!-- View count -->
            <p class="text-center text-muted small mt-3">
                <i class="fas fa-eye me-1"></i><?= number_format($listing['view_count']) ?> views &middot;
                Listed <?= time_ago($listing['listed_at'] ?? $listing['created_at']) ?>
            </p>
        </div>
    </div>

    <!-- Similar Listings -->
    <?php if ($similar): ?>
    <div class="mt-5">
        <h4 class="section-title mb-2">Similar Listings</h4>
        <div class="section-divider"></div>
        <div class="row g-3 mt-1">
            <?php foreach ($similar as $listing): ?>
            <div class="col-sm-6 col-lg-4">
                <?php include INC_PATH . '/partials/listing_card.php'; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": <?= json_encode($listing['title']) ?>,
  "description": <?= json_encode(substr(strip_tags($listing['public_description'] ?? ''), 0, 200)) ?>,
  "offers": {
    "@type": "Offer",
    "priceCurrency": "MYR",
    "price": "<?= $listing['asking_price'] ?? '0' ?>",
    "availability": "https://schema.org/InStock",
    "seller": { "@type": "Organization", "name": "PlotGold Malaysia" }
  }
}
</script>

<?php
$extra_scripts = <<<'JS'
<script>
document.getElementById('enquiryForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending…';
    try {
        const res = await PlotGold.postForm('/api/enquiry.php', new FormData(this));
        if (res.success) {
            PlotGold.toast('Enquiry sent! We will contact you shortly.', 'success');
            this.reset();
        } else {
            PlotGold.toast(res.error || 'Something went wrong.', 'danger');
        }
    } catch { PlotGold.toast('Network error. Please try again.', 'danger'); }
    btn.disabled = false;
    btn.innerHTML = orig;
});
</script>
JS;
include INC_PATH . '/footer.php';
?>

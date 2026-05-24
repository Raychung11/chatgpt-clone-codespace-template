<?php
// ============================================================
//  KOPONIX – Listing Detail Page
// ============================================================
require_once __DIR__ . '/../layout.php';

$seller_id = trim($_GET['id'] ?? '');
$seller    = $seller_id ? get_seller_by_id($seller_id) : null;
$available = $seller && $seller['status'] === 'active';

$icons  = cat_icons();
$colors = cat_colors();

if (!$available) {
    html_head('Listing Not Found');
    html_body_open();
    ?>
    <div class="card p-4 text-center" style="max-width:500px;margin:auto">
        <div style="font-size:2.5rem">🔍</div>
        <h5 class="mt-2">Listing Not Found</h5>
        <p class="text-muted">This listing is no longer available.</p>
        <a href="<?= MARKET_URL ?>/" class="btn btn-primary mt-2">Browse Services</a>
    </div>
    <?php
    html_footer();
    return;
}

$icon   = $icons[$seller['category']]  ?? '⭐';
$color  = $colors[$seller['category']] ?? '#607d8b';
$photos = array_values(array_filter([$seller['image'], $seller['gallery1'], $seller['gallery2']]));
$wa_url = whatsapp_url($seller['contact'] ?? '', 'Hi, I found your listing on Koponix: ' . $seller['service_title']);

html_head($seller['service_title'] . ' — Koponix');
html_body_open();
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= MARKET_URL ?>/" class="btn btn-sm btn-outline-secondary">← Browse</a>
    <a href="<?= MARKET_URL ?>/?category=<?= urlencode($seller['category']) ?>"
       class="text-decoration-none"><?= cat_badge($seller['category']) ?></a>
</div>

<div class="row g-4">

    <!-- ── Left: photos + full description ────────────────────── -->
    <div class="col-lg-7">

        <?php if ($photos): ?>
        <div id="listingCarousel" class="carousel slide rounded overflow-hidden mb-2"
             data-bs-ride="<?= count($photos) > 1 ? 'carousel' : 'false' ?>"
             style="box-shadow:0 4px 16px rgba(0,0,0,.12)">
            <div class="carousel-inner">
            <?php foreach ($photos as $i => $p): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <img src="<?= e(img_url($p)) ?>" class="d-block w-100"
                         style="height:360px;object-fit:cover">
                </div>
            <?php endforeach; ?>
            </div>
            <?php if (count($photos) > 1): ?>
            <button class="carousel-control-prev" type="button"
                    data-bs-target="#listingCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button"
                    data-bs-target="#listingCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
            <?php endif; ?>
        </div>

        <?php if (count($photos) > 1): ?>
        <div class="d-flex gap-2 mb-3">
            <?php foreach ($photos as $i => $p): ?>
            <img src="<?= e(img_url($p)) ?>"
                 class="thumb-img rounded"
                 onclick="bootstrap.Carousel.getInstance(document.getElementById('listingCarousel')).to(<?= $i ?>)"
                 style="width:70px;height:54px;object-fit:cover;cursor:pointer;
                        border:2px solid <?= $i === 0 ? '#1a5276' : '#dee2e6' ?>;
                        transition:border-color .15s">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="rounded mb-3" style="height:160px;
             background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>bb);
             display:flex;align-items:center;justify-content:center;gap:1rem">
            <span style="font-size:3.5rem"><?= $icon ?></span>
        </div>
        <?php endif; ?>

        <!-- Full description -->
        <div class="card p-4">
            <h6 class="fw-bold mb-3" style="color:#1a3a52">About this Service</h6>
            <div style="line-height:1.8;white-space:pre-wrap;color:#333"><?= e($seller['description']) ?></div>
        </div>

    </div>

    <!-- ── Right: info + actions ──────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card p-4" style="position:sticky;top:76px">

            <?= cat_badge($seller['category']) ?>
            <h4 class="mt-2 mb-1 fw-bold" style="color:#1a3a52;line-height:1.3">
                <?= e($seller['service_title']) ?>
            </h4>

            <div class="mt-3 mb-3" style="font-size:.88rem;line-height:2">
                <div>👤 <strong><?= e($seller['name']) ?></strong></div>
                <div>📍 <?= e($seller['area']) ?></div>
                <div>💰 <strong style="color:#1a5276"><?= e($seller['price_range']) ?></strong></div>
                <?php if ($seller['availability']): ?>
                <div>🕐 <?= e($seller['availability']) ?></div>
                <?php endif; ?>
                <?php if ($seller['experience']): ?>
                <div>🏅 <?= e($seller['experience']) ?> experience</div>
                <?php endif; ?>
            </div>

            <hr>

            <div class="d-grid gap-2">
                <a href="<?= MARKET_URL ?>/book.php?id=<?= urlencode($seller['id']) ?>"
                   class="btn btn-primary btn-lg fw-semibold">📅 Book Now</a>

                <?php if ($wa_url): ?>
                <a href="<?= e($wa_url) ?>" target="_blank" rel="noopener noreferrer"
                   class="btn btn-success fw-semibold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor"
                         viewBox="0 0 16 16" class="me-1 mb-1">
                        <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
                    </svg>
                    WhatsApp Provider
                </a>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                <a href="<?= PORTAL_URL ?>/messages.php?start=1&seller_kop=<?= urlencode($seller['koperasi_id']) ?>&seller_name=<?= urlencode($seller['name']) ?>&subject=<?= urlencode('Enquiry: '.$seller['service_title']) ?>&seller_id=<?= urlencode($seller['id']) ?>"
                   class="btn btn-outline-primary">💬 Send Message</a>
                <?php else: ?>
                <a href="<?= PORTAL_URL ?>/?login_required=1"
                   class="btn btn-outline-secondary">💬 Login to Message</a>
                <?php endif; ?>
            </div>

            <div class="mt-3 p-3 rounded small" style="background:#f0f4f8;color:#666;line-height:1.6">
                💡 Booking sends a <strong>request</strong> to the provider. They will confirm and contact you to arrange details and payment.
            </div>

        </div>
    </div>

</div>

<?php if (count($photos) > 1): ?>
<script>
document.getElementById('listingCarousel').addEventListener('slide.bs.carousel', e => {
    document.querySelectorAll('.thumb-img').forEach((img, i) => {
        img.style.borderColor = i === e.to ? '#1a5276' : '#dee2e6';
    });
});
</script>
<?php endif; ?>

<?php html_footer(); ?>

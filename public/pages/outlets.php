<?php
/**
 * Customer – Outlets / Store Locator
 * /public/pages/outlets.php
 */

$appTitle   = 'Outlets – F&B Loyalty';
$showHeader = true;
$pageHeader = 'Find an Outlet';
$extraHead  = '<style>.map-container{height:220px;border-radius:14px;overflow:hidden;background:#e9ecef;}</style>';
require BASE_PATH . '/public/layout/app_shell.php';
?>

<!-- Search -->
<div class="mb-3 position-relative">
    <i class="bi bi-search position-absolute" style="left:14px;top:50%;transform:translateY(-50%);color:#9aa0ac;"></i>
    <input type="text" id="outlet-search" class="form-control form-control-app" placeholder="Search outlets…" style="padding-left:38px;">
</div>

<!-- Near Me Button -->
<button class="btn btn-outline-primary btn-sm mb-3 w-100" id="btn-nearby">
    <i class="bi bi-geo-alt-fill me-1"></i>Find Near Me
</button>

<!-- Outlets List -->
<div id="outlets-list">
    <?php for($i=0;$i<3;$i++): ?>
    <div class="skeleton mb-2" style="height:100px;border-radius:14px;"></div>
    <?php endfor; ?>
</div>

<!-- Outlet Detail Modal -->
<div class="modal fade" id="outletModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" id="outlet-modal-name"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="outlet-modal-body"></div>
            <div class="modal-footer border-0 pt-0">
                <a href="#" id="outlet-maps-link" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-map me-1"></i>Open Maps
                </a>
                <a href="/app/reservations" class="btn btn-sm text-white" style="background:#e94560;">
                    <i class="bi bi-calendar-check me-1"></i>Book Table
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let allOutlets = [];
const outletModal = new bootstrap.Modal(document.getElementById('outletModal'));

function renderOutlets(outlets) {
    const list = document.getElementById('outlets-list');
    if (!outlets.length) {
        list.innerHTML = '<div class="text-center text-muted py-5">No outlets found.</div>';
        return;
    }
    list.innerHTML = outlets.map(o => `
        <div class="card-clean mb-2 p-3 d-flex gap-3 align-items-center" onclick="openOutlet(${o.id})" style="cursor:pointer;">
            <div style="width:52px;height:52px;border-radius:12px;background:#f0f2f7;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">🏪</div>
            <div class="flex-grow-1">
                <div class="fw-semibold small">${o.name}</div>
                <div class="text-muted" style="font-size:.78rem;">${o.city}, ${o.state}</div>
                ${o.distance_km ? `<div class="outlet-distance">${o.distance_km} km away</div>` : ''}
            </div>
            <span class="badge bg-${o.status==='active'?'success':'warning'} small">${o.status==='active'?'Open':'Closed'}</span>
        </div>`).join('');
}

function openOutlet(id) {
    const o = allOutlets.find(x => x.id === id);
    if (!o) return;
    document.getElementById('outlet-modal-name').textContent = o.name;

    const daysOfWeek = ['mon','tue','wed','thu','fri','sat','sun'];
    const dayNames   = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    const hours = o.opening_hours || {};
    const todayKey = daysOfWeek[new Date().getDay() === 0 ? 6 : new Date().getDay() - 1];

    const hoursHtml = daysOfWeek.map((d,i) =>
        `<div class="d-flex justify-content-between small ${d===todayKey?'fw-bold text-success':'text-muted'}">
            <span>${dayNames[i]}</span>
            <span>${hours[d] ? hours[d].replace('-',' – ') : 'Closed'}</span>
         </div>`
    ).join('');

    document.getElementById('outlet-modal-body').innerHTML = `
        <div class="mb-2 small text-muted"><i class="bi bi-geo-alt me-1"></i>${o.address}, ${o.city}, ${o.state} ${o.postcode||''}</div>
        ${o.phone ? `<div class="mb-2 small"><i class="bi bi-telephone me-1"></i><a href="tel:${o.phone}">${o.phone}</a></div>` : ''}
        <hr class="my-2">
        <div class="fw-semibold small mb-1">Opening Hours</div>
        ${hoursHtml}`;

    const mapsLink = document.getElementById('outlet-maps-link');
    mapsLink.href = o.lat && o.lng ? `https://maps.google.com/?q=${o.lat},${o.lng}` : `https://maps.google.com/?q=${encodeURIComponent(o.name + ' ' + o.city)}`;

    outletModal.show();
}

async function loadOutlets() {
    const res = await apiCall('outlets/list');
    if (res.status !== 'success') return;
    allOutlets = res.data.outlets;
    renderOutlets(allOutlets);
}

document.getElementById('outlet-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    renderOutlets(allOutlets.filter(o =>
        o.name.toLowerCase().includes(q) ||
        o.city.toLowerCase().includes(q) ||
        o.address.toLowerCase().includes(q)
    ));
});

document.getElementById('btn-nearby').onclick = () => {
    if (!navigator.geolocation) { showToast('Geolocation not supported.', 'error'); return; }
    const btn = document.getElementById('btn-nearby');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Locating…';

    navigator.geolocation.getCurrentPosition(async pos => {
        const { latitude: lat, longitude: lng } = pos.coords;
        const res = await apiCall(`outlets/nearby?lat=${lat}&lng=${lng}&radius=20`);
        if (res.status === 'success') {
            allOutlets = res.data.outlets;
            renderOutlets(allOutlets);
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt-fill me-1"></i>Find Near Me';
    }, () => {
        showToast('Could not get your location.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt-fill me-1"></i>Find Near Me';
    });
};

loadOutlets();
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

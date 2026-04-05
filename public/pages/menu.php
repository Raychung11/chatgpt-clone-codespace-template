<?php
/**
 * Customer PWA – Menu Browse
 * /public/pages/menu.php
 *
 * Fetches the full menu via /api/menu/categories-with-items.
 * Items can be added to cart (stored in localStorage as fnb_cart).
 * Requires login; cart persists across page loads.
 */

$appTitle   = 'Menu – F&B Loyalty';
$showHeader = true;
$pageHeader = 'Menu';
$headerBack = '/app/dashboard';

// Cart count badge injected from JS
$headerRight = '<a href="/app/cart" class="text-white position-relative" id="cart-btn" style="display:none;">
    <i class="bi bi-bag-fill" style="font-size:1.3rem;"></i>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count" style="font-size:.6rem;display:none;"></span>
</a>';

require BASE_PATH . '/public/layout/app_shell.php';
?>

<style>
/* Category pills */
.cat-pills { display:flex; gap:.5rem; overflow-x:auto; padding-bottom:.5rem; scrollbar-width:none; }
.cat-pills::-webkit-scrollbar { display:none; }
.cat-pill {
    white-space:nowrap; padding:.4rem 1rem; border-radius:20px; font-size:.82rem;
    border:1.5px solid #dee2e6; background:#fff; color:#444; cursor:pointer; transition:.15s;
    flex-shrink:0;
}
.cat-pill.active { background:#e94560; border-color:#e94560; color:#fff; font-weight:600; }

/* Menu item card */
.menu-card {
    background:#fff; border-radius:14px; overflow:hidden; display:flex;
    gap:0; margin-bottom:.75rem; box-shadow:0 1px 6px rgba(0,0,0,.06);
}
.menu-card-img {
    width:90px; min-height:90px; object-fit:cover; flex-shrink:0;
    background:#f4f6fb; display:flex; align-items:center; justify-content:center;
    font-size:2rem; color:#ccc;
}
.menu-card-img img { width:90px; height:90px; object-fit:cover; }
.menu-card-body { padding:.75rem; flex:1; min-width:0; }
.menu-card-name { font-weight:600; font-size:.92rem; margin-bottom:.2rem; }
.menu-card-desc { font-size:.76rem; color:#888; margin-bottom:.4rem;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.menu-card-price { font-weight:700; color:#e94560; font-size:.95rem; }
.add-btn {
    border:none; background:#e94560; color:#fff; border-radius:8px;
    padding:.25rem .65rem; font-size:1rem; line-height:1; cursor:pointer; transition:.15s;
    align-self:flex-end; flex-shrink:0; margin:.75rem .75rem .75rem 0;
}
.add-btn:hover { background:#c73652; }
.add-btn.in-cart { background:#198754; }

/* Featured badge */
.featured-ribbon {
    font-size:.65rem; background:#ffc107; color:#333; font-weight:700;
    padding:.1rem .45rem; border-radius:20px; margin-left:.4rem;
}

/* Sticky category header */
.category-header {
    position:sticky; top:56px; background:#f4f6fb; z-index:10;
    padding:.5rem 0 .3rem; margin-bottom:.5rem;
    border-bottom:1.5px solid #e3e8f0; font-weight:700; font-size:.95rem; color:#333;
}
</style>

<!-- Outlet selector -->
<div class="mb-3">
    <select class="form-select form-select-sm" id="outlet-select" onchange="loadMenu()">
        <option value="">Loading outlets…</option>
    </select>
</div>

<!-- Category filter pills -->
<div class="cat-pills mb-3" id="cat-pills">
    <div class="skeleton" style="width:80px;height:34px;border-radius:20px;flex-shrink:0;"></div>
    <div class="skeleton" style="width:100px;height:34px;border-radius:20px;flex-shrink:0;"></div>
    <div class="skeleton" style="width:70px;height:34px;border-radius:20px;flex-shrink:0;"></div>
</div>

<!-- Menu items list -->
<div id="menu-list">
    <?php for($i=0;$i<4;$i++): ?>
    <div class="skeleton mb-2" style="height:90px;border-radius:14px;"></div>
    <?php endfor; ?>
</div>

<!-- Item detail + add-to-cart bottom sheet -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="itemCanvas"
     style="height:auto;max-height:85vh;border-radius:24px 24px 0 0;">
    <div class="offcanvas-header border-0 pb-0">
        <h6 class="offcanvas-title fw-bold" id="item-canvas-title"></h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="item-canvas-body"></div>
</div>

<script>
// ─── Cart helpers (localStorage) ──────────────────────────────────────────────
function getCart() {
    try { return JSON.parse(localStorage.getItem('fnb_cart') || '[]'); } catch { return []; }
}
function saveCart(cart) {
    localStorage.setItem('fnb_cart', JSON.stringify(cart));
    updateCartUI();
}
function cartTotal() { return getCart().reduce((s, i) => s + i.qty, 0); }
function updateCartUI() {
    const n = cartTotal();
    const btn   = document.getElementById('cart-btn');
    const badge = document.getElementById('cart-count');
    if (btn)   btn.style.display = n > 0 ? '' : 'none';
    if (badge) { badge.textContent = n; badge.style.display = n > 0 ? '' : 'none'; }
    // Update add-buttons in DOM
    document.querySelectorAll('[data-item-id]').forEach(el => {
        const id   = parseInt(el.dataset.itemId);
        const inC  = getCart().some(c => c.id === id);
        el.classList.toggle('in-cart', inC);
        el.innerHTML = inC ? '<i class="bi bi-check-lg"></i>' : '+';
    });
}

function addToCart(item) {
    const cart = getCart();
    const idx  = cart.findIndex(c => c.id === item.id);
    if (idx >= 0) {
        cart[idx].qty += 1;
    } else {
        cart.push({ ...item, qty: 1 });
    }
    saveCart(cart);
    showToast(`${item.name} added to cart`, 'success');
}

// ─── State ─────────────────────────────────────────────────────────────────
let fullMenu = [];      // [{id, name, items:[...]}]
let activeCat = null;   // null = all

// ─── Boot ──────────────────────────────────────────────────────────────────
if (!localStorage.getItem('fnb_token')) {
    window.location.href = '/app/login';
} else {
    loadOutlets();
    updateCartUI();
}

// ─── Outlets ───────────────────────────────────────────────────────────────
async function loadOutlets() {
    const res = await apiCall('outlets/list');
    const sel = document.getElementById('outlet-select');
    if (res.status !== 'success' || !res.data.outlets.length) {
        sel.innerHTML = '<option value="">No outlets available</option>';
        return;
    }
    // Prefer the customer's preferred outlet
    const profile = JSON.parse(localStorage.getItem('fnb_profile') || '{}');
    const prefId  = profile.preferred_outlet_id || null;

    sel.innerHTML = '<option value="">All Outlets</option>' +
        res.data.outlets.map(o =>
            `<option value="${o.id}" ${o.id == prefId ? 'selected' : ''}>${escHtml(o.name)}</option>`
        ).join('');

    loadMenu();
}

// ─── Menu ──────────────────────────────────────────────────────────────────
async function loadMenu() {
    const outletId = document.getElementById('outlet-select').value || '';
    document.getElementById('menu-list').innerHTML =
        [1,2,3].map(() => '<div class="skeleton mb-2" style="height:90px;border-radius:14px;"></div>').join('');
    document.getElementById('cat-pills').innerHTML =
        [80,100,70].map(w => `<div class="skeleton" style="width:${w}px;height:34px;border-radius:20px;flex-shrink:0;"></div>`).join('');

    const res = await apiCall('menu/categories-with-items' + (outletId ? `?outlet_id=${outletId}` : ''));

    if (res.status !== 'success') {
        document.getElementById('menu-list').innerHTML =
            '<div class="text-center text-muted py-4 small">Failed to load menu.</div>';
        return;
    }

    fullMenu  = res.data.menu || [];
    activeCat = null;
    renderCategoryPills();
    renderMenu();
}

function renderCategoryPills() {
    const pills = document.getElementById('cat-pills');
    if (!fullMenu.length) { pills.innerHTML = ''; return; }

    pills.innerHTML = `<button class="cat-pill active" onclick="filterCat(null, this)">All</button>` +
        fullMenu.map(cat =>
            `<button class="cat-pill" onclick="filterCat(${cat.id}, this)">${escHtml(cat.name)}</button>`
        ).join('');
}

function filterCat(catId, btn) {
    activeCat = catId;
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    renderMenu();
    // scroll to category
    if (catId) {
        const el = document.getElementById('cat-section-' + catId);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function renderMenu() {
    const list = document.getElementById('menu-list');

    const categoriesToShow = activeCat
        ? fullMenu.filter(c => c.id === activeCat)
        : fullMenu;

    if (!categoriesToShow.length) {
        list.innerHTML = '<div class="text-center py-5 text-muted small">No menu items available.</div>';
        return;
    }

    list.innerHTML = categoriesToShow.map(cat => `
        <div id="cat-section-${cat.id}">
            <div class="category-header">${escHtml(cat.name)}</div>
            ${cat.items.map(item => menuCard(item)).join('')}
        </div>
    `).join('');

    updateCartUI();
}

function menuCard(item) {
    const imgHtml = item.image_url
        ? `<div class="menu-card-img"><img src="${escHtml(item.image_url)}" alt="${escHtml(item.name)}" loading="lazy" onerror="this.parentNode.innerHTML='🍽️';"></div>`
        : `<div class="menu-card-img">🍽️</div>`;

    return `
    <div class="menu-card" onclick="showItemDetail(${item.id})">
        ${imgHtml}
        <div class="menu-card-body">
            <div class="menu-card-name">
                ${escHtml(item.name)}
                ${item.is_featured ? '<span class="featured-ribbon">⭐ Popular</span>' : ''}
            </div>
            ${item.description ? `<div class="menu-card-desc">${escHtml(item.description)}</div>` : ''}
            <div class="menu-card-price">MYR ${parseFloat(item.price).toFixed(2)}</div>
        </div>
        <button class="add-btn" data-item-id="${item.id}"
                onclick="event.stopPropagation();quickAdd(${JSON.stringify(JSON.stringify({id:item.id,name:item.name,price:parseFloat(item.price),image_url:item.image_url||''}))})"
                >+</button>
    </div>`;
}

// Quick-add without opening detail sheet
function quickAdd(itemJson) {
    addToCart(JSON.parse(itemJson));
}

// ─── Item Detail Offcanvas ─────────────────────────────────────────────────
let itemCanvas = null;

function showItemDetail(itemId) {
    if (!itemCanvas) itemCanvas = new bootstrap.Offcanvas(document.getElementById('itemCanvas'));
    const item = fullMenu.flatMap(c => c.items).find(i => i.id === itemId);
    if (!item) return;

    document.getElementById('item-canvas-title').textContent = item.name;
    document.getElementById('item-canvas-body').innerHTML = `
        ${item.image_url ? `<img src="${escHtml(item.image_url)}" class="w-100 rounded-3 mb-3" style="max-height:200px;object-fit:cover;" onerror="this.remove();">` : ''}
        ${item.description ? `<p class="text-muted small mb-3">${escHtml(item.description)}</p>` : ''}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="fw-bold fs-5 text-danger">MYR ${parseFloat(item.price).toFixed(2)}</div>
            ${item.is_featured ? '<span class="badge bg-warning text-dark">⭐ Popular</span>' : ''}
        </div>
        <div class="d-flex align-items-center gap-3 mb-3" id="qty-row">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm rounded-circle" style="width:34px;height:34px;"
                        onclick="changeQty(-1)" id="qty-minus">–</button>
                <span class="fw-bold fs-5" id="qty-val">1</span>
                <button class="btn btn-outline-secondary btn-sm rounded-circle" style="width:34px;height:34px;"
                        onclick="changeQty(1)">+</button>
            </div>
            <button class="btn btn-primary flex-grow-1 fw-semibold" style="border-radius:12px;"
                    onclick="addFromSheet(${item.id}, '${escHtml(item.name).replace(/'/g,"\\'")}', ${parseFloat(item.price)}, '${escHtml(item.image_url||'')}')">
                Add to Cart &mdash; <span id="sheet-total">MYR ${parseFloat(item.price).toFixed(2)}</span>
            </button>
        </div>`;
    itemCanvas.show();
    window._sheetItem = { ...item, _qty: 1 };
}

function changeQty(delta) {
    const item = window._sheetItem;
    item._qty  = Math.max(1, item._qty + delta);
    document.getElementById('qty-val').textContent   = item._qty;
    document.getElementById('sheet-total').textContent = 'MYR ' + (item.price * item._qty).toFixed(2);
    document.getElementById('qty-minus').disabled = item._qty <= 1;
}

function addFromSheet(id, name, price, imageUrl) {
    const qty = window._sheetItem._qty || 1;
    const cart = getCart();
    const idx  = cart.findIndex(c => c.id === id);
    if (idx >= 0) cart[idx].qty += qty;
    else cart.push({ id, name, price, image_url: imageUrl, qty });
    saveCart(cart);
    showToast(`${qty}× ${name} added to cart`, 'success');
    itemCanvas.hide();
}

// ─── Utilities ─────────────────────────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

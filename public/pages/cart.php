<?php
/**
 * Customer PWA – Cart & Checkout
 * /public/pages/cart.php
 *
 * Reads cart from localStorage (fnb_cart).
 * Submits order via POST /api/orders/create.
 * Requires login.
 */

$appTitle   = 'Cart – F&B Loyalty';
$showHeader = true;
$pageHeader = 'Your Cart';
$headerBack = '/app/menu';

require BASE_PATH . '/public/layout/app_shell.php';
?>

<style>
.cart-item {
    background:#fff; border-radius:14px; padding:.85rem 1rem;
    margin-bottom:.65rem; display:flex; align-items:center; gap:.85rem;
    box-shadow:0 1px 6px rgba(0,0,0,.06);
}
.cart-item-img {
    width:56px; height:56px; border-radius:10px; object-fit:cover;
    background:#f4f6fb; flex-shrink:0; display:flex; align-items:center;
    justify-content:center; font-size:1.5rem; overflow:hidden;
}
.cart-item-img img { width:56px; height:56px; object-fit:cover; }
.qty-ctrl { display:flex; align-items:center; gap:.5rem; }
.qty-btn {
    border:1.5px solid #dee2e6; background:#fff; border-radius:8px;
    width:28px; height:28px; display:flex; align-items:center; justify-content:center;
    cursor:pointer; font-size:1rem; color:#444; line-height:1;
}
.qty-btn:hover { background:#f4f6fb; }
.qty-num { font-weight:700; min-width:22px; text-align:center; }

.order-summary {
    background:#fff; border-radius:16px; padding:1.1rem 1.2rem;
    box-shadow:0 1px 6px rgba(0,0,0,.06); margin-bottom:1rem;
}
.summary-row { display:flex; justify-content:space-between; font-size:.9rem; margin-bottom:.4rem; }
.summary-row.total { font-weight:700; font-size:1.05rem; border-top:1.5px solid #e3e8f0; padding-top:.5rem; margin-top:.3rem; }

.empty-state { text-align:center; padding:4rem 1rem; }
.empty-state .icon { font-size:4rem; margin-bottom:1rem; }

.checkout-bar {
    position:fixed; bottom:0; left:0; right:0;
    background:#fff; padding:1rem 1.2rem calc(1rem + env(safe-area-inset-bottom));
    box-shadow:0 -2px 12px rgba(0,0,0,.08); z-index:100;
}
</style>

<!-- Outlet + Order type selectors (shown above items) -->
<div id="cart-options" style="display:none;" class="mb-3">
    <div class="row g-2 mb-2">
        <div class="col-7">
            <label class="form-label small fw-semibold mb-1">Outlet *</label>
            <select class="form-select form-select-sm" id="checkout-outlet">
                <option value="">Loading…</option>
            </select>
        </div>
        <div class="col-5">
            <label class="form-label small fw-semibold mb-1">Order Type</label>
            <select class="form-select form-select-sm" id="checkout-type">
                <option value="dine_in">Dine-in</option>
                <option value="takeaway">Takeaway</option>
                <option value="delivery">Delivery</option>
            </select>
        </div>
    </div>
    <div id="table-row" class="mb-2">
        <label class="form-label small fw-semibold mb-1">Table No. <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" class="form-control form-control-sm" id="checkout-table" placeholder="e.g. A12" maxlength="20">
    </div>
    <div class="mb-2">
        <label class="form-label small fw-semibold mb-1">Notes <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" class="form-control form-control-sm" id="checkout-notes" placeholder="Allergies, special requests…" maxlength="255">
    </div>
</div>

<!-- Cart items -->
<div id="cart-list"></div>

<!-- Points redemption -->
<div id="points-row" style="display:none;" class="card-clean p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="small fw-semibold">Use Loyalty Points</div>
        <span class="badge bg-warning text-dark" id="points-available-badge"></span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <input type="range" class="form-range flex-grow-1" id="points-slider"
               min="0" step="10" value="0" oninput="updatePointsRedemption(this.value)">
        <span class="small fw-bold text-danger" id="points-selected-label" style="min-width:60px;text-align:right;">0 pts</span>
    </div>
    <div class="small text-muted mt-1">100 pts = MYR 1.00 discount · <span id="pts-discount-label">Saving MYR 0.00</span></div>
</div>

<!-- Order Summary -->
<div id="order-summary" style="display:none;" class="order-summary">
    <div class="summary-row"><span class="text-muted">Subtotal</span><span id="sum-subtotal">MYR 0.00</span></div>
    <div class="summary-row"><span class="text-muted">Tax (6%)</span><span id="sum-tax">MYR 0.00</span></div>
    <div class="summary-row text-success" id="sum-discount-row" style="display:none!important;">
        <span>Points Discount</span><span id="sum-discount">–MYR 0.00</span>
    </div>
    <div class="summary-row total"><span>Total</span><span id="sum-total">MYR 0.00</span></div>
    <div class="small text-success mt-2" id="sum-points-earn" style="display:none;"></div>
</div>

<!-- Spacer for fixed checkout bar -->
<div style="height:90px;"></div>

<!-- Fixed checkout bar -->
<div class="checkout-bar" id="checkout-bar" style="display:none;">
    <button class="btn btn-primary w-100 fw-semibold py-3" style="border-radius:14px;font-size:1.05rem;"
            onclick="placeOrder()" id="place-order-btn">
        Place Order
    </button>
</div>

<!-- Success modal -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 text-center p-4">
            <div style="font-size:3.5rem;margin-bottom:.75rem;">🎉</div>
            <h5 class="fw-bold mb-1">Order Placed!</h5>
            <p class="text-muted small mb-1" id="success-order-no"></p>
            <p class="text-success small fw-semibold mb-3" id="success-points"></p>
            <div class="d-flex flex-column gap-2">
                <a href="/app/orders" class="btn btn-primary" style="border-radius:12px;">View My Orders</a>
                <a href="/app/menu"   class="btn btn-outline-secondary" style="border-radius:12px;">Order More</a>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

<script>
if (!localStorage.getItem('fnb_token')) window.location.href = '/app/login';

const TAX_RATE = 0.06;
let pointsAvailable = 0;
let pointsToUse     = 0;

// ─── Boot ─────────────────────────────────────────────────────────────────
(async function init() {
    renderCart();
    await loadOutlets();
    await loadProfile();
})();

// ─── Cart helpers ─────────────────────────────────────────────────────────
function getCart() {
    try { return JSON.parse(localStorage.getItem('fnb_cart') || '[]'); } catch { return []; }
}
function saveCart(c) { localStorage.setItem('fnb_cart', JSON.stringify(c)); }

function updateQty(idx, delta) {
    const cart = getCart();
    cart[idx].qty = Math.max(0, cart[idx].qty + delta);
    if (cart[idx].qty === 0) cart.splice(idx, 1);
    saveCart(cart);
    renderCart();
}

function renderCart() {
    const cart = getCart();
    const list = document.getElementById('cart-list');

    if (!cart.length) {
        list.innerHTML = `
            <div class="empty-state">
                <div class="icon">🛒</div>
                <div class="fw-semibold mb-2">Your cart is empty</div>
                <div class="text-muted small mb-4">Browse the menu and add some items!</div>
                <a href="/app/menu" class="btn btn-primary px-4" style="border-radius:12px;">Go to Menu</a>
            </div>`;
        document.getElementById('cart-options').style.display   = 'none';
        document.getElementById('order-summary').style.display  = 'none';
        document.getElementById('checkout-bar').style.display   = 'none';
        document.getElementById('points-row').style.display     = 'none';
        return;
    }

    list.innerHTML = cart.map((item, idx) => {
        const imgHtml = item.image_url
            ? `<div class="cart-item-img"><img src="${escHtml(item.image_url)}" onerror="this.parentNode.innerHTML='🍽️'"></div>`
            : `<div class="cart-item-img">🍽️</div>`;
        return `
        <div class="cart-item">
            ${imgHtml}
            <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold small">${escHtml(item.name)}</div>
                <div class="text-danger fw-bold small">MYR ${parseFloat(item.price).toFixed(2)} each</div>
            </div>
            <div class="qty-ctrl">
                <button class="qty-btn" onclick="updateQty(${idx}, -1)">–</button>
                <span class="qty-num">${item.qty}</span>
                <button class="qty-btn" onclick="updateQty(${idx}, 1)">+</button>
            </div>
            <div class="fw-bold small ms-1" style="min-width:60px;text-align:right;">
                MYR ${(item.price * item.qty).toFixed(2)}
            </div>
        </div>`;
    }).join('');

    document.getElementById('cart-options').style.display  = '';
    document.getElementById('order-summary').style.display = '';
    document.getElementById('checkout-bar').style.display  = '';
    if (pointsAvailable > 0) document.getElementById('points-row').style.display = '';

    updateSummary();
}

function updateSummary() {
    const cart = getCart();
    const subtotal  = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const tax       = subtotal * TAX_RATE;
    const discount  = Math.min(pointsToUse * 0.01, subtotal + tax);
    const total     = Math.max(0, subtotal + tax - discount);
    const ptsEarned = Math.floor(total); // 1 pt per MYR 1

    document.getElementById('sum-subtotal').textContent = 'MYR ' + subtotal.toFixed(2);
    document.getElementById('sum-tax').textContent      = 'MYR ' + tax.toFixed(2);
    document.getElementById('sum-total').textContent    = 'MYR ' + total.toFixed(2);

    const discRow = document.getElementById('sum-discount-row');
    if (discount > 0) {
        discRow.style.removeProperty('display');
        document.getElementById('sum-discount').textContent = '–MYR ' + discount.toFixed(2);
    } else {
        discRow.style.display = 'none';
    }

    const earnEl = document.getElementById('sum-points-earn');
    if (ptsEarned > 0) {
        earnEl.textContent = `✨ You'll earn +${ptsEarned} loyalty points`;
        earnEl.style.display = '';
    } else {
        earnEl.style.display = 'none';
    }

    // Update place-order button label
    document.getElementById('place-order-btn').textContent = `Place Order · MYR ${total.toFixed(2)}`;
}

// ─── Points redemption ────────────────────────────────────────────────────
function updatePointsRedemption(val) {
    pointsToUse = parseInt(val) || 0;
    document.getElementById('points-selected-label').textContent = pointsToUse + ' pts';
    document.getElementById('pts-discount-label').textContent = 'Saving MYR ' + (pointsToUse * 0.01).toFixed(2);
    updateSummary();
}

async function loadProfile() {
    const res = await apiCall('customers/profile');
    if (res.status !== 'success') return;
    const p = res.data.profile;
    pointsAvailable = parseInt(p.total_points) || 0;

    // Cache for menu page
    localStorage.setItem('fnb_profile', JSON.stringify(p));

    if (pointsAvailable > 0) {
        const slider = document.getElementById('points-slider');
        slider.max   = pointsAvailable;
        document.getElementById('points-available-badge').textContent = `${pointsAvailable} pts available`;
        if (getCart().length) document.getElementById('points-row').style.display = '';
    }
}

// ─── Outlets ──────────────────────────────────────────────────────────────
async function loadOutlets() {
    const res = await apiCall('outlets/list');
    const sel = document.getElementById('checkout-outlet');
    if (res.status !== 'success' || !res.data.outlets.length) {
        sel.innerHTML = '<option value="">No outlets</option>';
        return;
    }
    const profile = JSON.parse(localStorage.getItem('fnb_profile') || '{}');
    const prefId  = profile.preferred_outlet_id || null;
    sel.innerHTML = res.data.outlets.map(o =>
        `<option value="${o.id}" ${o.id == prefId ? 'selected' : ''}>${escHtml(o.name)}</option>`
    ).join('');
}

// Show/hide table field based on order type
document.getElementById('checkout-type').addEventListener('change', function() {
    document.getElementById('table-row').style.display = this.value === 'dine_in' ? '' : 'none';
});

// ─── Place order ──────────────────────────────────────────────────────────
async function placeOrder() {
    const cart = getCart();
    if (!cart.length) { showToast('Cart is empty.', 'error'); return; }

    const outletId = parseInt(document.getElementById('checkout-outlet').value);
    if (!outletId) { showToast('Please select an outlet.', 'error'); return; }

    const orderType = document.getElementById('checkout-type').value;
    const tableNo   = document.getElementById('checkout-table').value.trim();
    const notes     = document.getElementById('checkout-notes').value.trim();

    const btn = document.getElementById('place-order-btn');
    btn.disabled    = true;
    btn.textContent = 'Placing order…';

    const payload = {
        outlet_id:   outletId,
        order_type:  orderType,
        table_no:    tableNo  || null,
        notes:       notes    || null,
        points_used: pointsToUse,
        items: cart.map(i => ({ menu_item_id: i.id, qty: i.qty })),
    };

    try {
        const res = await apiCall('orders/create', 'POST', payload);
        if (res.status !== 'success') {
            showToast(res.message || 'Order failed.', 'error');
            btn.disabled    = false;
            btn.textContent = 'Place Order';
            return;
        }

        // Clear cart
        localStorage.removeItem('fnb_cart');

        // Show success
        document.getElementById('success-order-no').textContent = 'Order ' + res.data.order_no;
        const pe = res.data.points_earned;
        document.getElementById('success-points').textContent =
            pe > 0 ? `+${pe} loyalty points earned!` : '';
        new bootstrap.Modal(document.getElementById('successModal')).show();

    } catch (e) {
        showToast('Network error. Please try again.', 'error');
        btn.disabled    = false;
        btn.textContent = 'Place Order';
    }
}

// ─── Utilities ────────────────────────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

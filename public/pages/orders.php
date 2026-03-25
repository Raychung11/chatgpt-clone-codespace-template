<?php
/**
 * Customer – My Orders
 * /public/pages/orders.php
 */

$appTitle   = 'My Orders – F&B Loyalty';
$showHeader = true;
$pageHeader = 'My Orders';
$headerBack = '/app/profile';
require BASE_PATH . '/public/layout/app_shell.php';
?>

<div id="orders-list">
    <?php for($i=0;$i<4;$i++): ?>
    <div class="skeleton mb-2" style="height:88px;border-radius:14px;"></div>
    <?php endfor; ?>
</div>

<!-- Order Detail Offcanvas -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="orderCanvas" style="height:85vh;border-radius:24px 24px 0 0;">
    <div class="offcanvas-header border-0">
        <h6 class="offcanvas-title fw-bold" id="order-canvas-title">Order Details</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="order-canvas-body"></div>
</div>

<script>
const orderCanvas = new bootstrap.Offcanvas(document.getElementById('orderCanvas'));
const statusColors = {pending:'warning',confirmed:'primary',preparing:'info',ready:'success',completed:'success',cancelled:'danger'};

async function loadOrders() {
    const res = await apiCall('orders/my');
    const list = document.getElementById('orders-list');

    if (res.status !== 'success' || !res.data.orders.length) {
        list.innerHTML = `
            <div class="text-center py-5">
                <div style="font-size:3rem;">🛍️</div>
                <div class="text-muted mt-2 small">No orders yet.</div>
            </div>`;
        return;
    }

    list.innerHTML = res.data.orders.map(o => `
        <div class="card-clean mb-2 p-3 d-flex gap-3 align-items-center" onclick="viewOrder(${o.id})" style="cursor:pointer;">
            <div class="list-item-icon" style="background:rgba(13,110,253,.1);color:#0d6efd;font-size:1.2rem;">🧾</div>
            <div class="flex-grow-1">
                <div class="fw-semibold small">${o.outlet_name}</div>
                <div class="text-muted" style="font-size:.78rem;">
                    <code>${o.order_no}</code> · ${new Date(o.created_at).toLocaleDateString('en-MY',{day:'numeric',month:'short',year:'numeric'})}
                </div>
                ${o.points_earned > 0 ? `<div class="text-success" style="font-size:.73rem;">+${o.points_earned} pts earned</div>` : ''}
            </div>
            <div class="text-end">
                <div class="fw-bold small">MYR ${parseFloat(o.total).toFixed(2)}</div>
                <span class="badge bg-${statusColors[o.status]||'secondary'}" style="font-size:.68rem;">${o.status}</span>
            </div>
        </div>`).join('');
}

async function viewOrder(id) {
    document.getElementById('order-canvas-body').innerHTML = '<div class="text-center py-4 text-muted small">Loading…</div>';
    orderCanvas.show();

    const res = await apiCall(`orders/detail?id=${id}`);
    if (res.status !== 'success') {
        document.getElementById('order-canvas-body').innerHTML = '<div class="text-center text-muted py-4">Failed to load order.</div>';
        return;
    }
    const o = res.data.order;
    document.getElementById('order-canvas-title').textContent = o.order_no;

    document.getElementById('order-canvas-body').innerHTML = `
        <div class="d-flex justify-content-between mb-3">
            <div>
                <div class="small text-muted">Outlet</div>
                <div class="fw-semibold">${o.outlet_name}</div>
            </div>
            <span class="badge bg-${statusColors[o.status]||'secondary'}">${o.status}</span>
        </div>

        <div class="card-clean mb-3">
            ${o.items.map(item => `
            <div class="list-item-clean">
                <div class="flex-grow-1 small">${item.name}</div>
                <div class="text-muted small">x${item.qty}</div>
                <div class="small fw-semibold">MYR ${parseFloat(item.subtotal).toFixed(2)}</div>
            </div>`).join('')}
        </div>

        <div class="card-clean p-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Subtotal</span><span>MYR ${parseFloat(o.subtotal).toFixed(2)}</span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Tax (6%)</span><span>MYR ${parseFloat(o.tax).toFixed(2)}</span>
            </div>
            ${o.discount > 0 ? `<div class="d-flex justify-content-between small text-success mb-1">
                <span>Discount</span><span>-MYR ${parseFloat(o.discount).toFixed(2)}</span>
            </div>` : ''}
            <hr class="my-2">
            <div class="d-flex justify-content-between fw-bold">
                <span>Total</span><span>MYR ${parseFloat(o.total).toFixed(2)}</span>
            </div>
        </div>

        ${o.points_earned > 0 ? `<div class="text-center text-success small mt-3 fw-semibold">✨ You earned +${o.points_earned} points for this order</div>` : ''}`;
}

if (!localStorage.getItem('fnb_token')) { window.location.href = '/app/login'; }
else { loadOrders(); }
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

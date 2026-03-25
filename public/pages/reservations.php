<?php
/**
 * Customer – Reservations Page
 * /public/pages/reservations.php
 */

$appTitle   = 'Reservations – F&B Loyalty';
$showHeader = true;
$pageHeader = 'Book a Table';
require BASE_PATH . '/public/layout/app_shell.php';
?>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><button class="nav-link active small" onclick="showTab('book')">New Booking</button></li>
    <li class="nav-item"><button class="nav-link small" onclick="showTab('my')">My Bookings</button></li>
</ul>

<!-- New Booking Form -->
<div id="tab-book">
    <div class="card-clean p-3 mb-3">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold small">Outlet *</label>
                <select id="res-outlet" class="form-select form-control-app">
                    <option value="">Select an outlet…</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold small">Date *</label>
                <input type="date" id="res-date" class="form-control form-control-app"
                       min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
                <label class="form-label fw-semibold small">Guests *</label>
                <select id="res-pax" class="form-select form-control-app">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?= $i ?>" <?= $i===2?'selected':'' ?>><?= $i ?> <?= $i===1?'person':'people' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Time Slots -->
            <div class="col-12">
                <label class="form-label fw-semibold small">Time *</label>
                <div id="time-slots" class="d-flex flex-wrap gap-2">
                    <div class="text-muted small">Select outlet and date first.</div>
                </div>
                <input type="hidden" id="res-time" value="">
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold small">Occasion (optional)</label>
                <select id="res-occasion" class="form-select form-control-app">
                    <option value="">None</option>
                    <option>Birthday</option>
                    <option>Anniversary</option>
                    <option>Business Dinner</option>
                    <option>Date Night</option>
                    <option>Family Gathering</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Special Request (optional)</label>
                <textarea id="res-request" class="form-control form-control-app" rows="2"
                          placeholder="Dietary requirements, seating preference…"></textarea>
            </div>
        </div>
    </div>

    <button class="btn-brand" id="btn-book">
        <i class="bi bi-calendar-check me-2"></i>Confirm Booking
    </button>
</div>

<!-- My Bookings -->
<div id="tab-my" style="display:none;">
    <div id="my-reservations">
        <div class="text-center text-muted py-4 small">Loading…</div>
    </div>
</div>

<script>
let selectedTime = '';

function showTab(tab) {
    document.getElementById('tab-book').style.display = tab==='book' ? '' : 'none';
    document.getElementById('tab-my').style.display   = tab==='my'   ? '' : 'none';
    document.querySelectorAll('.nav-pills .nav-link').forEach((b,i) =>
        b.classList.toggle('active', (tab==='book'&&i===0)||(tab==='my'&&i===1)));
    if (tab === 'my') loadMyReservations();
}

async function loadOutlets() {
    const res = await apiCall('outlets/list');
    if (res.status !== 'success') return;
    const sel = document.getElementById('res-outlet');
    res.data.outlets.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id; opt.textContent = o.name;
        sel.appendChild(opt);
    });
}

async function loadSlots() {
    const outletId = document.getElementById('res-outlet').value;
    const date     = document.getElementById('res-date').value;
    const container = document.getElementById('time-slots');

    if (!outletId || !date) {
        container.innerHTML = '<div class="text-muted small">Select outlet and date first.</div>';
        return;
    }

    container.innerHTML = '<div class="text-muted small">Loading slots…</div>';
    const res = await apiCall(`reservations/availability?outlet_id=${outletId}&date=${date}`);
    if (res.status !== 'success') return;

    selectedTime = '';
    document.getElementById('res-time').value = '';

    container.innerHTML = res.data.slots.map(s => `
        <button type="button" class="btn btn-sm ${s.available ? 'btn-outline-primary' : 'btn-light text-muted'} slot-btn"
                data-time="${s.time}" ${!s.available ? 'disabled' : ''}
                onclick="selectSlot(this, '${s.time}')">
            ${s.time}
        </button>`).join('');
}

function selectSlot(btn, time) {
    document.querySelectorAll('.slot-btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline-primary');
    });
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary');
    selectedTime = time;
    document.getElementById('res-time').value = time;
}

document.getElementById('res-outlet').addEventListener('change', loadSlots);
document.getElementById('res-date').addEventListener('change', loadSlots);

document.getElementById('btn-book').onclick = async () => {
    const outletId = document.getElementById('res-outlet').value;
    const date     = document.getElementById('res-date').value;
    const pax      = document.getElementById('res-pax').value;
    const time     = selectedTime;
    const occasion = document.getElementById('res-occasion').value;
    const request  = document.getElementById('res-request').value;

    if (!outletId) { showToast('Please select an outlet.', 'error'); return; }
    if (!time)     { showToast('Please select a time slot.', 'error'); return; }

    const btn = document.getElementById('btn-book');
    btn.disabled = true; btn.textContent = 'Booking…';

    const res = await apiCall('reservations/create', 'POST', {
        outlet_id: parseInt(outletId),
        party_size: parseInt(pax),
        reserved_date: date,
        reserved_time: time + ':00',
        occasion: occasion || null,
        special_request: request || null,
    });

    if (res.status === 'success') {
        showToast(`Booked! Ref: ${res.data.reservation_no}`, 'success');
        showTab('my');
    } else {
        showToast(res.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Confirm Booking';
};

async function loadMyReservations() {
    const res = await apiCall('reservations/my');
    const container = document.getElementById('my-reservations');
    if (res.status !== 'success' || !res.data.reservations.length) {
        container.innerHTML = '<div class="text-center text-muted py-5">No reservations yet.</div>';
        return;
    }
    const statusColors = {pending:'warning',confirmed:'primary',seated:'info',completed:'success',cancelled:'danger',no_show:'secondary'};
    container.innerHTML = res.data.reservations.map(r => `
        <div class="card-clean mb-2 p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold small">${r.outlet_name}</div>
                    <div class="text-muted" style="font-size:.78rem;">
                        📅 ${r.reserved_date} &nbsp;⏰ ${r.reserved_time.slice(0,5)} &nbsp;👥 ${r.party_size} pax
                    </div>
                    <code class="small">${r.reservation_no}</code>
                </div>
                <span class="badge bg-${statusColors[r.status]||'secondary'}">${r.status}</span>
            </div>
            ${r.occasion ? `<div class="mt-1 small text-muted">🎉 ${r.occasion}</div>` : ''}
            ${r.points_earned ? `<div class="mt-1 small text-success">+${r.points_earned} pts earned</div>` : ''}
        </div>`).join('');
}

if (!localStorage.getItem('fnb_token')) { window.location.href = '/app/login'; }
else { loadOutlets(); }
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

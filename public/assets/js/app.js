/* STRate AI — Frontend JS */

// ─── Flash messages ──────────────────────────────────────────────────────────
function showFlash(message, type = 'success') {
    const colors = {
        success: 'bg-green-100 text-green-800 border-green-300',
        error:   'bg-red-100 text-red-800 border-red-300',
        info:    'bg-blue-100 text-blue-800 border-blue-300',
    };
    const div = document.createElement('div');
    div.className = `fixed top-4 right-4 z-50 border rounded-lg px-5 py-3 shadow-lg text-sm font-medium ${colors[type] || colors.info}`;
    div.textContent = message;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

// ─── Cron job runner ─────────────────────────────────────────────────────────
async function runCronJob(jobName, btn) {
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = '⏳ Running...';

    try {
        const res  = await fetch(`/api/run_cron.php?job=${encodeURIComponent(jobName)}`);
        const data = await res.json();
        showFlash(data.message, data.ok ? 'success' : 'error');
        if (data.ok) setTimeout(() => location.reload(), 1000);
    } catch (e) {
        showFlash('Request failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

// ─── Recommendations generator ────────────────────────────────────────────────
async function generateRecommendations(propertyId, btn) {
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = '⏳ Generating...';

    try {
        const res  = await fetch(`/api/generate.php?property_id=${propertyId}`);
        const data = await res.json();
        showFlash(data.message, data.ok ? 'success' : 'error');
        if (data.ok) setTimeout(() => location.reload(), 800);
    } catch (e) {
        showFlash('Request failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}

// ─── Chart helpers ────────────────────────────────────────────────────────────
function makePriceChart(canvasId, labels, avgPrices, minPrices, maxPrices) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Avg Price (RM)',
                    data: avgPrices,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79,70,229,.08)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                },
                {
                    label: 'Min (RM)',
                    data: minPrices,
                    borderColor: '#10b981',
                    borderDash: [5, 3],
                    pointRadius: 0,
                    fill: false,
                },
                {
                    label: 'Max (RM)',
                    data: maxPrices,
                    borderColor: '#ef4444',
                    borderDash: [5, 3],
                    pointRadius: 0,
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { ticks: { callback: v => 'RM' + v } },
            },
        },
    });
}

function makeOccChart(canvasId, labels, values) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Occupancy %',
                data: values,
                backgroundColor: values.map(v =>
                    v >= 80 ? '#ef4444' : v >= 60 ? '#f59e0b' : '#10b981'
                ),
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
            },
        },
    });
}

function makeRecommendationChart(canvasId, labels, suggested, basePrices) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Recommended (RM)',
                    data: suggested,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79,70,229,.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 5,
                },
                {
                    label: 'Base Price (RM)',
                    data: basePrices,
                    borderColor: '#9ca3af',
                    borderDash: [6, 3],
                    pointRadius: 0,
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { ticks: { callback: v => 'RM' + v } },
            },
        },
    });
}

// ─── Delete property confirm ───────────────────────────────────────────────────
function confirmDelete(id, name) {
    if (confirm(`Delete "${name}"? This cannot be undone.`)) {
        window.location.href = `/properties.php?delete=${id}`;
    }
}

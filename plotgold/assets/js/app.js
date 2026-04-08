/**
 * PlotGold Malaysia — Application JavaScript
 * Vanilla JS + Bootstrap 5 interactions
 */

'use strict';

// ── CSRF Token helper ───────────────────────────────────────────────────────
const PlotGold = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',

    /**
     * POST JSON with CSRF header
     */
    async post(url, data = {}) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        });
        return resp.json();
    },

    /**
     * POST FormData (file uploads)
     */
    async postForm(url, formData) {
        formData.append('_csrf_token', this.csrfToken);
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        return resp.json();
    },

    toast(message, type = 'success', duration = 4000) {
        const el = document.createElement('div');
        el.className = `alert alert-${type} position-fixed bottom-0 end-0 m-3 fade show`;
        el.style.cssText = 'z-index:9999;min-width:280px;box-shadow:var(--shadow-lg)';
        el.innerHTML = message + `<button type="button" class="btn-close float-end" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), duration);
    }
};

// ── Listing Image Gallery ────────────────────────────────────────────────────
function initGallery() {
    const mainImg = document.getElementById('galleryMain');
    const thumbs  = document.querySelectorAll('.gallery-thumb');
    if (!mainImg || !thumbs.length) return;

    thumbs.forEach(thumb => {
        thumb.addEventListener('click', () => {
            mainImg.src = thumb.dataset.full || thumb.src;
            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        });
    });
}

// ── DIY Funeral Planner Cart ─────────────────────────────────────────────────
const PlannerCart = {
    items: JSON.parse(localStorage.getItem('pg_cart') ?? '[]'),

    save() {
        localStorage.setItem('pg_cart', JSON.stringify(this.items));
    },

    add(item) {
        const existing = this.items.find(i => i.id === item.id);
        if (existing) {
            existing.qty = (existing.qty || 1) + 1;
        } else {
            this.items.push({ ...item, qty: 1 });
        }
        this.save();
        this.render();
        PlotGold.toast(`<strong>${item.name}</strong> added to your plan.`);
    },

    remove(id) {
        this.items = this.items.filter(i => i.id !== id);
        this.save();
        this.render();
    },

    updateQty(id, qty) {
        const item = this.items.find(i => i.id === id);
        if (item) {
            item.qty = Math.max(1, parseInt(qty) || 1);
            this.save();
            this.render();
        }
    },

    total() {
        return this.items.reduce((sum, i) => sum + (parseFloat(i.price) || 0) * (i.qty || 1), 0);
    },

    render() {
        const container = document.getElementById('cartItems');
        const totalEl   = document.getElementById('cartTotal');
        const countEl   = document.getElementById('cartCount');

        if (countEl) {
            countEl.textContent = this.items.reduce((n, i) => n + (i.qty || 1), 0);
            countEl.style.display = this.items.length ? '' : 'none';
        }

        if (!container) return;

        if (!this.items.length) {
            container.innerHTML = '<p class="text-muted text-center py-3 small">Your plan is empty.<br>Add services below.</p>';
            if (totalEl) totalEl.textContent = 'RM 0.00';
            return;
        }

        container.innerHTML = this.items.map(item => `
            <div class="cart-item-row d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="fw-500 small">${escHtml(item.name)}</div>
                    <div class="text-muted" style="font-size:.78rem">${escHtml(item.category ?? '')}</div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <input type="number" min="1" max="99" value="${item.qty || 1}"
                               class="form-control form-control-sm" style="width:60px"
                               onchange="PlannerCart.updateQty('${escHtml(item.id)}', this.value)">
                        <span class="small text-muted">× RM ${parseFloat(item.price || 0).toFixed(2)}</span>
                    </div>
                </div>
                <div class="text-end ms-2">
                    <div class="fw-600 small">RM ${((item.price || 0) * (item.qty || 1)).toFixed(2)}</div>
                    <button class="btn btn-link btn-sm text-danger p-0 mt-1" onclick="PlannerCart.remove('${escHtml(item.id)}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `).join('');

        if (totalEl) totalEl.textContent = 'RM ' + this.total().toFixed(2);
    },

    clear() {
        this.items = [];
        this.save();
        this.render();
    }
};

// ── Listing Favourites ───────────────────────────────────────────────────────
function toggleFavourite(listingId, btn) {
    PlotGold.post('/api/favourites.php', { listing_id: listingId, action: 'toggle' })
        .then(res => {
            if (res.success) {
                const icon = btn.querySelector('i');
                if (res.state === 'added') {
                    icon.classList.replace('far', 'fas');
                    btn.classList.add('text-danger');
                    PlotGold.toast('Saved to your favourites.');
                } else {
                    icon.classList.replace('fas', 'far');
                    btn.classList.remove('text-danger');
                }
            } else if (res.redirect) {
                window.location.href = res.redirect;
            }
        })
        .catch(() => PlotGold.toast('Something went wrong. Please try again.', 'danger'));
}

// ── Compare Listings ─────────────────────────────────────────────────────────
const Compare = {
    ids: JSON.parse(sessionStorage.getItem('pg_compare') ?? '[]'),
    max: 3,

    toggle(id, btn) {
        const idx = this.ids.indexOf(id);
        if (idx > -1) {
            this.ids.splice(idx, 1);
            btn?.classList.remove('active');
        } else {
            if (this.ids.length >= this.max) {
                PlotGold.toast(`You can compare up to ${this.max} listings at a time.`, 'warning');
                return;
            }
            this.ids.push(id);
            btn?.classList.add('active');
        }
        sessionStorage.setItem('pg_compare', JSON.stringify(this.ids));
        this.updateBar();
    },

    updateBar() {
        const bar = document.getElementById('compareBar');
        if (!bar) return;
        if (this.ids.length) {
            bar.style.display = 'block';
            bar.querySelector('#compareCount').textContent = this.ids.length;
        } else {
            bar.style.display = 'none';
        }
    },

    go() {
        if (!this.ids.length) return;
        window.location.href = '/buyer/compare.php?ids=' + this.ids.join(',');
    }
};

// ── Listing Search / Filter ──────────────────────────────────────────────────
function initSearchFilters() {
    const form = document.getElementById('searchFilterForm');
    if (!form) return;

    // Auto-submit on select change (desktop)
    form.querySelectorAll('select[data-auto-submit]').forEach(sel => {
        sel.addEventListener('change', () => form.submit());
    });

    // Price range display
    const priceRange = form.querySelector('#priceRange');
    const priceLabel = form.querySelector('#priceRangeLabel');
    if (priceRange && priceLabel) {
        priceRange.addEventListener('input', () => {
            priceLabel.textContent = 'RM ' + Number(priceRange.value).toLocaleString();
        });
    }
}

// ── Admin Confirm Dialogs ────────────────────────────────────────────────────
function confirmAction(message, form) {
    if (confirm(message)) form.submit();
}

// ── Form Validation Helpers ──────────────────────────────────────────────────
function initFormValidation() {
    document.querySelectorAll('form.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// ── Image Preview ────────────────────────────────────────────────────────────
function previewImages(input, previewContainer) {
    const container = document.getElementById(previewContainer);
    if (!container || !input.files.length) return;
    container.innerHTML = '';
    [...input.files].forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'rounded me-2 mb-2';
            img.style.cssText = 'width:80px;height:60px;object-fit:cover';
            container.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// ── Escape HTML ──────────────────────────────────────────────────────────────
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

// ── Smooth Number Counters (stats) ───────────────────────────────────────────
function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el     = entry.target;
            const target = parseInt(el.dataset.counter, 10);
            let current  = 0;
            const step   = Math.ceil(target / 60);
            const timer  = setInterval(() => {
                current = Math.min(current + step, target);
                el.textContent = current.toLocaleString();
                if (current >= target) clearInterval(timer);
            }, 16);
            observer.unobserve(el);
        });
    }, { threshold: 0.4 });

    counters.forEach(el => observer.observe(el));
}

// ── Sticky Compare Bar ───────────────────────────────────────────────────────
function initCompareBar() {
    Compare.updateBar();
}

// ── Admin Sidebar Toggle ─────────────────────────────────────────────────────
function toggleAdminSidebar() {
    document.querySelector('.admin-sidebar')?.classList.toggle('open');
}

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initGallery();
    initSearchFilters();
    initFormValidation();
    initCounters();
    initCompareBar();
    PlannerCart.render();

    // Dismiss alerts after 6s
    document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert?.close();
        }, 6000);
    });

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
        new bootstrap.Tooltip(el)
    );
});

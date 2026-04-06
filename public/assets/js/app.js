/**
 * VideoSaaS — Global JS
 * Handles: toast notifications, hamburger menu, confirm dialogs, auto-dismiss alerts.
 */

'use strict';

// ── Toast system ──────────────────────────────────────────────────────────────
const Toast = (() => {
    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function show(message, type = 'info', duration = 3500) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        getContainer().appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'opacity .3s, transform .3s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 350);
        }, duration);
    }

    return {
        success: (msg) => show(msg, 'success'),
        error:   (msg) => show(msg, 'error', 5000),
        info:    (msg) => show(msg, 'info'),
    };
})();

// ── Hamburger / mobile nav ────────────────────────────────────────────────────
function initHamburger() {
    const btn = document.getElementById('hamburgerBtn');
    const nav = document.getElementById('mobileNav');
    if (!btn || !nav) return;

    btn.addEventListener('click', () => {
        const isOpen = btn.classList.toggle('open');
        nav.classList.toggle('open', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // Close on outside click
    nav.addEventListener('click', (e) => {
        if (e.target.tagName === 'A') {
            btn.classList.remove('open');
            nav.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
}

// ── Auto-dismiss flash alerts ─────────────────────────────────────────────────
function initAlerts() {
    document.querySelectorAll('.alert--success, .alert--info').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });
}

// ── Confirm button helper ─────────────────────────────────────────────────────
// Usage: <button data-confirm="Are you sure?"> or form with data-confirm
function initConfirms() {
    document.addEventListener('submit', (e) => {
        const msg = e.target.dataset.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-confirm]');
        if (btn && btn.tagName !== 'FORM') {
            if (!confirm(btn.dataset.confirm)) e.preventDefault();
        }
    });
}

// ── Copy to clipboard helper (global) ────────────────────────────────────────
function copyToClipboard(text, feedbackEl = null) {
    navigator.clipboard.writeText(text).then(() => {
        Toast.success('Copied!');
        if (feedbackEl) {
            const orig = feedbackEl.textContent;
            feedbackEl.textContent = '✓ Copied';
            setTimeout(() => { feedbackEl.textContent = orig; }, 2000);
        }
    }).catch(() => {
        // Fallback for older browsers
        const ta = Object.assign(document.createElement('textarea'), {
            value: text, style: 'position:fixed;opacity:0'
        });
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        Toast.success('Copied!');
    });
}

// ── Init on DOM ready ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initHamburger();
    initAlerts();
    initConfirms();
});

// ─── SilverDeals MY — Global JS ──────────────────────────────────────────────

(function () {
  'use strict';

  // ── Mobile nav toggle ──────────────────────────────────────────────────────
  const nav    = document.getElementById('mainNav');
  const toggle = document.getElementById('navToggle');
  const menu   = document.getElementById('navMenu');

  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      nav.classList.toggle('menu-open');
      toggle.setAttribute('aria-expanded', nav.classList.contains('menu-open'));
    });
    // Close on outside click
    document.addEventListener('click', (e) => {
      if (nav.classList.contains('menu-open') && !nav.contains(e.target)) {
        nav.classList.remove('menu-open');
      }
    });
  }

  // ── Dashboard sidebar toggle (mobile) ─────────────────────────────────────
  const sidebarToggle = document.getElementById('sidebarToggle');
  const dashLayout    = document.querySelector('.dash-layout');
  if (sidebarToggle && dashLayout) {
    sidebarToggle.addEventListener('click', () => {
      dashLayout.classList.toggle('sidebar-open');
    });
  }

  // ── Auto-dismiss alerts after 5s ──────────────────────────────────────────
  document.querySelectorAll('.alert').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  });

  // ── Password visibility toggle ────────────────────────────────────────────
  document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.togglePassword);
      if (!target) return;
      const isText = target.type === 'text';
      target.type  = isText ? 'password' : 'text';
      btn.textContent = isText ? '👁' : '🙈';
    });
  });

  // ── Confirm dialogs for destructive actions ───────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  // ── Simple character counter ──────────────────────────────────────────────
  document.querySelectorAll('[data-max-chars]').forEach((el) => {
    const max     = parseInt(el.dataset.maxChars, 10);
    const counter = document.getElementById(el.dataset.counterTarget);
    if (!counter) return;
    const update = () => { counter.textContent = `${el.value.length}/${max}`; };
    el.addEventListener('input', update);
    update();
  });

  // ── Active bottom nav item ────────────────────────────────────────────────
  const path = window.location.pathname;
  document.querySelectorAll('.bottom-nav__item').forEach((item) => {
    if (item.getAttribute('href') && path.includes(item.getAttribute('href').replace('.php', ''))) {
      item.classList.add('active');
    }
  });

})();

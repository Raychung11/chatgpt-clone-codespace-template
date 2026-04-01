<?php
// ─── SilverDeals MY — Member Layout Footer/Close ────────────────────────────
?>
    </div><!-- /.dash-content -->
  </main>
</div><!-- /.dash-layout -->

<!-- ── Mobile Bottom Nav ──────────────────────────────────────────────────── -->
<nav class="bottom-nav">
  <div class="bottom-nav__items">
    <a href="/member/dashboard.php"       class="bottom-nav__item"><span class="bottom-nav__icon">🏠</span>Home</a>
    <a href="/member/deals.php"           class="bottom-nav__item"><span class="bottom-nav__icon">🎁</span>Deals</a>
    <a href="/member/membership_card.php" class="bottom-nav__item"><span class="bottom-nav__icon">🃏</span>Card</a>
    <a href="/member/rewards.php"         class="bottom-nav__item"><span class="bottom-nav__icon">💰</span>Rewards</a>
    <a href="/member/profile.php"         class="bottom-nav__item"><span class="bottom-nav__icon">👤</span>Profile</a>
  </div>
</nav>

<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:299;"
     onclick="document.getElementById('dashLayout').classList.remove('sidebar-open');this.style.display='none';"></div>

<script src="/assets/js/app.js"></script>
<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const overlay       = document.getElementById('sidebarOverlay');
const layout        = document.getElementById('dashLayout');
if (sidebarToggle) {
  sidebarToggle.style.display = 'flex';
  sidebarToggle.addEventListener('click', () => {
    layout.classList.toggle('sidebar-open');
    overlay.style.display = layout.classList.contains('sidebar-open') ? 'block' : 'none';
  });
}
</script>
</body>
</html>

<?php
// ─── SilverDeals MY — Admin Layout Close ────────────────────────────────────
?>
    </div><!-- /.dash-content -->
  </main>
</div><!-- /.dash-layout -->

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

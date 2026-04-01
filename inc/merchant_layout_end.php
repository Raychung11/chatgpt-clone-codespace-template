<?php // ─── Merchant Layout Close ─── ?>
    </div>
  </main>
</div>

<div id="sidebarOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:299;"
     onclick="document.getElementById('dashLayout').classList.remove('sidebar-open');this.style.display='none';"></div>
<script src="/assets/js/app.js"></script>
<script>
const t=document.getElementById('sidebarToggle'),o=document.getElementById('sidebarOverlay'),l=document.getElementById('dashLayout');
if(t){t.style.display='flex';t.addEventListener('click',()=>{l.classList.toggle('sidebar-open');o.style.display=l.classList.contains('sidebar-open')?'block':'none';});}
</script>
</body>
</html>

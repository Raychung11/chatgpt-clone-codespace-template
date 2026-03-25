<?php
/**
 * Customer – Notifications
 * /public/pages/notifications.php
 */

$appTitle   = 'Notifications – F&B Loyalty';
$showHeader = true;
$pageHeader = 'Notifications';
$headerRight = '<button onclick="markAllRead()" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size:.75rem;">Mark all read</button>';
require BASE_PATH . '/public/layout/app_shell.php';
?>

<div id="notif-list">
    <?php for($i=0;$i<4;$i++): ?>
    <div class="skeleton mb-2" style="height:72px;border-radius:14px;"></div>
    <?php endfor; ?>
</div>

<script>
const notifIcons = {
    points:      { icon:'star-fill',       color:'#ffc107', bg:'rgba(255,193,7,.1)'   },
    reward:      { icon:'gift-fill',       color:'#e94560', bg:'rgba(233,69,96,.1)'   },
    promotion:   { icon:'megaphone-fill',  color:'#0d6efd', bg:'rgba(13,110,253,.1)'  },
    reservation: { icon:'calendar-check',  color:'#6f42c1', bg:'rgba(111,66,193,.1)'  },
    order:       { icon:'receipt',         color:'#198754', bg:'rgba(25,135,84,.1)'   },
    system:      { icon:'bell-fill',       color:'#6c757d', bg:'rgba(108,117,125,.1)' },
};

async function loadNotifications() {
    const res = await apiCall('notifications/list');
    const list = document.getElementById('notif-list');

    if (res.status !== 'success' || !res.data.notifications.length) {
        list.innerHTML = `
            <div class="text-center py-5">
                <div style="font-size:3rem;">🔔</div>
                <div class="text-muted mt-2 small">No notifications yet.</div>
            </div>`;
        return;
    }

    list.innerHTML = res.data.notifications.map(n => {
        const icon = notifIcons[n.type] || notifIcons.system;
        const timeStr = new Date(n.sent_at).toLocaleString('en-MY', {
            day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'
        });
        return `
        <div class="card-clean mb-2 d-flex gap-3 align-items-start p-3 ${!n.is_read ? 'border-start border-3 border-primary' : ''}"
             onclick="markRead(${n.id}, this)" style="cursor:pointer;${!n.is_read ? 'background:#f0f7ff;' : ''}">
            <div class="list-item-icon flex-shrink-0" style="background:${icon.bg};color:${icon.color};">
                <i class="bi bi-${icon.icon}"></i>
            </div>
            <div class="flex-grow-1">
                <div class="small fw-${n.is_read?'normal':'bold'}">${n.title}</div>
                <div class="text-muted" style="font-size:.78rem;line-height:1.4;">${n.body}</div>
                <div class="text-muted" style="font-size:.72rem;margin-top:3px;">${timeStr}</div>
            </div>
            ${!n.is_read ? '<div><span class="badge bg-primary" style="width:8px;height:8px;padding:0;border-radius:50%;"></span></div>' : ''}
        </div>`;
    }).join('');
}

async function markRead(id, el) {
    if (el.classList.contains('border-primary')) {
        await apiCall('notifications/read', 'POST', { notification_id: id });
        el.classList.remove('border-start', 'border-3', 'border-primary');
        el.style.background = '';
        const dot = el.querySelector('.badge');
        if (dot) dot.remove();
        const title = el.querySelector('.fw-bold');
        if (title) { title.classList.remove('fw-bold'); title.classList.add('fw-normal'); }
    }
}

async function markAllRead() {
    await apiCall('notifications/read-all', 'POST');
    showToast('All marked as read.', 'success');
    loadNotifications();
}

if (!localStorage.getItem('fnb_token')) { window.location.href = '/app/login'; }
else { loadNotifications(); }
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

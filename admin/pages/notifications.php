<?php
/**
 * Admin – Notifications (send & log)
 * /admin/pages/notifications.php
 */

$pageTitle  = 'Notifications';
$activePage = 'notifications';

// Handle send notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $title    = sanitize_string($_POST['title']   ?? '');
        $body     = sanitize_string($_POST['body']    ?? '', 500);
        $type     = sanitize_string($_POST['type']    ?? 'system');
        $channel  = sanitize_string($_POST['channel'] ?? 'in_app');
        $target   = sanitize_string($_POST['target']  ?? 'all');
        $userId   = sanitize_int($_POST['user_id']    ?? 0);

        if (!$title || !$body) {
            flash('error', 'Title and message are required.');
        } else {
            // Resolve target users
            $users = [];
            if ($target === 'single' && $userId) {
                $u = Database::fetchOne('SELECT id, name, phone FROM users WHERE id = ? AND role = "customer"', [$userId]);
                if ($u) $users = [$u];
            } else {
                $segmentMap = [
                    'all'        => "SELECT id, name, phone FROM users WHERE role='customer' AND status='active'",
                    'bronze'     => "SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='bronze' AND u.status='active'",
                    'silver'     => "SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='silver' AND u.status='active'",
                    'gold'       => "SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='gold' AND u.status='active'",
                    'platinum'   => "SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='platinum' AND u.status='active'",
                ];
                if (isset($segmentMap[$target])) {
                    $users = Database::fetchAll($segmentMap[$target]);
                }
            }

            $sent = 0;
            foreach ($users as $u) {
                Notification::send($u['id'], $title, $body, $type, $channel);
                if ($channel === 'whatsapp') {
                    Notification::sendWhatsApp($u['phone'], "{$title}\n\n{$body}");
                }
                $sent++;
            }

            admin_log('send_notification', 'notifications', null, "Sent {$sent} notifications to segment: {$target}");
            flash('success', "Notification sent to {$sent} user(s).");
        }
    }
    header('Location: /admin/notifications');
    exit;
}

// Recent notifications log
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$filterType = sanitize_string($_GET['type'] ?? '');
$where  = '1=1';
$params = [];
if ($filterType) {
    $where   .= ' AND n.type = ?';
    $params[] = $filterType;
}

$total = (int) Database::fetchOne(
    "SELECT COUNT(*) AS c FROM notifications n WHERE {$where}", $params
)['c'];
$pages = (int) ceil($total / $perPage);

$notifications = Database::fetchAll(
    "SELECT n.*, u.name AS user_name, u.phone AS user_phone
     FROM notifications n
     JOIN users u ON u.id = n.user_id
     WHERE {$where}
     ORDER BY n.sent_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    array_merge($params, [$perPage, $offset])
);

// Stats
$todaySent  = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM notifications WHERE DATE(sent_at) = CURDATE()")['c'];
$unread     = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0")['c'];

require __DIR__ . '/../layout/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Sent Today</div>
            <div class="fs-4 fw-bold text-primary"><?= number_format($todaySent) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Total Unread</div>
            <div class="fs-4 fw-bold text-warning"><?= number_format($unread) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Total All Time</div>
            <div class="fs-4 fw-bold"><?= number_format($total) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Send Form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-send me-2 text-primary"></i>Send Notification</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Target Segment</label>
                        <select name="target" class="form-select form-select-sm" id="notif-target">
                            <option value="all">All Customers</option>
                            <option value="bronze">Bronze Tier</option>
                            <option value="silver">Silver Tier</option>
                            <option value="gold">Gold Tier</option>
                            <option value="platinum">Platinum Tier</option>
                            <option value="single">Single Customer</option>
                        </select>
                    </div>

                    <!-- Single user search -->
                    <div class="mb-3" id="single-user-field" style="display:none;">
                        <label class="form-label fw-semibold small">Customer Phone / Name</label>
                        <input type="text" id="user-search-input" class="form-control form-control-sm"
                               placeholder="Search customer…">
                        <div id="user-search-results" class="border rounded mt-1" style="display:none;max-height:150px;overflow-y:auto;"></div>
                        <input type="hidden" name="user_id" id="selected-user-id">
                        <div id="selected-user-name" class="text-success small mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Channel</label>
                        <select name="channel" class="form-select form-select-sm">
                            <option value="in_app">In-App Only</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="system">System</option>
                            <option value="promotion">Promotion</option>
                            <option value="points">Points</option>
                            <option value="reward">Reward</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Title *</label>
                        <input type="text" name="title" class="form-control form-control-sm"
                               placeholder="e.g. Special Promotion!" maxlength="150" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Message *</label>
                        <textarea name="body" class="form-control form-control-sm" rows="4"
                                  placeholder="Your message here…" maxlength="500" required></textarea>
                    </div>

                    <button name="send_notification" type="submit" class="btn btn-primary btn-sm w-100"
                            onclick="return confirm('Send this notification?')">
                        <i class="bi bi-send me-1"></i>Send Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Notification Log -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Notification Log</h6>
                <!-- Filter -->
                <form method="GET" class="d-flex gap-2">
                    <select name="type" class="form-select form-select-sm" style="width:auto;"
                            onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <?php foreach (['system','promotion','points','reward','reservation','order'] as $t): ?>
                        <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Channel</th>
                                <th>Read</th>
                                <th>Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $n):
                                $typeColors = ['system'=>'secondary','promotion'=>'primary','points'=>'warning','reward'=>'success','reservation'=>'info','order'=>'dark'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($n['user_name']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($n['user_phone']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($n['title']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars(substr($n['body'],0,60)) ?>…</div>
                                </td>
                                <td><span class="badge bg-<?= $typeColors[$n['type']] ?? 'secondary' ?>"><?= ucfirst($n['type']) ?></span></td>
                                <td><span class="badge bg-light text-dark"><?= ucfirst($n['channel']) ?></span></td>
                                <td>
                                    <?php if ($n['is_read']): ?>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                    <i class="bi bi-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= time_ago($n['sent_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($notifications)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No notifications found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
                    <span class="text-muted">Page <?= $page ?> of <?= $pages ?></span>
                    <nav><ul class="pagination pagination-sm mb-0">
                        <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
                        <li class="page-item <?= $i===$page?'active':'' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide single user field
document.getElementById('notif-target').addEventListener('change', function() {
    const show = this.value === 'single';
    document.getElementById('single-user-field').style.display = show ? '' : 'none';
});

// Live customer search
let searchTimer;
document.getElementById('user-search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('user-search-results').style.display = 'none'; return; }

    searchTimer = setTimeout(async () => {
        const res = await fetch(`/api/admin/customer-search?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Admin': '1' }
        });
        // Fallback: search inline via query string
        const response = await fetch(`/admin/customers?search=${encodeURIComponent(q)}&_json=1`);
        // Simple approach: use existing customer list endpoint pattern
    }, 300);
});

// Simpler inline search using a small AJAX call
document.getElementById('user-search-input').addEventListener('keyup', async function() {
    const q = this.value.trim();
    const resultsBox = document.getElementById('user-search-results');

    if (q.length < 2) { resultsBox.style.display = 'none'; return; }

    const res = await fetch(`/api/customers/search?q=${encodeURIComponent(q)}`, {
        headers: { 'Authorization': 'Bearer admin-internal' }
    });

    // Use admin session AJAX endpoint instead
    const r = await fetch(`/admin/customers?search=${encodeURIComponent(q)}&ajax=1`);
    if (!r.ok) return;

    try {
        const data = await r.json();
        if (data.length) {
            resultsBox.style.display = '';
            resultsBox.innerHTML = data.map(u =>
                `<div class="px-3 py-2 border-bottom small" style="cursor:pointer;"
                      onclick="selectUser(${u.id}, '${u.name.replace(/'/g,"\\'")} (${u.phone})')">
                    <strong>${u.name}</strong> – ${u.phone}
                 </div>`
            ).join('');
        }
    } catch(e) { resultsBox.style.display = 'none'; }
});

function selectUser(id, label) {
    document.getElementById('selected-user-id').value = id;
    document.getElementById('selected-user-name').textContent = '✓ ' + label;
    document.getElementById('user-search-results').style.display = 'none';
    document.getElementById('user-search-input').value = label;
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

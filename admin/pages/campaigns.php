<?php
/**
 * Admin – Campaigns Management
 * /admin/pages/campaigns.php
 */

$pageTitle  = 'Campaigns';
$activePage = 'campaigns';

// Handle campaign launch (send to segment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['launch_campaign'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.'); header('Location: /admin/campaigns'); exit;
    }

    $cid = sanitize_int($_POST['campaign_id'] ?? 0);
    $campaign = Database::fetchOne('SELECT * FROM campaigns WHERE id = ? AND status = "active"', [$cid]);

    if ($campaign) {
        // Get target users
        $users = get_campaign_target_users($campaign['target_segment']);
        $sent  = 0;

        foreach ($users as $u) {
            $channels = explode(',', $campaign['channel']);
            foreach ($channels as $ch) {
                $ch = trim($ch);
                $msg = str_replace('{name}', $u['name'], $campaign['message_template'] ?? '');

                if ($ch === 'whatsapp') {
                    Notification::sendWhatsApp($u['phone'], $msg);
                }
                Notification::send($u['id'], $campaign['name'], $msg, 'promotion', $ch, 'campaign', $cid);

                Database::insert('INSERT INTO campaign_logs (campaign_id, user_id, channel, status) VALUES (?,?,?,"sent")',
                    [$cid, $u['id'], $ch]);
                $sent++;
            }
        }

        Database::execute('UPDATE campaigns SET sent_count = sent_count + ?, status = "completed" WHERE id = ?', [$sent, $cid]);
        admin_log('launch_campaign', 'campaigns', $cid, "Sent {$sent} messages");
        flash('success', "Campaign launched. {$sent} messages sent.");
    } else {
        flash('error', 'Campaign not found or already completed.');
    }
    header('Location: /admin/campaigns'); exit;
}

$campaigns = Database::fetchAll(
    'SELECT c.*, u.name AS created_by_name
     FROM campaigns c
     LEFT JOIN users u ON u.id = c.created_by
     ORDER BY c.created_at DESC'
);

require __DIR__ . '/../layout/header.php';

/**
 * Helper: get users for a campaign segment.
 */
function get_campaign_target_users(string $segment): array
{
    return match ($segment) {
        'all'           => Database::fetchAll("SELECT id, name, phone FROM users WHERE role='customer' AND status='active'"),
        'bronze'        => Database::fetchAll("SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='bronze' AND u.status='active'"),
        'silver'        => Database::fetchAll("SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='silver' AND u.status='active'"),
        'gold'          => Database::fetchAll("SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='gold' AND u.status='active'"),
        'platinum'      => Database::fetchAll("SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE cp.tier='platinum' AND u.status='active'"),
        'inactive_30'   => Database::fetchAll("SELECT id, name, phone FROM users WHERE role='customer' AND status='active' AND (last_login_at < DATE_SUB(NOW(), INTERVAL 30 DAY) OR last_login_at IS NULL)"),
        'inactive_60'   => Database::fetchAll("SELECT id, name, phone FROM users WHERE role='customer' AND status='active' AND (last_login_at < DATE_SUB(NOW(), INTERVAL 60 DAY) OR last_login_at IS NULL)"),
        'birthday_today'=> Database::fetchAll("SELECT u.id, u.name, u.phone FROM users u JOIN customer_profiles cp ON cp.user_id=u.id WHERE DAY(cp.date_of_birth)=DAY(NOW()) AND MONTH(cp.date_of_birth)=MONTH(NOW()) AND u.status='active'"),
        default         => [],
    };
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?= count($campaigns) ?> campaigns</span>
    <a href="/admin/campaigns/create" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Campaign
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Campaign</th>
                        <th>Type</th>
                        <th>Channel</th>
                        <th>Segment</th>
                        <th>Validity</th>
                        <th>Sent</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="text-muted" style="font-size:.78rem;">by <?= htmlspecialchars($c['created_by_name'] ?? 'System') ?></div>
                        </td>
                        <td><span class="badge bg-secondary"><?= ucfirst($c['type']) ?></span></td>
                        <td>
                            <?php foreach (explode(',', $c['channel']) as $ch): ?>
                            <span class="badge bg-info me-1"><?= ucfirst(trim($ch)) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?= ucfirst(str_replace('_', ' ', $c['target_segment'])) ?></td>
                        <td class="text-muted">
                            <?php if ($c['valid_from'] && $c['valid_until']): ?>
                            <?= date('d/m/y', strtotime($c['valid_from'])) ?> – <?= date('d/m/y', strtotime($c['valid_until'])) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= number_format($c['sent_count']) ?></td>
                        <td>
                            <?php
                            $cBadge = ['draft'=>'secondary','active'=>'success','completed'=>'primary','cancelled'=>'danger'];
                            ?>
                            <span class="badge bg-<?= $cBadge[$c['status']] ?? 'secondary' ?>"><?= ucfirst($c['status']) ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/campaigns/edit?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($c['status'] === 'active'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Launch this campaign now?')">
                                    <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">
                                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                    <button name="launch_campaign" class="btn btn-sm btn-success py-0 px-2" title="Launch">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($campaigns)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No campaigns yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

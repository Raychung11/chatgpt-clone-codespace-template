<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Marketing Dashboard';

// KPI: Total subscribers (subscribed status only)
$totalSubscribers = DB::fetch('SELECT COUNT(*) as n FROM email_subscribers WHERE status = ?', ['subscribed'])['n'];

// KPI: Campaigns sent
$campaignsSent = DB::fetch('SELECT COUNT(*) as n FROM email_campaigns WHERE status = ?', ['sent'])['n'];

// KPI: Average open rate across all sent campaigns
$avgOpenRow = DB::fetch(
    'SELECT SUM(total_opens) as opens, SUM(total_sent) as sent FROM email_campaigns WHERE status = ? AND total_sent > 0',
    ['sent']
);
$avgOpenRate = ($avgOpenRow && $avgOpenRow['sent'] > 0)
    ? round(($avgOpenRow['opens'] / $avgOpenRow['sent']) * 100, 1)
    : 0;

// KPI: Scheduled posts (next 7 days or status=scheduled)
$scheduledPosts = DB::fetch('SELECT COUNT(*) as n FROM social_posts WHERE status = ?', ['scheduled'])['n'];

// Chart.js: campaigns sent per month for last 6 months
$campaignMonths = DB::fetchAll(
    "SELECT DATE_FORMAT(sent_at, '%Y-%m') as month_key,
            DATE_FORMAT(sent_at, '%b %Y') as month_label,
            COUNT(*) as total
     FROM email_campaigns
     WHERE status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month_key, month_label
     ORDER BY month_key ASC"
);

// Build full 6-month array (fill gaps with 0)
$chartLabels = [];
$chartData   = [];
$monthMap    = [];
foreach ($campaignMonths as $row) {
    $monthMap[$row['month_key']] = ['label' => $row['month_label'], 'total' => (int)$row['total']];
}
for ($i = 5; $i >= 0; $i--) {
    $key   = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $chartLabels[] = $monthMap[$key]['label'] ?? $label;
    $chartData[]   = $monthMap[$key]['total'] ?? 0;
}

// Email performance: recent 5 sent campaigns
$recentCampaigns = DB::fetchAll(
    "SELECT ec.*, el.name as list_name
     FROM email_campaigns ec
     LEFT JOIN email_lists el ON ec.list_id = el.id
     WHERE ec.status = 'sent'
     ORDER BY ec.sent_at DESC
     LIMIT 5"
);

// Social calendar: upcoming 7 days
$calendarPosts = DB::fetchAll(
    "SELECT * FROM social_posts
     WHERE status = 'scheduled'
       AND scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
     ORDER BY scheduled_at ASC"
);

// Group calendar posts by date
$postsByDate = [];
foreach ($calendarPosts as $post) {
    $dateKey = date('Y-m-d', strtotime($post['scheduled_at']));
    $postsByDate[$dateKey][] = $post;
}

// Platform breakdown: count per platform
$platformBreakdown = DB::fetchAll(
    "SELECT platform, COUNT(*) as total FROM social_posts GROUP BY platform ORDER BY total DESC"
);

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Marketing Dashboard</h4>
            <p class="text-muted small mb-0">Email campaigns &amp; social media overview</p>
        </div>
        <a href="/admin/social-posts.php?action=new" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Quick Compose
        </a>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Subscribers</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($totalSubscribers) ?></div>
                        <div class="text-success small">Active subscribed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-envelope-check fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Campaigns Sent</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($campaignsSent) ?></div>
                        <div class="text-success small">All time</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-eye fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Avg Open Rate</div>
                        <div class="fs-4 fw-bold text-white"><?= $avgOpenRate ?>%</div>
                        <div class="text-info small">Across sent campaigns</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-calendar-event fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Scheduled Posts</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($scheduledPosts) ?></div>
                        <div class="text-warning small">Awaiting publish</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Campaign Chart -->
        <div class="col-lg-8">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Campaigns Sent — Last 6 Months</h6>
                    <a href="/admin/email-campaigns.php" class="text-primary small text-decoration-none">View all</a>
                </div>
                <canvas id="campaignsChart" height="100"></canvas>
            </div>
        </div>

        <!-- Platform Breakdown -->
        <div class="col-lg-4">
            <div class="admin-card rounded-4 p-4 h-100">
                <h6 class="text-white fw-semibold mb-3">Platform Breakdown</h6>
                <?php if (empty($platformBreakdown)): ?>
                <p class="text-muted small">No posts yet.</p>
                <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php
                    $platformColors = [
                        'twitter'   => 'info',
                        'linkedin'  => 'primary',
                        'facebook'  => 'primary',
                        'instagram' => 'danger',
                    ];
                    $platformIcons = [
                        'twitter'   => 'bi-twitter-x',
                        'linkedin'  => 'bi-linkedin',
                        'facebook'  => 'bi-facebook',
                        'instagram' => 'bi-instagram',
                    ];
                    foreach ($platformBreakdown as $pb):
                        $platforms = explode(',', $pb['platform']);
                        foreach ($platforms as $pname):
                            $pname = trim($pname);
                            $col = $platformColors[$pname] ?? 'secondary';
                            $ico = $platformIcons[$pname] ?? 'bi-globe';
                    ?>
                    <span class="badge bg-<?= $col ?> bg-opacity-10 text-<?= $col ?> border border-<?= $col ?> border-opacity-25 px-3 py-2 fs-6">
                        <i class="bi <?= $ico ?> me-1"></i><?= ucfirst($pname) ?>
                        <span class="ms-1 fw-bold"><?= (int)$pb['total'] ?></span>
                    </span>
                    <?php
                        endforeach;
                    endforeach; ?>
                </div>
                <?php endif; ?>

                <hr class="border-secondary border-opacity-25">
                <h6 class="text-white fw-semibold mb-3">Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="/admin/email-campaigns.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-envelope me-1"></i>New Email Campaign
                    </a>
                    <a href="/admin/social-posts.php?action=new" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-megaphone me-1"></i>Schedule Social Post
                    </a>
                    <a href="/admin/email-subscribers.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-people me-1"></i>Manage Subscribers
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Email Performance Table -->
        <div class="col-lg-7">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Email Performance</h6>
                    <a href="/admin/email-campaigns.php" class="text-primary small text-decoration-none">All campaigns</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Campaign</th>
                                <th>List</th>
                                <th class="text-end">Sent</th>
                                <th class="text-end">Open %</th>
                                <th class="text-end">Click %</th>
                                <th class="text-end">Unsub %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentCampaigns)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No sent campaigns yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentCampaigns as $c):
                                $sent  = (int)$c['total_sent'];
                                $openR = $sent > 0 ? round(($c['total_opens'] / $sent) * 100, 1) : 0;
                                $clkR  = $sent > 0 ? round(($c['total_clicks'] / $sent) * 100, 1) : 0;
                                $unsubR = $sent > 0 ? round(($c['total_unsubscribes'] / $sent) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="text-white small fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="text-muted" style="font-size:11px"><?= $c['sent_at'] ? date('d M Y', strtotime($c['sent_at'])) : '—' ?></div>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($c['list_name'] ?? '—') ?></td>
                                <td class="text-muted small text-end"><?= number_format($sent) ?></td>
                                <td class="text-end">
                                    <span class="<?= $openR >= 20 ? 'text-success' : ($openR >= 10 ? 'text-warning' : 'text-danger') ?> small fw-semibold"><?= $openR ?>%</span>
                                </td>
                                <td class="text-end">
                                    <span class="<?= $clkR >= 2 ? 'text-success' : 'text-warning' ?> small fw-semibold"><?= $clkR ?>%</span>
                                </td>
                                <td class="text-end">
                                    <span class="<?= $unsubR < 0.5 ? 'text-success' : 'text-danger' ?> small fw-semibold"><?= $unsubR ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Social Media Calendar -->
        <div class="col-lg-5">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Upcoming 7 Days</h6>
                    <a href="/admin/social-posts.php" class="text-primary small text-decoration-none">Full calendar</a>
                </div>
                <?php if (empty($postsByDate)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x fs-2 mb-2 d-block"></i>
                    No scheduled posts in the next 7 days.
                    <div class="mt-3">
                        <a href="/admin/social-posts.php?action=new" class="btn btn-primary btn-sm">Schedule a Post</a>
                    </div>
                </div>
                <?php else: ?>
                <?php
                $statusBadge = [
                    'draft'     => 'secondary',
                    'scheduled' => 'warning',
                    'published' => 'success',
                    'failed'    => 'danger',
                ];
                for ($d = 0; $d < 7; $d++):
                    $dateKey   = date('Y-m-d', strtotime("+$d days"));
                    $dateLabel = $d === 0 ? 'Today' : ($d === 1 ? 'Tomorrow' : date('D, d M', strtotime("+$d days")));
                    if (empty($postsByDate[$dateKey])) continue;
                ?>
                <div class="mb-3">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.05em">
                        <?= $dateLabel ?>
                    </div>
                    <?php foreach ($postsByDate[$dateKey] as $sp):
                        $platforms = explode(',', $sp['platform']);
                        $badgeCol  = $statusBadge[$sp['status']] ?? 'secondary';
                    ?>
                    <div class="d-flex align-items-start gap-2 mb-2 p-2 rounded-3" style="background:rgba(255,255,255,.04)">
                        <div class="d-flex gap-1 flex-shrink-0 mt-1">
                            <?php foreach ($platforms as $pn):
                                $pn  = trim($pn);
                                $col = $platformColors[$pn] ?? 'secondary';
                                $ico = $platformIcons[$pn] ?? 'bi-globe';
                            ?>
                            <span class="text-<?= $col ?>"><i class="bi <?= $ico ?>" style="font-size:13px"></i></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="text-white small text-truncate"><?= htmlspecialchars(mb_substr($sp['content'], 0, 80)) ?><?= mb_strlen($sp['content']) > 80 ? '…' : '' ?></div>
                            <div class="text-muted" style="font-size:11px"><?= date('H:i', strtotime($sp['scheduled_at'])) ?></div>
                        </div>
                        <span class="badge bg-<?= $badgeCol ?> flex-shrink-0"><?= ucfirst($sp['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ctx = document.getElementById('campaignsChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Campaigns Sent',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.15)',
                borderWidth: 2,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4,
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e1e2e',
                    titleColor: '#a0a0b0',
                    bodyColor: '#ffffff',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#6c6c8a' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#6c6c8a', stepSize: 1 }
                }
            }
        }
    });
})();
</script>

<?php require_once '../includes/admin-footer.php'; ?>

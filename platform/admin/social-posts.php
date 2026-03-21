<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $platforms    = $_POST['platforms'] ?? [];
        $platform     = implode(',', array_filter(array_map('trim', (array)$platforms)));
        $content      = trim($_POST['content'] ?? '');
        $mediaUrl     = trim($_POST['media_url'] ?? '');
        $hashtags     = trim($_POST['hashtags'] ?? '');
        $status       = $_POST['status'] ?? 'draft';
        $scheduledAt  = ($status === 'scheduled' && !empty($_POST['scheduled_at']))
                        ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at']))
                        : null;
        $campaignId   = (int)($_POST['campaign_id'] ?? 0) ?: null;

        if ($id > 0) {
            DB::update(
                'UPDATE social_posts SET platform=?, content=?, media_url=?, hashtags=?, status=?,
                 scheduled_at=?, campaign_id=? WHERE id=?',
                [$platform, $content, $mediaUrl, $hashtags, $status, $scheduledAt, $campaignId, $id]
            );
        } else {
            DB::insert(
                'INSERT INTO social_posts (platform, content, media_url, hashtags, status, scheduled_at, campaign_id, created_by)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$platform, $content, $mediaUrl, $hashtags, $status, $scheduledAt, $campaignId, Auth::id()]
            );
        }
        header('Location: /admin/social-posts.php?saved=1&view=' . ($_POST['view'] ?? 'calendar'));
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM social_posts WHERE id = ?', [$id]);
        }
        header('Location: /admin/social-posts.php?deleted=1&view=' . ($_POST['view'] ?? 'calendar'));
        exit;
    }
}

// ── View / Filter Params ──────────────────────────────────────────────────────
$view            = ($_GET['view'] ?? 'calendar') === 'list' ? 'list' : 'calendar';
$filterPlatforms = $_GET['platforms'] ?? [];
$filterStatus    = $_GET['status'] ?? '';
$monthParam      = $_GET['month'] ?? date('Y-m');

// Parse month
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$monthStart = $monthParam . '-01';
$monthTs    = strtotime($monthStart);
$monthEnd   = date('Y-m-t', $monthTs);
$prevMonth  = date('Y-m', strtotime('-1 month', $monthTs));
$nextMonth  = date('Y-m', strtotime('+1 month', $monthTs));
$monthLabel = date('F Y', $monthTs);

// ── Build posts query ─────────────────────────────────────────────────────────
$where  = [];
$params = [];

if (!empty($filterPlatforms)) {
    $platformClauses = [];
    foreach ($filterPlatforms as $p) {
        $p = trim($p);
        if (in_array($p, ['twitter','linkedin','facebook','instagram'], true)) {
            $platformClauses[] = 'FIND_IN_SET(?, platform)';
            $params[] = $p;
        }
    }
    if ($platformClauses) {
        $where[] = '(' . implode(' OR ', $platformClauses) . ')';
    }
}

if ($filterStatus !== '') {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}

if ($view === 'calendar') {
    $where[]  = 'DATE(scheduled_at) BETWEEN ? AND ?';
    $params[] = $monthStart;
    $params[] = $monthEnd;
} else {
    // list view: filter by month if chosen
    $where[]  = 'DATE(scheduled_at) BETWEEN ? AND ?';
    $params[] = $monthStart;
    $params[] = $monthEnd;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$posts = DB::fetchAll(
    "SELECT * FROM social_posts $whereSQL ORDER BY scheduled_at ASC, created_at DESC",
    $params
);

// Group posts by date for calendar view
$postsByDate = [];
foreach ($posts as $post) {
    $d = $post['scheduled_at'] ? date('Y-m-d', strtotime($post['scheduled_at'])) : null;
    if ($d) {
        $postsByDate[$d][] = $post;
    }
}

// For the modal: all email campaigns for linking
$emailCampaigns = DB::fetchAll(
    "SELECT id, name FROM email_campaigns ORDER BY created_at DESC LIMIT 50"
);

// Helpers
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
$platformLimits = [
    'twitter'   => 280,
    'linkedin'  => 3000,
    'facebook'  => 63206,
    'instagram' => 2200,
];
$statusBadge = [
    'draft'     => 'secondary',
    'scheduled' => 'warning',
    'published' => 'success',
    'failed'    => 'danger',
];

$pageTitle = 'Social Media Scheduler';
$extraHead = '<style>
.cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 4px; }
.cal-header { display: grid; grid-template-columns: repeat(7,1fr); gap: 4px; }
.cal-day { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07);
           border-radius: 8px; min-height: 90px; padding: 6px; font-size: 11px; overflow: hidden; }
.cal-day.today { border-color: #6366f1; }
.cal-day.other-month { opacity: .35; }
.cal-day-num { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
.cal-post-pill { border-radius: 4px; padding: 2px 5px; margin-bottom: 3px; line-height: 1.3;
                 background: rgba(255,255,255,.06); display: flex; align-items: center; gap: 4px; cursor: pointer; }
.cal-post-pill:hover { background: rgba(99,102,241,.25); }
.platform-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.dot-twitter   { background:#0dcaf0; }
.dot-linkedin  { background:#6366f1; }
.dot-facebook  { background:#6366f1; }
.dot-instagram { background:#dc3545; }
</style>';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Social Media Scheduler</h4>
            <p class="text-muted small mb-0">Schedule and manage posts across platforms</p>
        </div>
        <div class="d-flex gap-2">
            <!-- View toggle -->
            <div class="btn-group btn-group-sm" role="group">
                <a href="?view=calendar&month=<?= $monthParam ?>"
                   class="btn <?= $view === 'calendar' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-calendar3 me-1"></i>Calendar
                </a>
                <a href="?view=list&month=<?= $monthParam ?>"
                   class="btn <?= $view === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-list-ul me-1"></i>List
                </a>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#postModal"
                    onclick="openPostModal(null)">
                <i class="bi bi-plus-circle me-1"></i>New Post
            </button>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">Post saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">Post deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="admin-card rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <div class="col-sm-auto">
                <label class="form-label text-muted small mb-1">Platforms</label>
                <div class="d-flex gap-3 align-items-center pt-1">
                    <?php foreach (['twitter','linkedin','facebook','instagram'] as $p):
                        $col = $platformColors[$p];
                        $ico = $platformIcons[$p];
                        $checked = in_array($p, (array)$filterPlatforms) ? 'checked' : '';
                    ?>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="platforms[]"
                               value="<?= $p ?>" id="filterP_<?= $p ?>" <?= $checked ?>>
                        <label class="form-check-label text-<?= $col ?> small" for="filterP_<?= $p ?>">
                            <i class="bi <?= $ico ?> me-1"></i><?= ucfirst($p) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-sm-2">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All</option>
                    <?php foreach (['draft','scheduled','published','failed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label text-muted small mb-1">Month</label>
                <input type="month" name="month" value="<?= htmlspecialchars($monthParam) ?>"
                       class="form-control form-control-sm bg-dark text-white border-secondary">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="/admin/social-posts.php?view=<?= $view ?>" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>

    <?php if ($view === 'calendar'): ?>
    <!-- ── CALENDAR VIEW ──────────────────────────────────────────────────── -->
    <div class="admin-card rounded-4 p-4">
        <!-- Month navigation -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="?view=calendar&month=<?= $prevMonth ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-left"></i>
            </a>
            <h6 class="text-white fw-semibold mb-0"><?= htmlspecialchars($monthLabel) ?></h6>
            <a href="?view=calendar&month=<?= $nextMonth ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <!-- Day headers Mon-Sun -->
        <div class="cal-header mb-1">
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dh): ?>
            <div class="text-center text-muted small pb-1"><?= $dh ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Calendar grid -->
        <?php
        $firstDow = (int)date('N', $monthTs);   // 1=Mon…7=Sun
        $daysInMonth = (int)date('t', $monthTs);
        $todayStr = date('Y-m-d');
        $totalCells = ceil(($firstDow - 1 + $daysInMonth) / 7) * 7;
        ?>
        <div class="cal-grid">
            <?php for ($cell = 1; $cell <= $totalCells; $cell++):
                $dayNum = $cell - ($firstDow - 1);
                $isOther = ($dayNum < 1 || $dayNum > $daysInMonth);
                $realDay = $isOther ? null : $dayNum;
                $dateStr = $realDay ? date('Y-m', $monthTs) . '-' . sprintf('%02d', $realDay) : '';
                $isToday = ($dateStr === $todayStr);
                $dayPosts = $dateStr ? ($postsByDate[$dateStr] ?? []) : [];
            ?>
            <div class="cal-day <?= $isOther ? 'other-month' : '' ?> <?= $isToday ? 'today' : '' ?>">
                <?php if ($realDay): ?>
                <div class="cal-day-num text-<?= $isToday ? 'primary' : 'muted' ?>"><?= $realDay ?></div>
                <?php foreach (array_slice($dayPosts, 0, 4) as $dp):
                    $dpPlatforms = array_map('trim', explode(',', $dp['platform']));
                    $firstPlatform = $dpPlatforms[0] ?? '';
                    $dotClass = 'dot-' . ($firstPlatform ?: 'twitter');
                    $dpBadge = $statusBadge[$dp['status']] ?? 'secondary';
                ?>
                <div class="cal-post-pill"
                     onclick="openPostModal(<?= htmlspecialchars(json_encode($dp), ENT_QUOTES) ?>)"
                     data-bs-toggle="modal" data-bs-target="#postModal">
                    <div class="platform-dot <?= $dotClass ?>"></div>
                    <span class="text-white text-truncate" style="max-width:90px">
                        <?= htmlspecialchars(mb_substr($dp['content'], 0, 35)) ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (count($dayPosts) > 4): ?>
                <div class="text-muted" style="font-size:10px">+<?= count($dayPosts) - 4 ?> more</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- ── LIST VIEW ──────────────────────────────────────────────────────── -->
    <div class="admin-card rounded-4 p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Platform</th>
                        <th>Content</th>
                        <th>Hashtags</th>
                        <th>Status</th>
                        <th>Scheduled</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>Engagement</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No posts found for this period.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($posts as $post):
                        $postPlatforms = array_filter(array_map('trim', explode(',', $post['platform'])));
                        $badgeCol = $statusBadge[$post['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($postPlatforms as $pp):
                                    $col = $platformColors[$pp] ?? 'secondary';
                                    $ico = $platformIcons[$pp] ?? 'bi-globe';
                                ?>
                                <span class="badge bg-<?= $col ?> bg-opacity-15 text-<?= $col ?> border border-<?= $col ?> border-opacity-25">
                                    <i class="bi <?= $ico ?> me-1"></i><?= ucfirst($pp) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="max-width:250px">
                            <div class="text-white small text-truncate">
                                <?= htmlspecialchars(mb_substr($post['content'], 0, 100)) ?><?= mb_strlen($post['content']) > 100 ? '…' : '' ?>
                            </div>
                            <?php if ($post['media_url']): ?>
                            <div class="text-muted" style="font-size:10px">
                                <i class="bi bi-image me-1"></i><?= htmlspecialchars(basename($post['media_url'])) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:120px">
                            <span class="text-truncate d-inline-block" style="max-width:110px">
                                <?= htmlspecialchars($post['hashtags'] ?: '—') ?>
                            </span>
                        </td>
                        <td><span class="badge bg-<?= $badgeCol ?>"><?= ucfirst($post['status']) ?></span></td>
                        <td class="text-muted small text-nowrap">
                            <?= $post['scheduled_at'] ? date('d M Y H:i', strtotime($post['scheduled_at'])) : '—' ?>
                        </td>
                        <td class="text-muted small text-end"><?= $post['impressions'] ? number_format($post['impressions']) : '—' ?></td>
                        <td class="text-muted small text-end"><?= $post['clicks'] ? number_format($post['clicks']) : '—' ?></td>
                        <td class="text-muted small text-end"><?= $post['engagement'] ? number_format($post['engagement']) : '—' ?></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                                        onclick="openPostModal(<?= htmlspecialchars(json_encode($post), ENT_QUOTES) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#postModal"
                                        title="Edit">
                                    <i class="bi bi-pencil" style="font-size:12px"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this post?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                                        <i class="bi bi-trash" style="font-size:12px"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Post Modal -->
<div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="postModalLabel">New Post</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="postForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="pfId" value="0">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Platforms -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Platforms <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 flex-wrap">
                                <?php foreach (['twitter','linkedin','facebook','instagram'] as $p):
                                    $col = $platformColors[$p];
                                    $ico = $platformIcons[$p];
                                ?>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input platform-checkbox" type="checkbox"
                                           name="platforms[]" value="<?= $p ?>"
                                           id="pfPlatform_<?= $p ?>"
                                           onchange="updateCharCounter()">
                                    <label class="form-check-label text-<?= $col ?>" for="pfPlatform_<?= $p ?>">
                                        <i class="bi <?= $ico ?> me-1"></i><?= ucfirst($p) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="col-12">
                            <label class="form-label text-muted small">
                                Content <span class="text-danger">*</span>
                                <span id="charCounterLabel" class="ms-2 text-muted"
                                      style="font-weight:400;font-size:11px"></span>
                            </label>
                            <textarea name="content" id="pfContent" rows="5"
                                      class="form-control bg-dark text-white border-secondary"
                                      required oninput="updateCharCounter()"></textarea>
                            <div class="d-flex justify-content-end mt-1">
                                <span id="charCounter" class="small text-muted">0 chars</span>
                            </div>
                        </div>

                        <!-- Hashtags -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Hashtags</label>
                            <input type="text" name="hashtags" id="pfHashtags"
                                   class="form-control bg-dark text-white border-secondary"
                                   placeholder="#ai #automation #sme">
                        </div>

                        <!-- Media URL -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Media URL</label>
                            <input type="url" name="media_url" id="pfMediaUrl"
                                   class="form-control bg-dark text-white border-secondary"
                                   placeholder="https://...">
                        </div>

                        <!-- Status -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="pfStatus"
                                    class="form-select bg-dark text-white border-secondary"
                                    onchange="toggleScheduledAt(this.value)">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="published">Published</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>

                        <!-- Scheduled At -->
                        <div class="col-md-4" id="pfScheduledAtGroup" style="display:none">
                            <label class="form-label text-muted small">Scheduled At</label>
                            <input type="datetime-local" name="scheduled_at" id="pfScheduledAt"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>

                        <!-- Campaign link -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Linked Campaign <span class="text-muted">(optional)</span></label>
                            <select name="campaign_id" id="pfCampaignId"
                                    class="form-select bg-dark text-white border-secondary">
                                <option value="">— None —</option>
                                <?php foreach ($emailCampaigns as $ec): ?>
                                <option value="<?= $ec['id'] ?>"><?= htmlspecialchars($ec['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const platformLimits = {
    twitter:   280,
    linkedin:  3000,
    facebook:  63206,
    instagram: 2200
};

function updateCharCounter() {
    const checked = [...document.querySelectorAll('.platform-checkbox:checked')].map(c => c.value);
    const content = document.getElementById('pfContent').value;
    const len     = content.length;

    if (checked.length === 0) {
        document.getElementById('charCounterLabel').textContent = '';
        document.getElementById('charCounter').textContent = len + ' chars';
        document.getElementById('charCounter').className = 'small text-muted';
        return;
    }

    const limit = Math.min(...checked.map(p => platformLimits[p] || 63206));
    const platformName = checked.find(p => platformLimits[p] === limit) || '';
    document.getElementById('charCounterLabel').textContent =
        '(limit: ' + limit.toLocaleString() + ' for ' + platformName + ')';

    const rem = limit - len;
    const el  = document.getElementById('charCounter');
    el.textContent = len + ' / ' + limit.toLocaleString();
    if (rem < 0) {
        el.className = 'small text-danger fw-bold';
    } else if (rem < limit * 0.1) {
        el.className = 'small text-warning';
    } else {
        el.className = 'small text-muted';
    }
}

function toggleScheduledAt(status) {
    document.getElementById('pfScheduledAtGroup').style.display =
        (status === 'scheduled') ? '' : 'none';
}

function openPostModal(record) {
    // Reset form
    document.getElementById('postForm').reset();
    document.querySelectorAll('.platform-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('pfScheduledAtGroup').style.display = 'none';
    document.getElementById('charCounterLabel').textContent = '';
    document.getElementById('charCounter').textContent = '0 chars';

    if (!record) {
        document.getElementById('postModalLabel').textContent = 'New Post';
        document.getElementById('pfId').value = '0';
        return;
    }

    document.getElementById('postModalLabel').textContent = 'Edit Post';
    document.getElementById('pfId').value         = record.id;
    document.getElementById('pfContent').value    = record.content || '';
    document.getElementById('pfHashtags').value   = record.hashtags || '';
    document.getElementById('pfMediaUrl').value   = record.media_url || '';
    document.getElementById('pfStatus').value     = record.status || 'draft';
    document.getElementById('pfCampaignId').value = record.campaign_id || '';

    // Set platform checkboxes
    const platforms = record.platform ? record.platform.split(',') : [];
    platforms.forEach(p => {
        const cb = document.getElementById('pfPlatform_' + p.trim());
        if (cb) cb.checked = true;
    });

    // Scheduled at
    if (record.scheduled_at) {
        const dt = record.scheduled_at.replace(' ', 'T').substring(0, 16);
        document.getElementById('pfScheduledAt').value = dt;
    }

    toggleScheduledAt(record.status || 'draft');
    updateCharCounter();
}

// Auto-open modal if ?action=new is in URL
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'new') {
        const modal = new bootstrap.Modal(document.getElementById('postModal'));
        openPostModal(null);
        modal.show();
    }
})();
</script>

<?php require_once '../includes/admin-footer.php'; ?>

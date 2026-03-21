<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $subject      = trim($_POST['subject'] ?? '');
        $preview_text = trim($_POST['preview_text'] ?? '');
        $from_name    = trim($_POST['from_name'] ?? '');
        $from_email   = trim($_POST['from_email'] ?? '');
        $list_id      = (int)($_POST['list_id'] ?? 0) ?: null;
        $body_html    = $_POST['body_html'] ?? '';
        $status       = $_POST['status'] ?? 'draft';
        $scheduled_at = ($status === 'scheduled' && !empty($_POST['scheduled_at']))
                        ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at']))
                        : null;

        if ($id > 0) {
            DB::update(
                'UPDATE email_campaigns SET name=?, subject=?, preview_text=?, from_name=?, from_email=?,
                 list_id=?, body_html=?, status=?, scheduled_at=? WHERE id=?',
                [$name, $subject, $preview_text, $from_name, $from_email,
                 $list_id, $body_html, $status, $scheduled_at, $id]
            );
        } else {
            DB::insert(
                'INSERT INTO email_campaigns
                 (name, subject, preview_text, from_name, from_email, list_id, body_html, status, scheduled_at, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$name, $subject, $preview_text, $from_name, $from_email,
                 $list_id, $body_html, $status, $scheduled_at, Auth::id()]
            );
        }
        header('Location: /admin/email-campaigns.php?saved=1');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM email_campaigns WHERE id = ?', [$id]);
        }
        header('Location: /admin/email-campaigns.php?deleted=1');
        exit;
    }

    if ($action === 'duplicate') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $orig = DB::fetch('SELECT * FROM email_campaigns WHERE id = ?', [$id]);
            if ($orig) {
                DB::insert(
                    'INSERT INTO email_campaigns
                     (name, subject, preview_text, from_name, from_email, list_id, body_html, status, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [
                        $orig['name'] . ' (Copy)',
                        $orig['subject'],
                        $orig['preview_text'],
                        $orig['from_name'],
                        $orig['from_email'],
                        $orig['list_id'],
                        $orig['body_html'],
                        'draft',
                        Auth::id(),
                    ]
                );
            }
        }
        header('Location: /admin/email-campaigns.php?duplicated=1');
        exit;
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterList   = (int)($_GET['list_id'] ?? 0);
$editId       = (int)($_GET['edit'] ?? 0);

$where  = [];
$params = [];
if ($filterStatus !== '') {
    $where[]  = 'ec.status = ?';
    $params[] = $filterStatus;
}
if ($filterList > 0) {
    $where[]  = 'ec.list_id = ?';
    $params[] = $filterList;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$campaigns = DB::fetchAll(
    "SELECT ec.*, el.name as list_name
     FROM email_campaigns ec
     LEFT JOIN email_lists el ON ec.list_id = el.id
     $whereSQL
     ORDER BY ec.created_at DESC",
    $params
);

$lists = DB::fetchAll('SELECT id, name FROM email_lists ORDER BY name ASC');

// If editing, load the record
$editRecord = null;
if ($editId > 0) {
    $editRecord = DB::fetch('SELECT * FROM email_campaigns WHERE id = ?', [$editId]);
}

$pageTitle = 'Email Campaigns';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Email Campaigns</h4>
            <p class="text-muted small mb-0">Create, schedule and track email campaigns</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#campaignModal"
                onclick="openCampaignModal(null)">
            <i class="bi bi-plus-circle me-1"></i>New Campaign
        </button>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Campaign saved successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        Campaign deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['duplicated'])): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        Campaign duplicated as draft. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="admin-card rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Statuses</option>
                    <?php foreach (['draft','scheduled','sending','sent','paused'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label text-muted small mb-1">List</label>
                <select name="list_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="0">All Lists</option>
                    <?php foreach ($lists as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $filterList === (int)$l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="/admin/email-campaigns.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>

    <!-- Campaigns Table -->
    <div class="admin-card rounded-4 p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Name / Subject</th>
                        <th>List</th>
                        <th>Status</th>
                        <th>Scheduled / Sent</th>
                        <th class="text-end">Sent</th>
                        <th class="text-end">Open %</th>
                        <th class="text-end">Click %</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No campaigns found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($campaigns as $c):
                        $sent   = (int)$c['total_sent'];
                        $openR  = $sent > 0 ? round(($c['total_opens']  / $sent) * 100, 1) : 0;
                        $clkR   = $sent > 0 ? round(($c['total_clicks'] / $sent) * 100, 1) : 0;
                        $statusMap = [
                            'draft'     => 'secondary',
                            'scheduled' => 'info',
                            'sending'   => 'primary',
                            'sent'      => 'success',
                            'paused'    => 'warning',
                        ];
                        $badgeCol = $statusMap[$c['status']] ?? 'secondary';
                        $dateVal  = $c['status'] === 'sent' ? $c['sent_at'] : $c['scheduled_at'];
                    ?>
                    <tr>
                        <td>
                            <div class="text-white fw-semibold small"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($c['subject']) ?></div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($c['list_name'] ?? '—') ?></td>
                        <td><span class="badge bg-<?= $badgeCol ?>"><?= ucfirst($c['status']) ?></span></td>
                        <td class="text-muted small">
                            <?= $dateVal ? date('d M Y H:i', strtotime($dateVal)) : '—' ?>
                        </td>
                        <td class="text-muted small text-end"><?= $sent > 0 ? number_format($sent) : '—' ?></td>
                        <td class="text-end">
                            <?php if ($c['status'] === 'sent' && $sent > 0): ?>
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <div class="progress flex-grow-1" style="height:4px;min-width:50px">
                                    <div class="progress-bar bg-info" style="width:<?= min($openR, 100) ?>%"></div>
                                </div>
                                <span class="small <?= $openR >= 20 ? 'text-success' : ($openR >= 10 ? 'text-warning' : 'text-danger') ?>">
                                    <?= $openR ?>%
                                </span>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($c['status'] === 'sent' && $sent > 0): ?>
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <div class="progress flex-grow-1" style="height:4px;min-width:50px">
                                    <div class="progress-bar bg-success" style="width:<?= min($clkR * 10, 100) ?>%"></div>
                                </div>
                                <span class="small <?= $clkR >= 2 ? 'text-success' : 'text-warning' ?>">
                                    <?= $clkR ?>%
                                </span>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                                        onclick="openCampaignModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#campaignModal"
                                        title="Edit">
                                    <i class="bi bi-pencil" style="font-size:12px"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Duplicate this campaign?')">
                                    <input type="hidden" name="action" value="duplicate">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-outline-info btn-sm py-0 px-2" title="Duplicate">
                                        <i class="bi bi-copy" style="font-size:12px"></i>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
</div>

<!-- Campaign Add/Edit Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="campaignModalLabel">New Campaign</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="campaignForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="fieldId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="fieldName" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Subject Line <span class="text-danger">*</span></label>
                            <input type="text" name="subject" id="fieldSubject" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small">Preview Text</label>
                            <input type="text" name="preview_text" id="fieldPreviewText" class="form-control bg-dark text-white border-secondary"
                                   placeholder="Short preview shown in inbox…">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">From Name <span class="text-danger">*</span></label>
                            <input type="text" name="from_name" id="fieldFromName" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">From Email <span class="text-danger">*</span></label>
                            <input type="email" name="from_email" id="fieldFromEmail" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Target List</label>
                            <select name="list_id" id="fieldListId" class="form-select bg-dark text-white border-secondary">
                                <option value="">— Select list —</option>
                                <?php foreach ($lists as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="fieldStatus" class="form-select bg-dark text-white border-secondary"
                                    onchange="toggleScheduled(this.value)">
                                <?php foreach (['draft','scheduled','sending','sent','paused'] as $s): ?>
                                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3" id="scheduledAtGroup" style="display:none">
                            <label class="form-label text-muted small">Scheduled At</label>
                            <input type="datetime-local" name="scheduled_at" id="fieldScheduledAt"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Email Body (HTML) <span class="text-danger">*</span></label>
                            <textarea name="body_html" id="fieldBodyHtml" rows="12"
                                      class="form-control bg-dark text-white border-secondary font-monospace"
                                      style="font-size:12px;resize:vertical" required></textarea>
                            <div class="form-text text-muted">Enter the full HTML for the email body.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleScheduled(status) {
    document.getElementById('scheduledAtGroup').style.display = (status === 'scheduled') ? '' : 'none';
}

function openCampaignModal(record) {
    const modal = document.getElementById('campaignModal');
    if (!record) {
        document.getElementById('campaignModalLabel').textContent = 'New Campaign';
        document.getElementById('campaignForm').reset();
        document.getElementById('fieldId').value = '0';
        document.getElementById('scheduledAtGroup').style.display = 'none';
        return;
    }
    document.getElementById('campaignModalLabel').textContent = 'Edit Campaign';
    document.getElementById('fieldId').value        = record.id;
    document.getElementById('fieldName').value      = record.name;
    document.getElementById('fieldSubject').value   = record.subject;
    document.getElementById('fieldPreviewText').value = record.preview_text || '';
    document.getElementById('fieldFromName').value  = record.from_name;
    document.getElementById('fieldFromEmail').value = record.from_email;
    document.getElementById('fieldListId').value    = record.list_id || '';
    document.getElementById('fieldStatus').value    = record.status;
    document.getElementById('fieldBodyHtml').value  = record.body_html || '';

    if (record.scheduled_at) {
        const dt = record.scheduled_at.replace(' ', 'T').substring(0, 16);
        document.getElementById('fieldScheduledAt').value = dt;
    } else {
        document.getElementById('fieldScheduledAt').value = '';
    }
    toggleScheduled(record.status);
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>

<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $ownerId   = (int)($_POST['owner_id'] ?? 0) ?: null;
        $data = [
            'first_name'       => htmlspecialchars(trim($_POST['first_name'] ?? '')),
            'last_name'        => htmlspecialchars(trim($_POST['last_name']  ?? '')),
            'email'            => trim($_POST['email']     ?? ''),
            'phone'            => htmlspecialchars(trim($_POST['phone']     ?? '')),
            'company'          => htmlspecialchars(trim($_POST['company']   ?? '')),
            'job_title'        => htmlspecialchars(trim($_POST['job_title'] ?? '')),
            'source'           => $_POST['source'] ?? 'other',
            'status'           => $_POST['status'] ?? 'lead',
            'owner_id'         => $ownerId,
            'tags'             => htmlspecialchars(trim($_POST['tags']  ?? '')),
            'notes'            => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ];

        if ($id > 0) {
            DB::update('crm_contacts', $data, 'id = ?', [$id]);
        } else {
            DB::insert('crm_contacts', $data);
        }
        header('Location: crm-contacts.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM crm_contacts WHERE id = ?', [$id]);
        }
        header('Location: crm-contacts.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterSource = $_GET['source'] ?? '';
$search       = trim($_GET['search'] ?? '');

$where  = '1=1';
$params = [];

if ($filterStatus !== '') {
    $where   .= ' AND c.status = ?';
    $params[] = $filterStatus;
}
if ($filterSource !== '') {
    $where   .= ' AND c.source = ?';
    $params[] = $filterSource;
}
if ($search !== '') {
    $where   .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.company LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$contacts = DB::fetchAll(
    "SELECT c.*,
            u.name AS owner_name
     FROM crm_contacts c
     LEFT JOIN users u ON c.owner_id = u.id
     WHERE $where
     ORDER BY c.created_at DESC",
    $params
);

// ── Edit prefill ──────────────────────────────────────────────────────────────
$editContact = null;
$openModal   = false;
if (isset($_GET['edit'])) {
    $editId      = (int)$_GET['edit'];
    $editContact = DB::fetch('SELECT * FROM crm_contacts WHERE id = ?', [$editId]);
    if ($editContact) {
        $openModal = true;
    }
}

// ── Admin users for owner dropdown ───────────────────────────────────────────
$adminUsers = DB::fetchAll("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name");

$pageTitle = 'CRM Contacts';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">CRM Contacts</h4>
            <p class="text-muted small mb-0"><?= number_format(count($contacts)) ?> contact<?= count($contacts) != 1 ? 's' : '' ?> found</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/crm.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>CRM Dashboard
            </a>
            <a href="/admin/crm-deals.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-kanban me-1"></i>Deals
            </a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal">
                <i class="bi bi-plus-circle me-1"></i>Add Contact
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="admin-card rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label text-muted small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm bg-dark border-secondary text-white"
                       placeholder="Name, email, company…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Statuses</option>
                    <?php foreach (['lead', 'prospect', 'customer', 'churned', 'blocked'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Source</label>
                <select name="source" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Sources</option>
                    <?php foreach (['website', 'referral', 'social', 'cold_outreach', 'event', 'other'] as $src): ?>
                    <option value="<?= $src ?>" <?= $filterSource === $src ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $src)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
            <?php if ($filterStatus || $filterSource || $search): ?>
            <div class="col-sm-6 col-md-1">
                <a href="crm-contacts.php" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Contacts Table -->
    <div class="admin-card rounded-4 p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="border-bottom border-secondary">
                        <th class="px-4 py-3 text-muted small fw-semibold">Name / Email</th>
                        <th class="py-3 text-muted small fw-semibold">Company / Title</th>
                        <th class="py-3 text-muted small fw-semibold">Source</th>
                        <th class="py-3 text-muted small fw-semibold">Status</th>
                        <th class="py-3 text-muted small fw-semibold">Last Contacted</th>
                        <th class="py-3 text-muted small fw-semibold">Owner</th>
                        <th class="py-3 text-muted small fw-semibold text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                    <?php
                        $statusColors = [
                            'lead'     => 'secondary',
                            'prospect' => 'info',
                            'customer' => 'success',
                            'churned'  => 'warning',
                            'blocked'  => 'danger',
                        ];
                        $sourceColors = [
                            'website'      => 'primary',
                            'referral'     => 'success',
                            'social'       => 'info',
                            'cold_outreach'=> 'warning',
                            'event'        => 'secondary',
                            'other'        => 'secondary',
                        ];
                        $sBadge  = $statusColors[$contact['status']] ?? 'secondary';
                        $srcBadge = $sourceColors[$contact['source']] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-white fw-semibold small">
                                <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                            </div>
                            <div class="text-muted" style="font-size:11px">
                                <?= htmlspecialchars($contact['email']) ?>
                            </div>
                            <?php if ($contact['phone']): ?>
                            <div class="text-muted" style="font-size:11px">
                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($contact['phone']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3">
                            <div class="text-white small"><?= htmlspecialchars($contact['company'] ?: '—') ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($contact['job_title'] ?: '') ?></div>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-<?= $srcBadge ?> bg-opacity-75">
                                <?= ucfirst(str_replace('_', ' ', $contact['source'])) ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-<?= $sBadge ?>">
                                <?= ucfirst($contact['status']) ?>
                            </span>
                        </td>
                        <td class="py-3 text-muted small">
                            <?= $contact['last_contacted_at']
                                ? date('d M Y', strtotime($contact['last_contacted_at']))
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="py-3 text-muted small">
                            <?= htmlspecialchars($contact['owner_name'] ?? '—') ?>
                        </td>
                        <td class="py-3 pe-4 text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="crm-contacts.php?edit=<?= (int)$contact['id'] ?>"
                                   class="btn btn-outline-secondary btn-sm py-0 px-2"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="crm-deals.php?contact=<?= (int)$contact['id'] ?>"
                                   class="btn btn-outline-info btn-sm py-0 px-2"
                                   title="View Deals">
                                    <i class="bi bi-kanban"></i>
                                </a>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($contact['first_name'] . ' ' . $contact['last_name'])) ?>? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>
                            No contacts found.
                            <?php if ($filterStatus || $filterSource || $search): ?>
                            <a href="crm-contacts.php" class="d-block mt-1 small">Clear filters</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="contactModalLabel">
                    <i class="bi bi-person-plus me-2"></i>
                    <span id="contactModalTitleText"><?= $editContact ? 'Edit Contact' : 'Add Contact' ?></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="contactId" value="<?= $editContact ? (int)$editContact['id'] : '' ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control bg-dark border-secondary text-white" required
                                   value="<?= htmlspecialchars($editContact['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Last Name</label>
                            <input type="text" name="last_name" class="form-control bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($editContact['last_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control bg-dark border-secondary text-white" required
                                   value="<?= htmlspecialchars($editContact['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Phone</label>
                            <input type="text" name="phone" class="form-control bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($editContact['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Company</label>
                            <input type="text" name="company" class="form-control bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($editContact['company'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Job Title</label>
                            <input type="text" name="job_title" class="form-control bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($editContact['job_title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Source</label>
                            <select name="source" class="form-select bg-dark border-secondary text-white">
                                <?php foreach (['website', 'referral', 'social', 'cold_outreach', 'event', 'other'] as $src): ?>
                                <option value="<?= $src ?>"
                                    <?= ($editContact['source'] ?? 'other') === $src ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $src)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <?php foreach (['lead', 'prospect', 'customer', 'churned', 'blocked'] as $st): ?>
                                <option value="<?= $st ?>"
                                    <?= ($editContact['status'] ?? 'lead') === $st ? 'selected' : '' ?>>
                                    <?= ucfirst($st) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Owner</label>
                            <select name="owner_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($adminUsers as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"
                                    <?= isset($editContact['owner_id']) && $editContact['owner_id'] == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Tags <span class="text-muted fw-normal">(comma-separated)</span></label>
                            <input type="text" name="tags" class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. vip, newsletter, demo"
                                   value="<?= htmlspecialchars($editContact['tags'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" class="form-control bg-dark border-secondary text-white" rows="3"
                                      placeholder="Internal notes about this contact…"><?= htmlspecialchars($editContact['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($openModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('contactModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php require_once '../includes/admin-footer.php'; ?>

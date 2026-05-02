<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $contactId   = (int)($_POST['contact_id'] ?? 0);
        $ownerId     = (int)($_POST['owner_id'] ?? 0) ?: null;
        $stage       = $_POST['stage'] ?? 'lead';
        $lostReason  = $stage === 'closed_lost' ? htmlspecialchars(trim($_POST['lost_reason'] ?? '')) : null;

        $data = [
            'title'               => htmlspecialchars(trim($_POST['title'] ?? '')),
            'contact_id'          => $contactId,
            'value'               => (float)($_POST['value'] ?? 0),
            'currency'            => htmlspecialchars(trim($_POST['currency'] ?? 'USD')),
            'stage'               => $stage,
            'probability'         => (int)($_POST['probability'] ?? 10),
            'expected_close'      => trim($_POST['expected_close'] ?? '') ?: null,
            'owner_id'            => $ownerId,
            'notes'               => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'lost_reason'         => $lostReason,
            'updated_at'          => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            DB::update('crm_deals', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            DB::insert('crm_deals', $data);
        }

        $redirect = isset($_GET['contact']) ? 'crm-deals.php?contact=' . (int)$_GET['contact'] : 'crm-deals.php';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM crm_deals WHERE id = ?', [$id]);
        }
        $redirect = isset($_POST['contact_filter']) && $_POST['contact_filter']
            ? 'crm-deals.php?contact=' . (int)$_POST['contact_filter']
            : 'crm-deals.php';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'move') {
        $id    = (int)($_POST['id']    ?? 0);
        $stage = $_POST['stage'] ?? '';
        $validStages = ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        if ($id > 0 && in_array($stage, $validStages, true)) {
            DB::update('crm_deals',
                ['stage' => $stage, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?', [$id]
            );
        }
        header('Location: crm-deals.php');
        exit;
    }
}

// ── View toggle ───────────────────────────────────────────────────────────────
$view = ($_GET['view'] ?? 'kanban') === 'table' ? 'table' : 'kanban';

// ── Contact filter ────────────────────────────────────────────────────────────
$filterContactId = (int)($_GET['contact'] ?? 0);

// ── Fetch deals ───────────────────────────────────────────────────────────────
$whereClause  = '1=1';
$queryParams  = [];
if ($filterContactId > 0) {
    $whereClause  .= ' AND d.contact_id = ?';
    $queryParams[] = $filterContactId;
}

$deals = DB::fetchAll(
    "SELECT d.*,
            CONCAT(c.first_name,' ',c.last_name) AS contact_name,
            c.company AS contact_company,
            u.name AS owner_name
     FROM crm_deals d
     LEFT JOIN crm_contacts c ON d.contact_id = c.id
     LEFT JOIN users u ON d.owner_id = u.id
     WHERE $whereClause
     ORDER BY d.created_at DESC",
    $queryParams
);

// ── Summary stats ─────────────────────────────────────────────────────────────
$totalPipeline = 0.0;
$totalWon      = 0.0;
$totalLost     = 0.0;
foreach ($deals as $d) {
    if (!in_array($d['stage'], ['closed_won', 'closed_lost'], true)) {
        $totalPipeline += (float)$d['value'];
    }
    if ($d['stage'] === 'closed_won')  $totalWon  += (float)$d['value'];
    if ($d['stage'] === 'closed_lost') $totalLost += (float)$d['value'];
}

// ── Group deals by stage for Kanban ──────────────────────────────────────────
$stages = ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
$stageColors = [
    'lead'        => 'secondary',
    'qualified'   => 'info',
    'proposal'    => 'primary',
    'negotiation' => 'warning',
    'closed_won'  => 'success',
    'closed_lost' => 'danger',
];
$byStage = array_fill_keys($stages, []);
foreach ($deals as $d) {
    $byStage[$d['stage']][] = $d;
}

// ── Edit prefill ──────────────────────────────────────────────────────────────
$editDeal  = null;
$openModal = false;
if (isset($_GET['edit'])) {
    $editId   = (int)$_GET['edit'];
    $editDeal = DB::fetch('SELECT * FROM crm_deals WHERE id = ?', [$editId]);
    if ($editDeal) {
        $openModal = true;
    }
}

// ── Contacts + admin users for dropdowns ─────────────────────────────────────
$allContacts = DB::fetchAll(
    "SELECT id, CONCAT(first_name,' ',last_name) AS full_name, company FROM crm_contacts ORDER BY first_name"
);
$adminUsers = DB::fetchAll("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name");

// Filter contact info
$filterContact = null;
if ($filterContactId > 0) {
    $filterContact = DB::fetch(
        "SELECT CONCAT(first_name,' ',last_name) AS full_name, company FROM crm_contacts WHERE id = ?",
        [$filterContactId]
    );
}

$pageTitle = 'CRM Deals';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">
                Deal Pipeline
                <?php if ($filterContact): ?>
                <span class="text-muted fs-6 fw-normal ms-2">
                    — <?= htmlspecialchars($filterContact['full_name']) ?>
                    <?= $filterContact['company'] ? '(' . htmlspecialchars($filterContact['company']) . ')' : '' ?>
                </span>
                <?php endif; ?>
            </h4>
            <p class="text-muted small mb-0"><?= number_format(count($deals)) ?> deal<?= count($deals) != 1 ? 's' : '' ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($filterContactId): ?>
            <a href="crm-deals.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle me-1"></i>All Deals
            </a>
            <?php endif; ?>
            <a href="/admin/crm.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>CRM Dashboard
            </a>
            <a href="/admin/crm-contacts.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i>Contacts
            </a>
            <!-- View Toggle -->
            <div class="btn-group btn-group-sm">
                <a href="crm-deals.php?view=kanban<?= $filterContactId ? '&contact=' . $filterContactId : '' ?>"
                   class="btn <?= $view === 'kanban' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-kanban me-1"></i>Kanban
                </a>
                <a href="crm-deals.php?view=table<?= $filterContactId ? '&contact=' . $filterContactId : '' ?>"
                   class="btn <?= $view === 'table' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-table me-1"></i>Table
                </a>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dealModal">
                <i class="bi bi-plus-circle me-1"></i>Add Deal
            </button>
        </div>
    </div>

    <!-- Summary Bar -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-funnel me-1"></i>Open Pipeline</div>
                <div class="fs-4 fw-bold text-white"><?= APP_CURRENCY ?><?= number_format($totalPipeline, 0) ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-trophy me-1"></i>Total Won</div>
                <div class="fs-4 fw-bold text-success"><?= APP_CURRENCY ?><?= number_format($totalWon, 0) ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-x-circle me-1"></i>Total Lost</div>
                <div class="fs-4 fw-bold text-danger"><?= APP_CURRENCY ?><?= number_format($totalLost, 0) ?></div>
            </div>
        </div>
    </div>

    <?php if ($view === 'kanban'): ?>
    <!-- ── Kanban View ──────────────────────────────────────────────────────── -->
    <div class="kanban-board d-flex gap-3 overflow-auto pb-3">
        <?php foreach ($stages as $stage):
            $color       = $stageColors[$stage];
            $stageDeals  = $byStage[$stage];
            $stageTotal  = array_sum(array_column($stageDeals, 'value'));
        ?>
        <div class="kanban-col flex-shrink-0" style="width:260px;">
            <!-- Column Header -->
            <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                <span class="badge bg-<?= $color ?> fs-6 px-3 py-2">
                    <?= ucfirst(str_replace('_', ' ', $stage)) ?>
                    <span class="ms-1 opacity-75"><?= count($stageDeals) ?></span>
                </span>
                <span class="text-muted small"><?= APP_CURRENCY ?><?= number_format($stageTotal, 0) ?></span>
            </div>
            <!-- Deal Cards -->
            <div class="d-flex flex-column gap-2">
                <?php foreach ($stageDeals as $deal): ?>
                <div class="admin-card rounded-3 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="text-white small fw-semibold" style="line-height:1.3">
                            <?= htmlspecialchars($deal['title']) ?>
                        </div>
                        <div class="d-flex gap-1 ms-2 flex-shrink-0">
                            <a href="crm-deals.php?edit=<?= (int)$deal['id'] ?><?= $filterContactId ? '&contact=' . $filterContactId : '' ?>&view=kanban"
                               class="btn btn-outline-secondary btn-sm py-0 px-1" style="font-size:10px" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this deal?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$deal['id'] ?>">
                                <input type="hidden" name="contact_filter" value="<?= $filterContactId ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size:10px" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php if ($deal['contact_company'] || $deal['contact_name']): ?>
                    <div class="text-muted" style="font-size:11px">
                        <i class="bi bi-building me-1"></i>
                        <?= htmlspecialchars($deal['contact_company'] ?: $deal['contact_name']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="text-white fw-bold small"><?= APP_CURRENCY ?><?= number_format($deal['value'], 0) ?></span>
                        <span class="badge bg-<?= $color ?> bg-opacity-50 text-white" style="font-size:10px">
                            <?= (int)$deal['probability'] ?>%
                        </span>
                    </div>
                    <?php if ($deal['expected_close']): ?>
                    <div class="text-muted mt-1" style="font-size:10px">
                        <i class="bi bi-calendar me-1"></i><?= date('d M Y', strtotime($deal['expected_close'])) ?>
                    </div>
                    <?php endif; ?>
                    <!-- Move to stage form -->
                    <div class="mt-2">
                        <form method="POST" class="d-flex align-items-center gap-1">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="id" value="<?= (int)$deal['id'] ?>">
                            <select name="stage" class="form-select form-select-sm bg-dark border-secondary text-muted"
                                    style="font-size:10px;padding:2px 6px;"
                                    onchange="this.form.submit()">
                                <?php foreach ($stages as $s): ?>
                                <option value="<?= $s ?>" <?= $s === $deal['stage'] ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($stageDeals)): ?>
                <div class="text-center text-muted py-4" style="font-size:12px;border:1px dashed rgba(255,255,255,0.1);border-radius:8px;">
                    No deals
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ── Table View ──────────────────────────────────────────────────────── -->
    <div class="admin-card rounded-4 p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="border-bottom border-secondary">
                        <th class="px-4 py-3 text-muted small fw-semibold">Title</th>
                        <th class="py-3 text-muted small fw-semibold">Contact / Company</th>
                        <th class="py-3 text-muted small fw-semibold">Value</th>
                        <th class="py-3 text-muted small fw-semibold">Stage</th>
                        <th class="py-3 text-muted small fw-semibold">Probability</th>
                        <th class="py-3 text-muted small fw-semibold">Expected Close</th>
                        <th class="py-3 text-muted small fw-semibold">Owner</th>
                        <th class="py-3 text-muted small fw-semibold text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deals as $deal): ?>
                    <?php $color = $stageColors[$deal['stage']] ?? 'secondary'; ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-white small fw-semibold"><?= htmlspecialchars($deal['title']) ?></div>
                            <?php if ($deal['notes']): ?>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars(mb_strimwidth($deal['notes'], 0, 60, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3">
                            <div class="text-white small">
                                <a href="crm-contacts.php?edit=<?= (int)$deal['contact_id'] ?>"
                                   class="text-info text-decoration-none">
                                    <?= htmlspecialchars($deal['contact_name'] ?? '—') ?>
                                </a>
                            </div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($deal['contact_company'] ?? '') ?></div>
                        </td>
                        <td class="py-3">
                            <span class="text-white fw-semibold small"><?= APP_CURRENCY ?><?= number_format($deal['value'], 0) ?></span>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-<?= $color ?>">
                                <?= ucfirst(str_replace('_', ' ', $deal['stage'])) ?>
                            </span>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:6px;background:#1e1e2e;min-width:60px;">
                                    <div class="progress-bar bg-<?= $color ?>"
                                         style="width:<?= (int)$deal['probability'] ?>%"></div>
                                </div>
                                <span class="text-muted small"><?= (int)$deal['probability'] ?>%</span>
                            </div>
                        </td>
                        <td class="py-3 text-muted small">
                            <?= $deal['expected_close']
                                ? date('d M Y', strtotime($deal['expected_close']))
                                : '—' ?>
                        </td>
                        <td class="py-3 text-muted small"><?= htmlspecialchars($deal['owner_name'] ?? '—') ?></td>
                        <td class="py-3 pe-4 text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="crm-deals.php?edit=<?= (int)$deal['id'] ?>&view=table<?= $filterContactId ? '&contact=' . $filterContactId : '' ?>"
                                   class="btn btn-outline-secondary btn-sm py-0 px-2" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($deal['title'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$deal['id'] ?>">
                                    <input type="hidden" name="contact_filter" value="<?= $filterContactId ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($deals)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-kanban fs-2 d-block mb-2 opacity-25"></i>
                            No deals found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add / Edit Deal Modal -->
<div class="modal fade" id="dealModal" tabindex="-1" aria-labelledby="dealModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="dealModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>
                    <?= $editDeal ? 'Edit Deal' : 'Add Deal' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editDeal ? (int)$editDeal['id'] : '' ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Deal Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required
                                   placeholder="e.g. AI Agent Suite — Acme Corp"
                                   value="<?= htmlspecialchars($editDeal['title'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Contact <span class="text-danger">*</span></label>
                            <select name="contact_id" class="form-select bg-dark border-secondary text-white" required>
                                <option value="">— Select contact —</option>
                                <?php foreach ($allContacts as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?php
                                        $selectedContact = $editDeal
                                            ? ($editDeal['contact_id'] == $c['id'])
                                            : ($filterContactId == $c['id']);
                                        echo $selectedContact ? 'selected' : '';
                                    ?>>
                                    <?= htmlspecialchars($c['full_name']) ?>
                                    <?= $c['company'] ? '(' . htmlspecialchars($c['company']) . ')' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Value</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted"><?= APP_CURRENCY ?></span>
                                <input type="number" name="value" step="0.01" min="0"
                                       class="form-control bg-dark border-secondary text-white"
                                       placeholder="0.00"
                                       value="<?= $editDeal ? number_format((float)$editDeal['value'], 2, '.', '') : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Currency</label>
                            <input type="text" name="currency" class="form-control bg-dark border-secondary text-white"
                                   maxlength="3" placeholder="USD"
                                   value="<?= htmlspecialchars($editDeal['currency'] ?? 'USD') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Stage</label>
                            <select name="stage" id="dealStage" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($stages as $s): ?>
                                <option value="<?= $s ?>"
                                    <?= ($editDeal['stage'] ?? 'lead') === $s ? 'selected' : '' ?>
                                    data-prob="<?= ['lead'=>10,'qualified'=>30,'proposal'=>60,'negotiation'=>80,'closed_won'=>100,'closed_lost'=>0][$s] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Probability (%)</label>
                            <input type="number" name="probability" id="dealProbability"
                                   class="form-control bg-dark border-secondary text-white"
                                   min="0" max="100"
                                   value="<?= $editDeal ? (int)$editDeal['probability'] : 10 ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Expected Close Date</label>
                            <input type="date" name="expected_close" class="form-control bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($editDeal['expected_close'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Owner</label>
                            <select name="owner_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($adminUsers as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"
                                    <?= isset($editDeal['owner_id']) && $editDeal['owner_id'] == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="lostReasonWrap"
                             style="<?= ($editDeal && $editDeal['stage'] === 'closed_lost') ? '' : 'display:none' ?>">
                            <label class="form-label text-muted small">Lost Reason</label>
                            <input type="text" name="lost_reason" class="form-control bg-dark border-secondary text-white"
                                   placeholder="Why was this deal lost?"
                                   value="<?= htmlspecialchars($editDeal['lost_reason'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" class="form-control bg-dark border-secondary text-white" rows="3"
                                      placeholder="Deal notes…"><?= htmlspecialchars($editDeal['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Deal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$probMap = json_encode(['lead'=>10,'qualified'=>30,'proposal'=>60,'negotiation'=>80,'closed_won'=>100,'closed_lost'=>0]);
$extraScripts = '
<script>
(function () {
    // Stage → probability auto-suggest
    const probMap = ' . $probMap . ';
    const stageEl = document.getElementById("dealStage");
    const probEl  = document.getElementById("dealProbability");
    const lostWrap = document.getElementById("lostReasonWrap");

    function syncStage() {
        if (stageEl && probEl) {
            probEl.value = probMap[stageEl.value] ?? 10;
        }
        if (lostWrap) {
            lostWrap.style.display = (stageEl && stageEl.value === "closed_lost") ? "" : "none";
        }
    }

    if (stageEl) {
        stageEl.addEventListener("change", syncStage);
    }
' . ($openModal ? '
    document.addEventListener("DOMContentLoaded", function () {
        var modal = new bootstrap.Modal(document.getElementById("dealModal"));
        modal.show();
    });
' : '') . '
})();
</script>
';
require_once '../includes/admin-footer.php';
?>

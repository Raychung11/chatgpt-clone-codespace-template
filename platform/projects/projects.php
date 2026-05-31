<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/projectos.php';

try { ProjectOS::ensureTables(); } catch (\Throwable $e) {}
Auth::requireLogin();

$userId = Auth::id();
$flash  = ['type' => '', 'msg' => ''];

// ── POST Actions ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Create ──────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $name       = trim($_POST['name']       ?? '');
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $client     = trim($_POST['client']     ?? '');
        $department = trim($_POST['department'] ?? '');
        $managerTxt = trim($_POST['manager_name'] ?? '');
        $budget     = $_POST['budget'] !== '' ? (float)$_POST['budget'] : null;
        $startDate  = $_POST['start_date'] ?? null ?: null;
        $endDate    = $_POST['end_date']   ?? null ?: null;
        $status     = $_POST['status']     ?? 'planning';
        $desc       = trim($_POST['description'] ?? '');

        if ($name === '') {
            $flash = ['type' => 'danger', 'msg' => 'Project name is required.'];
        } else {
            // Resolve manager_id if manager_name matches a user
            $managerId = null;
            if ($managerTxt !== '') {
                $mRow = DB::fetch("SELECT id FROM users WHERE name = ? LIMIT 1", [$managerTxt]);
                if ($mRow) {
                    $managerId = (int) $mRow['id'];
                }
            }

            $projectId = DB::insert('projects', [
                'name'           => $name,
                'code'           => $code,
                'client'         => $client ?: null,
                'department'     => $department ?: null,
                'manager_id'     => $managerId,
                'budget'         => $budget,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'status'         => $status,
                'completion_pct' => 0,
                'description'    => $desc ?: null,
                'created_by'     => $userId,
            ]);

            // Add creator as manager in project_members
            try {
                DB::insert('project_members', [
                    'project_id' => $projectId,
                    'user_id'    => $userId,
                    'role'       => 'manager',
                ]);
            } catch (\Throwable $e) {
                // Ignore duplicate
            }

            ProjectOS::log((int)$projectId, 'project', (int)$projectId, 'created', "Project \"{$name}\" created.");
            $flash = ['type' => 'success', 'msg' => "Project <strong>" . htmlspecialchars($name) . "</strong> created successfully."];
        }
    }

    // ── Update ──────────────────────────────────────────────────────────────
    elseif ($action === 'update') {
        $projectId  = (int)($_POST['project_id'] ?? 0);
        $name       = trim($_POST['name']         ?? '');
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $client     = trim($_POST['client']       ?? '');
        $department = trim($_POST['department']   ?? '');
        $managerTxt = trim($_POST['manager_name'] ?? '');
        $budget     = $_POST['budget'] !== '' ? (float)$_POST['budget'] : null;
        $startDate  = $_POST['start_date'] ?? null ?: null;
        $endDate    = $_POST['end_date']   ?? null ?: null;
        $status     = $_POST['status']     ?? 'planning';
        $completionPct = (int)($_POST['completion_pct'] ?? 0);
        $desc       = trim($_POST['description']  ?? '');

        if ($projectId < 1 || $name === '') {
            $flash = ['type' => 'danger', 'msg' => 'Invalid project or missing name.'];
        } else {
            // Verify ownership or membership
            $owned = DB::fetch(
                "SELECT id FROM projects WHERE id = ? AND (created_by = ? OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
                [$projectId, $userId, $userId]
            );

            if (!$owned) {
                $flash = ['type' => 'danger', 'msg' => 'You do not have permission to edit this project.'];
            } else {
                $managerId = null;
                if ($managerTxt !== '') {
                    $mRow = DB::fetch("SELECT id FROM users WHERE name = ? LIMIT 1", [$managerTxt]);
                    if ($mRow) {
                        $managerId = (int)$mRow['id'];
                    }
                }

                DB::update('projects', [
                    'name'           => $name,
                    'code'           => $code,
                    'client'         => $client     ?: null,
                    'department'     => $department ?: null,
                    'manager_id'     => $managerId,
                    'budget'         => $budget,
                    'start_date'     => $startDate,
                    'end_date'       => $endDate,
                    'status'         => $status,
                    'completion_pct' => max(0, min(100, $completionPct)),
                    'description'    => $desc ?: null,
                ], 'id = ?', [$projectId]);

                ProjectOS::log($projectId, 'project', $projectId, 'updated', "Project \"{$name}\" updated.");
                $flash = ['type' => 'success', 'msg' => "Project <strong>" . htmlspecialchars($name) . "</strong> updated successfully."];
            }
        }
    }

    // ── Delete ──────────────────────────────────────────────────────────────
    elseif ($action === 'delete') {
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($projectId < 1) {
            $flash = ['type' => 'danger', 'msg' => 'Invalid project.'];
        } else {
            $row = DB::fetch("SELECT id, name FROM projects WHERE id = ? AND created_by = ?", [$projectId, $userId]);

            if (!$row) {
                $flash = ['type' => 'danger', 'msg' => 'Project not found or you are not the creator.'];
            } else {
                DB::query("DELETE FROM projects WHERE id = ?", [$projectId]);
                $flash = ['type' => 'success', 'msg' => "Project <strong>" . htmlspecialchars($row['name']) . "</strong> deleted."];
            }
        }
    }
}

// ── GET: Load projects ────────────────────────────────────────────────────────

$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['q']      ?? '');

$params     = [$userId, $userId];
$whereParts = ["(p.created_by = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?))"];

if ($statusFilter !== '') {
    $whereParts[] = "p.status = ?";
    $params[]     = $statusFilter;
}

if ($search !== '') {
    $whereParts[] = "(p.name LIKE ? OR p.code LIKE ? OR p.client LIKE ?)";
    $like         = '%' . $search . '%';
    $params[]     = $like;
    $params[]     = $like;
    $params[]     = $like;
}

$whereClause = implode(' AND ', $whereParts);

$projects = DB::fetchAll(
    "SELECT p.*,
            COALESCE(manager.name, '') AS manager_name
     FROM projects p
     LEFT JOIN users manager ON manager.id = p.manager_id
     WHERE {$whereClause}
     ORDER BY p.created_at DESC",
    $params
);

// Attach health scores
foreach ($projects as &$proj) {
    $proj['health'] = ProjectOS::getHealthScore((int)$proj['id']);
}
unset($proj);

// ── Page ──────────────────────────────────────────────────────────────────────

$pageTitle = 'Projects';
require '../includes/header.php';

$statusOptions = [
    ''          => 'All Statuses',
    'planning'  => 'Planning',
    'active'    => 'Active',
    'on_hold'   => 'On Hold',
    'delayed'   => 'Delayed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-0">
            <i class="bi bi-folder2-open me-2 text-primary"></i>Projects
        </h1>
        <p class="text-muted mb-0 small">Manage, track, and organise all projects.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle me-1"></i>New Project
        </button>
    </div>
</div>

<!-- ── Flash Message ───────────────────────────────────────────────────────── -->
<?php if ($flash['msg'] !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle' ?> me-2"></i>
    <?= $flash['msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Filter Bar ─────────────────────────────────────────────────────────── -->
<div class="glass-card p-3 mb-4">
    <form method="GET" action="projects.php" class="row g-2 align-items-end">
        <div class="col-12 col-sm-6 col-md-5">
            <label class="form-label small text-muted mb-1">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text"
                       name="q"
                       value="<?= htmlspecialchars($search) ?>"
                       class="form-control bg-dark border-secondary text-white"
                       placeholder="Name, code, or client…">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <label class="form-label small text-muted mb-1">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white">
                <?php foreach ($statusOptions as $val => $label): ?>
                <option value="<?= htmlspecialchars($val) ?>" <?= $statusFilter === $val ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a href="projects.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── Projects Table ─────────────────────────────────────────────────────── -->
<div class="glass-card p-0 overflow-hidden mb-5">
    <?php if (empty($projects)): ?>
    <div class="text-center py-5 px-4">
        <i class="bi bi-folder-x display-3 text-muted mb-3 d-block"></i>
        <h5 class="fw-semibold">No projects found</h5>
        <p class="text-muted mb-3">
            <?php if ($search !== '' || $statusFilter !== ''): ?>
                No projects match your current filters.
                <a href="projects.php" class="text-primary">Clear filters</a>
            <?php else: ?>
                Get started by creating your first project.
            <?php endif; ?>
        </p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle me-1"></i>New Project
        </button>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr class="border-bottom border-secondary border-opacity-50">
                    <th class="ps-4 py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Project</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Code</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Client</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Manager</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Status</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Start</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">End</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em; min-width:120px;">Completion</th>
                    <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Health</th>
                    <th class="pe-4 py-3 text-muted small fw-semibold text-uppercase text-end" style="letter-spacing:.05em;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $proj): ?>
                <?php
                    $h      = $proj['health'];
                    $score  = (int)$h['score'];
                    $pct    = max(0, min(100, (int)$proj['completion_pct']));
                    $pctBar = $pct >= 80 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger');
                    if ($score >= 80)      { $hBadge = 'bg-success'; }
                    elseif ($score >= 60)  { $hBadge = 'bg-warning text-dark'; }
                    else                   { $hBadge = 'bg-danger'; }
                ?>
                <tr>
                    <td class="ps-4 py-3">
                        <a href="view.php?id=<?= (int)$proj['id'] ?>" class="text-white fw-semibold text-decoration-none">
                            <?= htmlspecialchars($proj['name']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if (!empty($proj['code'])): ?>
                        <span class="badge bg-secondary bg-opacity-50 font-monospace">
                            <?= htmlspecialchars($proj['code']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($proj['client'] ?? '—') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($proj['manager_name'] ?: '—') ?></td>
                    <td>
                        <span class="badge <?= ProjectOS::statusBadge($proj['status']) ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $proj['status']))) ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= $proj['start_date'] ? htmlspecialchars($proj['start_date']) : '—' ?></td>
                    <td class="text-muted small"><?= $proj['end_date']   ? htmlspecialchars($proj['end_date'])   : '—' ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:5px; min-width:60px;">
                                <div class="progress-bar <?= $pctBar ?>" style="width:<?= $pct ?>%;"></div>
                            </div>
                            <span class="small text-muted" style="width:34px; text-align:right;"><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $hBadge ?>">
                            <?= $score ?> — <?= htmlspecialchars($h['label']) ?>
                        </span>
                    </td>
                    <td class="pe-4 text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <!-- View -->
                            <a href="view.php?id=<?= (int)$proj['id'] ?>"
                               class="btn btn-sm btn-outline-primary"
                               title="View project">
                                <i class="bi bi-eye"></i>
                            </a>

                            <!-- Edit -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary js-edit-btn"
                                    title="Edit project"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    data-id="<?= (int)$proj['id'] ?>"
                                    data-name="<?= htmlspecialchars($proj['name'], ENT_QUOTES) ?>"
                                    data-code="<?= htmlspecialchars($proj['code'] ?? '', ENT_QUOTES) ?>"
                                    data-client="<?= htmlspecialchars($proj['client'] ?? '', ENT_QUOTES) ?>"
                                    data-department="<?= htmlspecialchars($proj['department'] ?? '', ENT_QUOTES) ?>"
                                    data-manager="<?= htmlspecialchars($proj['manager_name'] ?? '', ENT_QUOTES) ?>"
                                    data-budget="<?= htmlspecialchars($proj['budget'] ?? '', ENT_QUOTES) ?>"
                                    data-start="<?= htmlspecialchars($proj['start_date'] ?? '', ENT_QUOTES) ?>"
                                    data-end="<?= htmlspecialchars($proj['end_date'] ?? '', ENT_QUOTES) ?>"
                                    data-status="<?= htmlspecialchars($proj['status'], ENT_QUOTES) ?>"
                                    data-pct="<?= (int)$proj['completion_pct'] ?>"
                                    data-desc="<?= htmlspecialchars($proj['description'] ?? '', ENT_QUOTES) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Delete -->
                            <?php if ((int)$proj['created_by'] === $userId): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Delete project"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal-<?= (int)$proj['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>

                            <!-- Delete confirm modal (lightweight, one per row) -->
                            <div class="modal fade" id="deleteModal-<?= (int)$proj['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content bg-dark border-secondary">
                                        <div class="modal-header border-secondary">
                                            <h5 class="modal-title text-danger">
                                                <i class="bi bi-trash me-2"></i>Delete Project
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-1">Are you sure you want to delete:</p>
                                            <p class="fw-bold text-white"><?= htmlspecialchars($proj['name']) ?></p>
                                            <p class="text-muted small mb-0">This will permanently delete the project and all associated meetings, tasks, issues, and activity logs.</p>
                                        </div>
                                        <div class="modal-footer border-secondary">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="POST" action="projects.php" class="d-inline">
                                                <input type="hidden" name="action"     value="delete">
                                                <input type="hidden" name="project_id" value="<?= (int)$proj['id'] ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="px-4 py-2 border-top border-secondary border-opacity-25 text-muted small">
        <?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?> found
    </div>
    <?php endif; ?>
</div>


<!-- ════════════════════════════════════════════════════════════════════════════
     CREATE PROJECT MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">

            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-semibold" id="createModalLabel">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Create New Project
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="projects.php">
                <input type="hidden" name="action" value="create">

                <div class="modal-body px-4 py-3">
                    <div class="row g-3">

                        <!-- Project Name -->
                        <div class="col-12 col-md-8">
                            <label class="form-label small text-muted mb-1" for="c_name">
                                Project Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="c_name"
                                   name="name"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. ERP Implementation Phase 2"
                                   required>
                        </div>

                        <!-- Code -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="c_code">
                                Project Code
                            </label>
                            <input type="text"
                                   id="c_code"
                                   name="code"
                                   class="form-control bg-dark border-secondary text-white font-monospace"
                                   placeholder="e.g. ERP-P2"
                                   maxlength="50">
                        </div>

                        <!-- Client -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="c_client">Client</label>
                            <input type="text"
                                   id="c_client"
                                   name="client"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Client or organisation name">
                        </div>

                        <!-- Department -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="c_department">Department</label>
                            <input type="text"
                                   id="c_department"
                                   name="department"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. IT, Operations, Finance">
                        </div>

                        <!-- Manager -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="c_manager">Project Manager</label>
                            <input type="text"
                                   id="c_manager"
                                   name="manager_name"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Manager full name">
                        </div>

                        <!-- Budget -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="c_budget">
                                Budget (<?= defined('APP_CURRENCY') ? htmlspecialchars(APP_CURRENCY) : '$' ?>)
                            </label>
                            <input type="number"
                                   id="c_budget"
                                   name="budget"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="0.00"
                                   step="0.01"
                                   min="0">
                        </div>

                        <!-- Start Date -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="c_start">Start Date</label>
                            <input type="date"
                                   id="c_start"
                                   name="start_date"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- End Date -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="c_end">End Date</label>
                            <input type="date"
                                   id="c_end"
                                   name="end_date"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- Status -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="c_status">Status</label>
                            <select id="c_status" name="status" class="form-select bg-dark border-secondary text-white">
                                <option value="planning" selected>Planning</option>
                                <option value="active">Active</option>
                                <option value="on_hold">On Hold</option>
                                <option value="delayed">Delayed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1" for="c_desc">Description</label>
                            <textarea id="c_desc"
                                      name="description"
                                      class="form-control bg-dark border-secondary text-white"
                                      rows="3"
                                      placeholder="Brief project overview, objectives, or scope…"></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Create Project
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════════════════
     EDIT PROJECT MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">

            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-semibold" id="editModalLabel">
                    <i class="bi bi-pencil me-2 text-warning"></i>Edit Project
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="projects.php" id="editForm">
                <input type="hidden" name="action"     value="update">
                <input type="hidden" name="project_id" id="e_project_id">

                <div class="modal-body px-4 py-3">
                    <div class="row g-3">

                        <!-- Project Name -->
                        <div class="col-12 col-md-8">
                            <label class="form-label small text-muted mb-1" for="e_name">
                                Project Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="e_name"
                                   name="name"
                                   class="form-control bg-dark border-secondary text-white"
                                   required>
                        </div>

                        <!-- Code -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="e_code">Project Code</label>
                            <input type="text"
                                   id="e_code"
                                   name="code"
                                   class="form-control bg-dark border-secondary text-white font-monospace"
                                   maxlength="50">
                        </div>

                        <!-- Client -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="e_client">Client</label>
                            <input type="text"
                                   id="e_client"
                                   name="client"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- Department -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="e_department">Department</label>
                            <input type="text"
                                   id="e_department"
                                   name="department"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- Manager -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="e_manager">Project Manager</label>
                            <input type="text"
                                   id="e_manager"
                                   name="manager_name"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- Budget -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1" for="e_budget">
                                Budget (<?= defined('APP_CURRENCY') ? htmlspecialchars(APP_CURRENCY) : '$' ?>)
                            </label>
                            <input type="number"
                                   id="e_budget"
                                   name="budget"
                                   class="form-control bg-dark border-secondary text-white"
                                   step="0.01"
                                   min="0">
                        </div>

                        <!-- Start Date -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="e_start">Start Date</label>
                            <input type="date"
                                   id="e_start"
                                   name="start_date"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- End Date -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="e_end">End Date</label>
                            <input type="date"
                                   id="e_end"
                                   name="end_date"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- Status -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="e_status">Status</label>
                            <select id="e_status" name="status" class="form-select bg-dark border-secondary text-white">
                                <option value="planning">Planning</option>
                                <option value="active">Active</option>
                                <option value="on_hold">On Hold</option>
                                <option value="delayed">Delayed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Completion % -->
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted mb-1" for="e_pct">
                                Completion % <span id="e_pct_val" class="text-white ms-1">0</span>
                            </label>
                            <input type="range"
                                   id="e_pct"
                                   name="completion_pct"
                                   class="form-range"
                                   min="0"
                                   max="100"
                                   step="5"
                                   value="0"
                                   oninput="document.getElementById('e_pct_val').textContent = this.value + '%'">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1" for="e_desc">Description</label>
                            <textarea id="e_desc"
                                      name="description"
                                      class="form-control bg-dark border-secondary text-white"
                                      rows="3"></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-semibold">
                        <i class="bi bi-check-circle me-1"></i>Save Changes
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>


<!-- ── JS: Populate Edit Modal ────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    document.querySelectorAll('.js-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = btn.dataset;

            document.getElementById('e_project_id').value = d.id      || '';
            document.getElementById('e_name').value        = d.name    || '';
            document.getElementById('e_code').value        = d.code    || '';
            document.getElementById('e_client').value      = d.client  || '';
            document.getElementById('e_department').value  = d.department || '';
            document.getElementById('e_manager').value     = d.manager || '';
            document.getElementById('e_budget').value      = d.budget  || '';
            document.getElementById('e_start').value       = d.start   || '';
            document.getElementById('e_end').value         = d.end     || '';
            document.getElementById('e_desc').value        = d.desc    || '';

            // Status dropdown
            var statusSel = document.getElementById('e_status');
            for (var i = 0; i < statusSel.options.length; i++) {
                if (statusSel.options[i].value === d.status) {
                    statusSel.selectedIndex = i;
                    break;
                }
            }

            // Completion range
            var pct = parseInt(d.pct, 10) || 0;
            document.getElementById('e_pct').value          = pct;
            document.getElementById('e_pct_val').textContent = pct + '%';
        });
    });
}());
</script>

<?php require '../includes/footer.php'; ?>

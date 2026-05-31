<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/projectos.php';

try { ProjectOS::ensureTables(); } catch (\Throwable $e) {}
Auth::requireLogin();

$userId = Auth::id();

// ── Stats ────────────────────────────────────────────────────────────────────

$totalProjects = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM projects
     WHERE created_by = ?
        OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)",
    [$userId, $userId]
)['cnt'];

$activeProjects = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM projects
     WHERE status = 'active'
       AND (created_by = ?
            OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId]
)['cnt'];

$delayedProjects = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM projects
     WHERE status = 'delayed'
       AND (created_by = ?
            OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId]
)['cnt'];

$completedProjects = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM projects
     WHERE status = 'completed'
       AND (created_by = ?
            OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId]
)['cnt'];

$overdueTasks = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM action_items
     WHERE due_date < CURDATE()
       AND status NOT IN ('completed', 'cancelled')
       AND project_id IN (
           SELECT id FROM projects
           WHERE created_by = ?
              OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)
       )",
    [$userId, $userId]
)['cnt'];

$openIssues = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM issues
     WHERE status NOT IN ('resolved', 'closed')
       AND project_id IN (
           SELECT id FROM projects
           WHERE created_by = ?
              OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)
       )",
    [$userId, $userId]
)['cnt'];

$criticalIssues = (int) DB::fetch(
    "SELECT COUNT(*) AS cnt
     FROM issues
     WHERE priority = 'critical'
       AND status NOT IN ('resolved', 'closed')
       AND project_id IN (
           SELECT id FROM projects
           WHERE created_by = ?
              OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)
       )",
    [$userId, $userId]
)['cnt'];

// ── Projects list ─────────────────────────────────────────────────────────────

$projects = DB::fetchAll(
    "SELECT p.*,
            manager.name  AS manager_name,
            manager.email AS manager_email
     FROM projects p
     LEFT JOIN users manager ON manager.id = p.manager_id
     WHERE p.created_by = ?
        OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)
     ORDER BY p.created_at DESC
     LIMIT 20",
    [$userId, $userId]
);

// Attach per-project health scores + quick counts
foreach ($projects as &$proj) {
    $proj['health'] = ProjectOS::getHealthScore($proj['id']);

    $proj['task_count'] = (int) DB::fetch(
        "SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ?",
        [$proj['id']]
    )['cnt'];

    $proj['issue_count'] = (int) DB::fetch(
        "SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND status NOT IN ('resolved','closed')",
        [$proj['id']]
    )['cnt'];
}
unset($proj);

// ── Recent Activity ───────────────────────────────────────────────────────────

$recentActivity = DB::fetchAll(
    "SELECT al.*, u.name AS actor_name
     FROM project_activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE al.project_id IN (
         SELECT id FROM projects
         WHERE created_by = ?
            OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)
     )
     ORDER BY al.created_at DESC
     LIMIT 20",
    [$userId, $userId]
);

// ── Page ──────────────────────────────────────────────────────────────────────

$pageTitle = 'ProjectOS';
require '../includes/header.php';

// Helper: health score colour class
function healthColour(int $score): string {
    if ($score >= 80) return 'text-success';
    if ($score >= 50) return 'text-warning';
    return 'text-danger';
}
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-0">ProjectOS™</h1>
        <p class="text-muted mb-0 small">AI-Powered Project Execution Operating System</p>
    </div>
    <div class="d-flex gap-2">
        <a href="projects.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>New Project
        </a>
        <a href="projects.php" class="btn btn-outline-secondary">
            <i class="bi bi-grid me-1"></i>All Projects
        </a>
    </div>
</div>

<!-- ── Stats Row ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Total Projects -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="bi bi-folder fs-5 text-primary"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1"><?= $totalProjects ?></div>
                    <div class="text-muted small">Total Projects</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="bi bi-play-circle fs-5 text-success"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-success"><?= $activeProjects ?></div>
                    <div class="text-muted small">Active</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delayed -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="bi bi-exclamation-triangle fs-5 text-danger"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-danger"><?= $delayedProjects ?></div>
                    <div class="text-muted small">Delayed</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overdue Tasks -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="bi bi-clock fs-5 text-warning"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-warning"><?= $overdueTasks ?></div>
                    <div class="text-muted small">Overdue Tasks</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Open Issues -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="bi bi-bug fs-5 text-danger"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-danger"><?= $openIssues ?></div>
                    <div class="text-muted small">Open Issues
                        <?php if ($criticalIssues > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $criticalIssues ?> critical</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Projects Grid ───────────────────────────────────────────────────────── -->
<h5 class="fw-semibold mb-3">
    <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Your Projects
</h5>

<?php if (empty($projects)): ?>
<!-- Empty State -->
<div class="glass-card p-5 text-center">
    <i class="bi bi-folder-plus display-3 text-muted mb-3 d-block"></i>
    <h4 class="fw-semibold mb-2">No projects yet</h4>
    <p class="text-muted mb-4">Start your first project and take control of your execution.</p>
    <a href="projects.php" class="btn btn-primary btn-lg">
        <i class="bi bi-plus-circle me-2"></i>Start Your First Project
    </a>
</div>
<?php else: ?>
<div class="row g-3 mb-5">
    <?php foreach ($projects as $proj): ?>
    <?php
        $healthData = $proj['health'];
        $healthScore = (int)($healthData['score'] ?? 0);
        $hColour     = healthColour($healthScore);
        $hColor      = $healthData['color'] ?? '#6b7280';
        $hLabel      = $healthData['label'] ?? 'No Data';
        $pct         = max(0, min(100, (int)($proj['completion_pct'] ?? 0)));
        $pctColour   = $pct >= 80 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger');
    ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="glass-card p-4 h-100 d-flex flex-column">

            <!-- Name + Code -->
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                    <h6 class="fw-bold mb-0">
                        <a href="view.php?id=<?= (int)$proj['id'] ?>" class="text-white text-decoration-none stretched-link-manual">
                            <?= htmlspecialchars($proj['name']) ?>
                        </a>
                    </h6>
                    <?php if (!empty($proj['code'])): ?>
                        <span class="badge bg-secondary bg-opacity-50 font-monospace small">
                            <?= htmlspecialchars($proj['code']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?= ProjectOS::statusBadge($proj['status'] ?? 'draft') ?>
            </div>

            <!-- Client -->
            <?php if (!empty($proj['client'])): ?>
            <div class="text-muted small mb-2">
                <i class="bi bi-building me-1"></i><?= htmlspecialchars($proj['client']) ?>
            </div>
            <?php endif; ?>

            <!-- Progress Bar -->
            <div class="mb-3">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Completion</span>
                    <span><?= $pct ?>%</span>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar <?= $pctColour ?>" style="width:<?= $pct ?>%;"></div>
                </div>
            </div>

            <!-- Health Score -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="fs-4 fw-bold" style="color:<?= $hColor ?>"><?= $healthScore ?></span>
                <div>
                    <div class="small fw-semibold <?= $hColour ?>">Health Score</div>
                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($hLabel) ?></div>
                </div>
            </div>

            <!-- Meta -->
            <div class="row g-1 small text-muted mb-3">
                <div class="col-6">
                    <i class="bi bi-person me-1"></i>
                    <?= htmlspecialchars($proj['manager_name'] ?? '—') ?>
                </div>
                <div class="col-6">
                    <i class="bi bi-calendar me-1"></i>
                    <?= $proj['start_date'] ? htmlspecialchars($proj['start_date']) : '—' ?>
                </div>
                <div class="col-6">
                    <i class="bi bi-check2-square me-1"></i>
                    <?= (int)$proj['task_count'] ?> task<?= $proj['task_count'] !== 1 ? 's' : '' ?>
                </div>
                <div class="col-6">
                    <i class="bi bi-flag me-1"></i>
                    <?= $proj['end_date'] ? htmlspecialchars($proj['end_date']) : '—' ?>
                </div>
            </div>

            <!-- Quick Stats + CTA -->
            <div class="mt-auto d-flex align-items-center justify-content-between">
                <span class="badge bg-danger bg-opacity-20 text-danger">
                    <i class="bi bi-bug me-1"></i><?= (int)$proj['issue_count'] ?> issue<?= $proj['issue_count'] !== 1 ? 's' : '' ?>
                </span>
                <a href="view.php?id=<?= (int)$proj['id'] ?>" class="btn btn-sm btn-outline-primary">
                    Open Project <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Recent Activity ─────────────────────────────────────────────────────── -->
<h5 class="fw-semibold mb-3">
    <i class="bi bi-activity me-2 text-primary"></i>Recent Activity
</h5>

<div class="glass-card p-4">
    <?php if (empty($recentActivity)): ?>
        <p class="text-muted mb-0 text-center py-3">No activity yet.</p>
    <?php else: ?>
    <ul class="list-unstyled mb-0">
        <?php foreach ($recentActivity as $i => $log): ?>
        <li class="d-flex gap-3 <?= $i < count($recentActivity) - 1 ? 'mb-3 pb-3 border-bottom border-secondary border-opacity-25' : '' ?>">
            <!-- Timeline dot -->
            <div class="d-flex flex-column align-items-center" style="width:20px;">
                <div class="rounded-circle bg-primary" style="width:10px;height:10px;margin-top:4px;flex-shrink:0;"></div>
                <?php if ($i < count($recentActivity) - 1): ?>
                <div class="flex-grow-1 border-start border-secondary border-opacity-25" style="width:1px;min-height:20px;"></div>
                <?php endif; ?>
            </div>
            <!-- Content -->
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                    <div>
                        <span class="fw-semibold small">
                            <?= htmlspecialchars(ucfirst($log['entity_type'] ?? '')) ?>
                        </span>
                        <span class="text-muted small mx-1">·</span>
                        <span class="small text-info">
                            <?= htmlspecialchars(ucfirst($log['action'] ?? '')) ?>
                        </span>
                        <?php if (!empty($log['actor_name'])): ?>
                            <span class="text-muted small ms-1">by <?= htmlspecialchars($log['actor_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-muted" style="font-size:.72rem;white-space:nowrap;">
                        <?= htmlspecialchars(date('M j, g:ia', strtotime($log['created_at']))) ?>
                    </span>
                </div>
                <?php if (!empty($log['note'])): ?>
                <div class="text-muted small mt-1"><?= htmlspecialchars($log['note']) ?></div>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>

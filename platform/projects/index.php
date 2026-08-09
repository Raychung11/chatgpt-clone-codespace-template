<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/projectos.php';

try { ProjectOS::ensureTables(); } catch (\Throwable $e) {}
Auth::requireLogin();

$userId = Auth::id();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalProjects = (int)(DB::fetch(
    "SELECT COUNT(*) AS cnt FROM projects
     WHERE created_by = ? OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)",
    [$userId, $userId])['cnt'] ?? 0);

$activeProjects = (int)(DB::fetch(
    "SELECT COUNT(*) AS cnt FROM projects
     WHERE status = 'active'
       AND (created_by = ? OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId])['cnt'] ?? 0);

$delayedProjects = (int)(DB::fetch(
    "SELECT COUNT(*) AS cnt FROM projects
     WHERE status = 'delayed'
       AND (created_by = ? OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId])['cnt'] ?? 0);

$overdueTasks = (int)(DB::fetch(
    "SELECT COUNT(*) AS cnt FROM action_items
     WHERE due_date < CURDATE() AND status NOT IN ('completed','cancelled')
       AND project_id IN (SELECT id FROM projects WHERE created_by = ?
           OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId])['cnt'] ?? 0);

$openIssues = (int)(DB::fetch(
    "SELECT COUNT(*) AS cnt FROM issues
     WHERE status NOT IN ('resolved','closed')
       AND project_id IN (SELECT id FROM projects WHERE created_by = ?
           OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId])['cnt'] ?? 0);

$criticalIssues = (int)(DB::fetch(
    "SELECT COUNT(*) AS cnt FROM issues
     WHERE severity = 'critical' AND status NOT IN ('resolved','closed')
       AND project_id IN (SELECT id FROM projects WHERE created_by = ?
           OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))",
    [$userId, $userId])['cnt'] ?? 0);

// ── Projects ──────────────────────────────────────────────────────────────────
$projects = DB::fetchAll(
    "SELECT p.*, u.name AS manager_name
     FROM projects p
     LEFT JOIN users u ON u.id = p.manager_id
     WHERE p.created_by = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)
     ORDER BY p.updated_at DESC LIMIT 24",
    [$userId, $userId]);

foreach ($projects as &$proj) {
    $proj['health']      = ProjectOS::getHealthScore($proj['id']);
    $proj['task_count']  = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ?", [$proj['id']])['cnt'] ?? 0);
    $proj['issue_count'] = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND status NOT IN ('resolved','closed')", [$proj['id']])['cnt'] ?? 0);
}
unset($proj);

// ── Recent Activity ───────────────────────────────────────────────────────────
$recentActivity = DB::fetchAll(
    "SELECT al.*, u.name AS actor_name, p.name AS project_name
     FROM project_activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     LEFT JOIN projects p ON p.id = al.project_id
     WHERE al.project_id IN (
         SELECT id FROM projects WHERE created_by = ?
            OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))
     ORDER BY al.created_at DESC LIMIT 15",
    [$userId, $userId]);

$pageTitle = 'ProjectOS™';
require '../includes/header.php';
?>

<style>
.pos-header {
    background: linear-gradient(135deg, rgba(139,92,246,0.08) 0%, rgba(99,102,241,0.04) 100%);
    border-bottom: 1px solid rgba(139,92,246,0.15);
    padding: 40px 0 32px;
    margin-bottom: 0;
}
.stat-card {
    background: #111118;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: border-color 0.2s;
}
.stat-card:hover { border-color: rgba(255,255,255,0.12); }
.stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.project-card {
    background: #111118;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.25s;
    display: flex; flex-direction: column;
    height: 100%;
}
.project-card:hover {
    border-color: rgba(139,92,246,0.35);
    box-shadow: 0 12px 40px rgba(139,92,246,0.1);
    transform: translateY(-3px);
}
.project-card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
.project-card-footer {
    padding: 14px 20px;
    border-top: 1px solid rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: space-between;
    gap: 8px;
}
.health-ring {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 13px; flex-shrink: 0;
}
.activity-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #6366f1; flex-shrink: 0; margin-top: 5px;
}
.activity-line {
    width: 1px; background: rgba(255,255,255,0.07);
    flex: 1; min-height: 16px; margin: 3px auto 0;
}
.pos-section-title {
    font-size: 13px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.08em; color: #6b7280; margin-bottom: 16px;
}
</style>

<!-- Page Header -->
<div class="pos-header">
    <div class="container">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <a href="/dashboard.php" class="text-muted text-decoration-none small">
                        <i class="bi bi-house me-1"></i>Dashboard
                    </a>
                    <span class="text-muted small">/</span>
                    <span class="text-muted small">ProjectOS™</span>
                </div>
                <h1 class="h3 fw-bold text-white mb-1">
                    <i class="bi bi-kanban me-2" style="color:#8b5cf6"></i>ProjectOS™
                </h1>
                <p class="text-muted mb-0" style="font-size:14px">
                    AI-Powered Project Execution Operating System
                    <span class="badge ms-2" style="background:rgba(139,92,246,0.2);color:#c4b5fd;font-size:10px;font-weight:500">
                        <?= $totalProjects ?> Project<?= $totalProjects !== 1 ? 's' : '' ?>
                    </span>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="projects.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="bi bi-list-ul me-1"></i>All Projects
                </a>
                <a href="projects.php?action=new" class="btn btn-sm px-4 fw-semibold"
                   style="background:linear-gradient(135deg,#8b5cf6,#6366f1);border:none;color:#fff">
                    <i class="bi bi-plus-circle me-1"></i>New Project
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-5">
    <?php
    $stats = [
        ['label'=>'Total Projects',  'value'=>$totalProjects,   'icon'=>'bi-folder2-open',        'bg'=>'rgba(99,102,241,0.15)',  'color'=>'#a5b4fc'],
        ['label'=>'Active',          'value'=>$activeProjects,  'icon'=>'bi-play-circle-fill',    'bg'=>'rgba(16,185,129,0.15)', 'color'=>'#6ee7b7'],
        ['label'=>'Delayed',         'value'=>$delayedProjects, 'icon'=>'bi-exclamation-circle',  'bg'=>'rgba(239,68,68,0.15)',  'color'=>'#fca5a5'],
        ['label'=>'Overdue Tasks',   'value'=>$overdueTasks,    'icon'=>'bi-clock-history',        'bg'=>'rgba(245,158,11,0.15)', 'color'=>'#fcd34d'],
        ['label'=>'Open Issues',     'value'=>$openIssues,      'icon'=>'bi-bug',                  'bg'=>'rgba(239,68,68,0.15)',  'color'=>'#fca5a5'],
        ['label'=>'Critical Issues', 'value'=>$criticalIssues,  'icon'=>'bi-shield-exclamation',  'bg'=>'rgba(239,68,68,0.2)',   'color'=>'#f87171'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $s['bg'] ?>">
                <i class="bi <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:18px"></i>
            </div>
            <div>
                <div class="fw-bold text-white" style="font-size:22px;line-height:1"><?= $s['value'] ?></div>
                <div class="text-muted" style="font-size:11px;margin-top:3px"><?= $s['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <!-- ── Projects Grid ──────────────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="pos-section-title mb-0">Your Projects</div>
            <?php if ($totalProjects > 6): ?>
            <a href="projects.php" class="text-muted small text-decoration-none">
                View all <i class="bi bi-arrow-right ms-1"></i>
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($projects)): ?>
        <!-- Empty State -->
        <div class="text-center py-5" style="background:#111118;border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:48px!important">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4"
                 style="width:72px;height:72px;background:rgba(139,92,246,0.12)">
                <i class="bi bi-kanban" style="font-size:28px;color:#8b5cf6"></i>
            </div>
            <h5 class="text-white fw-semibold mb-2">No projects yet</h5>
            <p class="text-muted mb-4" style="max-width:320px;margin:auto">
                Create your first project and turn meetings into execution with AI.
            </p>
            <a href="projects.php?action=new" class="btn px-5 fw-semibold"
               style="background:linear-gradient(135deg,#8b5cf6,#6366f1);border:none;color:#fff">
                <i class="bi bi-plus-circle me-2"></i>Start First Project
            </a>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach (array_slice($projects, 0, 6) as $proj):
                $h      = $proj['health'];
                $score  = (int)($h['score'] ?? 0);
                $hColor = $h['color'] ?? '#6b7280';
                $hLabel = $h['label'] ?? 'No Data';
                $pct    = max(0, min(100, (int)($proj['completion_pct'] ?? 0)));
                $pctBar = $pct >= 80 ? '#10b981' : ($pct >= 40 ? '#f59e0b' : '#ef4444');

                $statusColors = [
                    'planning'  => ['bg'=>'rgba(107,114,128,0.2)', 'color'=>'#9ca3af'],
                    'active'    => ['bg'=>'rgba(16,185,129,0.2)',  'color'=>'#6ee7b7'],
                    'on_hold'   => ['bg'=>'rgba(245,158,11,0.2)',  'color'=>'#fcd34d'],
                    'delayed'   => ['bg'=>'rgba(239,68,68,0.2)',   'color'=>'#fca5a5'],
                    'completed' => ['bg'=>'rgba(99,102,241,0.2)',  'color'=>'#a5b4fc'],
                    'cancelled' => ['bg'=>'rgba(31,41,55,0.8)',    'color'=>'#6b7280'],
                ];
                $sc = $statusColors[$proj['status']] ?? ['bg'=>'rgba(107,114,128,0.2)','color'=>'#9ca3af'];
            ?>
            <div class="col-sm-6">
                <div class="project-card">
                    <div class="project-card-body">
                        <!-- Header: name + status -->
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                            <div class="flex-grow-1 min-width-0">
                                <h6 class="text-white fw-semibold mb-0 text-truncate" style="font-size:14px">
                                    <?= htmlspecialchars($proj['name']) ?>
                                </h6>
                                <?php if (!empty($proj['code'])): ?>
                                <span class="text-muted font-monospace" style="font-size:11px">
                                    <?= htmlspecialchars($proj['code']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <span class="badge flex-shrink-0" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-size:10px;font-weight:600">
                                <?= ucfirst(str_replace('_',' ',$proj['status'])) ?>
                            </span>
                        </div>

                        <?php if (!empty($proj['client'])): ?>
                        <div class="text-muted mb-3" style="font-size:12px">
                            <i class="bi bi-building me-1"></i><?= htmlspecialchars($proj['client']) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:11px">
                                <span class="text-muted">Progress</span>
                                <span class="text-white fw-semibold"><?= $pct ?>%</span>
                            </div>
                            <div class="rounded-pill" style="height:5px;background:rgba(255,255,255,0.07)">
                                <div class="rounded-pill" style="height:5px;width:<?= $pct ?>%;background:<?= $pctBar ?>;transition:width 0.3s"></div>
                            </div>
                        </div>

                        <!-- Health + Meta -->
                        <div class="d-flex align-items-center justify-content-between mt-auto">
                            <div class="d-flex align-items-center gap-2">
                                <div class="health-ring" style="background:<?= $hColor ?>22;color:<?= $hColor ?>;border:2px solid <?= $hColor ?>44">
                                    <?= $score ?>
                                </div>
                                <div>
                                    <div style="font-size:11px;font-weight:600;color:<?= $hColor ?>"><?= htmlspecialchars($hLabel) ?></div>
                                    <div class="text-muted" style="font-size:10px">Health Score</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted" style="font-size:11px">
                                    <i class="bi bi-check2-square me-1"></i><?= $proj['task_count'] ?> tasks
                                </div>
                                <?php if ($proj['issue_count'] > 0): ?>
                                <div style="font-size:11px;color:#fca5a5">
                                    <i class="bi bi-bug me-1"></i><?= $proj['issue_count'] ?> issue<?= $proj['issue_count'] !== 1 ? 's' : '' ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="project-card-footer">
                        <div class="text-muted" style="font-size:11px">
                            <?php if (!empty($proj['manager_name'])): ?>
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($proj['manager_name']) ?>
                            <?php elseif (!empty($proj['end_date'])): ?>
                            <i class="bi bi-calendar me-1"></i><?= date('d M Y', strtotime($proj['end_date'])) ?>
                            <?php else: ?>
                            <i class="bi bi-calendar me-1"></i>No deadline
                            <?php endif; ?>
                        </div>
                        <a href="view.php?id=<?= (int)$proj['id'] ?>"
                           class="btn btn-sm px-3 fw-semibold"
                           style="background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.3);color:#c4b5fd;font-size:12px">
                            Open <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($projects) > 6): ?>
        <div class="text-center mt-3">
            <a href="projects.php" class="btn btn-outline-secondary btn-sm px-4">
                View all <?= count($projects) ?> projects <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ── Right Column ────────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Quick Links -->
        <div class="pos-section-title">Quick Actions</div>
        <div class="d-grid gap-2 mb-4">
            <a href="projects.php?action=new"
               class="btn text-start d-flex align-items-center gap-3 py-3 px-4"
               style="background:#111118;border:1px solid rgba(139,92,246,0.2);border-radius:12px;color:#c4b5fd">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(139,92,246,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-plus-circle" style="color:#8b5cf6"></i>
                </div>
                <div>
                    <div class="fw-semibold" style="font-size:13px">New Project</div>
                    <div class="text-muted" style="font-size:11px">Start tracking a new initiative</div>
                </div>
            </a>
            <a href="projects.php"
               class="btn text-start d-flex align-items-center gap-3 py-3 px-4"
               style="background:#111118;border:1px solid rgba(255,255,255,0.06);border-radius:12px;color:#9ca3af">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi bi-grid" style="color:#6366f1"></i>
                </div>
                <div>
                    <div class="fw-semibold" style="font-size:13px;color:#e5e7eb">All Projects</div>
                    <div class="text-muted" style="font-size:11px">Browse & manage projects</div>
                </div>
            </a>
        </div>

        <!-- Status Summary -->
        <?php if ($totalProjects > 0): ?>
        <div class="pos-section-title">Status Breakdown</div>
        <?php
        $statuses = DB::fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM projects
             WHERE created_by = ? OR id IN (SELECT project_id FROM project_members WHERE user_id = ?)
             GROUP BY status ORDER BY cnt DESC",
            [$userId, $userId]);
        $statusColors2 = [
            'active'=>'#10b981','planning'=>'#6366f1','on_hold'=>'#f59e0b',
            'delayed'=>'#ef4444','completed'=>'#8b5cf6','cancelled'=>'#6b7280',
        ];
        ?>
        <div style="background:#111118;border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:20px;margin-bottom:24px">
            <?php foreach ($statuses as $i => $st): ?>
            <div class="<?= $i < count($statuses)-1 ? 'mb-3' : '' ?>">
                <div class="d-flex justify-content-between mb-1" style="font-size:12px">
                    <span class="text-muted text-capitalize"><?= str_replace('_',' ',$st['status']) ?></span>
                    <span class="text-white fw-semibold"><?= $st['cnt'] ?></span>
                </div>
                <div class="rounded-pill" style="height:4px;background:rgba(255,255,255,0.06)">
                    <div class="rounded-pill" style="height:4px;width:<?= round($st['cnt']/$totalProjects*100) ?>%;background:<?= $statusColors2[$st['status']] ?? '#6b7280' ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="pos-section-title">Recent Activity</div>
        <div style="background:#111118;border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden">
            <?php if (empty($recentActivity)): ?>
            <div class="text-center py-5 text-muted" style="font-size:13px">
                <i class="bi bi-activity d-block mb-2 fs-4"></i>No activity yet
            </div>
            <?php else: ?>
            <div class="p-4">
                <?php
                $entityIcons = [
                    'project'   => ['icon'=>'bi-folder2','color'=>'#6366f1'],
                    'meeting'   => ['icon'=>'bi-camera-video','color'=>'#8b5cf6'],
                    'task'      => ['icon'=>'bi-check2-square','color'=>'#10b981'],
                    'issue'     => ['icon'=>'bi-bug','color'=>'#ef4444'],
                    'milestone' => ['icon'=>'bi-flag','color'=>'#f59e0b'],
                    'decision'  => ['icon'=>'bi-lightning','color'=>'#f59e0b'],
                ];
                foreach ($recentActivity as $i => $log):
                    $last = $i === count($recentActivity) - 1;
                    $ei = $entityIcons[$log['entity_type']] ?? ['icon'=>'bi-dot','color'=>'#6b7280'];
                ?>
                <div class="d-flex gap-3 <?= !$last ? 'mb-3' : '' ?>">
                    <div class="d-flex flex-column align-items-center" style="width:20px">
                        <div style="width:20px;height:20px;border-radius:50%;background:<?= $ei['color'] ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi <?= $ei['icon'] ?>" style="color:<?= $ei['color'] ?>;font-size:9px"></i>
                        </div>
                        <?php if (!$last): ?>
                        <div style="width:1px;background:rgba(255,255,255,0.06);flex:1;min-height:12px;margin-top:3px"></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 pb-<?= !$last ? '0' : '0' ?>" style="min-width:0">
                        <div class="text-muted" style="font-size:11px;line-height:1.4">
                            <span class="text-white" style="font-size:12px">
                                <?= htmlspecialchars(ucfirst($log['entity_type'] ?? '')) ?>
                            </span>
                            <span class="text-primary mx-1"><?= htmlspecialchars($log['action'] ?? '') ?></span>
                            <?php if (!empty($log['project_name'])): ?>
                            in <span class="text-muted"><?= htmlspecialchars($log['project_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($log['note'])): ?>
                        <div class="text-muted text-truncate" style="font-size:11px"><?= htmlspecialchars($log['note']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:10px;margin-top:2px">
                            <?php
                            $ts = strtotime($log['created_at']);
                            $diff = time() - $ts;
                            if ($diff < 60)           echo 'just now';
                            elseif ($diff < 3600)     echo round($diff/60) . 'm ago';
                            elseif ($diff < 86400)    echo round($diff/3600) . 'h ago';
                            else                      echo date('d M', $ts);
                            ?>
                            <?= !empty($log['actor_name']) ? '· ' . htmlspecialchars($log['actor_name']) : '' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /row -->
</div><!-- /container -->

<?php require '../includes/footer.php'; ?>

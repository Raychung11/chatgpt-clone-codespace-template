<?php
// ─────────────────────────────────────────────────────────────────────────────
// ProjectOS – Project Detail View
// /platform/projects/view.php
// ─────────────────────────────────────────────────────────────────────────────

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/projectos.php';

Auth::requireLogin();
ProjectOS::ensureTables();

$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) {
    header('Location: /projects/');
    exit;
}

$project = DB::fetch(
    'SELECT p.*, u.name AS manager_name
     FROM projects p
     LEFT JOIN users u ON u.id = p.manager_id
     WHERE p.id = ?',
    [$projectId]
);
if (!$project) {
    header('Location: /projects/');
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST ACTIONS
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Meeting
    if ($action === 'add_meeting') {
        $meetingId = DB::insert('meetings', [
            'project_id'   => $projectId,
            'title'        => trim($_POST['title'] ?? ''),
            'type'         => $_POST['type'] ?? 'weekly',
            'meeting_date' => $_POST['meeting_date'] ?? null,
            'meeting_time' => $_POST['meeting_time'] ?? null,
            'venue'        => trim($_POST['venue'] ?? ''),
            'objective'    => trim($_POST['objective'] ?? ''),
            'raw_notes'    => trim($_POST['raw_notes'] ?? ''),
            'created_by'   => Auth::id(),
        ]);
        // Insert attendees
        $attendeesRaw = trim($_POST['attendees'] ?? '');
        if ($attendeesRaw && $meetingId) {
            $names = array_filter(array_map('trim', explode(',', $attendeesRaw)));
            foreach ($names as $name) {
                if ($name !== '') {
                    DB::insert('meeting_attendees', [
                        'meeting_id' => $meetingId,
                        'name'       => $name,
                        'attended'   => 1,
                    ]);
                }
            }
        }
        if ($meetingId) {
            ProjectOS::log($projectId, 'meeting', (int)$meetingId, 'created', 'Meeting created: ' . trim($_POST['title'] ?? ''));
        }
        header("Location: view.php?id={$projectId}&tab=meetings");
        exit;
    }

    // 2. Approve Minutes
    if ($action === 'approve_minutes') {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        if ($meetingId) {
            DB::update(
                'meetings',
                ['minutes_approved' => 1, 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')],
                'id = ? AND project_id = ?',
                [$meetingId, $projectId]
            );
            ProjectOS::log($projectId, 'meeting', $meetingId, 'approved', 'Minutes approved');
        }
        header("Location: view.php?id={$projectId}&tab=meetings");
        exit;
    }

    // 3. Add Task
    if ($action === 'add_task') {
        $taskRef = ProjectOS::nextRef('TASK', $projectId, 'action_items');
        $taskId = DB::insert('action_items', [
            'project_id'  => $projectId,
            'task_ref'    => $taskRef,
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'owner_name'  => trim($_POST['owner_name'] ?? ''),
            'due_date'    => $_POST['due_date'] ?: null,
            'priority'    => $_POST['priority'] ?? 'medium',
            'status'      => $_POST['status'] ?? 'pending',
            'created_by'  => Auth::id(),
        ]);
        if ($taskId) {
            ProjectOS::log($projectId, 'task', (int)$taskId, 'created', "Task {$taskRef} created: " . trim($_POST['title'] ?? ''));
        }
        header("Location: view.php?id={$projectId}&tab=tasks");
        exit;
    }

    // 4. Update Task
    if ($action === 'update_task') {
        $taskId  = (int)($_POST['task_id'] ?? 0);
        $status  = $_POST['status'] ?? 'pending';
        $priority = $_POST['priority'] ?? 'medium';
        if ($taskId) {
            $data = ['status' => $status, 'priority' => $priority];
            if ($status === 'completed') {
                $data['completed_at'] = date('Y-m-d H:i:s');
            }
            DB::update('action_items', $data, 'id = ? AND project_id = ?', [$taskId, $projectId]);
            ProjectOS::log($projectId, 'task', $taskId, 'updated', "Status changed to {$status}");
        }
        header("Location: view.php?id={$projectId}&tab=tasks");
        exit;
    }

    // 5. Add Issue
    if ($action === 'add_issue') {
        $issueRef = ProjectOS::nextRef('ISS', $projectId, 'issues');
        $issueId = DB::insert('issues', [
            'project_id'  => $projectId,
            'issue_ref'   => $issueRef,
            'title'       => trim($_POST['title'] ?? ''),
            'category'    => trim($_POST['category'] ?? ''),
            'severity'    => $_POST['severity'] ?? 'medium',
            'description' => trim($_POST['description'] ?? ''),
            'root_cause'  => trim($_POST['root_cause'] ?? ''),
            'owner_name'  => trim($_POST['owner_name'] ?? ''),
            'status'      => 'open',
            'created_by'  => Auth::id(),
        ]);
        if ($issueId) {
            ProjectOS::log($projectId, 'issue', (int)$issueId, 'created', "Issue {$issueRef} created: " . trim($_POST['title'] ?? ''));
        }
        header("Location: view.php?id={$projectId}&tab=issues");
        exit;
    }

    // 6. Update Issue
    if ($action === 'update_issue') {
        $issueId   = (int)($_POST['issue_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'open';
        $notes     = trim($_POST['notes'] ?? '');
        if ($issueId) {
            $data = ['status' => $newStatus];
            if (in_array($newStatus, ['solved', 'closed', 'verified'])) {
                $data['resolved_at'] = date('Y-m-d H:i:s');
            }
            DB::update('issues', $data, 'id = ? AND project_id = ?', [$issueId, $projectId]);
            // Insert status change comment
            DB::insert('issue_comments', [
                'issue_id'      => $issueId,
                'user_id'       => Auth::id(),
                'comment'       => $notes ?: "Status changed to {$newStatus}",
                'status_change' => $newStatus,
            ]);
            ProjectOS::log($projectId, 'issue', $issueId, 'updated', "Status changed to {$newStatus}");
        }
        header("Location: view.php?id={$projectId}&tab=issues");
        exit;
    }

    // 7. Add Milestone
    if ($action === 'add_milestone') {
        $msId = DB::insert('project_milestones', [
            'project_id'  => $projectId,
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'due_date'    => $_POST['due_date'] ?: null,
            'status'      => $_POST['status'] ?? 'upcoming',
        ]);
        if ($msId) {
            ProjectOS::log($projectId, 'milestone', (int)$msId, 'created', 'Milestone created: ' . trim($_POST['title'] ?? ''));
        }
        header("Location: view.php?id={$projectId}&tab=milestones");
        exit;
    }

    // 8. Update Milestone
    if ($action === 'update_milestone') {
        $msId          = (int)($_POST['milestone_id'] ?? 0);
        $msStatus      = $_POST['status'] ?? 'upcoming';
        $completedDate = $_POST['completed_date'] ?: null;
        if ($msId) {
            DB::update(
                'project_milestones',
                ['status' => $msStatus, 'completed_date' => $completedDate],
                'id = ? AND project_id = ?',
                [$msId, $projectId]
            );
            ProjectOS::log($projectId, 'milestone', $msId, 'updated', "Milestone status: {$msStatus}");
        }
        header("Location: view.php?id={$projectId}&tab=milestones");
        exit;
    }

    // 9. Add Decision
    if ($action === 'add_decision') {
        $decRef    = ProjectOS::nextRef('DEC', $projectId, 'decisions');
        $meetingId = (int)($_POST['meeting_id'] ?? 0) ?: null;
        $decId = DB::insert('decisions', [
            'project_id'   => $projectId,
            'meeting_id'   => $meetingId,
            'decision_ref' => $decRef,
            'summary'      => trim($_POST['summary'] ?? ''),
            'made_by'      => trim($_POST['made_by'] ?? ''),
            'decided_at'   => $_POST['decided_at'] ?: null,
            'impact'       => $_POST['impact'] ?? 'medium',
            'status'       => 'active',
            'created_by'   => Auth::id(),
        ]);
        if ($decId) {
            ProjectOS::log($projectId, 'decision', (int)$decId, 'created', "Decision {$decRef} created");
        }
        header("Location: view.php?id={$projectId}&tab=decisions");
        exit;
    }

    // 10. Update Project Completion %
    if ($action === 'update_project_pct') {
        $pct = max(0, min(100, (int)($_POST['completion_pct'] ?? 0)));
        DB::update('projects', ['completion_pct' => $pct], 'id = ?', [$projectId]);
        ProjectOS::log($projectId, 'project', $projectId, 'updated', "Completion updated to {$pct}%");
        header("Location: view.php?id={$projectId}");
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LOAD DATA
// ─────────────────────────────────────────────────────────────────────────────

$activeTab = $_GET['tab'] ?? 'overview';

$health = ProjectOS::getHealthScore($projectId);

$meetings = DB::fetchAll(
    'SELECT * FROM meetings WHERE project_id = ? ORDER BY meeting_date DESC',
    [$projectId]
);

$tasks = DB::fetchAll(
    'SELECT a.*, u.name AS user_name
     FROM action_items a
     LEFT JOIN users u ON u.id = a.owner_id
     WHERE a.project_id = ?
     ORDER BY FIELD(a.priority,"critical","high","medium","low"), a.due_date ASC',
    [$projectId]
);

$issues = DB::fetchAll(
    'SELECT i.*, u.name AS user_name
     FROM issues i
     LEFT JOIN users u ON u.id = i.owner_id
     WHERE i.project_id = ?
     ORDER BY FIELD(i.severity,"critical","high","medium","low"), i.created_at DESC',
    [$projectId]
);

$milestones = DB::fetchAll(
    'SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date ASC',
    [$projectId]
);

$decisions = DB::fetchAll(
    'SELECT * FROM decisions WHERE project_id = ? ORDER BY created_at DESC',
    [$projectId]
);

$activityLog = DB::fetchAll(
    'SELECT l.*, u.name AS user_name
     FROM project_activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE l.project_id = ?
     ORDER BY l.created_at DESC
     LIMIT 50',
    [$projectId]
);

$members = DB::fetchAll(
    'SELECT pm.*, u.name AS member_name, u.email AS member_email
     FROM project_members pm
     LEFT JOIN users u ON u.id = pm.user_id
     WHERE pm.project_id = ?',
    [$projectId]
);

// Quick counts
$openTasks = 0;
$overdueTasks = 0;
$today = date('Y-m-d');
foreach ($tasks as $t) {
    if (!in_array($t['status'], ['completed', 'cancelled'])) {
        $openTasks++;
        if (!empty($t['due_date']) && $t['due_date'] < $today) {
            $overdueTasks++;
        }
    }
}

$openIssues = 0;
$criticalIssues = 0;
foreach ($issues as $i) {
    if (!in_array($i['status'], ['closed', 'verified', 'solved'])) {
        $openIssues++;
        if ($i['severity'] === 'critical') {
            $criticalIssues++;
        }
    }
}

$upcomingMilestones = 0;
foreach ($milestones as $m) {
    if (in_array($m['status'], ['upcoming', 'active']) && !empty($m['due_date']) && $m['due_date'] >= $today) {
        $upcomingMilestones++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PAGE
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = htmlspecialchars($project['name']);
require '../includes/header.php';

// Helper: format dates nicely
function fmtDate(?string $d): string {
    if (!$d) return '—';
    return date('d M Y', strtotime($d));
}
function fmtDateTime(?string $d): string {
    if (!$d) return '—';
    return date('d M Y H:i', strtotime($d));
}
?>

<div class="container-fluid py-4 px-4">

<!-- ═══════════════════════════════════════════════════════════════════════════
     PROJECT HEADER
═══════════════════════════════════════════════════════════════════════════ -->
<div class="glass-card p-4 mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <!-- Left: back + name -->
        <div class="flex-grow-1">
            <div class="mb-2">
                <a href="/projects/projects.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i>Back to Projects
                </a>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <h4 class="text-white fw-bold mb-0"><?= htmlspecialchars($project['name']) ?></h4>
                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($project['code']) ?></span>
                <span class="badge <?= ProjectOS::statusBadge($project['status']) ?>"><?= ucfirst(str_replace('_', ' ', $project['status'])) ?></span>
            </div>
            <div class="text-muted small d-flex flex-wrap gap-3 mt-1">
                <?php if ($project['client']): ?>
                    <span><i class="bi bi-building me-1"></i><?= htmlspecialchars($project['client']) ?></span>
                <?php endif; ?>
                <?php if ($project['manager_name']): ?>
                    <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($project['manager_name']) ?></span>
                <?php endif; ?>
                <?php if ($project['start_date']): ?>
                    <span><i class="bi bi-calendar-range me-1"></i><?= fmtDate($project['start_date']) ?><?= $project['end_date'] ? ' → ' . fmtDate($project['end_date']) : '' ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: completion + AI button -->
        <div class="d-flex align-items-center gap-3 flex-shrink-0">
            <form method="POST" action="view.php?id=<?= $projectId ?>" class="d-flex align-items-center gap-2">
                <input type="hidden" name="action" value="update_project_pct">
                <label class="text-muted small mb-0 me-1">Completion:</label>
                <input type="number" name="completion_pct" min="0" max="100"
                       value="<?= (int)$project['completion_pct'] ?>"
                       class="form-control form-control-sm bg-dark border-secondary text-white"
                       style="width:70px;"
                       onchange="this.form.submit()">
                <span class="text-muted small">%</span>
            </form>
            <button class="btn btn-outline-primary btn-sm"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#aiPanel"
                    aria-controls="aiPanel">
                <i class="bi bi-stars me-1"></i>AI Assistant
            </button>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <small class="text-muted">Project Progress</small>
            <small class="text-white fw-semibold"><?= (int)$project['completion_pct'] ?>%</small>
        </div>
        <div class="progress" style="height:8px;">
            <div class="progress-bar bg-primary"
                 role="progressbar"
                 style="width:<?= (int)$project['completion_pct'] ?>%"
                 aria-valuenow="<?= (int)$project['completion_pct'] ?>"
                 aria-valuemin="0"
                 aria-valuemax="100"></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     STATS ROW
═══════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <!-- Health Score -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100 text-center">
            <div class="fs-2 fw-bold lh-1" style="color:<?= $health['color'] ?>"><?= $health['score'] ?></div>
            <div class="text-muted small mt-1">Health Score</div>
            <div class="small mt-1" style="color:<?= $health['color'] ?>"><?= htmlspecialchars($health['label']) ?></div>
        </div>
    </div>
    <!-- Open Tasks -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100 text-center">
            <div class="fs-2 fw-bold lh-1 text-white"><?= $openTasks ?></div>
            <div class="text-muted small mt-1">Open Tasks</div>
        </div>
    </div>
    <!-- Overdue -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100 text-center">
            <div class="fs-2 fw-bold lh-1 <?= $overdueTasks > 0 ? 'text-danger' : 'text-white' ?>"><?= $overdueTasks ?></div>
            <div class="text-muted small mt-1">Overdue</div>
        </div>
    </div>
    <!-- Open Issues -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100 text-center">
            <div class="fs-2 fw-bold lh-1 text-white"><?= $openIssues ?></div>
            <div class="text-muted small mt-1">Open Issues</div>
        </div>
    </div>
    <!-- Critical Issues -->
    <div class="col-6 col-md-4 col-xl">
        <div class="glass-card p-3 h-100 text-center">
            <div class="fs-2 fw-bold lh-1 <?= $criticalIssues > 0 ? 'text-danger' : 'text-white' ?>"><?= $criticalIssues ?></div>
            <div class="text-muted small mt-1">Critical Issues</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB NAVIGATION
═══════════════════════════════════════════════════════════════════════════ -->
<ul class="nav nav-tabs border-secondary mb-4 flex-nowrap overflow-auto" id="projectTabs">
    <?php
    $tabs = [
        'overview'   => ['icon' => 'bi-grid',           'label' => 'Overview'],
        'meetings'   => ['icon' => 'bi-calendar-event', 'label' => 'Meetings'],
        'tasks'      => ['icon' => 'bi-check2-square',  'label' => 'Tasks'],
        'issues'     => ['icon' => 'bi-bug',             'label' => 'Issues'],
        'milestones' => ['icon' => 'bi-flag',            'label' => 'Milestones'],
        'decisions'  => ['icon' => 'bi-clipboard-check','label' => 'Decisions'],
        'activity'   => ['icon' => 'bi-activity',       'label' => 'Activity'],
    ];
    foreach ($tabs as $key => $info):
    ?>
    <li class="nav-item">
        <a class="nav-link text-nowrap <?= $activeTab === $key ? 'active' : 'text-muted' ?>"
           href="view.php?id=<?= $projectId ?>&tab=<?= $key ?>">
            <i class="bi <?= $info['icon'] ?> me-1"></i><?= $info['label'] ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB CONTENT
═══════════════════════════════════════════════════════════════════════════ -->

<!-- ──────────────────────────── OVERVIEW TAB ──────────────────────────────── -->
<?php if ($activeTab === 'overview'): ?>
<div class="row g-4">
    <!-- LEFT COLUMN -->
    <div class="col-lg-8">
        <!-- Description -->
        <div class="glass-card p-4 mb-4">
            <h6 class="text-white fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Project Description</h6>
            <?php if ($project['description']): ?>
                <p class="text-muted mb-0" style="white-space:pre-wrap;"><?= htmlspecialchars($project['description']) ?></p>
            <?php else: ?>
                <p class="text-muted mb-0 fst-italic">No description provided.</p>
            <?php endif; ?>
        </div>

        <!-- Health Score Breakdown -->
        <div class="glass-card p-4 mb-4">
            <h6 class="text-white fw-semibold mb-3"><i class="bi bi-heart-pulse me-2 text-success"></i>Health Score Breakdown</h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Task Completion Rate</small>
                    <small class="text-white"><?= $health['taskRate'] ?>%</small>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:<?= $health['taskRate'] ?>%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Issue Closure Rate</small>
                    <small class="text-white"><?= $health['issueRate'] ?>%</small>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar bg-warning" style="width:<?= $health['issueRate'] ?>%"></div>
                </div>
            </div>
            <div class="mb-0">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Milestone Completion</small>
                    <small class="text-white"><?= $health['msRate'] ?>%</small>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:<?= $health['msRate'] ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Recent Meetings -->
        <div class="glass-card p-4">
            <h6 class="text-white fw-semibold mb-3"><i class="bi bi-calendar-event me-2 text-info"></i>Recent Meetings</h6>
            <?php $recentMeetings = array_slice($meetings, 0, 3); ?>
            <?php if ($recentMeetings): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentMeetings as $m): ?>
                    <div class="list-group-item bg-transparent border-secondary px-0 py-3">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-white fw-medium"><?= htmlspecialchars($m['title']) ?></div>
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-calendar2 me-1"></i><?= fmtDate($m['meeting_date']) ?>
                                    <?php if ($m['venue']): ?>
                                        <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($m['venue']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-shrink-0">
                                <span class="badge bg-dark border border-secondary"><?= ucfirst(str_replace('_', ' ', $m['type'])) ?></span>
                                <?php if ($m['minutes_approved']): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($meetings) > 3): ?>
                    <a href="view.php?id=<?= $projectId ?>&tab=meetings" class="btn btn-sm btn-outline-secondary mt-2">View all <?= count($meetings) ?> meetings</a>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted fst-italic mb-0">No meetings recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-lg-4">
        <!-- Project Details -->
        <div class="glass-card p-4 mb-4">
            <h6 class="text-white fw-semibold mb-3"><i class="bi bi-card-list me-2 text-secondary"></i>Project Details</h6>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Client</dt>
                <dd class="col-7 text-white mb-2"><?= htmlspecialchars($project['client'] ?: '—') ?></dd>
                <dt class="col-5 text-muted">Department</dt>
                <dd class="col-7 text-white mb-2"><?= htmlspecialchars($project['department'] ?: '—') ?></dd>
                <dt class="col-5 text-muted">Budget</dt>
                <dd class="col-7 text-white mb-2">
                    <?= $project['budget'] !== null ? APP_CURRENCY . number_format((float)$project['budget'], 2) : '—' ?>
                </dd>
                <dt class="col-5 text-muted">Start Date</dt>
                <dd class="col-7 text-white mb-2"><?= fmtDate($project['start_date']) ?></dd>
                <dt class="col-5 text-muted">End Date</dt>
                <dd class="col-7 text-white mb-2"><?= fmtDate($project['end_date']) ?></dd>
                <dt class="col-5 text-muted">Status</dt>
                <dd class="col-7 mb-2">
                    <span class="badge <?= ProjectOS::statusBadge($project['status']) ?>"><?= ucfirst(str_replace('_', ' ', $project['status'])) ?></span>
                </dd>
                <dt class="col-5 text-muted">Completion</dt>
                <dd class="col-7 text-white mb-0"><?= (int)$project['completion_pct'] ?>%</dd>
            </dl>
        </div>

        <!-- Members -->
        <div class="glass-card p-4 mb-4">
            <h6 class="text-white fw-semibold mb-3"><i class="bi bi-people me-2 text-warning"></i>Team Members</h6>
            <?php if ($members): ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($members as $mem): ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:32px;height:32px;">
                            <span class="text-white fw-bold" style="font-size:12px;"><?= strtoupper(mb_substr($mem['member_name'] ?? '?', 0, 1)) ?></span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-white small fw-medium text-truncate"><?= htmlspecialchars($mem['member_name'] ?? 'Unknown') ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= ucfirst($mem['role']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted fst-italic small mb-0">No members assigned.</p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="glass-card p-4">
            <h6 class="text-white fw-semibold mb-3"><i class="bi bi-lightning me-2 text-success"></i>Quick Actions</h6>
            <div class="d-grid gap-2">
                <a href="view.php?id=<?= $projectId ?>&tab=meetings" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-calendar-plus me-2"></i>Add Meeting
                </a>
                <a href="view.php?id=<?= $projectId ?>&tab=tasks" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-square me-2"></i>Add Task
                </a>
                <a href="view.php?id=<?= $projectId ?>&tab=issues" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-bug me-2"></i>Log Issue
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ──────────────────────────── MEETINGS TAB ──────────────────────────────── -->
<?php if ($activeTab === 'meetings'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h6 class="text-white fw-semibold mb-0">Meetings <span class="badge bg-secondary ms-1"><?= count($meetings) ?></span></h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMeetingModal">
        <i class="bi bi-calendar-plus me-1"></i>Add Meeting
    </button>
</div>

<?php if ($meetings): ?>
<div class="glass-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr class="border-secondary">
                    <th class="px-4 py-3 text-muted fw-normal small">Date</th>
                    <th class="py-3 text-muted fw-normal small">Title</th>
                    <th class="py-3 text-muted fw-normal small">Type</th>
                    <th class="py-3 text-muted fw-normal small">Venue</th>
                    <th class="py-3 text-muted fw-normal small">Status</th>
                    <th class="py-3 text-muted fw-normal small">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meetings as $m): ?>
                <tr class="border-secondary">
                    <td class="px-4 py-3 text-muted small text-nowrap"><?= fmtDate($m['meeting_date']) ?></td>
                    <td class="py-3">
                        <div class="text-white fw-medium"><?= htmlspecialchars($m['title']) ?></div>
                        <?php if ($m['objective']): ?>
                            <div class="text-muted small text-truncate" style="max-width:280px;"><?= htmlspecialchars($m['objective']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3">
                        <span class="badge bg-dark border border-secondary small"><?= ucfirst(str_replace('_', ' ', $m['type'])) ?></span>
                    </td>
                    <td class="py-3 text-muted small"><?= htmlspecialchars($m['venue'] ?: '—') ?></td>
                    <td class="py-3">
                        <?php if ($m['minutes_approved']): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3">
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- View Minutes Toggle -->
                            <button class="btn btn-outline-secondary btn-sm"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#minutes-<?= $m['id'] ?>"
                                    aria-expanded="false">
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                            <!-- Generate AI Minutes -->
                            <?php if (!$m['ai_minutes']): ?>
                            <button class="btn btn-outline-info btn-sm"
                                    type="button"
                                    onclick="generateMinutes(<?= $m['id'] ?>, this)">
                                <i class="bi bi-stars me-1"></i>Generate AI Minutes
                            </button>
                            <?php endif; ?>
                            <!-- Approve -->
                            <?php if (!$m['minutes_approved']): ?>
                            <form method="POST" action="view.php?id=<?= $projectId ?>" class="d-inline">
                                <input type="hidden" name="action" value="approve_minutes">
                                <input type="hidden" name="meeting_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-check2-circle me-1"></i>Approve
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <!-- Inline Minutes Accordion Row -->
                <tr class="border-secondary">
                    <td colspan="6" class="p-0">
                        <div class="collapse" id="minutes-<?= $m['id'] ?>">
                            <div class="p-4 bg-dark bg-opacity-50">
                                <?php if ($m['ai_minutes']): ?>
                                    <div class="mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-primary">AI Minutes</span>
                                        <?php if ($m['minutes_approved']): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small" style="white-space:pre-wrap;"><?= htmlspecialchars($m['ai_minutes']) ?></div>
                                <?php elseif ($m['raw_notes']): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-secondary">Raw Notes</span>
                                    </div>
                                    <div class="text-muted small" style="white-space:pre-wrap;"><?= htmlspecialchars($m['raw_notes']) ?></div>
                                <?php else: ?>
                                    <p class="text-muted fst-italic small mb-0">No notes or minutes available for this meeting.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="glass-card p-5 text-center">
    <i class="bi bi-calendar-x fs-1 text-muted mb-3 d-block"></i>
    <p class="text-muted mb-3">No meetings recorded yet.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMeetingModal">
        <i class="bi bi-calendar-plus me-1"></i>Schedule First Meeting
    </button>
</div>
<?php endif; ?>


<!-- Add Meeting Modal -->
<div class="modal fade" id="addMeetingModal" tabindex="-1" aria-labelledby="addMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="addMeetingModalLabel">
                    <i class="bi bi-calendar-plus me-2 text-primary"></i>Add Meeting
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="view.php?id=<?= $projectId ?>">
                <input type="hidden" name="action" value="add_meeting">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Meeting Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required placeholder="e.g. Weekly Standup">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Type</label>
                            <select name="type" class="form-select bg-dark border-secondary text-white">
                                <option value="weekly">Weekly</option>
                                <option value="kickoff">Kickoff</option>
                                <option value="monthly">Monthly</option>
                                <option value="progress_review">Progress Review</option>
                                <option value="client">Client</option>
                                <option value="uat">UAT</option>
                                <option value="go_live">Go Live</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Date</label>
                            <input type="date" name="meeting_date" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Time</label>
                            <input type="time" name="meeting_time" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Venue / Location</label>
                            <input type="text" name="venue" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Conference Room A / Zoom">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Objective</label>
                            <textarea name="objective" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="What is the goal of this meeting?"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Raw Notes / Key Points</label>
                            <textarea name="raw_notes" class="form-control bg-dark border-secondary text-white" rows="4" placeholder="Paste meeting notes, bullet points, key discussions..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Attendees <span class="text-muted">(comma-separated names)</span></label>
                            <input type="text" name="attendees" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Alice, Bob, Charlie">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar-check me-1"></i>Save Meeting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ──────────────────────────── TASKS TAB ─────────────────────────────────── -->
<?php if ($activeTab === 'tasks'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <h6 class="text-white fw-semibold mb-0 me-1">Tasks <span class="badge bg-secondary ms-1"><?= count($tasks) ?></span></h6>
        <button class="btn btn-sm btn-outline-secondary task-filter active" data-filter="all">All</button>
        <button class="btn btn-sm btn-outline-secondary task-filter" data-filter="pending">Pending</button>
        <button class="btn btn-sm btn-outline-secondary task-filter" data-filter="in_progress">In Progress</button>
        <button class="btn btn-sm btn-outline-secondary task-filter" data-filter="completed">Completed</button>
        <button class="btn btn-sm btn-outline-danger task-filter" data-filter="overdue">Overdue</button>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="bi bi-plus-square me-1"></i>Add Task
    </button>
</div>

<?php if ($tasks): ?>
<div class="glass-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle" id="tasksTable">
            <thead>
                <tr class="border-secondary">
                    <th class="px-4 py-3 text-muted fw-normal small">Ref</th>
                    <th class="py-3 text-muted fw-normal small">Title</th>
                    <th class="py-3 text-muted fw-normal small">Owner</th>
                    <th class="py-3 text-muted fw-normal small">Due Date</th>
                    <th class="py-3 text-muted fw-normal small">Priority</th>
                    <th class="py-3 text-muted fw-normal small">Status</th>
                    <th class="py-3 text-muted fw-normal small">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t):
                    $isOverdue = !in_array($t['status'], ['completed', 'cancelled'])
                        && !empty($t['due_date'])
                        && $t['due_date'] < $today;
                ?>
                <tr class="border-secondary task-row"
                    data-status="<?= htmlspecialchars($t['status']) ?>"
                    data-overdue="<?= $isOverdue ? '1' : '0' ?>">
                    <td class="px-4 py-3">
                        <span class="badge bg-dark border border-secondary font-monospace small"><?= htmlspecialchars($t['task_ref'] ?: '—') ?></span>
                    </td>
                    <td class="py-3">
                        <div class="text-white fw-medium"><?= htmlspecialchars($t['title']) ?></div>
                        <?php if ($t['description']): ?>
                            <div class="text-muted small text-truncate" style="max-width:260px;"><?= htmlspecialchars($t['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 text-muted small"><?= htmlspecialchars($t['owner_name'] ?: ($t['user_name'] ?: '—')) ?></td>
                    <td class="py-3 small text-nowrap <?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= fmtDate($t['due_date']) ?>
                        <?php if ($isOverdue): ?>
                            <i class="bi bi-exclamation-triangle-fill ms-1"></i>
                        <?php endif; ?>
                    </td>
                    <td class="py-3">
                        <span class="badge <?= ProjectOS::statusBadge($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span>
                    </td>
                    <td class="py-3">
                        <span class="badge <?= ProjectOS::statusBadge($t['status']) ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                    </td>
                    <td class="py-3">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                Update
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end border-secondary">
                                <?php $statuses = ['pending','assigned','in_progress','waiting','completed','cancelled']; ?>
                                <?php foreach ($statuses as $st): ?>
                                <li>
                                    <form method="POST" action="view.php?id=<?= $projectId ?>">
                                        <input type="hidden" name="action" value="update_task">
                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $st ?>">
                                        <input type="hidden" name="priority" value="<?= $t['priority'] ?>">
                                        <button type="submit" class="dropdown-item <?= $t['status'] === $st ? 'active' : '' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                        </button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="glass-card p-5 text-center">
    <i class="bi bi-check2-square fs-1 text-muted mb-3 d-block"></i>
    <p class="text-muted mb-3">No tasks yet. Add the first action item.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="bi bi-plus-square me-1"></i>Add Task
    </button>
</div>
<?php endif; ?>


<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="addTaskModalLabel">
                    <i class="bi bi-plus-square me-2 text-primary"></i>Add Task
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="view.php?id=<?= $projectId ?>">
                <input type="hidden" name="action" value="add_task">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required placeholder="Task title">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="Details..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Owner Name</label>
                            <input type="text" name="owner_name" class="form-control bg-dark border-secondary text-white" placeholder="Assigned to">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Due Date</label>
                            <input type="date" name="due_date" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Priority</label>
                            <select name="priority" class="form-select bg-dark border-secondary text-white">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <option value="pending" selected>Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="waiting">Waiting</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ──────────────────────────── ISSUES TAB ────────────────────────────────── -->
<?php if ($activeTab === 'issues'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <h6 class="text-white fw-semibold mb-0 me-1">Issues <span class="badge bg-secondary ms-1"><?= count($issues) ?></span></h6>
        <button class="btn btn-sm btn-outline-secondary issue-filter active" data-filter="all">All</button>
        <button class="btn btn-sm btn-outline-danger issue-filter" data-filter="critical">Critical</button>
        <button class="btn btn-sm btn-outline-warning issue-filter" data-filter="high">High</button>
        <button class="btn btn-sm btn-outline-info issue-filter" data-filter="medium">Medium</button>
        <button class="btn btn-sm btn-outline-secondary issue-filter" data-filter="low">Low</button>
    </div>
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addIssueModal">
        <i class="bi bi-bug me-1"></i>Log Issue
    </button>
</div>

<?php if ($issues): ?>
<div class="glass-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle" id="issuesTable">
            <thead>
                <tr class="border-secondary">
                    <th class="px-4 py-3 text-muted fw-normal small">Ref</th>
                    <th class="py-3 text-muted fw-normal small">Title</th>
                    <th class="py-3 text-muted fw-normal small">Severity</th>
                    <th class="py-3 text-muted fw-normal small">Category</th>
                    <th class="py-3 text-muted fw-normal small">Owner</th>
                    <th class="py-3 text-muted fw-normal small">Status</th>
                    <th class="py-3 text-muted fw-normal small">Created</th>
                    <th class="py-3 text-muted fw-normal small">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $iss): ?>
                <tr class="border-secondary issue-row" data-severity="<?= htmlspecialchars($iss['severity']) ?>">
                    <td class="px-4 py-3">
                        <span class="badge bg-dark border border-secondary font-monospace small"><?= htmlspecialchars($iss['issue_ref'] ?: '—') ?></span>
                    </td>
                    <td class="py-3">
                        <div class="text-white fw-medium"><?= htmlspecialchars($iss['title']) ?></div>
                        <?php if ($iss['description']): ?>
                            <div class="text-muted small text-truncate" style="max-width:250px;"><?= htmlspecialchars($iss['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-3">
                        <span class="badge <?= ProjectOS::statusBadge($iss['severity']) ?>"><?= ucfirst($iss['severity']) ?></span>
                    </td>
                    <td class="py-3 text-muted small"><?= htmlspecialchars($iss['category'] ?: '—') ?></td>
                    <td class="py-3 text-muted small"><?= htmlspecialchars($iss['owner_name'] ?: ($iss['user_name'] ?: '—')) ?></td>
                    <td class="py-3">
                        <span class="badge <?= ProjectOS::statusBadge($iss['status']) ?>"><?= ucfirst(str_replace('_', ' ', $iss['status'])) ?></span>
                    </td>
                    <td class="py-3 text-muted small text-nowrap"><?= fmtDate($iss['created_at']) ?></td>
                    <td class="py-3">
                        <button class="btn btn-sm btn-outline-secondary"
                                type="button"
                                onclick="openUpdateIssueModal(<?= $iss['id'] ?>, '<?= htmlspecialchars($iss['status'], ENT_QUOTES) ?>', <?= htmlspecialchars(json_encode($iss['title']), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil me-1"></i>Update
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="glass-card p-5 text-center">
    <i class="bi bi-bug fs-1 text-muted mb-3 d-block"></i>
    <p class="text-muted mb-3">No issues logged. Great news!</p>
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addIssueModal">
        <i class="bi bi-bug me-1"></i>Log First Issue
    </button>
</div>
<?php endif; ?>


<!-- Add Issue Modal -->
<div class="modal fade" id="addIssueModal" tabindex="-1" aria-labelledby="addIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="addIssueModalLabel">
                    <i class="bi bi-bug me-2 text-warning"></i>Log Issue
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="view.php?id=<?= $projectId ?>">
                <input type="hidden" name="action" value="add_issue">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Issue Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required placeholder="Describe the issue briefly">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Category</label>
                            <input type="text" name="category" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Technical, Process, Resource">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Severity</label>
                            <select name="severity" class="form-select bg-dark border-secondary text-white">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="Detailed description of the issue..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Root Cause</label>
                            <textarea name="root_cause" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="What caused this issue?"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Owner Name</label>
                            <input type="text" name="owner_name" class="form-control bg-dark border-secondary text-white" placeholder="Responsible person">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-bug me-1"></i>Log Issue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Issue Modal -->
<div class="modal fade" id="updateIssueModal" tabindex="-1" aria-labelledby="updateIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="updateIssueModalLabel">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>Update Issue
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="view.php?id=<?= $projectId ?>" id="updateIssueForm">
                <input type="hidden" name="action" value="update_issue">
                <input type="hidden" name="issue_id" id="updateIssueId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Issue</label>
                        <div class="text-white fw-medium" id="updateIssueTitle"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">New Status</label>
                        <select name="status" id="updateIssueStatus" class="form-select bg-dark border-secondary text-white">
                            <option value="open">Open</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="solved">Solved</option>
                            <option value="verified">Verified</option>
                            <option value="closed">Closed</option>
                            <option value="reopened">Reopened</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small">Notes / Comment</label>
                        <textarea name="notes" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="Add a comment about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check2 me-1"></i>Update Issue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ──────────────────────────── MILESTONES TAB ─────────────────────────────── -->
<?php if ($activeTab === 'milestones'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h6 class="text-white fw-semibold mb-0">Milestones <span class="badge bg-secondary ms-1"><?= count($milestones) ?></span></h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
        <i class="bi bi-flag me-1"></i>Add Milestone
    </button>
</div>

<?php if ($milestones): ?>
<div class="row g-3">
    <?php foreach ($milestones as $ms):
        $borderColor = match($ms['status']) {
            'completed' => '#10b981',
            'active'    => '#3b82f6',
            'delayed'   => '#ef4444',
            default     => '#6b7280',
        };
    ?>
    <div class="col-md-4">
        <div class="glass-card p-4 h-100" style="border-left: 4px solid <?= $borderColor ?>;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="text-white fw-semibold mb-0 flex-grow-1 me-2"><?= htmlspecialchars($ms['title']) ?></h6>
                <span class="badge <?= ProjectOS::statusBadge($ms['status']) ?> flex-shrink-0"><?= ucfirst($ms['status']) ?></span>
            </div>
            <?php if ($ms['description']): ?>
                <p class="text-muted small mb-2"><?= htmlspecialchars($ms['description']) ?></p>
            <?php endif; ?>
            <div class="text-muted small mb-2">
                <i class="bi bi-calendar me-1"></i>Due: <?= fmtDate($ms['due_date']) ?>
            </div>
            <?php if ($ms['completed_date']): ?>
                <div class="text-success small mb-2">
                    <i class="bi bi-check-circle me-1"></i>Completed: <?= fmtDate($ms['completed_date']) ?>
                </div>
            <?php endif; ?>
            <?php if ($ms['status'] !== 'completed'): ?>
            <form method="POST" action="view.php?id=<?= $projectId ?>" class="d-flex gap-2 mt-3">
                <input type="hidden" name="action" value="update_milestone">
                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                <input type="hidden" name="status" value="completed">
                <input type="date" name="completed_date" value="<?= date('Y-m-d') ?>"
                       class="form-control form-control-sm bg-dark border-secondary text-white flex-grow-1">
                <button type="submit" class="btn btn-sm btn-success flex-shrink-0">
                    <i class="bi bi-check2-circle me-1"></i>Mark Done
                </button>
            </form>
            <?php else: ?>
            <form method="POST" action="view.php?id=<?= $projectId ?>" class="mt-3">
                <input type="hidden" name="action" value="update_milestone">
                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                <input type="hidden" name="status" value="upcoming">
                <input type="hidden" name="completed_date" value="">
                <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="glass-card p-5 text-center">
    <i class="bi bi-flag fs-1 text-muted mb-3 d-block"></i>
    <p class="text-muted mb-3">No milestones defined yet.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
        <i class="bi bi-flag me-1"></i>Add First Milestone
    </button>
</div>
<?php endif; ?>


<!-- Add Milestone Modal -->
<div class="modal fade" id="addMilestoneModal" tabindex="-1" aria-labelledby="addMilestoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="addMilestoneModalLabel">
                    <i class="bi bi-flag me-2 text-primary"></i>Add Milestone
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="view.php?id=<?= $projectId ?>">
                <input type="hidden" name="action" value="add_milestone">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required placeholder="Milestone name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Description</label>
                        <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="What does this milestone represent?"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Due Date</label>
                            <input type="date" name="due_date" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <option value="upcoming" selected>Upcoming</option>
                                <option value="active">Active</option>
                                <option value="delayed">Delayed</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-flag-fill me-1"></i>Save Milestone
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ──────────────────────────── DECISIONS TAB ─────────────────────────────── -->
<?php if ($activeTab === 'decisions'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h6 class="text-white fw-semibold mb-0">Decisions <span class="badge bg-secondary ms-1"><?= count($decisions) ?></span></h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDecisionModal">
        <i class="bi bi-clipboard-plus me-1"></i>Add Decision
    </button>
</div>

<?php if ($decisions): ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($decisions as $dec): ?>
    <div class="glass-card p-4">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-dark border border-secondary font-monospace small"><?= htmlspecialchars($dec['decision_ref'] ?: '—') ?></span>
                    <span class="badge <?= ProjectOS::statusBadge($dec['impact']) ?>"><?= ucfirst($dec['impact']) ?> Impact</span>
                    <span class="badge <?= ProjectOS::statusBadge($dec['status']) ?>"><?= ucfirst($dec['status']) ?></span>
                </div>
                <p class="text-white mb-2"><?= htmlspecialchars($dec['summary']) ?></p>
                <div class="text-muted small d-flex flex-wrap gap-3">
                    <?php if ($dec['made_by']): ?>
                        <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($dec['made_by']) ?></span>
                    <?php endif; ?>
                    <?php if ($dec['decided_at']): ?>
                        <span><i class="bi bi-calendar2-check me-1"></i><?= fmtDate($dec['decided_at']) ?></span>
                    <?php endif; ?>
                    <?php if ($dec['meeting_id']): ?>
                        <span><i class="bi bi-camera-video me-1"></i>Linked to meeting</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="glass-card p-5 text-center">
    <i class="bi bi-clipboard-check fs-1 text-muted mb-3 d-block"></i>
    <p class="text-muted mb-3">No decisions recorded yet.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDecisionModal">
        <i class="bi bi-clipboard-plus me-1"></i>Record First Decision
    </button>
</div>
<?php endif; ?>


<!-- Add Decision Modal -->
<div class="modal fade" id="addDecisionModal" tabindex="-1" aria-labelledby="addDecisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="addDecisionModalLabel">
                    <i class="bi bi-clipboard-plus me-2 text-primary"></i>Record Decision
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="view.php?id=<?= $projectId ?>">
                <input type="hidden" name="action" value="add_decision">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Decision Summary <span class="text-danger">*</span></label>
                        <textarea name="summary" class="form-control bg-dark border-secondary text-white" rows="3" required placeholder="Describe the decision made..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Made By</label>
                            <input type="text" name="made_by" class="form-control bg-dark border-secondary text-white" placeholder="Decision maker or group">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Decision Date</label>
                            <input type="date" name="decided_at" class="form-control bg-dark border-secondary text-white" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Impact</label>
                            <select name="impact" class="form-select bg-dark border-secondary text-white">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Linked Meeting</label>
                            <select name="meeting_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">— None —</option>
                                <?php foreach ($meetings as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= fmtDate($m['meeting_date']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-clipboard-check me-1"></i>Save Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ──────────────────────────── ACTIVITY TAB ──────────────────────────────── -->
<?php if ($activeTab === 'activity'): ?>
<h6 class="text-white fw-semibold mb-3">Activity Log <span class="badge bg-secondary ms-1"><?= count($activityLog) ?></span></h6>

<?php if ($activityLog): ?>
<div class="glass-card p-4">
    <div class="d-flex flex-column gap-0">
        <?php foreach ($activityLog as $idx => $log):
            $icon = match($log['entity_type']) {
                'task'      => 'bi-check-circle text-success',
                'issue'     => 'bi-bug text-danger',
                'meeting'   => 'bi-calendar-event text-info',
                'milestone' => 'bi-flag text-warning',
                'decision'  => 'bi-clipboard-check text-primary',
                default     => 'bi-activity text-secondary',
            };
        ?>
        <div class="d-flex gap-3 <?= $idx < count($activityLog) - 1 ? 'pb-3 border-bottom border-secondary mb-3' : '' ?>">
            <div class="flex-shrink-0 mt-1">
                <div class="rounded-circle bg-dark border border-secondary d-flex align-items-center justify-content-center"
                     style="width:34px;height:34px;">
                    <i class="bi <?= $icon ?>" style="font-size:14px;"></i>
                </div>
            </div>
            <div class="flex-grow-1">
                <div class="text-white small">
                    <span class="fw-semibold"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                    <span class="text-muted"> <?= htmlspecialchars($log['action']) ?> </span>
                    <?php if ($log['entity_type']): ?>
                        <span class="badge bg-dark border border-secondary small"><?= htmlspecialchars($log['entity_type']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($log['note']): ?>
                    <div class="text-muted small mt-1"><?= htmlspecialchars($log['note']) ?></div>
                <?php endif; ?>
                <?php if ($log['old_value'] || $log['new_value']): ?>
                    <div class="text-muted small mt-1">
                        <?php if ($log['old_value']): ?>
                            <span class="text-danger"><i class="bi bi-dash-circle me-1"></i><?= htmlspecialchars($log['old_value']) ?></span>
                        <?php endif; ?>
                        <?php if ($log['old_value'] && $log['new_value']): ?>
                            <span class="mx-1">→</span>
                        <?php endif; ?>
                        <?php if ($log['new_value']): ?>
                            <span class="text-success"><i class="bi bi-plus-circle me-1"></i><?= htmlspecialchars($log['new_value']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="text-muted mt-1" style="font-size:11px;"><?= fmtDateTime($log['created_at']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="glass-card p-5 text-center">
    <i class="bi bi-activity fs-1 text-muted mb-3 d-block"></i>
    <p class="text-muted mb-0">No activity recorded yet.</p>
</div>
<?php endif; ?>
<?php endif; ?>


</div><!-- /container-fluid -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     AI ASSISTANT OFFCANVAS
═══════════════════════════════════════════════════════════════════════════ -->
<div class="offcanvas offcanvas-end bg-dark border-secondary"
     tabindex="-1"
     id="aiPanel"
     aria-labelledby="aiPanelLabel"
     style="width:420px;">
    <div class="offcanvas-header border-secondary border-bottom">
        <h5 class="offcanvas-title text-white" id="aiPanelLabel">
            <i class="bi bi-stars me-2 text-primary"></i>AI Project Assistant
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0" style="height:calc(100% - 57px);">
        <!-- Suggested Questions -->
        <div class="px-3 pt-3 pb-2 border-bottom border-secondary">
            <div class="text-muted small mb-2">Suggested questions:</div>
            <div class="d-flex flex-wrap gap-2">
                <?php
                $suggestions = [
                    'What is overdue?',
                    'Summarize project status',
                    'What are the critical issues?',
                    'Prepare next meeting agenda',
                    'Generate client update',
                ];
                foreach ($suggestions as $s):
                ?>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary ai-suggest"
                        style="font-size:11px;"
                        data-question="<?= htmlspecialchars($s, ENT_QUOTES) ?>">
                    <?= htmlspecialchars($s) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chat Messages -->
        <div id="chatMessages"
             class="flex-grow-1 overflow-y-auto p-3 d-flex flex-column gap-3"
             style="min-height:200px;">
            <div class="text-center text-muted small py-4">
                <i class="bi bi-stars fs-4 d-block mb-2 text-primary"></i>
                Ask me anything about <strong class="text-white"><?= htmlspecialchars($project['name']) ?></strong>
            </div>
        </div>

        <!-- Input Area -->
        <div class="p-3 border-top border-secondary">
            <div class="d-flex gap-2">
                <textarea id="aiQuestion"
                          class="form-control bg-dark border-secondary text-white flex-grow-1"
                          rows="2"
                          placeholder="Ask about tasks, issues, progress..."
                          style="resize:none;font-size:13px;"></textarea>
                <button type="button"
                        id="aiAskBtn"
                        class="btn btn-primary align-self-end"
                        onclick="askAI()">
                    <i class="bi bi-send"></i>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════════════════ -->
<script>
// ── AI Minutes Generation ─────────────────────────────────────────────────────
async function generateMinutes(meetingId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    const r = await fetch('/api/project-minutes.php', {
        method: 'POST',
        body: new URLSearchParams({meeting_id: meetingId})
    });
    const d = await r.json();
    if (d.ok) {
        location.reload();
    } else {
        alert('Error: ' + d.error);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-stars me-1"></i>Generate AI Minutes';
    }
}

// ── Update Issue Modal ────────────────────────────────────────────────────────
function openUpdateIssueModal(issueId, currentStatus, issueTitle) {
    document.getElementById('updateIssueId').value = issueId;
    document.getElementById('updateIssueTitle').textContent = issueTitle;
    const sel = document.getElementById('updateIssueStatus');
    if (sel) sel.value = currentStatus;
    const modal = new bootstrap.Modal(document.getElementById('updateIssueModal'));
    modal.show();
}

// ── Task Filter ───────────────────────────────────────────────────────────────
document.querySelectorAll('.task-filter').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.task-filter').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('.task-row').forEach(row => {
            if (filter === 'all') {
                row.style.display = '';
            } else if (filter === 'overdue') {
                row.style.display = row.dataset.overdue === '1' ? '' : 'none';
            } else {
                row.style.display = row.dataset.status === filter ? '' : 'none';
            }
        });
    });
});

// ── Issue Filter ──────────────────────────────────────────────────────────────
document.querySelectorAll('.issue-filter').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.issue-filter').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('.issue-row').forEach(row => {
            row.style.display = (filter === 'all' || row.dataset.severity === filter) ? '' : 'none';
        });
    });
});

// ── AI Assistant ──────────────────────────────────────────────────────────────
const projectId = <?= $projectId ?>;

document.querySelectorAll('.ai-suggest').forEach(chip => {
    chip.addEventListener('click', function () {
        document.getElementById('aiQuestion').value = this.dataset.question;
        askAI();
    });
});

document.getElementById('aiQuestion')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        askAI();
    }
});

async function askAI() {
    const questionEl = document.getElementById('aiQuestion');
    const question = questionEl.value.trim();
    if (!question) return;

    const chatEl = document.getElementById('chatMessages');
    const askBtn = document.getElementById('aiAskBtn');

    // Append user message
    const userMsg = document.createElement('div');
    userMsg.className = 'd-flex justify-content-end';
    userMsg.innerHTML = `<div class="bg-primary bg-opacity-20 border border-primary border-opacity-25 rounded-3 px-3 py-2 text-white small" style="max-width:85%;">${escHtml(question)}</div>`;
    chatEl.appendChild(userMsg);
    questionEl.value = '';

    // Loading indicator
    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'd-flex justify-content-start';
    loadingMsg.innerHTML = `<div class="bg-dark border border-secondary rounded-3 px-3 py-2 text-muted small d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span> Thinking...</div>`;
    chatEl.appendChild(loadingMsg);
    chatEl.scrollTop = chatEl.scrollHeight;
    askBtn.disabled = true;

    try {
        const resp = await fetch('/api/project-assistant.php', {
            method: 'POST',
            body: new URLSearchParams({project_id: projectId, question: question})
        });
        const data = await resp.json();
        loadingMsg.remove();

        const aiMsg = document.createElement('div');
        aiMsg.className = 'd-flex justify-content-start';
        const answer = data.answer || data.response || data.error || 'No response received.';
        aiMsg.innerHTML = `<div class="bg-dark border border-secondary rounded-3 px-3 py-2 text-white small" style="max-width:90%;white-space:pre-wrap;">${escHtml(answer)}</div>`;
        chatEl.appendChild(aiMsg);
    } catch (err) {
        loadingMsg.remove();
        const errMsg = document.createElement('div');
        errMsg.className = 'd-flex justify-content-start';
        errMsg.innerHTML = `<div class="bg-danger bg-opacity-20 border border-danger border-opacity-25 rounded-3 px-3 py-2 text-danger small">Error connecting to AI assistant.</div>`;
        chatEl.appendChild(errMsg);
    }

    askBtn.disabled = false;
    chatEl.scrollTop = chatEl.scrollHeight;
}

function escHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>

<?php require '../includes/footer.php'; ?>

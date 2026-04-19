<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

// ── Filters ────────────────────────────────────────────────────────────────────
$filterUser   = clean($_GET['user']   ?? '');
$filterAction = clean($_GET['action'] ?? '');
$filterEntity = clean($_GET['entity'] ?? '');
$filterDate   = clean($_GET['date']   ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));
$perPage      = ADMIN_PER_PAGE;

$where  = ['1=1'];
$params = [];

if ($filterUser) {
    $where[]  = '(up.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}
if ($filterAction) {
    $where[]  = 'al.action LIKE ?';
    $params[] = "%$filterAction%";
}
if ($filterEntity) {
    $where[]  = 'al.entity_type = ?';
    $params[] = $filterEntity;
}
if ($filterDate) {
    $where[]  = 'DATE(al.created_at) = ?';
    $params[] = $filterDate;
}

$whereStr = implode(' AND ', $where);

$baseSql = "SELECT al.*, u.email, up.full_name
            FROM activity_logs al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN user_profiles up ON up.user_id = al.user_id
            WHERE $whereStr";

$total  = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($baseSql) x", $params)['c'] ?? 0);
$pages  = (int)ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$logs = Database::fetchAll("$baseSql ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Distinct entity types for filter dropdown
$entityTypes = Database::fetchAll(
    "SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type"
);

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_admin();
    $allLogs = Database::fetchAll("$baseSql ORDER BY al.created_at DESC LIMIT 10000", $params);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date/Time', 'User', 'Email', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address']);
    foreach ($allLogs as $row) {
        fputcsv($out, [
            $row['created_at'],
            $row['full_name'] ?? 'System',
            $row['email'] ?? '—',
            $row['action'],
            $row['entity_type'] ?? '',
            $row['entity_id'] ?? '',
            $row['description'] ?? '',
            $row['ip_address'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// Action badge colours
function action_badge_class(string $action): string {
    if (str_contains($action, 'delete') || str_contains($action, 'reject')) return 'bg-danger';
    if (str_contains($action, 'creat') || str_contains($action, 'add')    || str_contains($action, 'register')) return 'bg-success';
    if (str_contains($action, 'updat') || str_contains($action, 'save')   || str_contains($action, 'edit'))    return 'bg-primary';
    if (str_contains($action, 'login') || str_contains($action, 'logout') || str_contains($action, 'auth'))    return 'bg-info';
    return 'bg-secondary';
}

$page_title = 'Activity Logs';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-700 text-navy mb-0">
                <i class="fas fa-file-export me-2" style="color:var(--pg-gold);"></i>Activity Logs
            </h4>
            <div class="text-muted small mt-1"><?= number_format($total) ?> entries</div>
        </div>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-download me-1"></i>Export CSV
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="pg-card p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small fw-600 mb-1">User</label>
                <input type="text" name="user" class="form-control form-control-sm"
                       placeholder="Name or email" value="<?= h($filterUser) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-600 mb-1">Action</label>
                <input type="text" name="action" class="form-control form-control-sm"
                       placeholder="e.g. park_saved" value="<?= h($filterAction) ?>">
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-600 mb-1">Entity Type</label>
                <select name="entity" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($entityTypes as $et): ?>
                        <option value="<?= h($et['entity_type']) ?>" <?= $filterEntity === $et['entity_type'] ? 'selected' : '' ?>>
                            <?= h($et['entity_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-600 mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= h($filterDate) ?>">
            </div>
            <div class="col-sm-2 d-flex gap-2">
                <button type="submit" class="btn btn-gold btn-sm flex-fill">Filter</button>
                <a href="<?= pg_url('admin/activity_logs.php') ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </div>
    </form>

    <!-- Logs table -->
    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th style="width:150px">Date / Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="small text-muted" style="white-space:nowrap;">
                        <?= format_date($log['created_at'], 'd M Y') ?><br>
                        <span style="font-size:.7rem;"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                    </td>
                    <td>
                        <div class="small fw-500"><?= h($log['full_name'] ?? 'System') ?></div>
                        <div class="text-muted" style="font-size:.7rem;"><?= h($log['email'] ?? '') ?></div>
                    </td>
                    <td>
                        <span class="badge <?= action_badge_class($log['action']) ?>"
                              style="font-size:.68rem;font-weight:500;">
                            <?= h(str_replace('_', ' ', $log['action'])) ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?php if ($log['entity_type']): ?>
                            <code style="font-size:.72rem;"><?= h($log['entity_type']) ?></code>
                            <?php if ($log['entity_id']): ?>
                                <span class="text-muted"> #<?= (int)$log['entity_id'] ?></span>
                            <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="small text-muted" style="max-width:280px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                        <?= h($log['description'] ?? '') ?>
                    </td>
                    <td class="small text-muted" style="white-space:nowrap;"><?= h($log['ip_address'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-file-export fa-2x mb-2 d-block"></i>
                            No log entries found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <?php
        $paginateData = [
            'rows'     => $logs,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'per_page' => $perPage,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        ];
        $baseUrl = pg_url('admin/activity_logs.php') . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
        echo pagination_links($paginateData, $baseUrl);
        ?>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>

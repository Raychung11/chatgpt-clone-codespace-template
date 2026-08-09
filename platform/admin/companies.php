<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Ensure tables exist
try {
    DB::query("CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL UNIQUE, industry VARCHAR(100) DEFAULT '',
        size ENUM('1-5','6-20','21-50','51-200','200+') DEFAULT '1-5',
        owner_id INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
    )");
} catch (Throwable $e) {}

// Search / filter
$search = trim($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($search) {
    $where = 'WHERE c.name LIKE ? OR u.name LIKE ? OR u.email LIKE ?';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$companies = DB::fetchAll(
    "SELECT c.*,
            u.name  as owner_name,
            u.email as owner_email,
            (SELECT COUNT(*) FROM users m WHERE m.company_id = c.id) as member_count
     FROM companies c
     LEFT JOIN users u ON c.owner_id = u.id
     $where
     ORDER BY c.created_at DESC",
    $params
);

// Stats
$totalCompanies = count($companies);
$totalMembers   = array_sum(array_column($companies, 'member_count'));
$avgSize        = $totalCompanies ? round($totalMembers / $totalCompanies, 1) : 0;

$pageTitle = 'Companies';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid p-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-1"><i class="bi bi-buildings me-2 text-primary"></i>Companies</h4>
            <p class="text-muted small mb-0">All registered workspaces and their teams</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="glass-card rounded-4 p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-15 text-primary"><i class="bi bi-buildings fs-5"></i></div>
                <div>
                    <div class="fs-5 fw-bold text-white"><?= $totalCompanies ?></div>
                    <div class="text-muted small">Total Workspaces</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="glass-card rounded-4 p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-15 text-success"><i class="bi bi-people fs-5"></i></div>
                <div>
                    <div class="fs-5 fw-bold text-white"><?= $totalMembers ?></div>
                    <div class="text-muted small">Total Members</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="glass-card rounded-4 p-3 d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-15 text-warning"><i class="bi bi-person-check fs-5"></i></div>
                <div>
                    <div class="fs-5 fw-bold text-white"><?= $avgSize ?></div>
                    <div class="text-muted small">Avg Members / Workspace</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="glass-card rounded-4 p-4 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-dark border-secondary text-white"
                           placeholder="Search by company or owner..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?><a href="/admin/companies.php" class="btn btn-outline-secondary ms-1">Clear</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="glass-card rounded-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead style="border-bottom:1px solid rgba(255,255,255,0.1)">
                    <tr>
                        <th class="px-4 py-3 text-muted fw-normal" style="font-size:12px">WORKSPACE</th>
                        <th class="py-3 text-muted fw-normal" style="font-size:12px">OWNER</th>
                        <th class="py-3 text-muted fw-normal" style="font-size:12px">INDUSTRY / SIZE</th>
                        <th class="py-3 text-muted fw-normal text-center" style="font-size:12px">MEMBERS</th>
                        <th class="py-3 text-muted fw-normal" style="font-size:12px">CREATED</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No companies found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($companies as $c): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-white fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($c['slug']) ?></div>
                        </td>
                        <td class="py-3">
                            <?php if ($c['owner_name']): ?>
                            <div class="text-white small"><?= htmlspecialchars($c['owner_name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($c['owner_email']) ?></div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3">
                            <div class="text-white small"><?= $c['industry'] ? htmlspecialchars($c['industry']) : '<span class="text-muted">—</span>' ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($c['size']) ?> people</div>
                        </td>
                        <td class="py-3 text-center">
                            <span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25 px-2">
                                <?= $c['member_count'] ?>
                            </span>
                        </td>
                        <td class="py-3 text-muted small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once '../includes/admin-footer.php'; ?>

<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();

// Ensure company columns exist (safe to call repeatedly)
Auth::ensureCompanyColumns();

// Create companies / invitations tables if missing
DB::query("CREATE TABLE IF NOT EXISTS companies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    slug         VARCHAR(200) NOT NULL UNIQUE,
    industry     VARCHAR(100) DEFAULT '',
    size         ENUM('1-5','6-20','21-50','51-200','200+') DEFAULT '1-5',
    owner_id     INT DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
)");
DB::query("CREATE TABLE IF NOT EXISTS company_invitations (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    company_id   INT NOT NULL,
    email        VARCHAR(200) NOT NULL,
    token        VARCHAR(64) NOT NULL UNIQUE,
    role         ENUM('admin','member') DEFAULT 'member',
    invited_by   INT DEFAULT NULL,
    expires_at   DATETIME NOT NULL,
    accepted_at  DATETIME DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
)");

$userId = Auth::id();
$user   = Auth::user();

// Bootstrap: if this user has no company, create one for them
if (!$user['company_id'] ?? true) {
    $u = DB::fetch('SELECT company_id FROM users WHERE id = ?', [$userId]);
    if (empty($u['company_id'])) {
        $wsName    = $user['company'] ?: ($user['name'] . "'s Workspace");
        $slug      = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $wsName), '-')) ?: 'workspace';
        $base = $slug; $i = 1;
        while (DB::fetch('SELECT id FROM companies WHERE slug = ?', [$slug])) $slug = $base . '-' . $i++;
        $cid = DB::insert('companies', ['name' => htmlspecialchars($wsName), 'slug' => $slug, 'owner_id' => $userId]);
        DB::update('users', ['company_id' => $cid, 'company_role' => 'owner'], 'id = ?', [$userId]);
        $user = Auth::user(); // reload
        unset($_SESSION['company_id'], $_SESSION['company_role']);
    }
}

$companyId = Auth::companyId();
$myRole    = Auth::companyRole();
$canManage = in_array($myRole, ['owner', 'admin']);

$company = $companyId ? DB::fetch('SELECT * FROM companies WHERE id = ?', [$companyId]) : null;

$flash = '';

/* ── Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_company' && $company) {
        $newName = htmlspecialchars(trim($_POST['name'] ?? ''));
        $newInd  = htmlspecialchars(trim($_POST['industry'] ?? ''));
        $newSize = $_POST['size'] ?? '1-5';
        if ($newName) {
            DB::update('companies', ['name' => $newName, 'industry' => $newInd, 'size' => $newSize], 'id = ?', [$companyId]);
            $company = DB::fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
            $flash = 'Company profile updated.';
        }
    }

    if ($action === 'invite' && $company) {
        $invEmail = strtolower(trim($_POST['email'] ?? ''));
        $invRole  = in_array($_POST['role'] ?? '', ['admin','member']) ? $_POST['role'] : 'member';
        if (filter_var($invEmail, FILTER_VALIDATE_EMAIL)) {
            // Check not already a member
            $existing = DB::fetch('SELECT id FROM users WHERE email = ? AND company_id = ?', [$invEmail, $companyId]);
            if ($existing) {
                $flash = 'error:This person is already a member of your workspace.';
            } else {
                // Upsert invitation (cancel old pending one for same email)
                DB::query('DELETE FROM company_invitations WHERE company_id=? AND email=? AND accepted_at IS NULL', [$companyId, $invEmail]);
                $token = bin2hex(random_bytes(32));
                DB::insert('company_invitations', [
                    'company_id' => $companyId,
                    'email'      => $invEmail,
                    'token'      => $token,
                    'role'       => $invRole,
                    'invited_by' => $userId,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                ]);
                $flash = 'invited:' . $token;
            }
        } else {
            $flash = 'error:Please enter a valid email address.';
        }
    }

    if ($action === 'remove_member') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId && $targetId !== $userId) {
            $target = DB::fetch('SELECT id, company_role FROM users WHERE id = ? AND company_id = ?', [$targetId, $companyId]);
            // Owners can't be removed by admins
            if ($target && !($target['company_role'] === 'owner' && $myRole !== 'owner')) {
                DB::update('users', ['company_id' => null, 'company_role' => 'member'], 'id = ?', [$targetId]);
                $flash = 'Member removed.';
            }
        }
    }

    if ($action === 'change_role') {
        $targetId  = (int)($_POST['target_id'] ?? 0);
        $newRole   = in_array($_POST['role'] ?? '', ['admin','member']) ? $_POST['role'] : 'member';
        if ($targetId && $targetId !== $userId && $myRole === 'owner') {
            DB::fetch('SELECT id FROM users WHERE id = ? AND company_id = ?', [$targetId, $companyId])
                && DB::update('users', ['company_role' => $newRole], 'id = ?', [$targetId]);
            $flash = 'Role updated.';
        }
    }

    if ($action === 'cancel_invitation') {
        $invId = (int)($_POST['inv_id'] ?? 0);
        if ($invId) DB::query('DELETE FROM company_invitations WHERE id = ? AND company_id = ?', [$invId, $companyId]);
        $flash = 'Invitation cancelled.';
    }
}

// Load team data
$members = $companyId ? DB::fetchAll(
    'SELECT id, name, email, company_role, created_at FROM users WHERE company_id = ? ORDER BY company_role ASC, name ASC',
    [$companyId]
) : [];

$pendingInvites = $companyId ? DB::fetchAll(
    'SELECT ci.*, u.name as inviter_name FROM company_invitations ci
     LEFT JOIN users u ON ci.invited_by = u.id
     WHERE ci.company_id = ? AND ci.accepted_at IS NULL AND ci.expires_at > NOW()
     ORDER BY ci.created_at DESC',
    [$companyId]
) : [];

// Parse flash
$flashType = 'success';
$flashMsg  = '';
$inviteLink = '';
if ($flash) {
    if (str_starts_with($flash, 'error:')) { $flashType = 'danger'; $flashMsg = substr($flash, 6); }
    elseif (str_starts_with($flash, 'invited:')) { $inviteLink = SITE_URL . '/invite.php?token=' . substr($flash, 8); $flashMsg = 'Invitation created!'; }
    else $flashMsg = $flash;
}

$pageTitle = 'Team Management';
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/dashboard.php" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Team Management</h3>
            <p class="text-muted small mb-0">Manage your workspace and invite team members</p>
        </div>
    </div>

    <?php if ($flashMsg): ?>
    <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMsg) ?>
        <?php if ($inviteLink): ?>
        <div class="mt-2">
            <strong>Share this invitation link:</strong>
            <div class="input-group mt-1" style="max-width:500px">
                <input type="text" class="form-control form-control-sm bg-dark border-secondary text-white" id="inviteLink" value="<?= htmlspecialchars($inviteLink) ?>" readonly>
                <button class="btn btn-sm btn-outline-secondary" onclick="copyInviteLink()"><i class="bi bi-clipboard"></i></button>
            </div>
            <div class="text-muted small mt-1">Link expires in 7 days</div>
        </div>
        <?php endif; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Workspace + Invite -->
        <div class="col-lg-4">

            <!-- Workspace Profile -->
            <?php if ($company): ?>
            <div class="glass-card rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0"><i class="bi bi-building me-2"></i>Workspace</h6>
                    <?php if ($canManage): ?>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editCompany">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <div class="text-white fw-semibold fs-6"><?= htmlspecialchars($company['name']) ?></div>
                    <?php if ($company['industry']): ?>
                    <div class="text-muted small"><?= htmlspecialchars($company['industry']) ?></div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-2">
                        <span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25" style="font-size:10px">
                            <i class="bi bi-people me-1"></i><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?>
                        </span>
                        <span class="badge bg-secondary bg-opacity-30 text-muted border border-secondary border-opacity-25" style="font-size:10px">
                            <?= htmlspecialchars($company['size']) ?> people
                        </span>
                    </div>
                </div>

                <?php if ($canManage): ?>
                <div class="collapse" id="editCompany">
                    <hr class="border-secondary border-opacity-25">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_company">
                        <div class="mb-2">
                            <label class="form-label text-muted small">Workspace Name</label>
                            <input type="text" name="name" class="form-control form-control-sm bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($company['name']) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small">Industry</label>
                            <input type="text" name="industry" class="form-control form-control-sm bg-dark border-secondary text-white"
                                   value="<?= htmlspecialchars($company['industry'] ?? '') ?>" placeholder="e.g. F&B, Retail, Healthcare">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Team Size</label>
                            <select name="size" class="form-select form-select-sm bg-dark border-secondary text-white">
                                <?php foreach (['1-5','6-20','21-50','51-200','200+'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($company['size'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?> people</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm w-100">Save Changes</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Invite Member -->
            <?php if ($canManage): ?>
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-person-plus me-2 text-success"></i>Invite Member</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="invite">
                    <div class="mb-2">
                        <label class="form-label text-muted small">Email Address</label>
                        <input type="email" name="email" class="form-control bg-dark border-secondary text-white"
                               placeholder="colleague@company.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Role</label>
                        <select name="role" class="form-select bg-dark border-secondary text-white">
                            <option value="member">Member — can use AI tools</option>
                            <?php if ($myRole === 'owner'): ?>
                            <option value="admin">Admin — can invite & manage members</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-send me-2"></i>Generate Invite Link
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Members + Pending Invitations -->
        <div class="col-lg-8">

            <!-- Members -->
            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-people-fill me-2"></i>Members
                    <span class="badge bg-secondary ms-2" style="font-size:11px"><?= count($members) ?></span>
                </h6>

                <?php foreach ($members as $m):
                    $roleBadge = match($m['company_role']) {
                        'owner' => ['bg-warning text-dark', 'Owner'],
                        'admin' => ['bg-info text-dark', 'Admin'],
                        default => ['bg-secondary', 'Member'],
                    };
                ?>
                <div class="d-flex align-items-center gap-3 py-2 border-bottom border-secondary border-opacity-15">
                    <div class="avatar-initials sm flex-shrink-0"><?= strtoupper(substr($m['name'], 0, 2)) ?></div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white small fw-semibold">
                            <?= htmlspecialchars($m['name']) ?>
                            <?php if ($m['id'] === $userId): ?><span class="text-muted" style="font-size:11px"> (you)</span><?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:12px"><?= htmlspecialchars($m['email']) ?></div>
                    </div>
                    <span class="badge <?= $roleBadge[0] ?> flex-shrink-0" style="font-size:10px"><?= $roleBadge[1] ?></span>

                    <?php if ($canManage && $m['id'] !== $userId && $m['company_role'] !== 'owner'): ?>
                    <div class="dropdown flex-shrink-0">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                            <?php if ($myRole === 'owner'): ?>
                            <li>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="role" value="<?= $m['company_role'] === 'admin' ? 'member' : 'admin' ?>">
                                    <button class="dropdown-item" type="submit">
                                        <i class="bi bi-arrow-repeat me-2"></i>
                                        Make <?= $m['company_role'] === 'admin' ? 'Member' : 'Admin' ?>
                                    </button>
                                </form>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($m['name'])) ?> from this workspace?')">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <button class="dropdown-item text-danger" type="submit">
                                        <i class="bi bi-person-dash me-2"></i>Remove
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pending Invitations -->
            <?php if ($pendingInvites && $canManage): ?>
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-envelope-open me-2 text-warning"></i>Pending Invitations
                    <span class="badge bg-warning text-dark ms-2" style="font-size:11px"><?= count($pendingInvites) ?></span>
                </h6>
                <?php foreach ($pendingInvites as $inv): ?>
                <div class="d-flex align-items-center gap-3 py-2 border-bottom border-secondary border-opacity-15">
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white small"><?= htmlspecialchars($inv['email']) ?></div>
                        <div class="text-muted" style="font-size:11px">
                            <?= ucfirst($inv['role']) ?> &bull; Expires <?= date('d M', strtotime($inv['expires_at'])) ?>
                            <?php if ($inv['inviter_name']): ?>&bull; Invited by <?= htmlspecialchars($inv['inviter_name']) ?><?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary flex-shrink-0"
                        onclick="navigator.clipboard.writeText('<?= htmlspecialchars(SITE_URL.'/invite.php?token='.$inv['token']) ?>')" title="Copy invite link">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="cancel_invitation">
                        <input type="hidden" name="inv_id" value="<?= $inv['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel invitation">
                            <i class="bi bi-x"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function copyInviteLink() {
    const input = document.getElementById('inviteLink');
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        btn.innerHTML = '<i class="bi bi-check"></i>';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>

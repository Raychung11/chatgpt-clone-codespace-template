<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Ensure tables exist
try {
    DB::query("CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL UNIQUE, industry VARCHAR(100) DEFAULT '',
        size ENUM('1-5','6-20','21-50','51-200','200+') DEFAULT '1-5',
        owner_id INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    DB::query("CREATE TABLE IF NOT EXISTS company_invitations (
        id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL,
        email VARCHAR(200) NOT NULL, token VARCHAR(64) NOT NULL UNIQUE,
        role ENUM('admin','member') DEFAULT 'member', invited_by INT DEFAULT NULL,
        expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    Auth::ensureCompanyColumns();
} catch (Throwable $e) {}

$token = trim($_GET['token'] ?? '');
$invitation = $token ? DB::fetch(
    'SELECT ci.*, c.name as company_name FROM company_invitations ci
     JOIN companies c ON ci.company_id = c.id
     WHERE ci.token = ? AND ci.accepted_at IS NULL AND ci.expires_at > NOW()',
    [$token]
) : null;

$error = '';
$success = false;

if (!$token || !$invitation) {
    $error = 'This invitation link is invalid or has expired.';
}

// Handle accept (POST) — user must be logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation && Auth::check()) {
    $userId = Auth::id();
    $user   = Auth::user();

    // Already in this company
    if ($user['company_id'] == $invitation['company_id']) {
        $success = true;
    } else {
        try {
            DB::update('users', [
                'company_id'   => $invitation['company_id'],
                'company_role' => $invitation['role'],
            ], 'id = ?', [$userId]);
            DB::update('company_invitations', ['accepted_at' => date('Y-m-d H:i:s')], 'id = ?', [$invitation['id']]);
            // Invalidate cached session values
            unset($_SESSION['company_id'], $_SESSION['company_role']);
            $success = true;
        } catch (Throwable $e) {
            $error = 'Could not join workspace. Please try again.';
        }
    }
}

$pageTitle = $invitation ? 'Join ' . htmlspecialchars($invitation['company_name']) : 'Invalid Invitation';
require_once 'includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4">
                    <a href="/" class="navbar-brand fw-bold fs-4 text-white">
                        <i class="bi bi-cpu-fill me-2 text-primary"></i><?= SITE_NAME ?>
                    </a>
                </div>

                <?php if ($error && !$invitation): ?>
                <!-- Invalid / expired -->
                <div class="glass-card rounded-4 p-4 text-center">
                    <i class="bi bi-link-45deg fs-1 text-muted d-block mb-3" style="opacity:.4"></i>
                    <h5 class="text-white fw-bold mb-2">Invitation Not Found</h5>
                    <p class="text-muted small mb-4"><?= htmlspecialchars($error) ?></p>
                    <a href="/" class="btn btn-outline-secondary">Go to Homepage</a>
                </div>

                <?php elseif ($success): ?>
                <!-- Success -->
                <div class="glass-card rounded-4 p-4 text-center">
                    <div class="mb-3" style="width:56px;height:56px;border-radius:50%;background:rgba(16,185,129,0.2);display:flex;align-items:center;justify-content:center;margin:0 auto">
                        <i class="bi bi-check-lg text-success fs-3"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">You've Joined!</h5>
                    <p class="text-muted small mb-4">
                        You're now a member of <strong class="text-white"><?= htmlspecialchars($invitation['company_name']) ?></strong>.
                    </p>
                    <a href="/dashboard.php" class="btn btn-primary px-4">Go to Dashboard</a>
                </div>

                <?php else: ?>
                <!-- Accept invitation -->
                <div class="glass-card rounded-4 p-4">
                    <div class="text-center mb-4">
                        <div class="mb-3" style="width:56px;height:56px;border-radius:50%;background:rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;margin:0 auto">
                            <i class="bi bi-buildings text-primary fs-4"></i>
                        </div>
                        <h5 class="text-white fw-bold mb-1">You've been invited!</h5>
                        <p class="text-muted small">
                            Join <strong class="text-white"><?= htmlspecialchars($invitation['company_name']) ?></strong>
                            as a <span class="text-primary"><?= ucfirst($invitation['role']) ?></span>
                        </p>
                        <div class="text-muted" style="font-size:11px">Expires <?= date('d M Y', strtotime($invitation['expires_at'])) ?></div>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (Auth::check()): ?>
                        <?php $u = Auth::user(); ?>
                        <div class="rounded-3 p-3 mb-3 text-center" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)">
                            <div class="text-muted small mb-1">Joining as</div>
                            <div class="text-white fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                        <?php if ($u['company_id'] == $invitation['company_id']): ?>
                        <div class="alert alert-info py-2 small text-center">You're already a member of this workspace.</div>
                        <a href="/team.php" class="btn btn-primary w-100">Go to Team Page</a>
                        <?php else: ?>
                        <form method="POST">
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-buildings me-2"></i>Join <?= htmlspecialchars($invitation['company_name']) ?>
                            </button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="/logout.php" class="text-muted small">Use a different account</a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <a href="/register.php?invite=<?= urlencode($token) ?>" class="btn btn-primary py-2">
                                <i class="bi bi-person-plus me-2"></i>Create Account to Join
                            </a>
                            <a href="/login.php?redirect=<?= urlencode('/invite.php?token='.$token) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Join
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id               = (int)($_POST['id'] ?? 0);
        $author_name      = trim($_POST['author_name'] ?? '');
        $author_title     = trim($_POST['author_title'] ?? '');
        $author_avatar_url= trim($_POST['author_avatar_url'] ?? '');
        $platform         = $_POST['platform'] ?? 'other';
        $content          = trim($_POST['content'] ?? '');
        $rating           = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $featured         = isset($_POST['featured']) ? 1 : 0;
        $status           = $_POST['status'] ?? 'pending';

        $data = [
            'author_name'       => htmlspecialchars($author_name),
            'author_title'      => htmlspecialchars($author_title),
            'author_avatar_url' => htmlspecialchars($author_avatar_url),
            'platform'          => $platform,
            'content'           => htmlspecialchars($content),
            'rating'            => $rating,
            'featured'          => $featured,
            'status'            => $status,
        ];

        if ($id > 0) {
            DB::update('shoutouts', $data, 'id = ?', [$id]);
        } else {
            DB::insert('shoutouts', $data);
        }
        header('Location: /admin/shoutouts.php?saved=1');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM shoutouts WHERE id = ?', [$id]);
        }
        header('Location: /admin/shoutouts.php?deleted=1');
        exit;
    }

    if ($action === 'toggle_featured') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $current = DB::fetch('SELECT featured FROM shoutouts WHERE id = ?', [$id]);
            if ($current) {
                DB::update('shoutouts', ['featured' => $current['featured'] ? 0 : 1], 'id = ?', [$id]);
            }
        }
        header('Location: /admin/shoutouts.php?featured=1');
        exit;
    }

    if ($action === 'quick_publish') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::update('shoutouts', ['status' => 'published'], 'id = ?', [$id]);
        }
        header('Location: /admin/shoutouts.php?published=1');
        exit;
    }
}

// ── Data ─────────────────────────────────────────────────────────────────────
$shoutouts = DB::fetchAll("SELECT * FROM shoutouts ORDER BY created_at DESC");

// ── KPIs ─────────────────────────────────────────────────────────────────────
$kpiTotal    = DB::fetch("SELECT COUNT(*) as n FROM shoutouts")['n'] ?? 0;
$kpiPublish  = DB::fetch("SELECT COUNT(*) as n FROM shoutouts WHERE status = 'published'")['n'] ?? 0;
$kpiPending  = DB::fetch("SELECT COUNT(*) as n FROM shoutouts WHERE status = 'pending'")['n'] ?? 0;
$kpiFeatured = DB::fetch("SELECT COUNT(*) as n FROM shoutouts WHERE featured = 1")['n'] ?? 0;

$pageTitle = 'Shoutouts';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Shoutouts</h4>
            <p class="text-muted small mb-0">Testimonials, reviews &amp; social proof manager</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#shoutoutModal"
                onclick="openShoutoutModal(null)">
            <i class="bi bi-plus-circle me-1"></i>Add Shoutout
        </button>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Shoutout saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show"><i class="bi bi-trash me-2"></i>Shoutout deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['published'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-megaphone me-2"></i>Shoutout published! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['featured'])): ?>
    <div class="alert alert-info alert-dismissible fade show"><i class="bi bi-star me-2"></i>Featured status updated. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-chat-quote fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Shoutouts</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiTotal) ?></div>
                        <div class="text-primary small">All testimonials</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-megaphone fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Published</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiPublish) ?></div>
                        <div class="text-success small">Live on site</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Review</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiPending) ?></div>
                        <div class="text-warning small">Awaiting approval</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-star-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Featured</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiFeatured) ?></div>
                        <div class="text-info small">Highlighted items</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shoutouts Table -->
    <div class="admin-card rounded-4 p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Author</th>
                        <th>Platform</th>
                        <th>Content Preview</th>
                        <th class="text-center">Rating</th>
                        <th class="text-center">Featured</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shoutouts)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No shoutouts yet. Add your first testimonial.</td></tr>
                    <?php else: ?>
                    <?php
                    $platformColors = ['twitter'=>'info','linkedin'=>'primary','instagram'=>'danger','facebook'=>'primary','email'=>'success','other'=>'secondary'];
                    $platformIcons  = ['twitter'=>'bi-twitter-x','linkedin'=>'bi-linkedin','instagram'=>'bi-instagram','facebook'=>'bi-facebook','email'=>'bi-envelope','other'=>'bi-chat'];
                    $statusColors   = ['pending'=>'warning','published'=>'success','rejected'=>'danger'];
                    foreach ($shoutouts as $s):
                        $platCol = $platformColors[$s['platform']] ?? 'secondary';
                        $platIco = $platformIcons[$s['platform']] ?? 'bi-chat';
                        $statCol = $statusColors[$s['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($s['author_avatar_url']): ?>
                                <img src="<?= htmlspecialchars($s['author_avatar_url']) ?>" alt="" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                                <?php else: ?>
                                <div class="rounded-circle bg-primary bg-opacity-20 text-primary d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px;font-size:12px">
                                    <?= strtoupper(substr($s['author_name'], 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="text-white small fw-semibold"><?= htmlspecialchars($s['author_name']) ?></div>
                                    <?php if ($s['author_title']): ?>
                                    <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($s['author_title']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $platCol ?> bg-opacity-15 text-<?= $platCol ?> border border-<?= $platCol ?> border-opacity-25">
                                <i class="bi <?= $platIco ?> me-1"></i><?= ucfirst($s['platform']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-muted small" style="max-width:280px">
                                "<?= htmlspecialchars(mb_substr($s['content'], 0, 100)) ?><?= mb_strlen($s['content']) > 100 ? '…' : '' ?>"
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="text-warning">
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <i class="bi bi-star<?= $r <= $s['rating'] ? '-fill' : '' ?>" style="font-size:12px"></i>
                                <?php endfor; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-link p-0 <?= $s['featured'] ? 'text-warning' : 'text-muted' ?>" title="Toggle featured">
                                    <i class="bi bi-star<?= $s['featured'] ? '-fill' : '' ?> fs-5"></i>
                                </button>
                            </form>
                        </td>
                        <td><span class="badge bg-<?= $statCol ?>"><?= ucfirst($s['status']) ?></span></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end flex-wrap">
                                <?php if ($s['status'] !== 'published'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="quick_publish">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Publish">
                                        <i class="bi bi-megaphone" style="font-size:12px"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button class="btn btn-outline-info btn-sm py-0 px-2"
                                        onclick="showEmbed(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#embedModal" title="Get embed code">
                                    <i class="bi bi-code-slash" style="font-size:12px"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                                        onclick="openShoutoutModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#shoutoutModal" title="Edit">
                                    <i class="bi bi-pencil" style="font-size:12px"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this shoutout?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                                        <i class="bi bi-trash" style="font-size:12px"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Shoutout Modal -->
<div class="modal fade" id="shoutoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="shoutoutModalLabel">Add Shoutout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="shoutoutForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="sFieldId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Author Name <span class="text-danger">*</span></label>
                            <input type="text" name="author_name" id="sFieldAuthorName" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Author Title / Company</label>
                            <input type="text" name="author_title" id="sFieldAuthorTitle" class="form-control bg-dark text-white border-secondary"
                                   placeholder="e.g. Marketing Director at Acme">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Avatar URL</label>
                            <input type="url" name="author_avatar_url" id="sFieldAvatarUrl" class="form-control bg-dark text-white border-secondary"
                                   placeholder="https://...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Platform</label>
                            <select name="platform" id="sFieldPlatform" class="form-select bg-dark text-white border-secondary">
                                <?php foreach (['twitter','linkedin','instagram','facebook','email','other'] as $p): ?>
                                <option value="<?= $p ?>"><?= ucfirst($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Content <span class="text-danger">*</span></label>
                            <textarea name="content" id="sFieldContent" rows="4"
                                      class="form-control bg-dark text-white border-secondary" required
                                      placeholder="The testimonial text..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Rating</label>
                            <select name="rating" id="sFieldRating" class="form-select bg-dark text-white border-secondary">
                                <?php for ($r = 5; $r >= 1; $r--): ?>
                                <option value="<?= $r ?>"><?= str_repeat('★', $r) . str_repeat('☆', 5-$r) ?> (<?= $r ?>)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="sFieldStatus" class="form-select bg-dark text-white border-secondary">
                                <option value="pending">Pending</option>
                                <option value="published">Published</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="featured" id="sFieldFeatured" value="1">
                                <label class="form-check-label text-muted small" for="sFieldFeatured">Featured</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Shoutout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Embed Snippet Modal -->
<div class="modal fade" id="embedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="bi bi-code-slash me-2 text-info"></i>Embed Snippet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Copy and paste this HTML snippet anywhere on your website to display this testimonial.</p>
                <pre id="embedCode" class="p-3 rounded-3 text-success small" style="background:#0a0a0f;white-space:pre-wrap;word-break:break-all;border:1px solid rgba(255,255,255,0.08)"></pre>
                <button class="btn btn-outline-info btn-sm mt-2" onclick="copyEmbed()">
                    <i class="bi bi-clipboard me-1"></i>Copy to Clipboard
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openShoutoutModal(record) {
    document.getElementById('shoutoutModalLabel').textContent = record ? 'Edit Shoutout' : 'Add Shoutout';
    document.getElementById('shoutoutForm').reset();
    document.getElementById('sFieldId').value = '0';
    if (!record) return;
    document.getElementById('sFieldId').value          = record.id;
    document.getElementById('sFieldAuthorName').value  = record.author_name;
    document.getElementById('sFieldAuthorTitle').value = record.author_title || '';
    document.getElementById('sFieldAvatarUrl').value   = record.author_avatar_url || '';
    document.getElementById('sFieldPlatform').value    = record.platform;
    document.getElementById('sFieldContent').value     = record.content;
    document.getElementById('sFieldRating').value      = record.rating;
    document.getElementById('sFieldStatus').value      = record.status;
    document.getElementById('sFieldFeatured').checked  = record.featured == 1;
}

function showEmbed(record) {
    const stars = '★'.repeat(record.rating) + '☆'.repeat(5 - record.rating);
    const snippet = `<div class="shoutout-card" style="background:#111118;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:24px;max-width:480px;font-family:Inter,sans-serif">
  <div style="color:#facc15;margin-bottom:12px;font-size:18px">${stars}</div>
  <p style="color:#e5e7eb;font-size:15px;line-height:1.6;margin-bottom:16px">"${record.content.replace(/"/g,'&quot;')}"</p>
  <div style="display:flex;align-items:center;gap:12px">
    <div>
      <strong style="color:#fff;font-size:14px">${record.author_name}</strong>
      <div style="color:#9ca3af;font-size:12px">${record.author_title || ''}</div>
    </div>
  </div>
</div>`;
    document.getElementById('embedCode').textContent = snippet;
}

function copyEmbed() {
    const code = document.getElementById('embedCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>

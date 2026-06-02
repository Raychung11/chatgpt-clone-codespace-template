<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

$pageTitle = 'Blog Posts';
$msg = '';
$error = '';

// ── Slug generator helper ──────────────────────────────────────────────────────
function makeSlug(string $title): string {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

// ── POST actions ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($action === 'create') {
        try {
            $title       = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES);
            $slug        = makeSlug(trim($_POST['slug'] ?? '') ?: $title);
            $excerpt     = htmlspecialchars(trim($_POST['excerpt'] ?? ''), ENT_QUOTES);
            $content     = trim($_POST['content'] ?? '');
            $category    = htmlspecialchars(trim($_POST['category'] ?? 'General'), ENT_QUOTES);
            $tags        = htmlspecialchars(trim($_POST['tags'] ?? ''), ENT_QUOTES);
            $metaTitle   = htmlspecialchars(trim($_POST['meta_title'] ?? ''), ENT_QUOTES);
            $metaDesc    = htmlspecialchars(trim($_POST['meta_description'] ?? ''), ENT_QUOTES);
            $status      = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
            $publishedAt = !empty($_POST['published_at']) ? date('Y-m-d H:i:s', strtotime($_POST['published_at'])) : null;

            if (!$title) throw new Exception('Title is required.');

            // Ensure unique slug
            $existing = DB::fetch('SELECT id FROM blog_posts WHERE slug = ?', [$slug]);
            if ($existing) $slug .= '-' . time();

            DB::insert('blog_posts', [
                'title'            => $title,
                'slug'             => $slug,
                'excerpt'          => $excerpt,
                'content'          => $content,
                'category'         => $category,
                'tags'             => $tags,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
                'status'           => $status,
                'published_at'     => $publishedAt,
                'author_id'        => $_SESSION['user_id'] ?? null,
            ]);
            $msg = 'Blog post created successfully.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    // ── UPDATE ──
    elseif ($action === 'update') {
        try {
            $id          = (int)($_POST['id'] ?? 0);
            $title       = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES);
            $slug        = makeSlug(trim($_POST['slug'] ?? '') ?: $title);
            $excerpt     = htmlspecialchars(trim($_POST['excerpt'] ?? ''), ENT_QUOTES);
            $content     = trim($_POST['content'] ?? '');
            $category    = htmlspecialchars(trim($_POST['category'] ?? 'General'), ENT_QUOTES);
            $tags        = htmlspecialchars(trim($_POST['tags'] ?? ''), ENT_QUOTES);
            $metaTitle   = htmlspecialchars(trim($_POST['meta_title'] ?? ''), ENT_QUOTES);
            $metaDesc    = htmlspecialchars(trim($_POST['meta_description'] ?? ''), ENT_QUOTES);
            $status      = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
            $publishedAt = !empty($_POST['published_at']) ? date('Y-m-d H:i:s', strtotime($_POST['published_at'])) : null;

            if (!$id || !$title) throw new Exception('Invalid data.');

            // Ensure slug unique (exclude self)
            $existing = DB::fetch('SELECT id FROM blog_posts WHERE slug = ? AND id != ?', [$slug, $id]);
            if ($existing) $slug .= '-' . time();

            DB::update('blog_posts', [
                'title'            => $title,
                'slug'             => $slug,
                'excerpt'          => $excerpt,
                'content'          => $content,
                'category'         => $category,
                'tags'             => $tags,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
                'status'           => $status,
                'published_at'     => $publishedAt,
            ], 'id = ?', [$id]);
            $msg = 'Blog post updated successfully.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    // ── DELETE ──
    elseif ($action === 'delete') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Invalid ID.');
            DB::update('blog_posts', ['status' => 'draft'], 'id = ?', [$id]); // soft delete by draft
            // Hard delete:
            \DB::$pdo->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
            $msg = 'Blog post deleted.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    // ── TOGGLE STATUS ──
    elseif ($action === 'toggle_status') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Invalid ID.');
            $current = DB::fetch('SELECT status FROM blog_posts WHERE id = ?', [$id]);
            if (!$current) throw new Exception('Post not found.');
            $newStatus = ($current['status'] === 'published') ? 'draft' : 'published';
            $fields = ['status' => $newStatus];
            if ($newStatus === 'published') {
                $fields['published_at'] = date('Y-m-d H:i:s');
            }
            DB::update('blog_posts', $fields, 'id = ?', [$id]);
            $msg = 'Status changed to ' . $newStatus . '.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── Fetch posts ────────────────────────────────────────────────────────────────
$posts = [];
$categories = [];
try {
    $filterStatus = $_GET['status'] ?? '';
    $filterCat    = $_GET['cat'] ?? '';
    $searchQ      = trim($_GET['q'] ?? '');

    $where  = ['1=1'];
    $params = [];

    if ($filterStatus && in_array($filterStatus, ['draft', 'published'])) {
        $where[]  = 'status = ?';
        $params[] = $filterStatus;
    }
    if ($filterCat) {
        $where[]  = 'category = ?';
        $params[] = $filterCat;
    }
    if ($searchQ) {
        $where[]  = '(title LIKE ? OR excerpt LIKE ?)';
        $params   = array_merge($params, ["%$searchQ%", "%$searchQ%"]);
    }

    $whereSQL = implode(' AND ', $where);
    $posts    = DB::fetchAll(
        "SELECT bp.*, u.name as author_name
         FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.id
         WHERE $whereSQL ORDER BY bp.created_at DESC",
        $params
    );
    $categories = DB::fetchAll("SELECT DISTINCT category FROM blog_posts ORDER BY category");
} catch (Exception $e) {
    $error = 'Could not load posts — the blog_posts table may not exist yet. Run the database migration first.';
}

// Edit: load specific post
$editPost = null;
if (isset($_GET['edit'])) {
    try {
        $editPost = DB::fetch('SELECT * FROM blog_posts WHERE id = ?', [(int)$_GET['edit']]);
    } catch (Exception $e) {}
}

require_once '../includes/admin-header.php';
?>

<div class="admin-content p-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-white mb-0">Blog Posts</h4>
            <p class="text-muted small mb-0">Manage blog content and SEO</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postModal" id="newPostBtn">
            <i class="bi bi-plus-lg me-2"></i>New Post
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" action="/admin/blog.php" class="glass-card p-3 rounded-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="search" name="q" class="form-control form-control-sm bg-dark border-secondary text-white"
                       placeholder="Search posts..." value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Statuses</option>
                    <option value="published" <?= ($_GET['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft"     <?= ($_GET['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="cat" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c['category'], ENT_QUOTES) ?>"
                        <?= ($_GET['cat'] ?? '') === $c['category'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['category']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                <a href="/admin/blog.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </div>
    </form>

    <!-- Posts Table -->
    <div class="glass-card rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr class="border-secondary">
                        <th class="px-4 py-3 text-muted small fw-semibold">Title</th>
                        <th class="py-3 text-muted small fw-semibold">Category</th>
                        <th class="py-3 text-muted small fw-semibold">Status</th>
                        <th class="py-3 text-muted small fw-semibold">Views</th>
                        <th class="py-3 text-muted small fw-semibold">Published</th>
                        <th class="py-3 text-muted small fw-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-file-richtext d-block fs-1 mb-2 opacity-25"></i>
                        No posts found.
                        <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#postModal">
                            Create your first post
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($posts as $p): ?>
                <tr class="border-secondary border-opacity-25">
                    <td class="px-4 py-3">
                        <div class="text-white fw-semibold small"><?= htmlspecialchars($p['title']) ?></div>
                        <div class="text-muted" style="font-size:11px">
                            /blog/<?= htmlspecialchars($p['slug']) ?>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="badge bg-secondary bg-opacity-25 text-muted small"><?= htmlspecialchars($p['category'] ?? 'General') ?></span>
                    </td>
                    <td class="py-3">
                        <?php if ($p['status'] === 'published'): ?>
                        <span class="badge bg-success bg-opacity-20 text-success">Published</span>
                        <?php else: ?>
                        <span class="badge bg-warning bg-opacity-20 text-warning">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 text-muted small"><?= number_format((int)$p['views']) ?></td>
                    <td class="py-3 text-muted small">
                        <?= $p['published_at'] ? date('d M Y', strtotime($p['published_at'])) : '—' ?>
                    </td>
                    <td class="py-3">
                        <div class="d-flex gap-1">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- Toggle Status -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $p['status'] === 'published' ? 'warning' : 'success' ?>"
                                        title="<?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?>">
                                    <i class="bi bi-<?= $p['status'] === 'published' ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                            </form>
                            <!-- View Live -->
                            <?php if ($p['status'] === 'published'): ?>
                            <a href="/blog/<?= htmlspecialchars($p['slug'], ENT_QUOTES) ?>" target="_blank"
                               class="btn btn-sm btn-outline-secondary" title="View Live">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            <?php endif; ?>
                            <!-- Delete -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this post? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
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

</div><!-- /admin-content -->

<!-- Create / Edit Modal -->
<div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="postModalLabel">New Blog Post</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="postForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId" value="">

                    <div class="row g-3">
                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Title *</label>
                            <input type="text" name="title" id="formTitle"
                                   class="form-control bg-dark border-secondary text-white" required
                                   placeholder="e.g. 5 Ways Malaysian SMEs Can Use AI in 2026"
                                   oninput="autoSlug(this.value)">
                        </div>
                        <!-- Slug -->
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Slug (URL)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted small">/blog/</span>
                                <input type="text" name="slug" id="formSlug"
                                       class="form-control bg-dark border-secondary text-white"
                                       placeholder="auto-generated-from-title">
                            </div>
                        </div>
                        <!-- Category -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Category</label>
                            <input type="text" name="category" id="formCategory"
                                   class="form-control bg-dark border-secondary text-white"
                                   list="categoryList" placeholder="AI Strategy">
                            <datalist id="categoryList">
                                <option value="AI Strategy">
                                <option value="Customer Service">
                                <option value="Sales">
                                <option value="HR & Operations">
                                <option value="Finance">
                                <option value="Project Management">
                            </datalist>
                        </div>
                        <!-- Excerpt -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Excerpt (short description)</label>
                            <textarea name="excerpt" id="formExcerpt" rows="2"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="A short summary shown on the blog listing page..."></textarea>
                        </div>
                        <!-- Content -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Content *</label>
                            <textarea name="content" id="formContent" rows="15"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="Write your article content here... HTML tags are supported." required></textarea>
                            <div class="form-text text-muted">Supports basic HTML. Word count: <span id="wordCount">0</span></div>
                        </div>
                        <!-- Tags -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Tags (comma-separated)</label>
                            <input type="text" name="tags" id="formTags"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="AI, automation, SME, Malaysia">
                        </div>
                        <!-- Status -->
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="formStatus" class="form-select bg-dark border-secondary text-white">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <!-- Published At -->
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Publish Date</label>
                            <input type="datetime-local" name="published_at" id="formPublishedAt"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <div class="col-12"><hr class="border-secondary opacity-25"><p class="text-muted small fw-semibold mb-0">SEO / Meta</p></div>

                        <!-- Meta Title -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Meta Title <span class="text-muted">(leave blank to use title)</span></label>
                            <input type="text" name="meta_title" id="formMetaTitle"
                                   class="form-control bg-dark border-secondary text-white" maxlength="255"
                                   placeholder="SEO optimized title for Google...">
                        </div>
                        <!-- Meta Description -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Meta Description <span class="text-muted">(max 300 chars)</span></label>
                            <textarea name="meta_description" id="formMetaDesc" rows="2" maxlength="300"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="SEO meta description for Google search results..."></textarea>
                            <div class="form-text text-muted"><span id="metaDescCount">0</span>/300 characters</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-2"></i>Save Post
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-generate slug from title
function autoSlug(title) {
    var slugField = document.getElementById('formSlug');
    if (!slugField.dataset.manualEdit) {
        var slug = title.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slugField.value = slug;
    }
}
document.getElementById('formSlug').addEventListener('input', function() {
    this.dataset.manualEdit = true;
});

// Word count for content
document.getElementById('formContent').addEventListener('input', function() {
    var words = this.value.trim().split(/\s+/).filter(w => w.length > 0);
    document.getElementById('wordCount').textContent = words.length;
});

// Meta description char count
document.getElementById('formMetaDesc').addEventListener('input', function() {
    document.getElementById('metaDescCount').textContent = this.value.length;
});

// Open edit modal and populate fields
function openEditModal(post) {
    document.getElementById('postModalLabel').textContent = 'Edit Blog Post';
    document.getElementById('formAction').value           = 'update';
    document.getElementById('formId').value               = post.id;
    document.getElementById('formTitle').value            = post.title        || '';
    document.getElementById('formSlug').value             = post.slug         || '';
    document.getElementById('formSlug').dataset.manualEdit = true;
    document.getElementById('formExcerpt').value          = post.excerpt      || '';
    document.getElementById('formContent').value          = post.content      || '';
    document.getElementById('formCategory').value         = post.category     || '';
    document.getElementById('formTags').value             = post.tags         || '';
    document.getElementById('formMetaTitle').value        = post.meta_title   || '';
    document.getElementById('formMetaDesc').value         = post.meta_description || '';
    document.getElementById('formStatus').value           = post.status       || 'draft';

    // Format published_at for datetime-local input
    if (post.published_at) {
        var d = new Date(post.published_at.replace(' ', 'T'));
        var pad = n => String(n).padStart(2, '0');
        document.getElementById('formPublishedAt').value =
            d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) +
            'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    } else {
        document.getElementById('formPublishedAt').value = '';
    }

    // Update word count & meta desc count
    var words = (post.content || '').trim().split(/\s+/).filter(w => w.length > 0);
    document.getElementById('wordCount').textContent = words.length;
    document.getElementById('metaDescCount').textContent = (post.meta_description || '').length;

    var modal = new bootstrap.Modal(document.getElementById('postModal'));
    modal.show();
}

// Reset modal to create mode when opened via New Post button
document.getElementById('newPostBtn').addEventListener('click', function() {
    document.getElementById('postModalLabel').textContent = 'New Blog Post';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('postForm').reset();
    delete document.getElementById('formSlug').dataset.manualEdit;
    document.getElementById('wordCount').textContent = '0';
    document.getElementById('metaDescCount').textContent = '0';
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>

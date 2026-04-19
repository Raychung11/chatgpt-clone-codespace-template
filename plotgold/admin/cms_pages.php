<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$tab    = clean($_GET['tab']    ?? 'pages');
$action = clean($_GET['action'] ?? 'list');
$itemId = clean_int($_GET['id'] ?? 0);
$error  = '';

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $postAction = clean($_POST['_action'] ?? 'save_page');

    // Delete page
    if ($postAction === 'delete_page') {
        $id = clean_int($_POST['_page_id'] ?? 0);
        if ($id) {
            Database::query('DELETE FROM cms_pages WHERE id = ?', [$id]);
            activity_log(auth_user_id(), 'cms_page_deleted', 'cms_pages', $id);
            flash_set(FLASH_SUCCESS, 'Page deleted.');
        }
        redirect('admin/cms_pages.php');
    }

    // Delete block
    if ($postAction === 'delete_block') {
        $id = clean_int($_POST['_block_id'] ?? 0);
        if ($id) {
            Database::query('DELETE FROM cms_blocks WHERE id = ?', [$id]);
            activity_log(auth_user_id(), 'cms_block_deleted', 'cms_blocks', $id);
            flash_set(FLASH_SUCCESS, 'Block deleted.');
        }
        redirect('admin/cms_pages.php?tab=blocks');
    }

    // Toggle page publish
    if ($postAction === 'toggle_publish') {
        $id = clean_int($_POST['_page_id'] ?? 0);
        if ($id) {
            $cur = Database::fetchOne('SELECT is_published FROM cms_pages WHERE id=?', [$id]);
            $newState = $cur ? 1 - (int)$cur['is_published'] : 0;
            $publishedAt = $newState ? date('Y-m-d H:i:s') : null;
            Database::query(
                'UPDATE cms_pages SET is_published=?, published_at=?, updated_at=NOW() WHERE id=?',
                [$newState, $publishedAt, $id]
            );
            flash_set(FLASH_SUCCESS, $newState ? 'Page published.' : 'Page unpublished.');
        }
        redirect('admin/cms_pages.php');
    }

    // Save block
    if ($postAction === 'save_block') {
        $blockId   = clean_int($_POST['_block_id'] ?? 0);
        $blockKey  = preg_replace('/[^a-z0-9_]/', '_', strtolower(clean($_POST['block_key'] ?? '')));
        $blockType = clean($_POST['block_type'] ?? 'html');
        $bTitle    = clean($_POST['title'] ?? '');
        $bContent  = $_POST['content'] ?? '';
        $bSection  = clean($_POST['section'] ?? '');
        $bSort     = clean_int($_POST['sort_order'] ?? 0);
        $bActive   = (int)!empty($_POST['is_active']);

        if (!$blockKey) {
            flash_set(FLASH_ERROR, 'Block key is required.');
            redirect('admin/cms_pages.php?tab=blocks&action=' . ($blockId ? "edit_block&id=$blockId" : 'new_block'));
        }

        if ($blockId) {
            Database::query(
                'UPDATE cms_blocks SET block_key=?,block_type=?,title=?,content=?,section=?,sort_order=?,is_active=? WHERE id=?',
                [$blockKey, $blockType, $bTitle, $bContent, $bSection, $bSort, $bActive, $blockId]
            );
            flash_set(FLASH_SUCCESS, 'Block updated.');
        } else {
            Database::insert(
                'INSERT INTO cms_blocks (block_key,block_type,title,content,section,sort_order,is_active)
                 VALUES (?,?,?,?,?,?,?)',
                [$blockKey, $blockType, $bTitle, $bContent, $bSection, $bSort, $bActive]
            );
            flash_set(FLASH_SUCCESS, 'Block created.');
        }
        activity_log(auth_user_id(), 'cms_block_saved', 'cms_blocks', $blockId);
        redirect('admin/cms_pages.php?tab=blocks');
    }

    // Save page (default)
    $editId      = clean_int($_POST['_page_id'] ?? 0);
    $title       = clean($_POST['title'] ?? '');
    $slug        = trim(clean($_POST['slug'] ?? ''));
    $content     = $_POST['content'] ?? '';
    $metaTitle   = clean($_POST['meta_title'] ?? '');
    $metaDesc    = clean($_POST['meta_description'] ?? '');
    $isPublished = (int)!empty($_POST['is_published']);

    if (!$title) {
        $error = 'Page title is required.';
    } else {
        if (!$slug) $slug = slug($title);
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

        // Unique slug check
        $slugBase = $slug; $n = 0;
        $dupSql    = $editId
            ? 'SELECT id FROM cms_pages WHERE slug=? AND id!=?'
            : 'SELECT id FROM cms_pages WHERE slug=?';
        $dupParams = $editId ? [$slug, $editId] : [$slug];
        while (Database::fetchOne($dupSql, $dupParams)) {
            $slug = $slugBase . '-' . (++$n);
            $dupParams[0] = $slug;
        }

        if ($editId) {
            $existing    = Database::fetchOne('SELECT published_at, is_published FROM cms_pages WHERE id=?', [$editId]);
            $publishedAt = ($isPublished && $existing['published_at'])
                ? $existing['published_at']
                : ($isPublished ? date('Y-m-d H:i:s') : null);

            Database::query(
                'UPDATE cms_pages
                 SET title=?,slug=?,content=?,meta_title=?,meta_description=?,
                     is_published=?,published_at=?,updated_at=NOW()
                 WHERE id=?',
                [$title, $slug, $content, $metaTitle, $metaDesc, $isPublished, $publishedAt, $editId]
            );
            flash_set(FLASH_SUCCESS, 'Page updated.');
        } else {
            $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;
            Database::insert(
                'INSERT INTO cms_pages
                 (title,slug,content,meta_title,meta_description,is_published,published_at,author_id)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$title, $slug, $content, $metaTitle, $metaDesc, $isPublished, $publishedAt, auth_user_id()]
            );
            flash_set(FLASH_SUCCESS, 'Page created and ' . ($isPublished ? 'published.' : 'saved as draft.'));
        }
        activity_log(auth_user_id(), 'cms_page_saved', 'cms_pages', $editId ?: 0, $title);
        redirect('admin/cms_pages.php');
    }
}

// ── Load item for edit ─────────────────────────────────────────────────────────
$editItem = null;
if ($itemId && in_array($action, ['edit', 'edit_block'])) {
    $editItem = $action === 'edit'
        ? Database::fetchOne('SELECT * FROM cms_pages WHERE id=?', [$itemId])
        : Database::fetchOne('SELECT * FROM cms_blocks WHERE id=?', [$itemId]);
    if (!$editItem) redirect('admin/cms_pages.php' . ($action === 'edit_block' ? '?tab=blocks' : ''));
}

// ── List data ──────────────────────────────────────────────────────────────────
$pages = Database::fetchAll(
    'SELECT cp.*, up.full_name AS author_name
     FROM cms_pages cp
     LEFT JOIN user_profiles up ON up.user_id = cp.author_id
     ORDER BY cp.updated_at DESC'
);

$blocks = Database::fetchAll(
    'SELECT * FROM cms_blocks ORDER BY section ASC, sort_order ASC, block_key ASC'
);

$blockSections = array_unique(array_filter(array_column($blocks, 'section')));
sort($blockSections);

$showPageForm  = in_array($action, ['new', 'edit'])       && $tab !== 'blocks';
$showBlockForm = in_array($action, ['new_block', 'edit_block']);

$page_title = 'CMS Pages & Content';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>

    <!-- Header bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0">
            <i class="fas fa-file-alt me-2" style="color:var(--pg-gold);"></i>CMS Pages &amp; Content
        </h4>
        <div class="d-flex gap-2">
            <?php if ($tab === 'blocks'): ?>
                <a href="?tab=blocks&action=new_block" class="btn btn-gold btn-sm">
                    <i class="fas fa-plus me-1"></i>New Block
                </a>
            <?php else: ?>
                <a href="?action=new" class="btn btn-gold btn-sm">
                    <i class="fas fa-plus me-1"></i>New Page
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tab !== 'blocks' ? 'active fw-600' : 'text-muted' ?>"
               href="?tab=pages">
                <i class="fas fa-file-alt me-1"></i>Pages
                <span class="badge bg-light text-muted border ms-1"><?= count($pages) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'blocks' ? 'active fw-600' : 'text-muted' ?>"
               href="?tab=blocks">
                <i class="fas fa-cubes me-1"></i>Content Blocks
                <span class="badge bg-light text-muted border ms-1"><?= count($blocks) ?></span>
            </a>
        </li>
    </ul>

<?php /* ══════════════════════════════════════════════════
  PAGE FORM (new / edit)
══════════════════════════════════════════════════ */ ?>
<?php if ($showPageForm): ?>
<div class="pg-card p-4 mb-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= pg_url('admin/cms_pages.php') ?>" class="text-muted text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i>All Pages
        </a>
        <span class="text-muted">/</span>
        <span class="fw-600 text-navy"><?= $editItem ? 'Edit: ' . h($editItem['title']) : 'New Page' ?></span>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save_page">
        <?php if ($editItem): ?>
            <input type="hidden" name="_page_id" value="<?= $editItem['id'] ?>">
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left: main content -->
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label fw-600">Page Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="pageTitle" class="form-control form-control-lg"
                           required placeholder="e.g. Privacy Policy"
                           value="<?= h($editItem['title'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">URL Slug</label>
                    <div class="input-group">
                        <span class="input-group-text text-muted small"><?= pg_url('page/') ?></span>
                        <input type="text" name="slug" id="pageSlug" class="form-control"
                               placeholder="auto-generated"
                               value="<?= h($editItem['slug'] ?? '') ?>">
                    </div>
                    <div class="form-text">Leave blank to auto-generate from title.</div>
                </div>

                <!-- Content editor -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-600 mb-0">Page Content</label>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapText('pageContent','<strong>','</strong>')" title="Bold"><i class="fas fa-bold"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapText('pageContent','<em>','</em>')" title="Italic"><i class="fas fa-italic"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapText('pageContent','<h2>','</h2>')" title="Heading 2"><b>H2</b></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapText('pageContent','<h3>','</h3>')" title="Heading 3"><b>H3</b></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapText('pageContent','<ul>\n  <li>','</li>\n</ul>')" title="List"><i class="fas fa-list-ul"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapText('pageContent','<p>','</p>')" title="Paragraph"><i class="fas fa-paragraph"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertLink('pageContent')" title="Link"><i class="fas fa-link"></i></button>
                        </div>
                    </div>
                    <textarea name="content" id="pageContent"
                              class="form-control font-monospace"
                              rows="20"
                              placeholder="Write your page content here. HTML is fully supported."
                              style="font-size:.85rem;line-height:1.6;"><?= h($editItem['content'] ?? '') ?></textarea>
                    <div class="form-text d-flex justify-content-between">
                        <span>HTML is supported. Use the toolbar buttons above to insert tags.</span>
                        <span id="wordCount" class="text-muted">0 words</span>
                    </div>
                </div>
            </div>

            <!-- Right: meta & settings -->
            <div class="col-lg-4">
                <!-- Publish box -->
                <div class="pg-card p-3 mb-3" style="border-left:4px solid var(--pg-gold);">
                    <h6 class="fw-600 mb-3">Publish</h6>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_published" value="1" id="isPublished"
                               class="form-check-input"
                               <?= !empty($editItem['is_published']) ? 'checked' : '' ?>>
                        <label for="isPublished" class="form-check-label">
                            Publish immediately
                        </label>
                    </div>
                    <?php if (!empty($editItem['published_at'])): ?>
                        <div class="small text-muted mb-3">
                            Published: <?= format_date($editItem['published_at'], 'd M Y, H:i') ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($editItem['slug'])): ?>
                        <a href="<?= pg_url('page/' . h($editItem['slug'])) ?>"
                           target="_blank" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                            <i class="fas fa-eye me-1"></i>Preview Page
                        </a>
                    <?php endif; ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-gold">
                            <i class="fas fa-save me-1"></i>Save Page
                        </button>
                        <a href="<?= pg_url('admin/cms_pages.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </div>

                <!-- SEO -->
                <div class="pg-card p-3 mb-3">
                    <h6 class="fw-600 mb-3"><i class="fas fa-search me-1 text-muted"></i>SEO</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-600">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control form-control-sm"
                               placeholder="Defaults to page title"
                               value="<?= h($editItem['meta_title'] ?? '') ?>"
                               maxlength="200">
                        <div class="form-text" id="metaTitleCount">0 / 60</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-600">Meta Description</label>
                        <textarea name="meta_description" class="form-control form-control-sm"
                                  rows="3" placeholder="Brief page summary for search engines"
                                  maxlength="500"><?= h($editItem['meta_description'] ?? '') ?></textarea>
                        <div class="form-text" id="metaDescCount">0 / 160</div>
                    </div>
                </div>

                <!-- Page info -->
                <?php if ($editItem): ?>
                <div class="pg-card p-3 mb-3" style="background:#f8f9fa;">
                    <h6 class="fw-600 mb-3 text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;">Page Info</h6>
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <tr><td class="text-muted border-0 py-1">ID</td><td class="border-0 py-1"><?= $editItem['id'] ?></td></tr>
                        <tr><td class="text-muted border-0 py-1">Created</td><td class="border-0 py-1"><?= format_date($editItem['created_at'], 'd M Y') ?></td></tr>
                        <tr><td class="text-muted border-0 py-1">Updated</td><td class="border-0 py-1"><?= format_date($editItem['updated_at'], 'd M Y') ?></td></tr>
                        <tr><td class="text-muted border-0 py-1">Slug</td><td class="border-0 py-1"><code style="font-size:.75rem;"><?= h($editItem['slug']) ?></code></td></tr>
                    </table>
                </div>

                <!-- Danger zone -->
                <div class="pg-card p-3 border-danger" style="border:1px solid rgba(220,53,69,.3);">
                    <h6 class="fw-600 mb-2 text-danger" style="font-size:.8rem;">Danger Zone</h6>
                    <form method="POST"
                          onsubmit="return confirm('Delete \'<?= h(addslashes($editItem['title'])) ?>\'? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="delete_page">
                        <input type="hidden" name="_page_id" value="<?= $editItem['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="fas fa-trash-alt me-1"></i>Delete Page
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php /* ══════════════════════════════════════════════════
  BLOCK FORM (new_block / edit_block)
══════════════════════════════════════════════════ */ ?>
<?php elseif ($showBlockForm): ?>
<div class="pg-card p-4 mb-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= pg_url('admin/cms_pages.php?tab=blocks') ?>" class="text-muted text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i>All Blocks
        </a>
        <span class="text-muted">/</span>
        <span class="fw-600 text-navy"><?= $editItem ? 'Edit: ' . h($editItem['title'] ?: $editItem['block_key']) : 'New Block' ?></span>
    </div>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save_block">
        <?php if ($editItem): ?>
            <input type="hidden" name="_block_id" value="<?= $editItem['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-600 small">Block Key <span class="text-danger">*</span></label>
                <input type="text" name="block_key" class="form-control font-monospace"
                       required placeholder="e.g. hero_headline"
                       value="<?= h($editItem['block_key'] ?? '') ?>">
                <div class="form-text">Unique identifier used in templates. Lowercase, underscores only.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600 small">Type</label>
                <select name="block_type" class="form-select">
                    <?php foreach (['html','text','json','image','link'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= ($editItem['block_type'] ?? 'html') === $bt ? 'selected' : '' ?>>
                            <?= strtoupper($bt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600 small">Section</label>
                <input type="text" name="section" class="form-control"
                       placeholder="e.g. hero, footer"
                       value="<?= h($editItem['section'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-600 small">Display Title</label>
                <input type="text" name="title" class="form-control"
                       placeholder="Human-readable label"
                       value="<?= h($editItem['title'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-600 small">Sort Order</label>
                <input type="number" name="sort_order" class="form-control"
                       min="0" value="<?= (int)($editItem['sort_order'] ?? 0) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" value="1" id="blockActive"
                           class="form-check-input"
                           <?= ($editItem['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="blockActive" class="form-check-label small">Active</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-600 small">Content</label>
                <textarea name="content" class="form-control font-monospace"
                          rows="10"
                          style="font-size:.85rem;"
                          placeholder="Block content (HTML, JSON, URL, or plain text depending on type)"><?= h($editItem['content'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-gold">
                    <i class="fas fa-save me-1"></i>Save Block
                </button>
                <a href="<?= pg_url('admin/cms_pages.php?tab=blocks') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php else: /* ══════════════════ LIST VIEWS ══════════════════ */ ?>

<?php if ($tab !== 'blocks'): ?>
<!-- ── Pages List ──────────────────────────────────────────────────────────── -->
<div class="pg-card">
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pages as $p): ?>
            <tr>
                <td>
                    <div class="fw-500 small"><?= h($p['title']) ?></div>
                    <?php if ($p['meta_description']): ?>
                        <div class="text-muted" style="font-size:.72rem;"><?= h(substr($p['meta_description'], 0, 80)) ?>…</div>
                    <?php endif; ?>
                </td>
                <td>
                    <code class="small" style="font-size:.72rem;color:var(--pg-navy);"><?= h($p['slug']) ?></code>
                </td>
                <td>
                    <form method="POST" class="d-inline m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="toggle_publish">
                        <input type="hidden" name="_page_id" value="<?= $p['id'] ?>">
                        <button type="submit"
                                class="badge border-0 <?= $p['is_published'] ? 'bg-success' : 'bg-secondary' ?>"
                                style="cursor:pointer;font-size:.72rem;"
                                title="<?= $p['is_published'] ? 'Click to unpublish' : 'Click to publish' ?>">
                            <?= $p['is_published'] ? 'Published' : 'Draft' ?>
                        </button>
                    </form>
                    <?php if ($p['is_published'] && $p['published_at']): ?>
                        <div class="text-muted" style="font-size:.68rem;"><?= format_date($p['published_at'], 'd M Y') ?></div>
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?= h($p['author_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= format_date($p['updated_at'], 'd M Y, H:i') ?></td>
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-gold" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($p['is_published']): ?>
                            <a href="<?= pg_url('page/' . h($p['slug'])) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary" title="View live page">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$pages): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="fas fa-file-alt fa-2x mb-2 d-block"></i>
                        No pages yet. <a href="?action=new">Create your first page</a>.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick tips -->
<div class="mt-3 small text-muted">
    <i class="fas fa-info-circle me-1"></i>
    Pages are accessible at <code><?= pg_url('page/{slug}') ?></code> once published.
    Click the <strong>Draft / Published</strong> badge to toggle visibility instantly.
</div>

<?php else: ?>
<!-- ── Blocks List ─────────────────────────────────────────────────────────── -->
<?php
$blocksBySection = [];
foreach ($blocks as $b) {
    $sec = $b['section'] ?: '(no section)';
    $blocksBySection[$sec][] = $b;
}
ksort($blocksBySection);
?>
<?php foreach ($blocksBySection as $section => $sectionBlocks): ?>
<div class="pg-card mb-3">
    <div class="px-3 pt-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-600 text-navy mb-0">
            <i class="fas fa-layer-group me-1" style="color:var(--pg-gold);"></i>
            Section: <code><?= h($section) ?></code>
        </h6>
        <span class="badge bg-light text-muted border"><?= count($sectionBlocks) ?> block<?= count($sectionBlocks) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="width:30px">Sort</th>
                    <th>Block Key</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Content Preview</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sectionBlocks as $b): ?>
            <tr class="<?= $b['is_active'] ? '' : 'table-secondary' ?>">
                <td class="text-muted small"><?= (int)$b['sort_order'] ?></td>
                <td><code class="small" style="color:var(--pg-navy);"><?= h($b['block_key']) ?></code></td>
                <td class="small"><?= h($b['title'] ?: '—') ?></td>
                <td>
                    <span class="badge bg-light text-muted border" style="font-size:.65rem;">
                        <?= strtoupper(h($b['block_type'])) ?>
                    </span>
                </td>
                <td class="text-muted" style="max-width:260px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:.78rem;">
                    <?= h(strip_tags(substr($b['content'] ?? '', 0, 120))) ?>
                </td>
                <td>
                    <span class="status-pill <?= $b['is_active'] ? 'active' : 'expired' ?>">
                        <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <a href="?tab=blocks&action=edit_block&id=<?= $b['id'] ?>"
                           class="btn btn-sm btn-outline-gold" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" class="d-inline m-0"
                              onsubmit="return confirm('Delete block \'<?= h(addslashes($b['block_key'])) ?>\'?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="delete_block">
                            <input type="hidden" name="_block_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (!$blocks): ?>
<div class="pg-card text-center py-5 text-muted">
    <i class="fas fa-cubes fa-2x mb-2 d-block"></i>
    No content blocks yet. <a href="?tab=blocks&action=new_block">Create your first block</a>.
</div>
<?php endif; ?>

<div class="mt-2 small text-muted">
    <i class="fas fa-info-circle me-1"></i>
    Retrieve any block in PHP with: <code>Database::fetchOne("SELECT content FROM cms_blocks WHERE block_key=?", ['your_key'])</code>
</div>

<?php endif; /* tab */ ?>
<?php endif; /* form vs list */ ?>

</div><!-- /admin-main -->
</div><!-- /d-flex -->

<?php
$extra_scripts = <<<'JS'
<script>
// Auto-generate slug from title
const titleInput = document.getElementById('pageTitle');
const slugInput  = document.getElementById('pageSlug');
if (titleInput && slugInput) {
    titleInput.addEventListener('input', function () {
        if (slugInput.dataset.manual) return;
        slugInput.value = this.value
            .toLowerCase()
            .replace(/[^a-z0-9\s\-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    });
    slugInput.addEventListener('input', function () {
        this.dataset.manual = '1';
    });
}

// Word count
const contentArea = document.getElementById('pageContent');
const wordCountEl = document.getElementById('wordCount');
if (contentArea && wordCountEl) {
    function updateWordCount() {
        const txt = contentArea.value.replace(/<[^>]+>/g, ' ').trim();
        const words = txt ? txt.split(/\s+/).length : 0;
        wordCountEl.textContent = words.toLocaleString() + ' word' + (words !== 1 ? 's' : '');
    }
    contentArea.addEventListener('input', updateWordCount);
    updateWordCount();
}

// Meta title char counter
const metaTitleInput = document.getElementById('pageTitle');
const metaField = document.querySelector('[name="meta_title"]');
const metaTitleCount = document.getElementById('metaTitleCount');
const metaDescField  = document.querySelector('[name="meta_description"]');
const metaDescCount  = document.getElementById('metaDescCount');

function updateCharCount(field, display, limit) {
    if (!field || !display) return;
    const len = field.value.length;
    display.textContent = len + ' / ' + limit;
    display.style.color = len > limit ? '#dc3545' : '';
}

if (metaField)    { metaField.addEventListener('input',    () => updateCharCount(metaField,    metaTitleCount, 60));   updateCharCount(metaField,    metaTitleCount, 60); }
if (metaDescField){ metaDescField.addEventListener('input', () => updateCharCount(metaDescField, metaDescCount, 160));  updateCharCount(metaDescField, metaDescCount, 160); }

// HTML toolbar helpers
function wrapText(textareaId, before, after) {
    const ta    = document.getElementById(textareaId);
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    const sel   = ta.value.substring(start, end);
    ta.value    = ta.value.substring(0, start) + before + (sel || 'text') + after + ta.value.substring(end);
    ta.focus();
    ta.selectionStart = start + before.length;
    ta.selectionEnd   = start + before.length + (sel || 'text').length;
    if (typeof updateWordCount === 'function') updateWordCount();
}

function insertLink(textareaId) {
    const url = prompt('Enter URL:', 'https://');
    if (!url) return;
    const text = prompt('Link text:', 'Click here') || 'Click here';
    const ta = document.getElementById(textareaId);
    const start = ta.selectionStart;
    const insert = '<a href="' + url + '">' + text + '</a>';
    ta.value = ta.value.substring(0, start) + insert + ta.value.substring(ta.selectionEnd);
    ta.focus();
}
</script>
JS;
include INC_PATH . '/footer.php';
?>

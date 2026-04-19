<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$action = clean($_GET['action'] ?? 'list');
$itemId = clean_int($_GET['id'] ?? 0);
$error  = '';

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $postAction = clean($_POST['_action'] ?? 'save');

    if ($postAction === 'delete') {
        $id = clean_int($_POST['_faq_id'] ?? 0);
        if ($id) {
            Database::query('DELETE FROM faqs WHERE id = ?', [$id]);
            activity_log(auth_user_id(), 'faq_deleted', 'faqs', $id);
            flash_set(FLASH_SUCCESS, 'FAQ deleted.');
        }
        redirect('admin/faqs.php');
    }

    if ($postAction === 'toggle_active') {
        $id = clean_int($_POST['_faq_id'] ?? 0);
        if ($id) {
            Database::query('UPDATE faqs SET is_active = 1 - is_active WHERE id = ?', [$id]);
            flash_set(FLASH_SUCCESS, 'FAQ visibility updated.');
        }
        redirect('admin/faqs.php');
    }

    // Save (create / update)
    $editId   = clean_int($_POST['_faq_id'] ?? 0);
    $question = trim(clean($_POST['question'] ?? ''));
    $answer   = trim(clean($_POST['answer']   ?? ''));
    $category = clean($_POST['category']  ?? '');
    $sort     = clean_int($_POST['sort_order'] ?? 0);
    $active   = (int)!empty($_POST['is_active']);

    if (!$question || !$answer) {
        $error = 'Both question and answer are required.';
    } else {
        if ($editId) {
            Database::query(
                'UPDATE faqs SET question=?,answer=?,category=?,sort_order=?,is_active=? WHERE id=?',
                [$question, $answer, $category, $sort, $active, $editId]
            );
            flash_set(FLASH_SUCCESS, 'FAQ updated.');
        } else {
            Database::insert(
                'INSERT INTO faqs (question,answer,category,sort_order,is_active) VALUES (?,?,?,?,?)',
                [$question, $answer, $category, $sort, $active]
            );
            flash_set(FLASH_SUCCESS, 'FAQ created.');
        }
        activity_log(auth_user_id(), 'faq_saved', 'faqs', $editId ?: 0, $question);
        redirect('admin/faqs.php');
    }
}

// ── Load for edit ──────────────────────────────────────────────────────────────
$faq = null;
if ($itemId && $action === 'edit') {
    $faq = Database::fetchOne('SELECT * FROM faqs WHERE id = ?', [$itemId]);
    if (!$faq) redirect('admin/faqs.php');
}

// ── List with category grouping ────────────────────────────────────────────────
$faqs = Database::fetchAll('SELECT * FROM faqs ORDER BY category ASC, sort_order ASC, id ASC');
$categories = array_unique(array_filter(array_column($faqs, 'category')));
sort($categories);
$totalActive = count(array_filter($faqs, fn($f) => $f['is_active']));

$page_title = 'FAQs';
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
                <i class="fas fa-question-circle me-2" style="color:var(--pg-gold);"></i>FAQs
            </h4>
            <div class="text-muted small mt-1"><?= count($faqs) ?> total · <?= $totalActive ?> active</div>
        </div>
        <a href="?action=new" class="btn btn-gold btn-sm">
            <i class="fas fa-plus me-1"></i>Add FAQ
        </a>
    </div>

<?php if ($action === 'new' || ($action === 'edit' && $faq)): ?>
<!-- ── Form ─────────────────────────────────────────────────────────────────── -->
<div class="pg-card p-4 mb-4">
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= pg_url('admin/faqs.php') ?>" class="text-muted text-decoration-none">
            <i class="fas fa-arrow-left me-1"></i>All FAQs
        </a>
        <span class="text-muted">/</span>
        <span class="fw-600 text-navy"><?= $faq ? 'Edit FAQ' : 'New FAQ' ?></span>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save">
        <?php if ($faq): ?>
            <input type="hidden" name="_faq_id" value="<?= $faq['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-600">Question <span class="text-danger">*</span></label>
                <textarea name="question" class="form-control" rows="2" required
                          placeholder="e.g. How do I transfer ownership of a burial plot?"><?= h($faq['question'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-600">Answer <span class="text-danger">*</span></label>
                <textarea name="answer" class="form-control" rows="5" required
                          placeholder="Write a clear, helpful answer…"><?= h($faq['answer'] ?? '') ?></textarea>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-600 small">Category</label>
                <input type="text" name="category" class="form-control"
                       list="categoryList"
                       placeholder="e.g. Buying, Selling, Columbarium"
                       value="<?= h($faq['category'] ?? '') ?>">
                <datalist id="categoryList">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= h($cat) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-600 small">Sort Order</label>
                <input type="number" name="sort_order" class="form-control"
                       min="0" value="<?= (int)($faq['sort_order'] ?? 0) ?>">
                <div class="form-text">Lower = shown first.</div>
            </div>
            <div class="col-md-4 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input type="checkbox" name="is_active" value="1" id="faqActive"
                           class="form-check-input"
                           <?= ($faq['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="faqActive" class="form-check-label fw-500">
                        Show on site
                    </label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-gold">
                    <i class="fas fa-save me-1"></i>Save FAQ
                </button>
                <a href="<?= pg_url('admin/faqs.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                <?php if ($faq): ?>
                <form method="POST" class="ms-auto"
                      onsubmit="return confirm('Delete this FAQ? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="delete">
                    <input type="hidden" name="_faq_id" value="<?= $faq['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash-alt me-1"></i>Delete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ── FAQ List ──────────────────────────────────────────────────────────────── -->
<?php
$faqsByCategory = [];
foreach ($faqs as $f) {
    $cat = $f['category'] ?: '(uncategorised)';
    $faqsByCategory[$cat][] = $f;
}
ksort($faqsByCategory);
?>
<?php foreach ($faqsByCategory as $cat => $items): ?>
<div class="pg-card mb-3">
    <div class="px-3 pt-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-600 text-navy mb-0">
            <i class="fas fa-tag me-1" style="color:var(--pg-gold);font-size:.8rem;"></i>
            <?= h($cat) ?>
        </h6>
        <span class="badge bg-light text-muted border"><?= count($items) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>Question</th>
                    <th>Answer Preview</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $f): ?>
            <tr class="<?= $f['is_active'] ? '' : 'table-secondary' ?>">
                <td class="text-muted small"><?= (int)$f['sort_order'] ?></td>
                <td class="fw-500 small"><?= h($f['question']) ?></td>
                <td class="text-muted" style="max-width:320px;font-size:.78rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                    <?= h(substr($f['answer'], 0, 140)) ?>
                </td>
                <td>
                    <form method="POST" class="d-inline m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="toggle_active">
                        <input type="hidden" name="_faq_id" value="<?= $f['id'] ?>">
                        <button type="submit"
                                class="badge border-0 <?= $f['is_active'] ? 'bg-success' : 'bg-secondary' ?>"
                                style="cursor:pointer;"
                                title="Click to toggle">
                            <?= $f['is_active'] ? 'Active' : 'Hidden' ?>
                        </button>
                    </form>
                </td>
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <a href="?action=edit&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-gold" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" class="d-inline m-0"
                              onsubmit="return confirm('Delete this FAQ?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="delete">
                            <input type="hidden" name="_faq_id" value="<?= $f['id'] ?>">
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

<?php if (!$faqs): ?>
<div class="pg-card text-center py-5 text-muted">
    <i class="fas fa-question-circle fa-2x mb-2 d-block"></i>
    No FAQs yet. <a href="?action=new">Add your first FAQ</a>.
</div>
<?php endif; ?>

</div><!-- /admin-main -->
</div>
<?php include INC_PATH . '/footer.php'; ?>

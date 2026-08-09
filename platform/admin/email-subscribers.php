<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ─────────────────────────────────────────────────────────────
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save new list
    if ($action === 'save_list') {
        $name = trim($_POST['list_name'] ?? '');
        $desc = trim($_POST['list_description'] ?? '');
        if ($name !== '') {
            DB::insert('INSERT INTO email_lists (name, description) VALUES (?,?)', [$name, $desc]);
        }
        header('Location: /admin/email-subscribers.php?list_saved=1');
        exit;
    }

    // Edit list
    if ($action === 'edit_list') {
        $id   = (int)($_POST['list_id'] ?? 0);
        $name = trim($_POST['list_name'] ?? '');
        $desc = trim($_POST['list_description'] ?? '');
        if ($id > 0 && $name !== '') {
            DB::update('UPDATE email_lists SET name=?, description=? WHERE id=?', [$name, $desc, $id]);
        }
        header('Location: /admin/email-subscribers.php?list_saved=1');
        exit;
    }

    // Delete list
    if ($action === 'delete_list') {
        $id = (int)($_POST['list_id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM email_lists WHERE id = ?', [$id]);
        }
        header('Location: /admin/email-subscribers.php?list_deleted=1');
        exit;
    }

    // Add single subscriber
    if ($action === 'save') {
        $listId    = (int)($_POST['sub_list_id'] ?? 0) ?: null;
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        if ($email !== '') {
            // Check for existing
            $existing = $listId
                ? DB::fetch('SELECT id FROM email_subscribers WHERE list_id=? AND email=?', [$listId, $email])
                : DB::fetch('SELECT id FROM email_subscribers WHERE email=?', [$email]);
            if (!$existing) {
                DB::insert(
                    'INSERT INTO email_subscribers (list_id, email, first_name, last_name, status, subscribed_at)
                     VALUES (?,?,?,?,?,NOW())',
                    [$listId, $email, $firstName, $lastName, 'subscribed']
                );
            }
        }
        $redirect = '/admin/email-subscribers.php?saved=1' . ($listId ? '&list=' . $listId : '');
        header('Location: ' . $redirect);
        exit;
    }

    // Unsubscribe
    if ($action === 'unsubscribe') {
        $id     = (int)($_POST['id'] ?? 0);
        $listId = (int)($_POST['current_list'] ?? 0);
        if ($id > 0) {
            DB::update(
                'UPDATE email_subscribers SET status=?, unsubscribed_at=NOW() WHERE id=?',
                ['unsubscribed', $id]
            );
        }
        $redirect = '/admin/email-subscribers.php?unsubbed=1' . ($listId ? '&list=' . $listId : '');
        header('Location: ' . $redirect);
        exit;
    }

    // Delete subscriber
    if ($action === 'delete') {
        $id     = (int)($_POST['id'] ?? 0);
        $listId = (int)($_POST['current_list'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM email_subscribers WHERE id = ?', [$id]);
        }
        $redirect = '/admin/email-subscribers.php?deleted=1' . ($listId ? '&list=' . $listId : '');
        header('Location: ' . $redirect);
        exit;
    }

    // Bulk import
    if ($action === 'bulk_import') {
        $targetListId = (int)($_POST['import_list_id'] ?? 0) ?: null;
        $csvRaw       = trim($_POST['csv_data'] ?? '');
        $imported     = 0;
        $skipped      = 0;

        if ($csvRaw !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $csvRaw);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $parts = str_getcsv($line);
                $email = strtolower(trim($parts[0] ?? ''));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    continue;
                }
                $firstName = trim($parts[1] ?? '');
                $lastName  = trim($parts[2] ?? '');

                // Check uniqueness per list (or globally if no list)
                if ($targetListId) {
                    $exists = DB::fetch(
                        'SELECT id FROM email_subscribers WHERE list_id=? AND email=?',
                        [$targetListId, $email]
                    );
                } else {
                    $exists = DB::fetch(
                        'SELECT id FROM email_subscribers WHERE email=? AND list_id IS NULL',
                        [$email]
                    );
                }

                if ($exists) {
                    $skipped++;
                } else {
                    DB::insert(
                        'INSERT INTO email_subscribers (list_id, email, first_name, last_name, status, subscribed_at)
                         VALUES (?,?,?,?,?,NOW())',
                        [$targetListId, $email, $firstName, $lastName, 'subscribed']
                    );
                    $imported++;
                }
            }
        }
        $importResult = ['imported' => $imported, 'skipped' => $skipped];
    }
}

// ── Query Params ─────────────────────────────────────────────────────────────
$selectedList   = (int)($_GET['list'] ?? 0);
$filterStatus   = $_GET['status'] ?? '';
$search         = trim($_GET['q'] ?? '');

// ── Lists with counts ─────────────────────────────────────────────────────────
$lists = DB::fetchAll(
    'SELECT el.*, COUNT(es.id) as subscriber_count
     FROM email_lists el
     LEFT JOIN email_subscribers es ON es.list_id = el.id AND es.status = "subscribed"
     GROUP BY el.id
     ORDER BY el.name ASC'
);

// ── Subscribers query ─────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($selectedList > 0) {
    $where[]  = 'es.list_id = ?';
    $params[] = $selectedList;
}
if ($filterStatus !== '') {
    $where[]  = 'es.status = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[]  = '(es.email LIKE ? OR es.first_name LIKE ? OR es.last_name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$subscribers = DB::fetchAll(
    "SELECT es.*, el.name as list_name
     FROM email_subscribers es
     LEFT JOIN email_lists el ON es.list_id = el.id
     $whereSQL
     ORDER BY es.subscribed_at DESC
     LIMIT 500",
    $params
);

$totalCount = DB::fetch(
    "SELECT COUNT(*) as n FROM email_subscribers es $whereSQL",
    $params
)['n'] ?? 0;

$pageTitle = 'Email Subscribers';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Email Subscribers</h4>
            <p class="text-muted small mb-0">Manage mailing lists and subscriber records</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload me-1"></i>Bulk Import
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubscriberModal">
                <i class="bi bi-plus-circle me-1"></i>Add Subscriber
            </button>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">Subscriber added. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['unsubbed'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">Subscriber unsubscribed. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">Subscriber deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['list_saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">List saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['list_deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">List deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($importResult !== null): ?>
    <div class="alert alert-info alert-dismissible fade show">
        Import complete: <strong><?= $importResult['imported'] ?></strong> imported,
        <strong><?= $importResult['skipped'] ?></strong> skipped (duplicates / invalid).
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- LEFT: List Panel -->
        <div class="col-lg-3">
            <div class="admin-card rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Mailing Lists</h6>
                    <button class="btn btn-outline-primary btn-sm py-0 px-2"
                            data-bs-toggle="modal" data-bs-target="#listModal"
                            onclick="openListModal(null)">
                        <i class="bi bi-plus" style="font-size:14px"></i>
                    </button>
                </div>

                <!-- All subscribers link -->
                <a href="/admin/email-subscribers.php"
                   class="d-flex justify-content-between align-items-center p-2 rounded-3 mb-1 text-decoration-none
                          <?= $selectedList === 0 ? 'bg-primary bg-opacity-15 text-white' : 'text-muted' ?>">
                    <span class="small"><i class="bi bi-people me-2"></i>All Subscribers</span>
                    <span class="badge bg-secondary"><?= DB::fetch('SELECT COUNT(*) as n FROM email_subscribers')['n'] ?></span>
                </a>

                <?php foreach ($lists as $lst): ?>
                <div class="d-flex align-items-center mb-1 gap-1">
                    <a href="/admin/email-subscribers.php?list=<?= $lst['id'] ?>"
                       class="d-flex justify-content-between align-items-center p-2 rounded-3 text-decoration-none flex-grow-1
                              <?= $selectedList === (int)$lst['id'] ? 'bg-primary bg-opacity-15 text-white' : 'text-muted' ?>">
                        <span class="small text-truncate" style="max-width:130px">
                            <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($lst['name']) ?>
                        </span>
                        <span class="badge bg-secondary ms-1"><?= (int)$lst['subscriber_count'] ?></span>
                    </a>
                    <button class="btn btn-link btn-sm p-0 text-muted"
                            onclick="openListModal(<?= htmlspecialchars(json_encode($lst), ENT_QUOTES) ?>)"
                            data-bs-toggle="modal" data-bs-target="#listModal"
                            title="Edit list">
                        <i class="bi bi-pencil" style="font-size:11px"></i>
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this list and all its subscribers?')">
                        <input type="hidden" name="action" value="delete_list">
                        <input type="hidden" name="list_id" value="<?= $lst['id'] ?>">
                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="Delete list">
                            <i class="bi bi-trash" style="font-size:11px"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>

                <?php if (empty($lists)): ?>
                <p class="text-muted small text-center mt-3">No lists yet. Create one above.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Subscribers Panel -->
        <div class="col-lg-9">

            <!-- Filters -->
            <div class="admin-card rounded-4 p-3 mb-3">
                <form method="GET" class="row g-2 align-items-end">
                    <?php if ($selectedList > 0): ?>
                    <input type="hidden" name="list" value="<?= $selectedList ?>">
                    <?php endif; ?>
                    <div class="col-sm-5">
                        <label class="form-label text-muted small mb-1">Search email / name</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                               class="form-control form-control-sm bg-dark text-white border-secondary"
                               placeholder="Search…">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label text-muted small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="">All Statuses</option>
                            <?php foreach (['subscribed','unsubscribed','bounced','complained'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                        <a href="/admin/email-subscribers.php<?= $selectedList ? '?list=' . $selectedList : '' ?>"
                           class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
                    </div>
                </form>
            </div>

            <div class="admin-card rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">
                        <?php if ($selectedList > 0):
                            $activeList = array_filter($lists, fn($l) => (int)$l['id'] === $selectedList);
                            $activeList = reset($activeList);
                        ?>
                        <?= htmlspecialchars($activeList['name'] ?? 'List') ?>
                        <?php else: ?>All Subscribers<?php endif; ?>
                    </h6>
                    <span class="badge bg-secondary"><?= number_format($totalCount) ?> total</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Email</th>
                                <th>Name</th>
                                <th>List</th>
                                <th>Status</th>
                                <th>Subscribed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscribers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No subscribers found.</td>
                            </tr>
                            <?php else: ?>
                            <?php
                            $statusBadge = [
                                'subscribed'   => 'success',
                                'unsubscribed' => 'secondary',
                                'bounced'      => 'warning',
                                'complained'   => 'danger',
                            ];
                            foreach ($subscribers as $sub):
                                $badgeCol = $statusBadge[$sub['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="text-white small"><?= htmlspecialchars($sub['email']) ?></td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars(trim($sub['first_name'] . ' ' . $sub['last_name']) ?: '—') ?>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($sub['list_name'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $badgeCol ?>"><?= ucfirst($sub['status']) ?></span></td>
                                <td class="text-muted small">
                                    <?= $sub['subscribed_at'] ? date('d M Y', strtotime($sub['subscribed_at'])) : '—' ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($sub['status'] === 'subscribed'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="unsubscribe">
                                            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                            <input type="hidden" name="current_list" value="<?= $selectedList ?>">
                                            <button type="submit" class="btn btn-outline-warning btn-sm py-0 px-2" title="Unsubscribe">
                                                <i class="bi bi-person-dash" style="font-size:12px"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this subscriber?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                            <input type="hidden" name="current_list" value="<?= $selectedList ?>">
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
                <?php if ($totalCount > 500): ?>
                <div class="text-muted small text-center mt-2">Showing first 500 of <?= number_format($totalCount) ?> subscribers. Use search/filters to narrow results.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit List Modal -->
<div class="modal fade" id="listModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="listModalLabel">New Mailing List</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="listForm">
                <input type="hidden" name="action" id="listAction" value="save_list">
                <input type="hidden" name="list_id" id="listEditId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">List Name <span class="text-danger">*</span></label>
                        <input type="text" name="list_name" id="listNameField"
                               class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Description</label>
                        <textarea name="list_description" id="listDescField" rows="3"
                                  class="form-control bg-dark text-white border-secondary"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save List</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Single Subscriber Modal -->
<div class="modal fade" id="addSubscriberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Add Subscriber</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label text-muted small">First Name</label>
                            <input type="text" name="first_name" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col">
                            <label class="form-label text-muted small">Last Name</label>
                            <input type="text" name="last_name" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Add to List</label>
                        <select name="sub_list_id" class="form-select bg-dark text-white border-secondary">
                            <option value="">— No list —</option>
                            <?php foreach ($lists as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $selectedList === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subscriber</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Bulk Import Subscribers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_import">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Target List</label>
                        <select name="import_list_id" class="form-select bg-dark text-white border-secondary">
                            <option value="">— No list (import without list) —</option>
                            <?php foreach ($lists as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $selectedList === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">
                            CSV Data <span class="text-danger">*</span>
                            <span class="text-muted ms-2" style="font-weight:400">One row per line: <code>email,first_name,last_name</code></span>
                        </label>
                        <textarea name="csv_data" rows="10"
                                  class="form-control bg-dark text-white border-secondary font-monospace"
                                  style="font-size:12px"
                                  placeholder="john@example.com,John,Smith&#10;jane@example.com,Jane,Doe"
                                  required></textarea>
                    </div>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Duplicate emails per list will be skipped. Invalid email addresses will be skipped.
                        All imported contacts are set to <strong>subscribed</strong>.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openListModal(list) {
    if (!list) {
        document.getElementById('listModalLabel').textContent = 'New Mailing List';
        document.getElementById('listAction').value  = 'save_list';
        document.getElementById('listEditId').value  = '';
        document.getElementById('listNameField').value = '';
        document.getElementById('listDescField').value = '';
        return;
    }
    document.getElementById('listModalLabel').textContent = 'Edit List';
    document.getElementById('listAction').value   = 'edit_list';
    document.getElementById('listEditId').value   = list.id;
    document.getElementById('listNameField').value = list.name;
    document.getElementById('listDescField').value = list.description || '';
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>

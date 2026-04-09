<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_PROVIDER, '/register.php');

$provider = Database::fetchOne('SELECT * FROM providers WHERE user_id = ?', [auth_user_id()]);
if (!$provider) redirect('/register.php');
$pid = (int)$provider['id'];

// ── Handle status update POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $action = clean($_POST['action'] ?? '');
    $qid    = clean_int($_POST['quote_id'] ?? 0);

    if ($qid && $action === 'update_status') {
        $ns      = clean($_POST['new_status'] ?? '');
        $allowed = ['in_review', 'quoted', 'accepted', 'rejected'];
        if (in_array($ns, $allowed, true)) {
            $old = Database::fetchOne('SELECT status FROM quotations WHERE id = ?', [$qid])['status'] ?? '';
            Database::query('UPDATE quotations SET status = ? WHERE id = ?', [$ns, $qid]);
            Database::query(
                'INSERT INTO quote_status_logs (quotation_id, from_status, to_status, changed_by, note) VALUES (?,?,?,?,?)',
                [$qid, $old, $ns, auth_user_id(), clean($_POST['note'] ?? '')]
            );
            flash_set(FLASH_SUCCESS, 'Quote status updated.');
        }
    }
    redirect('provider/quotes.php');
}

// ── Filters ────────────────────────────────────────────────────────
$statusFilter = clean($_GET['status'] ?? '');
$modeFilter   = clean($_GET['mode']   ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($statusFilter) {
    $where[]  = 'q.status = ?';
    $params[] = $statusFilter;
} else {
    // Default: show actionable quotes only (exclude draft/expired)
    $where[]  = "q.status NOT IN ('draft','expired')";
}
if ($modeFilter) {
    $where[]  = 'q.mode = ?';
    $params[] = $modeFilter;
}

$sql   = 'SELECT q.* FROM quotations q WHERE ' . implode(' AND ', $where);
$total = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($sql) x", $params)['c'] ?? 0);
$pp    = 15;
$pages = (int)ceil($total / $pp);
$offset= ($page - 1) * $pp;

$quotes = Database::fetchAll(
    "$sql ORDER BY q.mode = 'emergency' DESC, q.created_at DESC LIMIT $pp OFFSET $offset",
    $params
);

// ── Detail view ────────────────────────────────────────────────────
$detail = null;
$detailItems = [];
$statusLog   = [];
if ($did = clean_int($_GET['id'] ?? 0)) {
    $detail = Database::fetchOne('SELECT * FROM quotations WHERE id = ?', [$did]);
    if ($detail) {
        $detailItems = Database::fetchAll(
            'SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order',
            [$did]
        );
        $statusLog = Database::fetchAll(
            'SELECT qsl.*, u.full_name AS changed_by_name
             FROM quote_status_logs qsl
             LEFT JOIN users u ON u.id = qsl.changed_by
             WHERE qsl.quotation_id = ?
             ORDER BY qsl.created_at DESC',
            [$did]
        );
    }
}

$page_title = 'Quote Requests';
$body_class = 'portal-layout';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <h4 class="fw-700 text-navy mb-4">Quote Requests</h4>

    <!-- Filter Bar -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-navy' : 'btn-outline-secondary' ?>">All Active (<?= $total ?>)</a>
        <?php foreach (['submitted' => 'New', 'in_review' => 'In Review', 'quoted' => 'Quoted', 'accepted' => 'Accepted', 'rejected' => 'Rejected'] as $s => $label): ?>
            <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-navy' : 'btn-outline-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="?mode=emergency" class="btn btn-sm <?= $modeFilter === 'emergency' ? 'btn-danger' : 'btn-outline-danger' ?>">
            <i class="fas fa-bolt me-1"></i>Emergency
        </a>
    </div>

    <?php if ($detail): ?>
    <!-- ── Detail Panel ─────────────────────────────────────────── -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="pg-card p-4 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-600 mb-0"><?= h($detail['quote_code']) ?></h5>
                        <div class="text-muted small"><?= time_ago($detail['created_at']) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($detail['mode'] === 'emergency'): ?>
                            <span class="badge bg-danger">Emergency</span>
                        <?php endif; ?>
                        <span class="status-pill <?= in_array($detail['status'], ['accepted']) ? 'active' : (in_array($detail['status'], ['in_review','quoted']) ? 'pending' : 'draft') ?>">
                            <?= ucfirst(str_replace('_', ' ', $detail['status'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="row g-3 mb-3 p-3 rounded-2" style="background:var(--pg-bg)">
                    <div class="col-sm-4">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase">Contact</div>
                        <div class="small fw-500"><?= h($detail['contact_name'] ?? 'Anonymous') ?></div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase">Phone</div>
                        <div class="small fw-500">
                            <?php if ($detail['contact_phone']): ?>
                                <a href="<?= whatsapp_link('Hi ' . ($detail['contact_name'] ?? '') . ', this is regarding your quote request ' . $detail['quote_code'], preg_replace('/[^0-9]/', '', $detail['contact_phone'])) ?>"
                                   target="_blank" class="text-success">
                                    <i class="fab fa-whatsapp me-1"></i><?= h($detail['contact_phone']) ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase">Religion</div>
                        <div class="small fw-500"><?= h($detail['religion'] ?? '—') ?></div>
                    </div>
                    <?php if ($detail['event_date']): ?>
                    <div class="col-sm-4">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase">Event Date</div>
                        <div class="small fw-500"><?= date('d M Y', strtotime($detail['event_date'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($detail['notes']): ?>
                    <div class="col-12">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase">Notes</div>
                        <div class="small"><?= h($detail['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Items -->
                <h6 class="fw-600 mb-3">Requested Services</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Service</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $grandTotal = 0;
                        foreach ($detailItems as $item):
                            $grandTotal += $item['subtotal'] ?? ($item['unit_price'] * $item['quantity']);
                        ?>
                        <tr>
                            <td>
                                <div class="small fw-500"><?= h($item['item_name']) ?></div>
                                <?php if ($item['category']): ?>
                                    <div class="text-muted" style="font-size:.72rem"><?= h($item['category']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small"><?= (int)$item['quantity'] ?></td>
                            <td class="text-end small">
                                <?= $item['unit_price'] ? format_currency($item['unit_price']) : '<span class="text-muted">TBD</span>' ?>
                            </td>
                            <td class="text-end small fw-500">
                                <?= $item['subtotal'] ? format_currency($item['subtotal']) : '<span class="text-muted">—</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-top">
                                <td colspan="3" class="text-end fw-600">Estimated Total</td>
                                <td class="text-end fw-700 text-navy"><?= $grandTotal > 0 ? format_currency($grandTotal) : '<span class="text-muted small">TBD</span>' ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Status Log -->
            <?php if ($statusLog): ?>
            <div class="pg-card p-4">
                <h6 class="fw-600 mb-3">Activity Log</h6>
                <?php foreach ($statusLog as $log): ?>
                <div class="d-flex gap-3 pb-2 mb-2 border-bottom">
                    <div class="nav-avatar flex-shrink-0" style="margin-top:2px"><i class="fas fa-history" style="font-size:.65rem"></i></div>
                    <div>
                        <div class="small">
                            <span class="fw-500"><?= h($log['changed_by_name'] ?? 'System') ?></span>
                            changed status
                            <?php if ($log['from_status']): ?>
                                from <span class="fw-500"><?= ucfirst(str_replace('_', ' ', $log['from_status'])) ?></span>
                            <?php endif; ?>
                            to <span class="fw-600 text-navy"><?= ucfirst(str_replace('_', ' ', $log['to_status'])) ?></span>
                        </div>
                        <?php if ($log['note']): ?><div class="text-muted" style="font-size:.78rem"><?= h($log['note']) ?></div><?php endif; ?>
                        <div class="text-muted" style="font-size:.72rem"><?= time_ago($log['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Panel -->
        <div class="col-lg-5">
            <?php if (!in_array($detail['status'], ['accepted','rejected','expired'])): ?>
            <div class="pg-card p-4 mb-3">
                <h6 class="fw-600 mb-3">Update Status</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="quote_id" value="<?= (int)$detail['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-select" required>
                            <option value="">— Select Status —</option>
                            <?php
                            $transitions = [
                                'submitted' => ['in_review' => 'In Review', 'rejected' => 'Reject'],
                                'in_review' => ['quoted' => 'Quoted (Send Price)', 'rejected' => 'Reject'],
                                'quoted'    => ['accepted' => 'Mark Accepted', 'rejected' => 'Reject'],
                            ];
                            $avail = $transitions[$detail['status']] ?? [];
                            foreach ($avail as $val => $label):
                            ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note (optional)</label>
                        <textarea name="note" class="form-control" rows="3"
                                  placeholder="Add a note about this status change…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-gold w-100">Update Status</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- WhatsApp Quick Contact -->
            <?php if ($detail['contact_phone']): ?>
            <div class="pg-card p-4 mb-3">
                <h6 class="fw-600 mb-3">Quick Contact</h6>
                <a href="<?= whatsapp_link(
                    'Hi ' . ($detail['contact_name'] ?? 'there') . ", thank you for your quote request ({$detail['quote_code']}) on PlotGold Malaysia. This is " . h($provider['business_name']) . ". How can we assist you?",
                    preg_replace('/[^0-9]/', '', $detail['contact_phone'])
                ) ?>" target="_blank" class="btn btn-whatsapp w-100 mb-2">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp Customer
                </a>
                <?php if ($detail['contact_email']): ?>
                <a href="mailto:<?= h($detail['contact_email']) ?>?subject=Re: Quote Request <?= h($detail['quote_code']) ?>"
                   class="btn btn-outline-secondary w-100">
                    <i class="fas fa-envelope me-2"></i>Email Customer
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="text-center">
                <a href="<?= pg_url('provider/quotes.php') ?>" class="small text-muted">
                    <i class="fas fa-arrow-left me-1"></i>Back to all quotes
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── List View ─────────────────────────────────────────────── -->
    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr><th>Reference</th><th>Contact</th><th>Services</th><th>Mode</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($quotes as $q): ?>
                <tr>
                    <td>
                        <div class="small fw-500"><?= h($q['quote_code']) ?></div>
                        <?php if ($q['religion']): ?><div class="text-muted" style="font-size:.72rem"><?= h($q['religion']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <div class="small fw-500"><?= h($q['contact_name'] ?? 'Anonymous') ?></div>
                        <?php if ($q['contact_phone']): ?>
                            <div class="text-muted" style="font-size:.72rem"><?= h($q['contact_phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted" style="max-width:160px">
                        <?php
                        $itemCount = (int)(Database::fetchOne('SELECT COUNT(*) c FROM quotation_items WHERE quotation_id = ?', [$q['id']])['c'] ?? 0);
                        echo $itemCount . ' item' . ($itemCount !== 1 ? 's' : '');
                        ?>
                    </td>
                    <td>
                        <?php if ($q['mode'] === 'emergency'): ?>
                            <span class="badge bg-danger">Emergency</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Standard</span>
                        <?php endif; ?>
                    </td>
                    <td class="small fw-500">
                        <?= $q['total'] > 0 ? format_currency($q['total']) : '<span class="text-muted">TBD</span>' ?>
                    </td>
                    <td>
                        <span class="status-pill <?= $q['status'] === 'accepted' ? 'active' : ($q['status'] === 'submitted' ? 'pending' : 'draft') ?>">
                            <?= ucfirst(str_replace('_', ' ', $q['status'])) ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= time_ago($q['created_at']) ?></td>
                    <td>
                        <a href="?id=<?= (int)$q['id'] ?>" class="btn btn-sm btn-outline-gold">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$quotes): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-2x mb-3 opacity-25 d-block"></i>
                        No quote requests found.
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <?= pagination_links(['rows'=>$quotes,'total'=>$total,'pages'=>$pages,'page'=>$page,'per_page'=>$pp,'has_prev'=>$page>1,'has_next'=>$page<$pages], pg_url('provider/quotes.php') . '?' . http_build_query(array_diff_key($_GET, ['page'=>'']))) ?>
    </div>
    <?php endif; ?>
</div>
</div>

<?php include INC_PATH . '/footer.php'; ?>

<?php
/**
 * Admin – Campaign Create/Edit
 * /admin/pages/campaigns_form.php
 */

$id       = sanitize_int($_GET['id'] ?? 0);
$campaign = $id ? Database::fetchOne('SELECT * FROM campaigns WHERE id = ?', [$id]) : null;
$isEdit   = (bool) $campaign;
$errors   = [];

$pageTitle  = $isEdit ? 'Edit Campaign' : 'New Campaign';
$activePage = 'campaigns';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $name     = sanitize_string($_POST['name']         ?? '');
        $desc     = sanitize_string($_POST['description']  ?? '', 500);
        $type     = sanitize_string($_POST['type']         ?? 'announcement');
        $channels = array_filter((array)($_POST['channel'] ?? []), fn($v) => in_array($v, ['whatsapp','push','email','in_app']));
        $msgTpl   = sanitize_string($_POST['message_template'] ?? '', 1000);
        $segment  = sanitize_string($_POST['target_segment'] ?? 'all');
        $bonus    = sanitize_int($_POST['bonus_points'] ?? 0) ?: null;
        $from     = sanitize_string($_POST['valid_from']  ?? '');
        $until    = sanitize_string($_POST['valid_until'] ?? '');
        $status   = in_array($_POST['status'], ['draft','active']) ? $_POST['status'] : 'draft';

        if (!$name)        $errors[] = 'Campaign name is required.';
        if (empty($channels)) $errors[] = 'At least one channel is required.';

        if (empty($errors)) {
            $channelStr = implode(',', $channels);
            $userId = Auth::currentUserId();
            $params = [$name,$desc,$type,$channelStr,$msgTpl,$segment,$bonus,$from?:null,$until?:null,$status,$userId];

            if ($isEdit) {
                Database::execute(
                    'UPDATE campaigns SET name=?,description=?,type=?,channel=?,message_template=?,target_segment=?,bonus_points=?,valid_from=?,valid_until=?,status=?,created_by=? WHERE id=?',
                    array_merge($params, [$id])
                );
                flash('success', 'Campaign updated.');
            } else {
                Database::insert(
                    'INSERT INTO campaigns (name,description,type,channel,message_template,target_segment,bonus_points,valid_from,valid_until,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    $params
                );
                flash('success', 'Campaign created.');
            }
            header('Location: /admin/campaigns'); exit;
        }
    }
}

$v = $campaign ?? [];
$activeChannels = array_flip(explode(',', $v['channel'] ?? ''));
require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3">
    <a href="/admin/campaigns" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px;">
    <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-semibold"><?= $pageTitle ?></h6></div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Campaign Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($v['name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Campaign Type</label>
                    <select name="type" class="form-select">
                        <?php foreach (['announcement','promotion','birthday','inactivity','referral','double_points'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($v['type']??'')===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Target Segment</label>
                    <select name="target_segment" class="form-select">
                        <?php foreach (['all','bronze','silver','gold','platinum','inactive_30','inactive_60','birthday_today'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($v['target_segment']??'')===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Channels *</label>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php foreach (['in_app'=>'In-App','whatsapp'=>'WhatsApp','push'=>'Push','email'=>'Email'] as $k=>$lbl): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channel[]" value="<?= $k ?>" id="ch_<?= $k ?>"
                                <?= isset($activeChannels[$k]) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="ch_<?= $k ?>"><?= $lbl ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Message Template</label>
                    <textarea name="message_template" class="form-control" rows="4" placeholder="Hi {name}, we have a special offer for you!"><?= htmlspecialchars($v['message_template'] ?? '') ?></textarea>
                    <div class="form-text">Use <code>{name}</code> to personalize messages.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Bonus Points (optional)</label>
                    <input type="number" name="bonus_points" class="form-control" min="0" value="<?= $v['bonus_points'] ?? '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Valid From</label>
                    <input type="datetime-local" name="valid_from" class="form-control" value="<?= $v['valid_from'] ? str_replace(' ','T',$v['valid_from']) : '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Valid Until</label>
                    <input type="datetime-local" name="valid_until" class="form-control" value="<?= $v['valid_until'] ? str_replace(' ','T',$v['valid_until']) : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft"  <?= ($v['status']??'')==='draft'  ?'selected':'' ?>>Draft</option>
                        <option value="active" <?= ($v['status']??'')==='active' ?'selected':'' ?>>Active (ready to launch)</option>
                    </select>
                </div>
            </div>
            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Campaign' : 'Save Campaign' ?></button>
                <a href="/admin/campaigns" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

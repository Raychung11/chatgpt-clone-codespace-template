<?php
/**
 * Shared referral dashboard partial.
 * Requires: $userId (int), $portalType ('buyer'|'seller'|'provider')
 */
defined('PLOTGOLD') or die;

$myCode   = referral_ensure_code($userId);
$myLink   = referral_link($myCode);
$stats    = referral_stats($userId);
$referrals = referral_list($userId, 50);

$statusLabel = [
    'registered' => is_lang('zh') ? '已注册' : 'Registered',
    'active'     => is_lang('zh') ? '活跃'   : 'Active',
    'rewarded'   => is_lang('zh') ? '已奖励' : 'Rewarded',
];
$statusClass = ['registered' => 'primary', 'active' => 'success', 'rewarded' => 'warning'];

$waMsg = urlencode(
    (is_lang('zh') ? '加入我在 PlotGold Malaysia — 马来西亚最受信赖的墓地交易平台。使用我的推荐链接注册：' : 'Join me on PlotGold Malaysia — Malaysia\'s trusted burial plot marketplace. Register here: ')
    . $myLink
);
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-700 text-navy mb-0">
            <i class="fas fa-gift me-2 text-gold"></i><?= is_lang('zh') ? '推荐好友' : 'Refer a Friend' ?>
        </h4>
        <p class="text-muted small mb-0">
            <?= is_lang('zh') ? '分享您的专属推荐链接，邀请好友加入 PlotGold。' : 'Share your unique link and grow the PlotGold community.' ?>
        </p>
    </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['icon' => 'fa-users',        'label' => is_lang('zh') ? '总推荐人数' : 'Total Referrals', 'value' => $stats['total'],      'color' => '#4299E1'],
        ['icon' => 'fa-user-check',   'label' => is_lang('zh') ? '已注册'     : 'Registered',      'value' => $stats['registered'], 'color' => 'var(--pg-gold)'],
        ['icon' => 'fa-user-friends', 'label' => is_lang('zh') ? '活跃用户'   : 'Active',           'value' => $stats['active'],     'color' => 'var(--pg-verified)'],
    ];
    foreach ($statCards as $c): ?>
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $c['value'] ?></div>
                    <div class="stat-label"><?= $c['label'] ?></div>
                </div>
                <div class="stat-icon" style="background:<?= $c['color'] ?>22;color:<?= $c['color'] ?>">
                    <i class="fas <?= $c['icon'] ?>"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Referral code & link card -->
<div class="pg-card p-4 mb-4">
    <div class="row g-4 align-items-center">
        <!-- Code -->
        <div class="col-md-4 text-center">
            <div class="text-muted small mb-1"><?= is_lang('zh') ? '您的推荐码' : 'Your Referral Code' ?></div>
            <div class="fw-700 text-navy" style="font-size:2rem;letter-spacing:.15em"><?= h($myCode) ?></div>
            <button class="btn btn-outline-secondary btn-sm mt-2" onclick="copyToClipboard('<?= h($myCode) ?>', this)"
                    data-copied="<?= is_lang('zh') ? '已复制！' : 'Copied!' ?>">
                <i class="fas fa-copy me-1"></i><?= is_lang('zh') ? '复制' : 'Copy Code' ?>
            </button>
        </div>

        <!-- Link -->
        <div class="col-md-8">
            <div class="text-muted small mb-1"><?= is_lang('zh') ? '您的推荐链接' : 'Your Referral Link' ?></div>
            <div class="input-group mb-2">
                <input type="text" id="referralLinkInput" class="form-control form-control-sm"
                       value="<?= h($myLink) ?>" readonly onclick="this.select()">
                <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard(document.getElementById('referralLinkInput').value, this)"
                        data-copied="<?= is_lang('zh') ? '已复制！' : 'Copied!' ?>">
                    <i class="fas fa-copy me-1"></i><?= is_lang('zh') ? '复制' : 'Copy' ?>
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="https://wa.me/?text=<?= $waMsg ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    <i class="fab fa-whatsapp me-1"></i><?= is_lang('zh') ? '分享至 WhatsApp' : 'Share via WhatsApp' ?>
                </a>
                <a href="https://t.me/share/url?url=<?= urlencode($myLink) ?>&text=<?= urlencode(is_lang('zh') ? '加入 PlotGold Malaysia！' : 'Join PlotGold Malaysia!') ?>"
                   target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                    <i class="fab fa-telegram me-1"></i><?= is_lang('zh') ? '分享至 Telegram' : 'Share via Telegram' ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- How it works -->
<div class="pg-card p-4 mb-4">
    <h6 class="fw-600 mb-3"><i class="fas fa-info-circle me-2 text-gold"></i><?= is_lang('zh') ? '如何运作' : 'How It Works' ?></h6>
    <div class="row g-3">
        <?php
        $steps = is_lang('zh') ? [
            ['icon' => 'fa-share-alt',  'title' => '分享链接', 'desc' => '将您的专属推荐链接或推荐码分享给好友和家人。'],
            ['icon' => 'fa-user-plus',  'title' => '好友注册', 'desc' => '好友使用您的链接注册 PlotGold 账号。'],
            ['icon' => 'fa-chart-line', 'title' => '追踪进度', 'desc' => '在此页面追踪您所有推荐的状态和记录。'],
        ] : [
            ['icon' => 'fa-share-alt',  'title' => 'Share Your Link', 'desc' => 'Share your unique referral link or code with friends and family.'],
            ['icon' => 'fa-user-plus',  'title' => 'Friend Registers', 'desc' => 'They sign up to PlotGold using your link or enter your code manually.'],
            ['icon' => 'fa-chart-line', 'title' => 'Track Progress', 'desc' => 'See all your referrals and their status right here.'],
        ];
        foreach ($steps as $i => $step): ?>
        <div class="col-md-4 text-center">
            <div class="step-circle mx-auto mb-2"><?= $i + 1 ?></div>
            <i class="fas <?= $step['icon'] ?> fs-4 text-gold mb-2 d-block"></i>
            <div class="fw-600 small"><?= $step['title'] ?></div>
            <p class="text-muted small mb-0"><?= $step['desc'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Referral list -->
<div class="pg-card">
    <div class="p-3 border-bottom">
        <h6 class="fw-600 mb-0">
            <i class="fas fa-list me-2 text-gold"></i>
            <?= is_lang('zh') ? '推荐记录' : 'Your Referrals' ?>
            <?php if ($stats['total']): ?>
                <span class="badge bg-secondary ms-1"><?= $stats['total'] ?></span>
            <?php endif; ?>
        </h6>
    </div>

    <?php if ($referrals): ?>
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead>
                <tr>
                    <th><?= is_lang('zh') ? '好友' : 'Friend' ?></th>
                    <th><?= is_lang('zh') ? '加入时间' : 'Joined' ?></th>
                    <th><?= is_lang('zh') ? '状态' : 'Status' ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($referrals as $r): ?>
            <tr>
                <td>
                    <div class="fw-500 small"><?= h($r['referee_name'] ?? '—') ?></div>
                    <div class="text-muted" style="font-size:.75rem">
                        <?= h(substr($r['referee_email'] ?? '', 0, strrpos($r['referee_email'] ?? '', '@') ?: 20)) ?>…
                    </div>
                </td>
                <td class="small text-muted"><?= time_ago($r['joined_at']) ?></td>
                <td>
                    <span class="badge bg-<?= $statusClass[$r['status']] ?? 'secondary' ?>">
                        <?= h($statusLabel[$r['status']] ?? ucfirst($r['status'])) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
        <h6 class="text-muted"><?= is_lang('zh') ? '暂无推荐记录' : 'No referrals yet' ?></h6>
        <p class="text-muted small"><?= is_lang('zh') ? '复制上方链接并分享给好友，开始推荐吧！' : 'Copy your link above and share it to get started!' ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>' + (btn.dataset.copied || 'Copied!');
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        el.remove();
    });
}
</script>

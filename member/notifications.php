<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'Notifications';
$active_nav = '';

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    csrf_abort();
    try {
        db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
    } catch (PDOException) {}
    redirect('/member/notifications.php');
}

// Mark single as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    try {
        db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
            ->execute([(int)$_GET['read'], $user['id']]);
    } catch (PDOException) {}
}

// Paginate
$per_page = 20;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$notifications = [];
$total = 0;
$unread_count = 0;

try {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $total = (int)$stmt->fetchColumn();

    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unread_count = (int)$stmt->fetchColumn();

    $stmt = db()->prepare("
        SELECT * FROM notifications WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user['id'], $per_page, $offset]);
    $notifications = $stmt->fetchAll();

    // Mark fetched as read (auto-read on view)
    $ids = array_column($notifications, 'id');
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        db()->exec("UPDATE notifications SET is_read = 1 WHERE id IN ({$in})");
    }
} catch (PDOException $e) { error_log('[Notifications] ' . $e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

$type_icons = [
    'reward'  => '💰',
    'deal'    => '🎁',
    'system'  => '⚙️',
    'info'    => 'ℹ️',
    'verification' => '✅',
    'warning' => '⚠️',
];

include __DIR__ . '/../inc/member_layout.php';
?>

<div class="flex-between" style="margin-bottom:var(--space-lg);">
  <div>
    <h2 style="margin-bottom:4px;">Notifications</h2>
    <?php if ($unread_count > 0): ?>
      <span class="badge badge--orange"><?= $unread_count ?> unread</span>
    <?php endif; ?>
  </div>
  <?php if ($unread_count > 0): ?>
    <a href="/member/notifications.php?mark_all_read=1&<?= csrf_field() ?>"
       onclick="return confirm('Mark all notifications as read?')"
       class="btn btn--muted btn--sm">✓ Mark all read</a>
  <?php endif; ?>
</div>

<?php if (!empty($notifications)): ?>
  <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
    <?php foreach ($notifications as $n): ?>
      <div class="card" style="padding:var(--space-md) var(--space-lg);display:flex;align-items:flex-start;gap:var(--space-md);<?= !$n['is_read'] ? 'border-left:3px solid var(--orange-primary);' : 'border-left:3px solid transparent;' ?>">
        <div style="font-size:28px;flex-shrink:0;margin-top:2px;"><?= $type_icons[$n['type']] ?? 'ℹ️' ?></div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-sm);flex-wrap:wrap;">
            <div style="font-weight:<?= !$n['is_read'] ? '700' : '500' ?>;font-size:16px;"><?= e($n['title']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;flex-shrink:0;"><?= time_ago($n['created_at']) ?></div>
          </div>
          <?php if ($n['body']): ?>
            <div style="font-size:15px;color:var(--text-muted);margin-top:4px;line-height:1.6;"><?= e($n['body']) ?></div>
          <?php endif; ?>
        </div>
        <?php if (!$n['is_read']): ?>
          <div style="width:10px;height:10px;border-radius:50%;background:var(--orange-primary);flex-shrink:0;margin-top:6px;"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:var(--space-xl);justify-content:center;">
      <?php if ($page_num > 1): ?>
        <a href="?page=<?= $page_num - 1 ?>" class="pagination__btn">← Prev</a>
      <?php endif; ?>
      <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
        <a href="?page=<?= $i ?>" class="pagination__btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?>
        <a href="?page=<?= $page_num + 1 ?>" class="pagination__btn">Next →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">🔔</div>
    <h3 class="empty-state__title">No notifications yet</h3>
    <p class="empty-state__text">We'll notify you about deals, rewards, and account updates here.</p>
    <a href="/member/deals.php" class="btn btn--primary">Browse Deals</a>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>

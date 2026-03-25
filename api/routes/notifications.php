<?php
/**
 * API – Notifications Routes
 * /api/routes/notifications.php
 *
 * GET  /api/notifications/list      – Get notifications
 * POST /api/notifications/read      – Mark as read
 * POST /api/notifications/read-all  – Mark all as read
 */

require_once BASE_PATH . '/api/middleware.php';
$user = api_require_auth();

switch ($action) {

    // GET /api/notifications/list
    case 'list':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $page    = max(1, sanitize_int($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $notifications = Database::fetchAll(
            'SELECT id, title, body, type, channel, is_read, reference_type, reference_id, sent_at, read_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY sent_at DESC
             LIMIT ? OFFSET ?',
            [$user['id'], $perPage, $offset]
        );

        $unread = Notification::getUnreadCount($user['id']);

        json_success('OK', [
            'notifications'  => $notifications,
            'unread_count'   => $unread,
        ]);
        break;

    // POST /api/notifications/read
    case 'read':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        $nid = sanitize_int($body['notification_id'] ?? 0);
        if (!$nid) json_error('notification_id is required.');

        Notification::markRead($nid, $user['id']);
        json_success('Marked as read.');
        break;

    // POST /api/notifications/read-all
    case 'read-all':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        Notification::markAllRead($user['id']);
        json_success('All notifications marked as read.');
        break;

    default:
        json_error('Invalid notifications endpoint.', null, 404);
}

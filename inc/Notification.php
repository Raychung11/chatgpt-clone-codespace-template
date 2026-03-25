<?php
/**
 * Notification Service
 * /inc/Notification.php
 *
 * Handles in-app, WhatsApp (AiServe), and push notifications.
 */

class Notification
{
    /**
     * Send an in-app notification to a user.
     */
    public static function send(int $userId, string $title, string $body, string $type = 'system', string $channel = 'in_app', string $refType = null, int $refId = null): int
    {
        return Database::insert(
            'INSERT INTO notifications (user_id, title, body, type, channel, reference_type, reference_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $title, $body, $type, $channel, $refType, $refId]
        );
    }

    /**
     * Send a WhatsApp message via AiServe API.
     */
    public static function sendWhatsApp(string $phone, string $message): bool
    {
        $cfg = require BASE_PATH . '/config/app.php';

        if (empty($cfg['whatsapp_api_key']) || empty($cfg['whatsapp_api_url'])) {
            error_log('[WhatsApp] API not configured.');
            return false;
        }

        $payload = json_encode([
            'apikey'  => $cfg['whatsapp_api_key'],
            'sender'  => $cfg['whatsapp_sender'],
            'number'  => $phone,
            'message' => $message,
        ]);

        $ch = curl_init($cfg['whatsapp_api_url'] . '/send-message');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('[WhatsApp] Failed. HTTP ' . $httpCode . ' Response: ' . $response);
            return false;
        }

        return true;
    }

    /**
     * Send birthday campaign message.
     */
    public static function sendBirthdayMessage(int $userId): void
    {
        $user = Database::fetchOne(
            'SELECT u.name, u.phone FROM users u
             JOIN customer_profiles cp ON cp.user_id = u.id
             WHERE u.id = ?',
            [$userId]
        );

        if (!$user) return;

        $msg = "🎂 Happy Birthday, {$user['name']}! Wishing you a wonderful day. As a special gift, you've received 50 bonus points! Use them on your next visit.";

        self::sendWhatsApp($user['phone'], $msg);
        self::send($userId, 'Happy Birthday! 🎂', $msg, 'promotion', 'whatsapp');

        // Award bonus points
        add_loyalty_points($userId, 50, 'bonus', 'campaign', null, 'Birthday bonus');
    }

    /**
     * Send inactivity re-engagement message.
     */
    public static function sendInactivityMessage(int $userId, int $inactiveDays): void
    {
        $user = Database::fetchOne('SELECT name, phone FROM users WHERE id = ?', [$userId]);
        if (!$user) return;

        $msg = "👋 Hi {$user['name']}, we miss you! It's been {$inactiveDays} days since your last visit. Come back and earn double points this week!";

        self::sendWhatsApp($user['phone'], $msg);
        self::send($userId, 'We miss you!', $msg, 'promotion', 'whatsapp');
    }

    /**
     * Get unread notification count for a user.
     */
    public static function getUnreadCount(int $userId): int
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Mark a notification as read.
     */
    public static function markRead(int $notifId, int $userId): void
    {
        Database::execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?',
            [$notifId, $userId]
        );
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllRead(int $userId): void
    {
        Database::execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }
}

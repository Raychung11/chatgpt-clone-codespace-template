<?php
/**
 * PlotGold Malaysia — Referral System Helpers
 */

defined('PLOTGOLD') or die('Direct access not permitted.');

/**
 * Generate a unique referral code for a user.
 * Format: PG + 6 uppercase alphanumeric chars, e.g. "PGAB3F91"
 */
function generate_referral_code(): string
{
    do {
        $code = 'PG' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    } while (Database::fetchOne('SELECT id FROM users WHERE referral_code = ?', [$code]));

    return $code;
}

/**
 * Return the full shareable referral URL for a given code.
 */
function referral_link(string $code): string
{
    return pg_url('register.php') . '?ref=' . urlencode($code);
}

/**
 * Ensure a user has a referral code; generate one if missing.
 * Safe to call on every page load for logged-in users.
 */
function referral_ensure_code(int $userId): string
{
    $row = Database::fetchOne('SELECT referral_code FROM users WHERE id = ?', [$userId]);
    if (!empty($row['referral_code'])) {
        return $row['referral_code'];
    }
    $code = generate_referral_code();
    Database::query('UPDATE users SET referral_code = ? WHERE id = ?', [$code, $userId]);
    return $code;
}

/**
 * Get referral statistics for a user.
 * Returns an array: total, active (registered+active), rewarded
 */
function referral_stats(int $userId): array
{
    $rows = Database::fetchAll(
        'SELECT status, COUNT(*) AS cnt FROM referrals WHERE referrer_id = ? GROUP BY status',
        [$userId]
    );
    $stats = ['total' => 0, 'registered' => 0, 'active' => 0, 'rewarded' => 0];
    foreach ($rows as $r) {
        $stats[$r['status']] = (int)$r['cnt'];
        $stats['total'] += (int)$r['cnt'];
    }
    return $stats;
}

/**
 * Get the list of referrals made by a user (with referee profile info).
 */
function referral_list(int $userId, int $limit = 50): array
{
    return Database::fetchAll(
        "SELECT r.*, up.full_name AS referee_name, u.email AS referee_email, u.created_at AS joined_at
         FROM referrals r
         JOIN users u ON u.id = r.referee_id
         LEFT JOIN user_profiles up ON up.user_id = r.referee_id
         WHERE r.referrer_id = ?
         ORDER BY r.created_at DESC
         LIMIT $limit",
        [$userId]
    );
}

/**
 * Record a referral. Called from auth_register() when a referral code is used.
 * Returns true if referral was recorded, false if invalid or already used.
 */
function referral_record(int $referrerId, int $refereeId): bool
{
    if ($referrerId === $refereeId) return false;

    // Check referee hasn't already been referred
    $existing = Database::fetchOne('SELECT id FROM referrals WHERE referee_id = ?', [$refereeId]);
    if ($existing) return false;

    Database::query(
        'INSERT IGNORE INTO referrals (referrer_id, referee_id, status) VALUES (?, ?, ?)',
        [$referrerId, $refereeId, 'registered']
    );
    Database::query(
        'UPDATE users SET referred_by_user_id = ? WHERE id = ?',
        [$referrerId, $refereeId]
    );
    return true;
}

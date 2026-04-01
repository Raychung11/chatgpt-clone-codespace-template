<?php
declare(strict_types=1);

// ─── SilverDeals MY — Points Engine ─────────────────────────────────────────
// Central module for earning, spending and adjusting SilverPoints.
// All point changes go through these functions so the ledger stays consistent.

/**
 * Add points to a user's wallet.
 * Creates wallet row if it doesn't exist yet.
 *
 * @param int    $userId
 * @param int    $amount      Positive integer
 * @param string $source      e.g. 'referral','welcome','verification','campaign','admin_adjust'
 * @param string $description Human-readable note
 * @param int|null $referenceId  FK to source record
 * @param string|null $expiresAt  'YYYY-MM-DD HH:MM:SS' or null
 * @return bool
 */
function points_add(int $userId, int $amount, string $source, string $description = '', ?int $referenceId = null, ?string $expiresAt = null): bool
{
    if ($amount <= 0) return false;

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Upsert wallet
        $pdo->prepare("
            INSERT INTO points_wallets (user_id, balance, lifetime_earned)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                balance          = balance + VALUES(balance),
                lifetime_earned  = lifetime_earned + VALUES(lifetime_earned),
                updated_at       = NOW()
        ")->execute([$userId, $amount, $amount]);

        // Get new balance
        $balance = (int)$pdo->prepare("SELECT balance FROM points_wallets WHERE user_id = ? LIMIT 1")
                             ->execute([$userId]) ? (int)$pdo->query("SELECT balance FROM points_wallets WHERE user_id = {$userId} LIMIT 1")->fetchColumn() : 0;

        // Re-fetch cleanly
        $stmt = $pdo->prepare("SELECT balance FROM points_wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $balance = (int)$stmt->fetchColumn();

        // Ledger entry
        $pdo->prepare("
            INSERT INTO points_transactions
                (user_id, type, amount, balance_after, source, reference_id, description, expires_at)
            VALUES (?, 'earn', ?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $amount, $balance, $source, $referenceId, $description ?: "Earned via {$source}", $expiresAt]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("[Points] add failed user={$userId} amount={$amount}: " . $e->getMessage());
        return false;
    }
}

/**
 * Deduct points from a user's wallet.
 * Returns false if insufficient balance.
 */
function points_spend(int $userId, int $amount, string $source, string $description = '', ?int $referenceId = null): bool
{
    if ($amount <= 0) return false;

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Lock wallet row
        $stmt = $pdo->prepare("SELECT balance FROM points_wallets WHERE user_id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId]);
        $balance = (int)$stmt->fetchColumn();

        if ($balance < $amount) {
            $pdo->rollBack();
            return false;
        }

        $newBalance = $balance - $amount;

        $pdo->prepare("
            UPDATE points_wallets
            SET balance = ?, lifetime_spent = lifetime_spent + ?, updated_at = NOW()
            WHERE user_id = ?
        ")->execute([$newBalance, $amount, $userId]);

        $pdo->prepare("
            INSERT INTO points_transactions
                (user_id, type, amount, balance_after, source, reference_id, description)
            VALUES (?, 'spend', ?, ?, ?, ?, ?)
        ")->execute([$userId, -$amount, $newBalance, $source, $referenceId, $description ?: "Spent via {$source}"]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("[Points] spend failed user={$userId} amount={$amount}: " . $e->getMessage());
        return false;
    }
}

/**
 * Admin manual adjustment (can be positive or negative).
 */
function points_adjust(int $userId, int $amount, string $description, int $adminId): bool
{
    if ($amount === 0) return false;

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT balance FROM points_wallets WHERE user_id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId]);
        $current = (int)$stmt->fetchColumn();

        $newBalance = max(0, $current + $amount); // never go below 0

        if ($amount > 0) {
            $pdo->prepare("
                UPDATE points_wallets
                SET balance = ?, lifetime_earned = lifetime_earned + ?, updated_at = NOW()
                WHERE user_id = ?
            ")->execute([$newBalance, $amount, $userId]);
        } else {
            $pdo->prepare("
                UPDATE points_wallets
                SET balance = ?, lifetime_spent = lifetime_spent + ?, updated_at = NOW()
                WHERE user_id = ?
            ")->execute([$newBalance, abs($amount), $userId]);
        }

        $pdo->prepare("
            INSERT INTO points_transactions
                (user_id, type, amount, balance_after, source, reference_id, description)
            VALUES (?, 'adjust', ?, ?, 'admin_adjust', ?, ?)
        ")->execute([$userId, $amount, $newBalance, $adminId, $description]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("[Points] adjust failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a user's current wallet balance.
 */
function points_balance(int $userId): int
{
    try {
        $stmt = db()->prepare("SELECT balance FROM points_wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (PDOException) {
        return 0;
    }
}

/**
 * Push a notification to a user.
 */
function notify(int $userId, string $title, string $body = '', string $type = 'info', ?int $referenceId = null, ?string $referenceType = null): void
{
    try {
        db()->prepare("
            INSERT INTO notifications (user_id, title, body, type, reference_id, reference_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $title, $body, $type, $referenceId, $referenceType]);
    } catch (PDOException $e) {
        error_log("[Notify] failed user={$userId}: " . $e->getMessage());
    }
}

/**
 * Generate a unique voucher code.
 */
function generate_voucher_code(): string
{
    return strtoupper('SD' . substr(bin2hex(random_bytes(4)), 0, 8));
}

/**
 * Activate a member account after verification approval.
 * - Sets user status to 'active'
 * - Creates member card
 * - Awards welcome + verification points
 * - Processes any pending referral rewards
 */
function activate_member(int $userId): void
{
    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Activate user
        $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$userId]);

        // Create/update member card (QR payload = user_id + timestamp + hash)
        $cardNumber = generate_member_number();
        $qrPayload  = json_encode([
            'uid'  => $userId,
            'card' => $cardNumber,
            'ts'   => time(),
            'sig'  => hash_hmac('sha256', $userId . $cardNumber, APP_NAME),
        ]);

        $stmt = $pdo->prepare("SELECT id FROM member_cards WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE member_cards SET card_number=?, qr_code_data=?, is_active=1, issued_at=NOW() WHERE user_id=?")
                ->execute([$cardNumber, $qrPayload, $userId]);
        } else {
            $pdo->prepare("INSERT INTO member_cards (user_id, card_number, qr_code_data, tier) VALUES (?, ?, ?, 'free')")
                ->execute([$userId, $cardNumber, $qrPayload]);
        }

        // Update member_profiles with member_number
        $pdo->prepare("UPDATE member_profiles SET member_number = ? WHERE user_id = ? AND (member_number IS NULL OR member_number = '')")
            ->execute([$cardNumber, $userId]);

        $pdo->commit();

        // Award verification bonus
        points_add($userId, POINTS_VERIFICATION_BONUS, 'verification', 'Senior verification approved bonus');

        // Award welcome bonus
        points_add($userId, POINTS_WELCOME_BONUS, 'welcome', 'Welcome to SilverDeals MY!');

        // Notifications
        notify($userId, 'Your membership is now active! 🎉',
            'Your account has been verified. Your digital membership card is ready. You received ' . (POINTS_VERIFICATION_BONUS + POINTS_WELCOME_BONUS) . ' SilverPoints.',
            'reward');

        // Process pending referral
        $stmt = $pdo->prepare("SELECT * FROM referrals WHERE referred_id = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$userId]);
        $referral = $stmt->fetch();
        if ($referral) {
            // Award referrer
            points_add((int)$referral['referrer_id'], POINTS_REFERRAL_BONUS, 'referral',
                'Referral reward — friend verified!', (int)$referral['id']);

            // Award new member referral bonus
            points_add($userId, POINTS_REFERRAL_BONUS / 2, 'referral',
                'Joined via referral bonus', (int)$referral['id']);

            $pdo->prepare("UPDATE referrals SET status='rewarded', rewarded_at=NOW(), qualified_at=NOW() WHERE id=?")
                ->execute([$referral['id']]);

            // Notify referrer
            notify((int)$referral['referrer_id'],
                'Referral Reward Earned! 🤝',
                'Your friend just verified their account. You earned ' . POINTS_REFERRAL_BONUS . ' SilverPoints!',
                'reward', (int)$referral['id'], 'referral');
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("[activate_member] failed user={$userId}: " . $e->getMessage());
    }
}

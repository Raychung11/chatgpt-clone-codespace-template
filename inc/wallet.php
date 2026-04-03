<?php
declare(strict_types=1);

/**
 * inc/wallet.php
 * Wallet / credit management functions.
 * All balance mutations run inside transactions with row-level locking.
 */

/**
 * Get current wallet balance for a user. Returns 0.00 if not found.
 */
function wallet_balance(int $user_id): float
{
    $stmt = db()->prepare('SELECT `balance` FROM `wallets` WHERE `user_id` = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ? (float)$row['balance'] : 0.0;
}

/**
 * Credit (add) credits to a user wallet.
 * Returns ['ok' => true] or ['ok' => false, 'error' => string]
 */
function wallet_credit(
    int    $user_id,
    float  $amount,
    string $type,           // 'topup','referral_reward','admin_adjustment'
    string $ref_type = '',
    ?int   $ref_id = null,
    string $note = '',
    string $created_by = 'system'
): array {
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Credit amount must be positive.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Lock row
        $stmt = $pdo->prepare('SELECT `balance` FROM `wallets` WHERE `user_id` = ? FOR UPDATE');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            // Auto-create wallet if missing
            $pdo->prepare('INSERT INTO `wallets` (`user_id`,`balance`) VALUES (?,0.00)')
                ->execute([$user_id]);
            $before = 0.0;
        } else {
            $before = (float)$row['balance'];
        }

        $after = round($before + $amount, 2);

        $pdo->prepare('UPDATE `wallets` SET `balance` = ? WHERE `user_id` = ?')
            ->execute([$after, $user_id]);

        $pdo->prepare(
            'INSERT INTO `wallet_transactions`
             (`user_id`,`type`,`amount`,`balance_before`,`balance_after`,`reference_type`,`reference_id`,`note`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$user_id, $type, $amount, $before, $after, $ref_type ?: null, $ref_id, $note, $created_by]);

        $pdo->commit();
        return ['ok' => true, 'balance' => $after];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[wallet_credit] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Wallet update failed. Please try again.'];
    }
}

/**
 * Deduct credits from a user wallet.
 * Checks for sufficient balance first.
 */
function wallet_deduct(
    int    $user_id,
    float  $amount,
    string $type = 'deduction',
    string $ref_type = '',
    ?int   $ref_id = null,
    string $note = '',
    string $created_by = 'system'
): array {
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Deduction amount must be positive.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT `balance` FROM `wallets` WHERE `user_id` = ? FOR UPDATE');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Wallet not found.'];
        }

        $before = (float)$row['balance'];

        if ($before < $amount) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Insufficient credits.'];
        }

        $after = round($before - $amount, 2);

        $pdo->prepare('UPDATE `wallets` SET `balance` = ? WHERE `user_id` = ?')
            ->execute([$after, $user_id]);

        $pdo->prepare(
            'INSERT INTO `wallet_transactions`
             (`user_id`,`type`,`amount`,`balance_before`,`balance_after`,`reference_type`,`reference_id`,`note`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$user_id, $type, -$amount, $before, $after, $ref_type ?: null, $ref_id, $note, $created_by]);

        $pdo->commit();
        return ['ok' => true, 'balance' => $after];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[wallet_deduct] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Wallet deduction failed. Please try again.'];
    }
}

/**
 * Refund credits to a user wallet (e.g. after a failed video job).
 */
function wallet_refund(
    int    $user_id,
    float  $amount,
    string $ref_type = 'video_job',
    ?int   $ref_id = null,
    string $note = ''
): array {
    return wallet_credit(
        $user_id, $amount, 'refund',
        $ref_type, $ref_id, $note, 'system'
    );
}

/**
 * Admin manual adjustment (can be positive or negative).
 */
function wallet_admin_adjust(
    int    $user_id,
    float  $amount,         // positive = add, negative = deduct
    string $note,
    int    $admin_id
): array {
    if ($amount > 0) {
        return wallet_credit($user_id, $amount, 'admin_adjustment', '', null, $note, 'admin:' . $admin_id);
    } elseif ($amount < 0) {
        return wallet_deduct($user_id, abs($amount), 'admin_adjustment', '', null, $note, 'admin:' . $admin_id);
    }
    return ['ok' => false, 'error' => 'Amount cannot be zero.'];
}

/**
 * Get recent wallet transactions for a user.
 */
function wallet_transactions(int $user_id, int $limit = 20, int $offset = 0): array
{
    $stmt = db()->prepare(
        'SELECT * FROM `wallet_transactions`
         WHERE `user_id` = ?
         ORDER BY `created_at` DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Count total transactions for a user (for pagination).
 */
function wallet_transaction_count(int $user_id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM `wallet_transactions` WHERE `user_id` = ?');
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Process a payment order approval: add credits and record transaction.
 * Called by admin when approving a payment order.
 * Returns ['ok' => true] or ['ok' => false, 'error' => ...]
 */
function process_payment_approval(int $order_id, int $admin_id): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT * FROM `payment_orders` WHERE `id` = ? AND `status` = "pending" LIMIT 1'
    );
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['ok' => false, 'error' => 'Order not found or already processed.'];
    }

    $pdo->beginTransaction();
    try {
        // Mark order approved
        $pdo->prepare(
            'UPDATE `payment_orders`
             SET `status` = "approved", `approved_by` = ?, `approved_at` = NOW()
             WHERE `id` = ?'
        )->execute([$admin_id, $order_id]);

        // Credit wallet
        $result = wallet_credit(
            (int)$order['user_id'],
            (float)$order['credits'],
            'topup',
            'payment_order',
            $order_id,
            'Payment order #' . $order_id . ' approved',
            'admin:' . $admin_id
        );

        if (!$result['ok']) {
            $pdo->rollBack();
            return $result;
        }

        // Trigger referral reward if this is user's first approved payment
        trigger_referral_reward_if_eligible((int)$order['user_id']);

        $pdo->commit();
        log_activity('admin', $admin_id, 'approve_payment', 'Approved order #' . $order_id);
        return ['ok' => true];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[process_payment_approval] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Approval failed. Please try again.'];
    }
}

/**
 * Reject a payment order.
 */
function process_payment_rejection(int $order_id, int $admin_id, string $reason): array
{
    $stmt = db()->prepare(
        'UPDATE `payment_orders`
         SET `status` = "rejected", `approved_by` = ?, `approved_at` = NOW(), `reject_reason` = ?
         WHERE `id` = ? AND `status` = "pending"'
    );
    $stmt->execute([$admin_id, $reason, $order_id]);

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Order not found or already processed.'];
    }

    log_activity('admin', $admin_id, 'reject_payment', 'Rejected order #' . $order_id . ': ' . $reason);
    return ['ok' => true];
}

/**
 * Trigger a referral reward for the referrer of $user_id
 * if this is the user's first successful purchase and not yet rewarded.
 */
function trigger_referral_reward_if_eligible(int $user_id): void
{
    $pdo = db();

    // Find a pending referral for this user (they were referred by someone)
    $stmt = $pdo->prepare(
        'SELECT r.*, u.referred_by FROM `referrals` r
         JOIN `users` u ON u.id = r.referee_id
         WHERE r.referee_id = ? AND r.status = "pending"
         LIMIT 1'
    );
    $stmt->execute([$user_id]);
    $referral = $stmt->fetch();

    if (!$referral) return;

    // Ensure this is really the first approved payment
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM `payment_orders`
         WHERE `user_id` = ? AND `status` = "approved"'
    );
    $count->execute([$user_id]);
    if ((int)$count->fetchColumn() !== 1) return; // only reward on 1st purchase

    $reward_credits = (float)setting('referral_reward_credits', 10.0);
    $referrer_id    = (int)$referral['referrer_id'];

    $pdo->beginTransaction();
    try {
        // Credit referrer
        wallet_credit(
            $referrer_id,
            $reward_credits,
            'referral_reward',
            'referral',
            (int)$referral['id'],
            'Referral reward for inviting user #' . $user_id,
            'system'
        );

        // Mark referral rewarded
        $pdo->prepare(
            'UPDATE `referrals` SET `status` = "rewarded", `rewarded_at` = NOW() WHERE `id` = ?'
        )->execute([$referral['id']]);

        // Log reward
        $pdo->prepare(
            'INSERT INTO `referral_rewards` (`referral_id`,`user_id`,`credits`,`note`)
             VALUES (?,?,?,?)'
        )->execute([
            $referral['id'],
            $referrer_id,
            $reward_credits,
            'Referral reward: user #' . $user_id . ' made first purchase',
        ]);

        $pdo->commit();
        log_activity('system', null, 'referral_reward', 'Rewarded referrer #' . $referrer_id . ' for user #' . $user_id);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[trigger_referral_reward] ' . $e->getMessage());
    }
}

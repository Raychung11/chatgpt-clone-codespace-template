<?php
declare(strict_types=1);

/**
 * inc/wallet.php
 * Wallet / credit management functions.
 * All balance mutations use row-level locking.
 * wallet_credit() and wallet_deduct() are safe to call inside or outside
 * an existing PDO transaction — they check inTransaction() first.
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
 * Safe to call inside an existing transaction — will not open a nested one.
 */
function wallet_credit(
    int    $user_id,
    float  $amount,
    string $type,
    string $ref_type = '',
    ?int   $ref_id = null,
    string $note = '',
    string $created_by = 'system'
): array {
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Credit amount must be positive.'];
    }

    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT `balance` FROM `wallets` WHERE `user_id` = ? FOR UPDATE');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row) {
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

        if ($ownTx) $pdo->commit();
        return ['ok' => true, 'balance' => $after];

    } catch (\Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[wallet_credit] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Wallet update failed: ' . $e->getMessage()];
    }
}

/**
 * Deduct credits from a user wallet.
 * Safe to call inside an existing transaction — will not open a nested one.
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
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT `balance` FROM `wallets` WHERE `user_id` = ? FOR UPDATE');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($ownTx) $pdo->rollBack();
            return ['ok' => false, 'error' => 'Wallet not found.'];
        }

        $before = (float)$row['balance'];

        if ($before < $amount) {
            if ($ownTx) $pdo->rollBack();
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

        if ($ownTx) $pdo->commit();
        return ['ok' => true, 'balance' => $after];

    } catch (\Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[wallet_deduct] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Wallet deduction failed: ' . $e->getMessage()];
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
    float  $amount,
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
 * Process a payment order approval: mark approved and credit wallet.
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
        $pdo->prepare(
            'UPDATE `payment_orders`
             SET `status` = "approved", `approved_by` = ?, `approved_at` = NOW()
             WHERE `id` = ?'
        )->execute([$admin_id, $order_id]);

        // wallet_credit() detects the active transaction and skips beginTransaction()
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

        $pdo->commit();

        // Post-commit: referral reward (its own transaction)
        trigger_referral_reward_if_eligible((int)$order['user_id']);

        log_activity('admin', $admin_id, 'approve_payment', 'Approved order #' . $order_id);

        if (function_exists('mail_payment_approved')) {
            $usr = $pdo->prepare('SELECT name, email FROM `users` WHERE id=? LIMIT 1');
            $usr->execute([(int)$order['user_id']]);
            if ($row = $usr->fetch()) {
                mail_payment_approved($row['email'], $row['name'], (float)$order['credits'], $order_id);
            }
        }

        return ['ok' => true];

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[process_payment_approval] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Approval failed: ' . $e->getMessage()];
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

    if (function_exists('mail_payment_rejected')) {
        $usr = db()->prepare('SELECT u.name, u.email FROM `payment_orders` po JOIN `users` u ON u.id=po.user_id WHERE po.id=? LIMIT 1');
        $usr->execute([$order_id]);
        if ($row = $usr->fetch()) {
            mail_payment_rejected($row['email'], $row['name'], $reason, $order_id);
        }
    }

    return ['ok' => true];
}

/**
 * Trigger a referral reward for the referrer of $user_id on their first purchase.
 */
function trigger_referral_reward_if_eligible(int $user_id): void
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT * FROM `referrals` WHERE `referee_id` = ? AND `status` = "pending" LIMIT 1'
    );
    $stmt->execute([$user_id]);
    $referral = $stmt->fetch();

    if (!$referral) return;

    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM `payment_orders` WHERE `user_id` = ? AND `status` = "approved"'
    );
    $count->execute([$user_id]);
    if ((int)$count->fetchColumn() !== 1) return;

    $reward_credits = (float)setting('referral_reward_credits', 10.0);
    $referrer_id    = (int)$referral['referrer_id'];

    $pdo->beginTransaction();
    try {
        // wallet_credit() detects the active transaction and skips beginTransaction()
        wallet_credit(
            $referrer_id,
            $reward_credits,
            'referral_reward',
            'referral',
            (int)$referral['id'],
            'Referral reward for inviting user #' . $user_id,
            'system'
        );

        $pdo->prepare(
            'UPDATE `referrals` SET `status` = "rewarded", `rewarded_at` = NOW() WHERE `id` = ?'
        )->execute([$referral['id']]);

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

        if (function_exists('mail_referral_reward')) {
            $ref     = $pdo->prepare('SELECT name, email FROM `users` WHERE id=? LIMIT 1');
            $ref->execute([$referrer_id]);
            $refRow  = $ref->fetch();
            $refUser = $pdo->prepare('SELECT name FROM `users` WHERE id=? LIMIT 1');
            $refUser->execute([$user_id]);
            $refUserRow = $refUser->fetch();
            if ($refRow && $refUserRow) {
                mail_referral_reward(
                    $refRow['email'], $refRow['name'],
                    $reward_credits, $refUserRow['name']
                );
            }
        }

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[trigger_referral_reward] ' . $e->getMessage());
    }
}

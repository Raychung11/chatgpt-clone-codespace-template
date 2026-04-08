Add a new payment gateway to the VideoSaaS platform.

The platform already has a manual payment approval flow in:
- `client/buy-credits.php` — user submits payment receipt
- `admin/payments.php` — admin approves/rejects
- `inc/wallet.php` — wallet_credit(), process_payment_approval()

To add a real payment gateway ($ARGUMENTS), you need to:
1. Read `client/buy-credits.php` and `inc/wallet.php` first
2. Add a new payment method tab/section to `client/buy-credits.php`
3. Create `client/payment-webhook.php` (or gateway-specific callback handler)
   - Verify webhook signature from the gateway
   - On successful payment: call `wallet_credit()` with the correct amount
   - Mark payment as approved in `payments` table
4. Add gateway API credentials to `config/config.php` and `settings` table
5. Write `sql/migrate_<gateway>.sql` for any new DB columns needed
6. Test with the gateway's sandbox/test mode
7. Commit and push to branch `claude/ai-video-saas-platform-bX7QE`

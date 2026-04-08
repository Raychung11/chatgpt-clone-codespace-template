Add a new feature to the VideoSaaS platform.

The user will describe the feature. Your job is to:
1. Read relevant existing files before touching anything
2. Plan what files need to be created or modified
3. Implement the feature following the project conventions:
   - PHP 8+ with `declare(strict_types=1)` at top of every file
   - PDO for all DB queries — no raw queries, always use prepared statements
   - Use `$pdo->inTransaction()` check before `beginTransaction()` / `rollBack()`
   - All wallet operations use `wallet_deduct()` / `wallet_credit()` / `wallet_refund()` from `inc/wallet.php`
   - Auth: `require_auth()` from `inc/auth.php` on every client page
   - CSRF: `csrf_field()` in every form, `csrf_verify()` on every POST
   - Flash messages: `flash_success()` / `flash_error()` then `redirect()`
   - Output escaping: always `e()` for HTML output, `json_encode()` for JS
   - Settings: `setting('key', DEFAULT_CONSTANT)` with `?: CONSTANT` fallback
   - New DB tables: write a `sql/migrate_*.sql` migration file
   - New pages: add nav link in `inc/layout.php` render_client_navbar()
4. Commit with a clear message and push to branch `claude/ai-video-saas-platform-bX7QE`

The feature requested: $ARGUMENTS

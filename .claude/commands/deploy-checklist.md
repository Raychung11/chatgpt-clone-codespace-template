Run a pre-deployment checklist for the VideoSaaS platform on Hostinger.

Check each of these and report status (✅ done / ⚠ needs attention / ❌ missing):

1. **Config**: `config/config.php` — are all constants set? No hardcoded localhost URLs?
2. **Secrets**: are API keys in environment variables or DB settings (not hardcoded)?
3. **DB migrations**: list all `sql/migrate_*.sql` files — remind user to run any new ones in phpMyAdmin
4. **Upload dirs**: `uploads/avatars/`, `uploads/avatar_audio/` — do they exist with correct .htaccess?
5. **Error logging**: `logs/` directory exists and is writable?
6. **Admin security**: `admin/reset-admin-password.php` and `admin/debug_byteplus.php` — warn if still present
7. **Git status**: any uncommitted changes?
8. **Cron**: remind about `cron/poll_jobs.php?secret=CRON_SECRET` for Hostinger

Report clearly what the user needs to action before going live.

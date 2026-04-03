# VideoSaaS — Installation Guide

## Phase 1 Setup (Auth + Wallet + Payments)

### 1. Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite (Hostinger compatible)
- cURL enabled
- fileinfo extension enabled

### 2. Database Setup
```sql
CREATE DATABASE videosaas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Then import the schema:
```bash
mysql -u your_user -p videosaas < sql/schema.sql
```

### 3. Configuration
1. Copy `.env.example` to `.env`
2. Fill in your DB credentials and BytePlus API key
3. Edit `config/config.php` if you need to change any defaults

### 4. Set Default Admin Password
Run this PHP snippet once to generate a proper password hash, then update the `admins` table:
```php
<?php echo password_hash('YourNewPassword123', PASSWORD_BCRYPT, ['cost' => 12]);
```
Then in MySQL:
```sql
UPDATE admins SET password_hash = '<generated_hash>' WHERE email = 'admin@example.com';
```

### 5. File Permissions
```bash
chmod 755 uploads/ uploads/receipts/ uploads/videos/ logs/
chmod 644 config/config.php
```

### 6. Web Server Document Root
Point your VirtualHost / domain to the `/public/` directory.

**Apache VirtualHost example:**
```apache
<VirtualHost *:80>
    DocumentRoot /home/user/videosaas/public
    ServerName yourdomain.com
    <Directory /home/user/videosaas/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 7. Cron Job (for video polling — Phase 2)
In cPanel or crontab:
```
* * * * * php /home/user/videosaas/cron/poll_jobs.php >> /home/user/videosaas/logs/cron.log 2>&1
```

### 8. URLs
| Page                | URL                             |
|---------------------|---------------------------------|
| Landing page        | `/`                             |
| Client register     | `/register.php`                 |
| Client login        | `/login.php`                    |
| Client dashboard    | `/client/dashboard.php`        |
| Buy credits         | `/client/buy-credits.php`      |
| Wallet              | `/client/wallet.php`           |
| Referral            | `/client/referral.php`         |
| Admin login         | `/admin/login.php`             |
| Admin dashboard     | `/admin/index.php`             |
| Admin payments      | `/admin/payments.php`          |
| Admin users         | `/admin/users.php`             |
| Admin wallets       | `/admin/wallets.php`           |
| Admin packages      | `/admin/packages.php`          |
| Admin settings      | `/admin/settings.php`          |

---
## Phase 2 — Coming next
- Video generation page (`client/generate.php`)
- BytePlus API provider (`inc/byteplus.php`)
- Job history (`client/history.php`)
- Admin job management (`admin/jobs.php`)
- Social sharing module

## Default Credentials (CHANGE THESE)
- Admin email: `admin@example.com`
- Admin password: Set manually via SQL (see step 4)

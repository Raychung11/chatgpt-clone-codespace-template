# PlotGold Malaysia — Deployment Guide

## Requirements
- PHP 8.2+ with extensions: pdo, pdo_mysql, fileinfo, mbstring, json
- MySQL 8.0+ or MariaDB 10.6+
- Apache 2.4+ with mod_rewrite enabled (or Nginx with rewrite rules)
- SSL certificate (required for production)

## Quick Start

### 1. Database Setup
```bash
mysql -u root -p < sql/001_schema.sql
mysql -u root -p plotgold < sql/002_seed.sql
```

### 2. Configuration
```bash
# Set environment variables on your server, OR edit config/config.php directly
# Required: DB_HOST, DB_NAME, DB_USER, DB_PASS, APP_URL
```

### 3. File Permissions
```bash
chmod 755 uploads/ logs/
chmod -R 644 uploads/ logs/
chown -R www-data:www-data uploads/ logs/
```

### 4. Apache Virtual Host
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/plotgold
    
    <Directory /var/www/plotgold>
        AllowOverride All
        Require all granted
    </Directory>
    
    # SSL config here
</VirtualHost>
```

### 5. Admin Account
After running seed data, set the admin password:
```php
// Run once in a temporary setup script:
$hash = password_hash('YourSecurePassword', PASSWORD_BCRYPT, ['cost' => 12]);
// UPDATE users SET password_hash = '$hash' WHERE email = 'admin@plotgold.my';
```

### 6. Cron Jobs (optional)
```bash
# Expire old listings daily
0 2 * * * php /var/www/plotgold/cron/expire_listings.php

# Refresh gold price cache
0 */6 * * * php /var/www/plotgold/cron/refresh_gold_price.php
```

## Security Checklist
- [ ] Change admin@plotgold.my password immediately after deploy
- [ ] Set strong DB password
- [ ] Enable HTTPS (uncomment HTTPS redirect in .htaccess)
- [ ] Set APP_DEBUG=false in production
- [ ] Configure SMTP for email notifications
- [ ] Set recaptcha keys in admin settings
- [ ] Ensure uploads/ directory is not publicly executable
- [ ] Configure firewall to restrict DB port

## File Structure Overview
```
plotgold/
├── config/         — Config & DB connection
├── inc/            — Shared includes, auth, functions
│   ├── partials/   — Reusable UI components
│   └── errors/     — Error pages
├── assets/         — CSS, JS, images
├── sql/            — Schema & seed data
├── uploads/        — User uploads (not in git)
├── logs/           — PHP error logs (not in git)
├── api/            — AJAX/API endpoints
├── admin/          — Admin back office
├── seller/         — Seller portal
├── buyer/          — Buyer portal
├── provider/       — Provider portal
├── parks/          — Memorial park pages
├── city/           — City landing pages
└── *.php           — Public pages
```

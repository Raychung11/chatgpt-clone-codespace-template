# F&B Loyalty Platform – Deployment Guide (Hostinger)

## Prerequisites
- Hostinger shared hosting or VPS
- PHP 8.1+
- MySQL 8.0+
- Apache with mod_rewrite enabled

---

## 1. Database Setup

1. Create a MySQL database in Hostinger hPanel
2. Import the schema:
   ```
   hPanel → Databases → phpMyAdmin → Import → schema.sql
   hPanel → Databases → phpMyAdmin → Import → database/migrations/001_api_tokens.sql
   ```
3. Note your DB host, name, user, password

---

## 2. Environment Config

Copy `.env.example` to `.env` and fill in real values:

```
APP_URL=https://yourdomain.com
DB_HOST=localhost
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_password
WHATSAPP_API_KEY=your_aiServe_key
GOOGLE_MAPS_KEY=your_maps_key
```

> **Important:** Hostinger shared hosting doesn't load `.env` natively.
> Edit `/config/app.php` and `/config/db.php` directly with your values, OR
> use a PHP `.env` loader package (optional).

---

## 3. File Upload

Option A – File Manager:
1. Zip the entire project folder
2. Upload via Hostinger hPanel → File Manager → public_html
3. Extract

Option B – Git:
```bash
git clone https://github.com/yourrepo/fnb-platform.git
```

---

## 4. Apache / .htaccess

The `.htaccess` is already configured. Ensure mod_rewrite is enabled.

For Hostinger shared hosting it is enabled by default.

---

## 5. Directory Structure (after deploy)

```
public_html/
├── .htaccess          ← routing rules
├── .env               ← environment variables
├── config/
│   ├── app.php
│   └── db.php
├── inc/               ← core classes
├── admin/             ← merchant admin panel
├── api/               ← REST API
├── public/            ← PWA assets + customer app
│   ├── css/
│   ├── icons/         ← PWA icons (add your own)
│   ├── manifest.json
│   ├── sw.js
│   └── pages/
├── database/
└── modules/           ← (future modules)
```

---

## 6. Default Admin Login

After importing schema.sql:
- URL: `https://yourdomain.com/admin/login`
- Email: `admin@example.com`
- Password: `Admin@1234`

> **Change immediately after first login!**

---

## 7. PWA Icons

Add PNG icons to `/public/icons/`:
- icon-72.png, icon-96.png, icon-128.png
- icon-144.png, icon-192.png, icon-512.png

Use tools like [pwa-asset-generator](https://github.com/elegantapp/pwa-asset-generator)
or [RealFaviconGenerator](https://realfavicongenerator.net).

---

## 8. WhatsApp Integration (AiServe)

1. Register at AiServe
2. Get API key and sender number
3. Set in `.env` or `config/app.php`

OTP and campaign messages will be sent via WhatsApp automatically.

---

## 9. HTTPS

Hostinger provides free SSL. Enable it in hPanel → SSL.

Uncomment the HTTPS redirect in `.htaccess`:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 10. Cron Job (Automated Campaigns)

For birthday and inactivity campaigns, set up a cron job in Hostinger:

```
0 9 * * * /usr/bin/php /home/user/public_html/modules/campaigns/cron.php
```

---

## API Endpoints Quick Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/auth/request-otp | Request OTP |
| POST | /api/auth/verify-otp  | Verify OTP + login |
| GET  | /api/auth/me          | Current user |
| GET  | /api/loyalty/balance  | Points balance |
| GET  | /api/loyalty/history  | Transaction history |
| GET  | /api/rewards/list     | Available rewards |
| POST | /api/rewards/redeem   | Redeem a reward |
| GET  | /api/outlets/list     | All outlets |
| GET  | /api/outlets/nearby   | Near me (lat/lng) |
| GET  | /api/outlets/menu     | Outlet menu |
| POST | /api/reservations/create | Book table |
| GET  | /api/reservations/my  | My bookings |
| POST | /api/orders/create    | Create order |
| GET  | /api/orders/my        | My orders |
| GET  | /api/notifications/list | Notifications |
| GET  | /api/referrals/my     | Referral stats |

All protected endpoints require: `Authorization: Bearer <token>`

All responses:
```json
{
  "status": "success|error",
  "message": "...",
  "data": {}
}
```

# SilverDeals MY

**Senior Membership & Rewards Ecosystem for Malaysians 50+**

Operated by **SLV Lifestyle Sdn Bhd** | Powered by **SLV Group Sdn Bhd**

---

## Tech Stack

- **PHP 8.3** — Server-rendered pages
- **MySQL 8+** — Database
- **Vanilla CSS/JS + Alpine.js-ready** — No bloated frameworks
- **Hostinger** — Target deployment (shared hosting or VPS)
- **Apache** — `.htaccess` included

---

## Quick Start (Local / Hostinger)

### 1. Clone & configure

```bash
git clone <repo>
cd silverdeals-my
```

### 2. Configure database

Copy and fill in your credentials:
```bash
cp config/database.php config/database.local.php
# edit config/database.local.php with your DB host/name/user/pass
```

Or set environment variables:
```
DB_HOST=127.0.0.1
DB_NAME=silverdeals
DB_USER=youruser
DB_PASS=yourpassword
APP_BASE_URL=https://silverdeals.my
APP_ENV=production
```

### 3. Run the schema

```sql
mysql -u youruser -p silverdeals < database/001_schema.sql
```

### 4. Point your web root

Point your Hostinger domain's document root to the **project root** (or upload all files there).

### 5. First login

- **Admin:** `admin@silverdeals.my` / `Admin@123`
- **Change the password immediately after first login.**

---

## Folder Structure

```
/public          — Public-facing pages (homepage, deals, register, login)
/member          — Member portal (dashboard, profile, rewards, etc.)
/merchant        — Merchant portal
/community       — Community partner portal
/admin           — Admin dashboard
/api             — API endpoints (JSON)
/inc             — Shared includes (bootstrap, auth, db, layouts)
/assets          — CSS, JS, images
/config          — App + DB config
/database        — SQL migrations
```

---

## Phase 1 Complete ✓

- [x] Full database schema (35 tables)
- [x] Config & bootstrap system
- [x] CSRF protection
- [x] Secure session handling
- [x] Orange theme CSS design system
- [x] Public homepage
- [x] Member registration with referral tracking
- [x] Member login with role-based redirects
- [x] Member dashboard shell
- [x] Admin login
- [x] Admin dashboard with KPIs
- [x] `.htaccess` security + caching
- [x] PWA manifest
- [x] Error pages (403, 404, 500, 503)

## Next: Phase 2

- Member profile completion
- Senior verification workflow
- Digital QR membership card
- Notification system

---

*SilverDeals MY — © 2025 SLV Lifestyle Sdn Bhd*

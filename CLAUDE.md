# AI101 Platform — Claude Code Project Memory

## What This Project Is
A SaaS marketplace platform to sell **101 AI agents to SMEs**. Built in PHP + MySQL, hosted on Hostinger.

The platform has two parts:
- `platform/` — the full PHP web platform (deploy this to Hostinger)
- `app.py` — the original Streamlit ChatGPT clone (separate, keep intact)

---

## Platform Architecture

```
platform/
├── index.php              # Public homepage
├── marketplace.php        # Product listing with filters/search
├── product.php            # Product detail page
├── checkout.php           # Stripe checkout (14-day trial)
├── login.php / register.php / logout.php
├── dashboard.php          # Customer dashboard
├── admin/
│   ├── index.php          # Admin overview dashboard
│   ├── products.php       # CRUD for all 101 products
│   ├── clients.php        # Customer management
│   ├── hr.php             # HR dashboard (headcount, leave, payroll trend)
│   ├── employees.php      # Employee CRUD (dept, salary, manager, type)
│   ├── leave.php          # Leave request management + approve/reject
│   └── payroll.php        # Payroll records + bulk generate
├── includes/
│   ├── config.php         # ← ALL config lives here (DB, Stripe, etc.)
│   ├── db.php             # DB class (PDO wrapper)
│   ├── auth.php           # Auth class (sessions, roles)
│   ├── header.php         # Public nav + <head>
│   ├── footer.php         # Public footer + scripts
│   ├── admin-header.php   # Admin sidebar layout
│   └── admin-footer.php   # Admin closing tags
├── assets/
│   ├── css/style.css      # Main dark theme (Bootstrap 5 based)
│   ├── css/admin.css      # Admin sidebar layout styles
│   └── js/main.js         # Vanilla JS (scroll fx, slug gen, toasts)
├── database.sql           # Full MySQL schema + seed data
├── .htaccess              # Apache config for Hostinger
└── README.md              # Deployment guide
```

---

## Tech Stack

| Layer | Choice |
|-------|--------|
| Language | PHP 8.0+ |
| Database | MySQL 5.7+ / MariaDB via PDO |
| CSS Framework | Bootstrap 5.3 |
| Icons | Bootstrap Icons 1.11 |
| Fonts | Inter (Google Fonts) |
| Payments | Stripe Checkout (subscription + one-time) |
| Hosting | Hostinger shared hosting |

---

## Coding Conventions — FOLLOW THESE ALWAYS

### PHP
- **All config** goes in `includes/config.php` via `define()` constants
- **All DB queries** use `DB::fetch()`, `DB::fetchAll()`, `DB::insert()`, `DB::update()` — never raw PDO
- **All auth checks** use `Auth::requireLogin()` or `Auth::requireAdmin()` at the top of protected pages
- **Always sanitize output** with `htmlspecialchars()` for user data in HTML
- **Always use `?` placeholders** in queries, never string concatenation
- Include order at top of every page: `config.php` → `db.php` → `auth.php`

```php
// Standard page header pattern:
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
Auth::requireLogin(); // or requireAdmin() for admin pages
$pageTitle = 'Page Title';
require_once 'includes/header.php';
// ... page content ...
require_once 'includes/footer.php';
```

### CSS / Design
- **Dark theme** — background `#0a0a0f`, cards `#111118`, section bg `#0d0d14`
- **Primary color** — `#6366f1` (indigo)
- **All new UI** uses `glass-card` class for panels, `product-card` for product tiles
- Use Bootstrap 5 utilities first, custom CSS only when Bootstrap doesn't cover it
- Custom CSS goes in `assets/css/style.css` (public) or `assets/css/admin.css` (admin only)

### Admin Pages
- All admin pages go in `platform/admin/`
- Always start with `Auth::requireAdmin()`
- Use `require_once '../includes/...'` (one level up)
- Use `admin-header.php` / `admin-footer.php` (not the public header/footer)

### New Pages Checklist
When adding a new page:
1. `require_once 'includes/config.php'` + `db.php` + `auth.php`
2. Set `$pageTitle` before `require_once 'includes/header.php'`
3. Wrap content in `<div class="container py-5">`
4. End with `require_once 'includes/footer.php'`
5. Add a nav link in `includes/header.php` if it's a main page

### Database
- All tables use `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`
- All user-content fields use `htmlspecialchars()` on insert
- Update `database.sql` when adding new tables (keep it as the single source of truth)
- Foreign keys always have `ON DELETE CASCADE` or `ON DELETE SET NULL`

---

## Key Constants (in config.php)
```php
SITE_NAME          // "AI101 Platform"
SITE_URL           // "https://yourdomain.com"
DB_HOST/NAME/USER/PASS
STRIPE_PUBLIC_KEY  // pk_live_... or pk_test_...
STRIPE_SECRET_KEY  // sk_live_... or sk_test_...
TRIAL_DAYS         // 14
CURRENCY_SYMBOL    // "$"
```

---

## Database Tables
| Table | Purpose |
|-------|---------|
| `users` | Customers + admins (role field) |
| `categories` | Product categories (8 categories) |
| `products` | The 101 AI agent listings |
| `subscriptions` | Recurring subscriptions (monthly/yearly) |
| `purchases` | One-time purchases |
| `reviews` | Product reviews (moderated) |
| `leads` | Contact form submissions |
| `settings` | Key-value site settings |
| `demo_sessions` | Demo usage tracking |
| `employees` | Staff — dept, job_title, employment_type, salary, pay_cycle, manager_id, status |
| `leave_requests` | Leave requests — type, dates, days_count, status (pending/approved/rejected/cancelled) |
| `payroll` | Payroll records — gross, deductions, net, pay period, status (draft/processed/paid) |

---

## HR Module (added 2026-03-21)

### Pages
- **`admin/hr.php`** — dashboard: KPI cards, dept breakdown bars, 6-month payroll chart (Chart.js), pending leave widget with inline approve/reject, upcoming leave, recent hires
- **`admin/employees.php`** — full CRUD, filters (dept/status/search), avatar initials, modal form, manager dropdown
- **`admin/leave.php`** — create/approve/reject requests; approving a current-dated leave auto-sets employee status to `on_leave`; rejecting resets to `active`
- **`admin/payroll.php`** — manual records + **Bulk Generate** (drafts from all active employees with salary for any period), mark-paid shortcut, gross/deductions/net summary footer

### Sidebar
`admin-header.php` has an **HR** section with a pending-leave badge on the Leave link.

### Key patterns used
- Payroll bulk-generate skips duplicates (checks period + employee_id)
- `leave_requests.reviewed_by` → FK to `users.id`
- `employees.manager_id` → self-referencing FK

---

## Deployment (Hostinger)
1. Import `database.sql` via phpMyAdmin
2. Edit `includes/config.php` with real DB credentials + domain
3. Upload `platform/` contents → `public_html/`
4. Add Stripe keys, uncomment Stripe code in `checkout.php`
5. Change default admin password (admin@ai101platform.com)

---

## What NOT to Do
- **Never** put DB credentials anywhere except `includes/config.php`
- **Never** echo raw `$_GET`/`$_POST` without `htmlspecialchars()`
- **Never** write raw SQL with string concat — always use prepared statements via `DB::`
- **Never** use `$_SESSION` directly — always use `Auth::` methods
- **Don't** create new CSS files — add to `style.css` or `admin.css`
- **Don't** touch `app.py` or `requirements.txt` — that's the separate Streamlit project

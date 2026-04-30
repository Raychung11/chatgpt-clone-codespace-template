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
│   ├── payroll.php        # Payroll records + bulk generate
│   ├── crm.php            # CRM dashboard (pipeline, activities, tasks)
│   ├── crm-contacts.php   # Contact CRUD (status, source, owner, tags)
│   ├── crm-deals.php      # Deal pipeline - kanban + table, stage-move
│   ├── suppliers.php      # Supplier CRUD (rating, category, PO links)
│   ├── purchase-orders.php# PO management (line items, status advance, auto PO#)
│   ├── marketing.php      # Marketing dashboard (campaign stats, social calendar)
│   ├── email-campaigns.php# Email campaign CRUD + duplicate action
│   ├── email-subscribers.php # List + subscriber management, bulk CSV import
│   └── social-posts.php   # Social post scheduler - calendar + list view
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
| `crm_contacts` | CRM contacts — source, status (lead/prospect/customer/churned/blocked), owner_id, tags |
| `crm_deals` | Deals — stage, value, probability, expected_close, contact_id, lost_reason |
| `crm_activities` | Activities — type (call/email/meeting/task/note), status, due_at, contact_id, deal_id |
| `suppliers` | Supplier records — rating, payment_terms, category, status |
| `purchase_orders` | POs — po_number, status (draft/sent/confirmed/received/cancelled), line items via FK |
| `purchase_order_items` | PO line items — description, qty, unit_price, total |
| `email_lists` | Subscriber lists |
| `email_subscribers` | Subscribers — status (subscribed/unsubscribed/bounced/complained), list_id |
| `email_campaigns` | Email campaigns — subject, body_html, status, scheduled_at, open/click stats |
| `social_posts` | Social posts — platform SET, content, scheduled_at, impressions/clicks/engagement |

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

## CRM Module (added 2026-03-21)

- **`admin/crm.php`** — KPIs, pipeline funnel bars, Chart.js monthly won-deals chart, recent activities, upcoming tasks with mark-done
- **`admin/crm-contacts.php`** — contact CRUD, status/source filter + search, "View Deals" link per contact
- **`admin/crm-deals.php`** — kanban + table toggle, stage-move select on cards, probability auto-suggest by stage, `lost_reason` field, `?contact=` filter

---

## Supplier Module (added 2026-03-21)

- **`admin/suppliers.php`** — CRUD with star rating, category/status filter, KPI row, "View POs" link
- **`admin/purchase-orders.php`** — PO header + dynamic line items, auto PO# (`PO-YYYYMM-XXXX`), status advance buttons (Draft→Send→Confirm→Received), overdue date highlight, subtotal/tax/total auto-calc

---

## Marketing Automation Module (added 2026-03-21)

- **`admin/marketing.php`** — subscriber KPIs, campaigns-per-month chart, email performance table, 7-day social calendar, platform breakdown pills
- **`admin/email-campaigns.php`** — campaign CRUD, duplicate action, open/click rate bars, `scheduled_at` shown only when status=scheduled
- **`admin/email-subscribers.php`** — list management panel + subscriber table, bulk CSV import (skips duplicates), single-add modal, unsubscribe action
- **`admin/social-posts.php`** — month calendar grid + list toggle, multi-platform checkboxes, dynamic char counter (most restrictive limit), optional campaign link, `?action=new` auto-opens modal

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

---

# AiServe BOS Capsule Business Model
## Version: 1.0 | Author: Ray Chung | Company: SLV Group Sdn Bhd
## Added to project context: 2026-04-30

---

## Business Vision

AiServe is a **Business Operating System (BOS)** for SMEs powered by modular AI **Capsules**.

> We do NOT sell chatbots. We sell a system that runs the business automatically.

---

## System Structure

```
AiServe = BOS Core + Capsules
├── BOS Core   → Platform / Entry / Lock-in System
└── Capsules   → Revenue Engine (Recurring)
```

---

## BOS Core (The "Machine")

Components:
- WhatsApp AI Inbox (multi-user, multi-device)
- CRM (Customer Database)
- Automation Engine
- Conversation Memory
- Dashboard (Admin + Analytics)

Objectives:
- Centralize all customer communication
- Replace manual customer service
- Capture structured business data
- Become the daily operating interface

BOS Core Pricing: **RM2,000 – RM5,000 / month** (mandatory subscription)

---

## Capsule System (The "Recurring Engine")

Capsules = Plug & Play · Industry-specific · Monthly subscription · Continuously improved

| Capsule | Purpose | Price/month |
|---------|---------|-------------|
| Customer Service | FAQ automation, multi-language, lead classification | RM1,500 – RM3,000 |
| Sales Conversion | Auto follow-up, quote generation, closing scripts, upsell | RM3,000 – RM8,000 |
| Daily Reporting | Sales reports, inventory, cash flow, AI anomaly detection | RM1,500 – RM4,000 |
| HR & Admin | Leave application, payroll query, staff FAQ, training | RM1,500 – RM3,500 |
| Marketing Automation | Broadcast automation, segmentation, campaign scheduling | RM2,000 – RM6,000 |
| AI Decision | Sales forecasting, churn prediction, business insights | RM5,000 – RM15,000 |

---

## Packaging / Pricing Plans

| Plan | Capsules Included | Price/month |
|------|-------------------|-------------|
| Starter | BOS Core + Customer Service | RM3,500 |
| Growth | BOS Core + Customer Service + Sales + Marketing | RM8,000 – RM12,000 |
| Enterprise | All Capsules + Custom workflows + AI Decision Layer | RM15,000 – RM30,000 |

---

## Lock-in Mechanism (Critical)

- All customer data stored inside AiServe
- Workflow automations NOT exportable
- API access controlled by AiServe
- WhatsApp deeply integrated
- Customer becomes dependent on: Data + Automation + AI logic

---

## Target Market

- Primary: SMEs (F&B, Travel, Furniture, Clinics)
- Secondary: Multi-branch businesses, Franchises

---

## Go-To-Market Strategy

1. **Entry**: Sell Customer Service Capsule first → solve immediate pain
2. **Expand**: Upsell Sales → Marketing → Reporting Capsules
3. **Retain**: Monthly insights reports + continuous AI improvement + data dependency

---

## Scaling Phases

- **Phase 1**: Direct Sales — close high-ticket clients (RM10k+/month)
- **Phase 2**: Template Standardization — build industry-specific capsule templates
- **Phase 3**: Marketplace — Capsule Store + Partner Ecosystem (Subscription + Commission)

---

## Future Vision: 5-Layer OS for SMEs

1. Communication Layer (WhatsApp)
2. Automation Layer (AI)
3. Data Layer (CRM + Analytics)
4. Decision Layer (AI Insights)
5. Marketplace Layer

---

## Revenue Philosophy

- Entry Low → Lock Customer
- Expand Modules → Increase ARPU
- Subscription → Predictable Revenue
- Data → Long-term Value

---

## How This Affects Platform Development

When building features, always think in terms of:
- **Capsules** (not just "products" or "agents") — each product IS a Capsule
- **BOS Core** — the platform itself is the lock-in mechanism
- **ARPU expansion** — every new Capsule is an upsell opportunity
- **Lock-in** — prioritize features that make customers depend on the system
- **SME-first** — simple, industry-specific, 1-click deployment
- **Subscription** — favour recurring revenue features over one-time tools

Terminology in code/UI:
- "Products" in DB/admin = Capsules in customer-facing UI
- "Subscriptions" = active Capsule deployments
- "Dashboard" = BOS Dashboard
- "Modules/Tools" = Capsule features the customer uses

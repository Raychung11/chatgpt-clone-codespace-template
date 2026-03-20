# AI101 Platform — Hostinger Deployment Guide

## What's Built
A complete PHP platform with:
- **Homepage** — Hero, categories, featured products, pricing, testimonials, contact form
- **Marketplace** — Search, filter by category/price, sort, pagination
- **Product Detail** — Features, pricing, demo links, reviews, related products
- **Authentication** — Login, Register, Logout (secure sessions)
- **Customer Dashboard** — View owned agents, subscriptions, recommendations
- **Admin Panel** — Dashboard overview, product management, client management, revenue stats
- **Checkout** — Stripe-ready checkout with trial flow
- **Database** — Full MySQL schema with seed data

## Quick Start on Hostinger

### 1. Create Database
1. Login to **hPanel** → **Databases** → **MySQL Databases**
2. Create a new database: `ai101_platform`
3. Create a database user and grant all privileges
4. Import `database.sql` via phpMyAdmin

### 2. Configure
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai101_platform');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('SITE_URL', 'https://yourdomain.com');
```

### 3. Upload Files
Upload the entire `platform/` folder contents to your `public_html/` directory via:
- File Manager in hPanel, OR
- FTP/SFTP with FileZilla

### 4. Stripe Integration
1. Create account at [stripe.com](https://stripe.com)
2. Get your API keys from the Dashboard
3. Add keys to `config.php`
4. Create products & prices in Stripe Dashboard
5. Update `stripe_price_monthly` / `stripe_price_yearly` in the database
6. Install Stripe PHP SDK: `composer require stripe/stripe-php`
7. Uncomment the Stripe code in `checkout.php`

### 5. Admin Login
Default admin credentials (change immediately!):
- Email: `admin@ai101platform.com`
- Password: `password` (hash is for 'password' — change via phpMyAdmin)

## File Structure
```
platform/
├── index.php           # Homepage
├── marketplace.php     # Product listing
├── product.php         # Product detail
├── checkout.php        # Stripe checkout
├── login.php
├── register.php
├── logout.php
├── dashboard.php       # Customer dashboard
├── admin/
│   ├── index.php       # Admin dashboard
│   ├── products.php    # Manage all 101 products
│   └── clients.php     # Customer management
├── includes/
│   ├── config.php      # ← EDIT THIS FIRST
│   ├── db.php
│   ├── auth.php
│   ├── header.php
│   ├── footer.php
│   ├── admin-header.php
│   └── admin-footer.php
├── assets/
│   ├── css/style.css
│   ├── css/admin.css
│   └── js/main.js
├── database.sql        # Import this into MySQL
└── .htaccess           # Apache config for Hostinger
```

-- ============================================================
-- AI101 Platform - Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS ai101_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai101_platform;

-- Users (customers + admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') DEFAULT 'customer',
    company VARCHAR(150),
    phone VARCHAR(30),
    avatar VARCHAR(255),
    stripe_customer_id VARCHAR(100),
    email_verified TINYINT(1) DEFAULT 0,
    email_token VARCHAR(64),
    reset_token VARCHAR(64),
    reset_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories for products
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'bi-grid',
    color VARCHAR(20) DEFAULT '#6366f1',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Products (the 101 AI agent offerings)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    tagline VARCHAR(255),
    description TEXT,
    features JSON,
    use_cases JSON,
    thumbnail VARCHAR(255),
    demo_url VARCHAR(255),
    demo_type ENUM('iframe','link','built-in') DEFAULT 'link',
    price_monthly DECIMAL(10,2) DEFAULT 0.00,
    price_yearly DECIMAL(10,2) DEFAULT 0.00,
    price_onetime DECIMAL(10,2) DEFAULT 0.00,
    pricing_model ENUM('monthly','yearly','onetime','free') DEFAULT 'monthly',
    stripe_price_monthly VARCHAR(100),
    stripe_price_yearly VARCHAR(100),
    badge VARCHAR(50),
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    sales_count INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Subscriptions (recurring)
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    stripe_subscription_id VARCHAR(100),
    stripe_customer_id VARCHAR(100),
    plan ENUM('monthly','yearly') DEFAULT 'monthly',
    status ENUM('active','canceled','past_due','trialing','paused') DEFAULT 'active',
    trial_ends_at DATETIME,
    current_period_start DATETIME,
    current_period_end DATETIME,
    canceled_at DATETIME,
    amount DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- One-time purchases
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    stripe_payment_intent_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'usd',
    status ENUM('pending','completed','refunded','failed') DEFAULT 'pending',
    invoice_number VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Demo usage tracking
CREATE TABLE demo_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    session_token VARCHAR(64),
    ip_address VARCHAR(45),
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    messages_count INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Product reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(150),
    body TEXT,
    is_approved TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Leads / contact inquiries
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    company VARCHAR(150),
    phone VARCHAR(30),
    product_interest INT,
    message TEXT,
    status ENUM('new','contacted','qualified','converted','lost') DEFAULT 'new',
    source VARCHAR(80),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_interest) REFERENCES products(id) ON DELETE SET NULL
);

-- Site settings
CREATE TABLE settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- Seed Data
-- ============================================================

-- Admin user (password: Admin@1234 - change immediately!)
INSERT INTO users (name, email, password, role, email_verified) VALUES
('Admin', 'admin@ai101platform.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Categories
INSERT INTO categories (name, slug, description, icon, color, sort_order) VALUES
('Customer Service', 'customer-service', 'AI agents that handle customer support and engagement', 'bi-headset', '#6366f1', 1),
('Sales & Marketing', 'sales-marketing', 'AI tools to boost sales and automate marketing tasks', 'bi-graph-up-arrow', '#f59e0b', 2),
('HR & Recruitment', 'hr-recruitment', 'Automate hiring, onboarding, and employee management', 'bi-people', '#10b981', 3),
('Finance & Accounting', 'finance-accounting', 'Smart tools for invoicing, bookkeeping, and reporting', 'bi-calculator', '#3b82f6', 4),
('Operations', 'operations', 'Streamline workflows and day-to-day business operations', 'bi-gear', '#8b5cf6', 5),
('Content & Writing', 'content-writing', 'AI-powered content creation and copywriting', 'bi-pencil-square', '#ec4899', 6),
('E-Commerce', 'ecommerce', 'AI tools for online stores and product management', 'bi-bag', '#f97316', 7),
('Legal & Compliance', 'legal-compliance', 'Document review, contracts, and compliance automation', 'bi-shield-check', '#14b8a6', 8);

-- Sample products
INSERT INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES
(1, 'SmartSupport AI', 'smartsupport-ai', 'Never miss a customer query again', 'An intelligent customer support agent that handles FAQs, escalates complex issues, and learns from every interaction to improve over time.', '["24/7 automated responses","CRM integration","Sentiment analysis","Multi-language support","Human handoff","Analytics dashboard"]', 'Popular', 1, 79.00, 790.00, 'monthly', 1),
(2, 'LeadHunter AI', 'leadhunter-ai', 'Find and qualify leads on autopilot', 'AI-powered lead generation and qualification engine that researches prospects, scores leads, and drafts personalised outreach emails.', '["Lead scoring","Email drafting","LinkedIn research","CRM sync","Campaign analytics","A/B testing"]', 'Hot', 1, 99.00, 990.00, 'monthly', 2),
(3, 'HireBot AI', 'hirebot-ai', 'Screen candidates 10x faster', 'Automate resume screening, schedule interviews, and assess candidates with AI-powered scoring aligned to your job requirements.', '["Resume parsing","AI scoring","Interview scheduling","ATS integration","Bias detection","Reporting"]', 'New', 1, 89.00, 890.00, 'monthly', 3),
(4, 'InvoiceGenius AI', 'invoicegenius-ai', 'Get paid faster with smart invoicing', 'Automatically generate invoices, chase late payments, reconcile accounts, and produce financial summaries with zero manual effort.', '["Auto invoicing","Payment reminders","Expense tracking","Multi-currency","Xero/QuickBooks sync","Tax reports"]', NULL, 0, 59.00, 590.00, 'monthly', 4),
(6, 'ContentCraft AI', 'contentcraft-ai', 'Create on-brand content in seconds', 'Generate blog posts, social captions, email newsletters, and product descriptions that match your brand voice — ready to publish.', '["Blog generation","Social media posts","Email campaigns","SEO optimisation","Brand voice training","Plagiarism check"]', 'Popular', 1, 49.00, 490.00, 'monthly', 5),
(7, 'ShopBot AI', 'shopbot-ai', 'Turn browsers into buyers automatically', 'An AI shopping assistant that answers product questions, recommends items, handles returns, and recovers abandoned carts.', '["Product Q&A","Upsell recommendations","Cart recovery","Returns handling","Shopify/WooCommerce","Analytics"]', NULL, 0, 69.00, 690.00, 'monthly', 6);

-- Default settings
INSERT INTO settings (`key`, `value`) VALUES
('site_name', 'AI101 Platform'),
('site_tagline', '101 AI Agents for Modern SMEs'),
('stripe_mode', 'test'),
('currency', 'USD'),
('trial_days', '14'),
('contact_email', 'hello@ai101platform.com'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', '');

-- ============================================================
-- Accounting Module Tables
-- ============================================================

-- Expenses (business cost tracking)
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('software','hosting','marketing','salaries','operations','tax','other') NOT NULL DEFAULT 'other',
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    expense_date DATE NOT NULL,
    vendor VARCHAR(150),
    reference VARCHAR(100),
    notes TEXT,
    receipt_url VARCHAR(255),
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Invoices (customer invoices)
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    subscription_id INT,
    purchase_id INT,
    status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    notes TEXT,
    paid_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL
);

-- Invoice line items
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(8,2) DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- ============================================================
-- HR Module Tables
-- ============================================================

-- Employees
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    department ENUM('engineering','marketing','sales','support','operations','finance','hr','management') NOT NULL DEFAULT 'operations',
    job_title VARCHAR(150),
    employment_type ENUM('full_time','part_time','contractor','intern') DEFAULT 'full_time',
    status ENUM('active','on_leave','terminated') DEFAULT 'active',
    start_date DATE NOT NULL,
    end_date DATE,
    salary DECIMAL(10,2),
    salary_currency VARCHAR(3) DEFAULT 'USD',
    pay_cycle ENUM('monthly','biweekly','weekly') DEFAULT 'monthly',
    manager_id INT,
    avatar VARCHAR(255),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Leave requests
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('annual','sick','unpaid','parental','bereavement','other') NOT NULL DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count DECIMAL(4,1) NOT NULL DEFAULT 1,
    reason TEXT,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    reviewed_by INT,
    reviewed_at DATETIME,
    review_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Payroll runs
CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    gross_amount DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('draft','processed','paid') DEFAULT 'draft',
    payment_date DATE,
    reference VARCHAR(100),
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- CRM Module Tables
-- ============================================================

CREATE TABLE IF NOT EXISTS crm_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    company VARCHAR(150),
    job_title VARCHAR(150),
    source ENUM('website','referral','social','cold_outreach','event','other') DEFAULT 'website',
    status ENUM('lead','prospect','customer','churned','blocked') DEFAULT 'lead',
    owner_id INT,
    tags VARCHAR(255),
    notes TEXT,
    last_contacted_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS crm_deals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    contact_id INT,
    value DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    stage ENUM('lead','qualified','proposal','negotiation','closed_won','closed_lost') DEFAULT 'lead',
    probability INT DEFAULT 0 COMMENT 'Percent 0-100',
    expected_close DATE,
    owner_id INT,
    notes TEXT,
    lost_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS crm_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT,
    deal_id INT,
    type ENUM('call','email','meeting','task','note') NOT NULL DEFAULT 'note',
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    status ENUM('planned','done','cancelled') DEFAULT 'planned',
    due_at DATETIME,
    done_at DATETIME,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (deal_id) REFERENCES crm_deals(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- Supplier Module Tables
-- ============================================================

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact_name VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(30),
    website VARCHAR(255),
    country VARCHAR(100),
    category VARCHAR(100),
    payment_terms VARCHAR(100),
    status ENUM('active','inactive','blacklisted') DEFAULT 'active',
    rating TINYINT DEFAULT NULL COMMENT '1-5 stars',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    status ENUM('draft','sent','confirmed','received','cancelled') DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_date DATE,
    received_date DATE,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
);

-- ============================================================
-- Marketing Automation Tables
-- ============================================================

CREATE TABLE IF NOT EXISTS email_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    status ENUM('subscribed','unsubscribed','bounced','complained') DEFAULT 'subscribed',
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at DATETIME,
    UNIQUE KEY uq_list_email (list_id, email),
    FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    preview_text VARCHAR(255),
    from_name VARCHAR(100),
    from_email VARCHAR(150),
    list_id INT,
    body_html TEXT,
    status ENUM('draft','scheduled','sending','sent','paused') DEFAULT 'draft',
    scheduled_at DATETIME,
    sent_at DATETIME,
    total_sent INT DEFAULT 0,
    total_opens INT DEFAULT 0,
    total_clicks INT DEFAULT 0,
    total_unsubscribes INT DEFAULT 0,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform SET('twitter','linkedin','facebook','instagram') NOT NULL,
    content TEXT NOT NULL,
    media_url VARCHAR(500),
    hashtags VARCHAR(500),
    status ENUM('draft','scheduled','published','failed') DEFAULT 'draft',
    scheduled_at DATETIME,
    published_at DATETIME,
    campaign_id INT,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    engagement INT DEFAULT 0,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- Warehouse & Logistics Tables
-- ============================================================

CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'US',
    manager VARCHAR(100),
    phone VARCHAR(30),
    capacity INT COMMENT 'max storage units',
    status ENUM('active','inactive') DEFAULT 'active',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    description TEXT,
    warehouse_id INT,
    bin_location VARCHAR(50) COMMENT 'e.g. A-12-3',
    qty_on_hand INT DEFAULT 0,
    qty_reserved INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    reorder_qty INT DEFAULT 50,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    supplier_id INT,
    status ENUM('active','inactive','discontinued') DEFAULT 'active',
    image_url VARCHAR(500),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    type ENUM('inbound','outbound','adjustment','transfer') NOT NULL,
    qty INT NOT NULL COMMENT 'positive = in, negative = out',
    qty_before INT DEFAULT 0,
    qty_after INT DEFAULT 0,
    reference VARCHAR(100) COMMENT 'PO number, shipment number, etc.',
    reason VARCHAR(255),
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_number VARCHAR(50) NOT NULL UNIQUE,
    carrier VARCHAR(100),
    tracking_number VARCHAR(150),
    status ENUM('pending','dispatched','in_transit','out_for_delivery','delivered','returned','cancelled') DEFAULT 'pending',
    direction ENUM('outbound','inbound') DEFAULT 'outbound',
    origin_name VARCHAR(150),
    origin_address TEXT,
    dest_name VARCHAR(150),
    dest_address TEXT,
    dest_city VARCHAR(100),
    dest_country VARCHAR(100),
    dispatch_date DATE,
    est_delivery DATE,
    actual_delivery DATE,
    weight DECIMAL(8,2) COMMENT 'kg',
    dimensions VARCHAR(100) COMMENT 'LxWxH cm',
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    insurance_value DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shipment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    item_id INT,
    description VARCHAR(255) NOT NULL,
    sku VARCHAR(80),
    quantity INT DEFAULT 1,
    unit_value DECIMAL(10,2) DEFAULT 0,
    total_value DECIMAL(12,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
);

-- ============================================================
-- Homepage Builder Settings (added 2026-03-21)
-- ============================================================

INSERT IGNORE INTO settings (`key`, `value`) VALUES
('active_theme', 'dark'),
('page_sections_order', '["hero","categories","featured","how_it_works","pricing","testimonials","cta","contact"]'),
('page_sections_visibility', '{"hero":1,"categories":1,"featured":1,"how_it_works":1,"pricing":1,"testimonials":1,"cta":1,"contact":1}'),
('nav_items', '[{"label":"Home","url":"\/"},{"label":"Marketplace","url":"\/marketplace.php"},{"label":"Pricing","url":"\/pricing.php"},{"label":"About","url":"\/about.php"},{"label":"Contact","url":"\/contact.php"}]');

-- ============================================================
-- Digital Marketing Module (added 2026-03-21)
-- ============================================================

-- Promotions
CREATE TABLE IF NOT EXISTS promotions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  description TEXT,
  type ENUM('percentage','fixed','bogo','free_shipping') DEFAULT 'percentage',
  discount_value DECIMAL(10,2) DEFAULT 0,
  min_order_value DECIMAL(10,2) DEFAULT 0,
  max_uses INT DEFAULT NULL,
  used_count INT DEFAULT 0,
  start_date DATE,
  end_date DATE,
  status ENUM('active','scheduled','expired','paused') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Vouchers
CREATE TABLE IF NOT EXISTS vouchers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  type ENUM('percentage','fixed','free_shipping') DEFAULT 'percentage',
  value DECIMAL(10,2) DEFAULT 0,
  min_order DECIMAL(10,2) DEFAULT 0,
  usage_limit INT DEFAULT 1,
  used_count INT DEFAULT 0,
  customer_id INT DEFAULT NULL,
  expires_at DATE DEFAULT NULL,
  status ENUM('active','expired','disabled') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Shoutouts / Testimonials
CREATE TABLE IF NOT EXISTS shoutouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_name VARCHAR(200) NOT NULL,
  author_title VARCHAR(200),
  author_avatar_url VARCHAR(500),
  platform ENUM('twitter','linkedin','instagram','facebook','email','other') DEFAULT 'other',
  content TEXT NOT NULL,
  rating TINYINT DEFAULT 5,
  featured TINYINT(1) DEFAULT 0,
  status ENUM('pending','published','rejected') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Behavior Events
CREATE TABLE IF NOT EXISTS behavior_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(100),
  user_id INT DEFAULT NULL,
  event_type VARCHAR(50),
  page_url VARCHAR(500),
  referrer VARCHAR(500),
  duration_seconds INT DEFAULT 0,
  converted TINYINT(1) DEFAULT 0,
  user_agent VARCHAR(500),
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- AI Guide Conversations
CREATE TABLE IF NOT EXISTS ai_guide_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(100),
  user_id INT DEFAULT NULL,
  question TEXT,
  answer TEXT,
  answered TINYINT(1) DEFAULT 1,
  satisfaction TINYINT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Seed: Promotions
INSERT IGNORE INTO promotions (name, description, type, discount_value, min_order_value, max_uses, start_date, end_date, status) VALUES
('Summer Sale', '20% off all AI agents', 'percentage', 20, 0, 500, '2026-06-01', '2026-08-31', 'scheduled'),
('New Customer Discount', '$10 off first order', 'fixed', 10, 49, 1000, '2026-01-01', '2026-12-31', 'active'),
('Buy One Get One', 'BOGO on selected plans', 'bogo', 0, 99, 200, '2026-03-01', '2026-04-30', 'active'),
('March Madness', '15% off all monthly plans', 'percentage', 15, 0, 300, '2026-03-01', '2026-03-31', 'active'),
('Enterprise Free Ship', 'Free shipping on enterprise orders', 'free_shipping', 0, 299, NULL, '2026-01-01', '2026-12-31', 'active');

-- Seed: Vouchers
INSERT IGNORE INTO vouchers (code, type, value, min_order, usage_limit, expires_at, status) VALUES
('WELCOME20', 'percentage', 20, 0, 1000, '2026-12-31', 'active'),
('SAVE10NOW', 'fixed', 10, 49, 500, '2026-06-30', 'active'),
('FREESHIP99', 'free_shipping', 0, 99, 300, '2026-09-30', 'active'),
('VIP30OFF', 'percentage', 30, 199, 50, '2026-06-30', 'active'),
('FIRSTBUY15', 'percentage', 15, 0, 999, '2026-12-31', 'active');

-- Seed: Shoutouts
INSERT IGNORE INTO shoutouts (author_name, author_title, platform, content, rating, featured, status) VALUES
('Sarah Chen', 'Marketing Director at TechCorp', 'linkedin', 'AI101 transformed our marketing workflow. The AI agents handle 80% of our repetitive tasks now!', 5, 1, 'published'),
('Marcus Williams', 'SME Owner', 'twitter', 'Honestly the best investment for my small business. The HR AI alone saves me 10 hours a week.', 5, 1, 'published'),
('Priya Patel', 'Operations Manager', 'email', 'The CRM agent is incredibly smart. It predicted our biggest client was about to churn and we saved the deal.', 4, 0, 'published'),
('James O''Brien', 'Founder & CEO, StartupLab', 'linkedin', 'We replaced 3 SaaS tools with AI101 agents. Better results, half the cost. Absolutely recommend.', 5, 1, 'published'),
('Anna Schmidt', 'HR Manager', 'email', 'Leave management used to take me hours every week. The AI HR module does it in minutes. Game changer!', 5, 0, 'pending');

-- Seed: AI Guide Config (if not already present)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('ai_guide_config', '{"name":"Aria","emoji":"\ud83e\udd16","greeting":"Hi! I am Aria, your AI shopping guide. How can I help you today?","personality":"friendly","position":"bottom-right","color":"#6366f1","delay":3,"auto_open":["homepage"],"kb":{"products":"We offer 101 AI agents for SMEs covering HR, CRM, Marketing, Finance, and more.","pricing":"Plans start from $49\/month. Yearly plans save 20%. A 14-day free trial is available.","shipping":"All products are digital \u2014 instant access after purchase.","returns":"14-day money-back guarantee on all plans.","about":"AI101 is a marketplace of AI agents designed to help SMEs automate their business."}}');

-- ============================================================
-- Multi-Outlet Module (added 2026-03-21)
-- ============================================================

CREATE TABLE IF NOT EXISTS outlets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  code VARCHAR(20) UNIQUE NOT NULL,
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100),
  country VARCHAR(100) DEFAULT 'Malaysia',
  phone VARCHAR(50),
  email VARCHAR(200),
  manager_name VARCHAR(200),
  outlet_type ENUM('retail','kiosk','warehouse','online','franchise') DEFAULT 'retail',
  status ENUM('active','inactive','temporarily_closed') DEFAULT 'active',
  opening_date DATE,
  operating_hours VARCHAR(200),
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS outlet_sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  outlet_id INT NOT NULL,
  order_ref VARCHAR(100),
  product_name VARCHAR(200),
  amount DECIMAL(10,2) DEFAULT 0,
  quantity INT DEFAULT 1,
  sale_date DATE,
  payment_method ENUM('cash','card','ewallet','bank_transfer') DEFAULT 'cash',
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE
);

INSERT IGNORE INTO outlets (name, code, city, state, manager_name, outlet_type, status, opening_date, operating_hours) VALUES
('Flagship Store KL', 'KL-01', 'Kuala Lumpur', 'WP KL', 'Ahmad Razif', 'retail', 'active', '2022-01-15', '10am–10pm Daily'),
('Mid Valley Kiosk', 'MV-02', 'Kuala Lumpur', 'WP KL', 'Siti Nora', 'kiosk', 'active', '2022-06-01', '10am–10pm Daily'),
('Penang Branch', 'PG-03', 'George Town', 'Penang', 'Lim Wei Jian', 'retail', 'active', '2023-03-10', '10am–9pm Daily'),
('Online Store', 'ON-04', '-', '-', 'Raj Kumar', 'online', 'active', '2022-01-01', '24/7'),
('Johor Bahru Franchise', 'JB-05', 'Johor Bahru', 'Johor', 'Hafiz Ismail', 'franchise', 'active', '2024-01-20', '10am–10pm Daily');

-- ============================================================
-- Cash Flow Module (added 2026-03-21)
-- ============================================================

CREATE TABLE IF NOT EXISTS cash_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  account_type ENUM('bank','cash','ewallet','petty_cash') DEFAULT 'bank',
  bank_name VARCHAR(200),
  account_number VARCHAR(100),
  opening_balance DECIMAL(12,2) DEFAULT 0,
  current_balance DECIMAL(12,2) DEFAULT 0,
  currency VARCHAR(10) DEFAULT 'MYR',
  status ENUM('active','inactive') DEFAULT 'active',
  outlet_id INT DEFAULT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cash_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  transaction_type ENUM('inflow','outflow','transfer') DEFAULT 'inflow',
  category VARCHAR(100),
  description TEXT,
  amount DECIMAL(12,2) NOT NULL,
  reference VARCHAR(200),
  transaction_date DATE NOT NULL,
  outlet_id INT DEFAULT NULL,
  reconciled TINYINT(1) DEFAULT 0,
  created_by INT,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES cash_accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO cash_accounts (name, account_type, bank_name, account_number, opening_balance, current_balance) VALUES
('Maybank Current', 'bank', 'Maybank', '5642-1234-5678', 50000.00, 87350.00),
('CIMB Savings', 'bank', 'CIMB', '7001-9876-5432', 20000.00, 34200.00),
('Petty Cash - HQ', 'petty_cash', NULL, NULL, 2000.00, 850.00),
('Touch n Go eWallet', 'ewallet', NULL, NULL, 500.00, 1200.00);

-- ============================================================
-- Debtor & Creditor Ageing (added 2026-03-21)
-- ============================================================

CREATE TABLE IF NOT EXISTS debtor_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  note TEXT NOT NULL,
  follow_up_date DATE,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS supplier_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  po_id INT DEFAULT NULL,
  invoice_number VARCHAR(100) NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) DEFAULT 0,
  status ENUM('unpaid','partial','paid','disputed','overdue') DEFAULT 'unpaid',
  payment_terms INT DEFAULT 30,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL
);

-- ============================================================
-- Customer AI Modules
-- ============================================================

-- Leave requests submitted via customer module (linked to users, not employees)
CREATE TABLE IF NOT EXISTS user_leave_requests (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  leave_type  ENUM('annual','sick','unpaid','parental','bereavement','other') NOT NULL DEFAULT 'annual',
  start_date  DATE NOT NULL,
  end_date    DATE NOT NULL,
  days_count  DECIMAL(4,1) NOT NULL DEFAULT 1,
  reason      TEXT,
  status      ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  reviewed_by INT DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  review_note TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- AI tool usage log (optional analytics)
CREATE TABLE IF NOT EXISTS ai_usage_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  module     VARCHAR(50) NOT NULL,
  tokens_est INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

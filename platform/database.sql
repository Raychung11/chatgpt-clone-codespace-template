-- ============================================================
-- AI101 Platform - Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================
-- HOSTINGER: Select your database in phpMyAdmin BEFORE importing.
-- Do NOT run CREATE DATABASE / USE here — Hostinger creates the DB for you.
-- ============================================================

-- Company workspaces (Unified Identity)
-- NOTE: companies table must be created before users (no FK on companies at creation time)
-- Run these ALTER statements after both tables exist:
--   ALTER TABLE companies ADD FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL;
--   ALTER TABLE users     ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;
CREATE TABLE IF NOT EXISTS companies (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    slug       VARCHAR(200) NOT NULL UNIQUE,
    industry   VARCHAR(100) DEFAULT '',
    size       ENUM('1-5','6-20','21-50','51-200','200+') DEFAULT '1-5',
    owner_id   INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Team invitations
CREATE TABLE IF NOT EXISTS company_invitations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    company_id  INT NOT NULL,
    email       VARCHAR(200) NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    role        ENUM('admin','member') DEFAULT 'member',
    invited_by  INT DEFAULT NULL,
    expires_at  DATETIME NOT NULL,
    accepted_at DATETIME DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Users (customers + admins)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') DEFAULT 'customer',
    company VARCHAR(150),
    company_id INT DEFAULT NULL,
    company_role ENUM('owner','admin','member') DEFAULT 'owner',
    phone VARCHAR(30),
    avatar VARCHAR(255),
    stripe_customer_id VARCHAR(100),
    email_verified TINYINT(1) DEFAULT 0,
    email_token VARCHAR(64),
    reset_token VARCHAR(64),
    reset_expires DATETIME,
    total_points INT DEFAULT 0,
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories for products
CREATE TABLE IF NOT EXISTS categories (
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
CREATE TABLE IF NOT EXISTS products (
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
CREATE TABLE IF NOT EXISTS subscriptions (
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
CREATE TABLE IF NOT EXISTS purchases (
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
CREATE TABLE IF NOT EXISTS demo_sessions (
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
CREATE TABLE IF NOT EXISTS reviews (
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
CREATE TABLE IF NOT EXISTS leads (
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
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- Seed Data
-- ============================================================

-- Admin user (password: Admin@1234 - change immediately!)
INSERT IGNORE INTO users (name, email, password, role, email_verified) VALUES
('Admin', 'admin@ai101platform.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Categories
INSERT IGNORE INTO categories (name, slug, description, icon, color, sort_order) VALUES
('Customer Service', 'customer-service', 'AI Capsules that handle WhatsApp queries, FAQ automation, and customer engagement 24/7', 'bi-headset', '#6366f1', 1),
('Sales & Marketing', 'sales-marketing', 'Capsules that automate follow-ups, lead nurturing, and marketing campaigns', 'bi-graph-up-arrow', '#f59e0b', 2),
('HR & Recruitment', 'hr-recruitment', 'Automate leave management, payroll queries, candidate screening, and onboarding', 'bi-people', '#10b981', 3),
('Finance & Accounting', 'finance-accounting', 'Smart Capsules for invoicing, collections, and automated financial reporting', 'bi-calculator', '#3b82f6', 4),
('Operations', 'operations', 'Daily reporting, SOP management, and operational workflow automation', 'bi-gear', '#8b5cf6', 5),
('Content & Writing', 'content-writing', 'AI content creation for blogs, social media, WhatsApp broadcasts, and email', 'bi-pencil-square', '#ec4899', 6),
('E-Commerce', 'ecommerce', 'AI tools for online stores — product Q&A, cart recovery, and order support', 'bi-bag', '#f97316', 7),
('Legal & Compliance', 'legal-compliance', 'Contract review, compliance checklists, and regulatory update automation', 'bi-shield-check', '#14b8a6', 8);

-- AiServe BOS Capsule product lineup
INSERT IGNORE INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES
(1, 'WhatsApp CS Capsule', 'whatsapp-cs-capsule', 'Handle every customer query via WhatsApp, 24/7', 'Deploy an AI customer service agent directly inside WhatsApp. Handles FAQs, processes orders, escalates to human agents, and learns from every conversation — so your team can focus on high-value work.', '["24/7 WhatsApp automation","Multi-language support (EN/MY/ZH)","Smart human handoff with context","CRM contact sync","Sentiment detection & alerts","Monthly performance report"]', 'Popular', 1, 1500.00, 15000.00, 'monthly', 1),
(1, 'FAQ Automation Capsule', 'faq-automation-capsule', 'Instantly answer your top 100 FAQs — without lifting a finger', 'Train the AI on your business FAQs, pricing, policies, and products. Deploy across WhatsApp, website chat, or email. Handles repetitive queries automatically so your team only deals with complex issues.', '["Custom FAQ training (up to 200 Q&As)","Multi-channel deployment","Auto-suggest top answers","Learns from team corrections","Unanswered query alerts","Analytics dashboard"]', 'New', 0, 800.00, 8000.00, 'monthly', 2),
(1, 'Live Chat Capsule', 'live-chat-capsule', 'Convert website visitors into customers while you sleep', 'An AI-powered live chat agent for your website that qualifies leads, answers product questions, books appointments, and captures contact details — automatically, around the clock.', '["Instant visitor response (<3 seconds)","Lead capture & qualification","Appointment / demo booking","Product recommendation engine","Customisable chat widget","Real-time analytics"]', NULL, 0, 1200.00, 12000.00, 'monthly', 3),
(2, 'Sales Conversion Capsule', 'sales-conversion-capsule', 'Turn leads into customers with AI-powered follow-up', 'Never lose a lead again. This Capsule automatically follows up with prospects via WhatsApp and email, sends AI-generated quotes, handles objections, and nudges deals to close — without your sales team lifting a finger.', '["Auto follow-up sequences (Day 1/3/7/14)","AI quote & proposal generation","Objection handling scripts","Deal stage tracking","Upsell & cross-sell triggers","Weekly sales performance report"]', 'Hot', 1, 3000.00, 30000.00, 'monthly', 4),
(2, 'Lead Generation Capsule', 'lead-generation-capsule', 'Find, qualify, and nurture leads entirely on autopilot', 'AI researches your ideal customers, scores inbound enquiries by purchase intent, and routes hot leads to your sales team instantly — complete with background research and a recommended opening script.', '["Inbound lead scoring & prioritisation","Prospect research & profiling","Auto-qualification conversation flow","Hot lead instant alerts","CRM pipeline integration","Weekly pipeline report"]', 'Popular', 1, 2000.00, 20000.00, 'monthly', 5),
(2, 'Marketing Automation Capsule', 'marketing-automation-capsule', 'Run campaigns that feel personal — at massive scale', 'Segment your customer base, schedule WhatsApp and email broadcasts, and trigger personalised campaigns based on customer behaviour. Set up once and let the Capsule manage your entire marketing calendar.', '["WhatsApp broadcast automation","Dynamic customer segmentation","Behaviour-triggered campaigns","A/B campaign testing","Open rate & click analytics","Monthly ROI report"]', NULL, 1, 2500.00, 25000.00, 'monthly', 6),
(3, 'HR Admin Capsule', 'hr-admin-capsule', 'Handle HR queries and leave requests without an HR team', 'Employees ask questions — the Capsule answers. Leave applications, payroll queries, company policies, and onboarding steps are fully automated, freeing your HR team to focus on people strategy.', '["Leave request automation & approval flow","Payroll query handling","Company policy search (AI-powered)","New hire onboarding checklists","HR analytics dashboard","Multi-department support"]', NULL, 0, 1500.00, 15000.00, 'monthly', 7),
(3, 'Recruitment Capsule', 'recruitment-capsule', 'Screen 10x more candidates in half the time', 'Post a job, receive applications, and let AI screen resumes, score candidates against your job criteria, and schedule interviews automatically — delivering a ranked shortlist without reading 200 CVs.', '["Resume screening & AI scoring","Automated interview scheduling","Candidate ranking & comparison","Job posting templates (BM/EN)","ATS-ready data export","Time-to-hire analytics"]', 'New', 0, 2000.00, 20000.00, 'monthly', 8),
(4, 'Finance Reporting Capsule', 'finance-reporting-capsule', 'Your P&L, cash flow, and KPIs delivered every morning', 'Connect your accounting data and receive automated daily, weekly, and monthly financial reports straight to WhatsApp or email. AI highlights anomalies, flags risks, and summarises performance for non-finance managers.', '["Daily P&L snapshots","7-day cash flow forecast","Anomaly & variance alerts","KPI dashboard (revenue, margin, burn)","WhatsApp report delivery","Multi-branch consolidation"]', NULL, 0, 1800.00, 18000.00, 'monthly', 9),
(4, 'Invoice & Collections Capsule', 'invoice-collections-capsule', 'Get paid faster with AI-powered invoice automation', 'Generate professional invoices automatically, send polite payment reminders via WhatsApp, follow up on overdue accounts with escalating sequences, and reconcile payments without touching a spreadsheet.', '["Auto invoice generation","WhatsApp payment reminders (Day 1/7/14/30)","Overdue account follow-up","Payment reconciliation","Aged debtor report","Multi-currency support"]', NULL, 0, 900.00, 9000.00, 'monthly', 10),
(5, 'Daily Reporting Capsule', 'daily-reporting-capsule', 'Business performance delivered to your phone every morning', 'Connect your sales, inventory, and finance data. Every morning, receive a plain-language business summary via WhatsApp — sales vs target, stock levels, cash position, and what needs your attention today.', '["Daily WhatsApp performance summary","Sales vs target tracking","Low stock & reorder alerts","Cash position snapshot","AI anomaly detection & explanation","Fully customisable report schedule"]', NULL, 1, 2000.00, 20000.00, 'monthly', 11),
(5, 'SOP Management Capsule', 'sop-management-capsule', 'Ensure every team member follows the right process, every time', 'Store all your Standard Operating Procedures in one AI-searchable library. Staff ask via WhatsApp and get the right steps instantly. Managers get notified when procedures are skipped or SOPs are out of date.', '["AI-searchable SOP library","WhatsApp process lookup for staff","SOP version control & history","Compliance reminder notifications","Built-in staff training quizzes","Usage & completion analytics"]', NULL, 0, 1200.00, 12000.00, 'monthly', 12),
(6, 'Content Creation Capsule', 'content-creation-capsule', 'On-brand content for every channel — ready in seconds', 'Train the AI on your brand voice, products, and audience once. Then generate blog posts, WhatsApp broadcast messages, social captions, product descriptions, and email newsletters on demand.', '["Blog post generation (SEO-optimised)","WhatsApp broadcast copy","Social media captions (FB/IG/TikTok)","Email newsletter drafts","Brand voice training","Unlimited content requests"]', NULL, 0, 1000.00, 10000.00, 'monthly', 13),
(7, 'E-Commerce Capsule', 'ecommerce-capsule', 'AI that handles your online store — from enquiry to repeat purchase', 'Your AI-powered e-commerce assistant answers product questions, recommends items, tracks order status, manages returns, and recovers abandoned carts — across WhatsApp, web chat, and social DMs.', '["Product Q&A automation","Personalised product recommendations","Cart abandonment recovery","Order status self-service","Returns & exchange handling","Shopee / Lazada / WooCommerce integration"]', NULL, 0, 2500.00, 25000.00, 'monthly', 14),
(8, 'Compliance Capsule', 'compliance-capsule', 'Stay compliant and legally protected — without a full-time lawyer', 'AI reviews your contracts, flags compliance risks, drafts standard business agreements, maintains your policy library, and keeps your team updated on regulatory changes relevant to your industry.', '["Contract review & risk flagging","Standard agreement drafting (NDA, SLA, PO)","Compliance checklist automation","Regulatory update alerts (MY law)","Policy document generation","Risk scoring & recommendations"]', NULL, 0, 2000.00, 20000.00, 'monthly', 15),
(5, 'AI Decision Capsule', 'ai-decision-capsule', 'Let AI tell you what to do next — before your competitors figure it out', 'The most advanced Capsule in the BOS. Analyses all your business data to predict sales trends, identify churn risk, recommend pricing strategies, and deliver AI-generated weekly executive briefings you can act on immediately.', '["90-day sales forecasting","Customer churn prediction & prevention alerts","Pricing optimisation recommendations","Competitor & market benchmarking","Weekly AI executive briefing","Custom model training on your data"]', 'Premium', 1, 5000.00, 50000.00, 'monthly', 16);

-- AI Tool products (batch 2) — each standalone tool sold individually
INSERT IGNORE INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES
(1, 'Chatbot Flow Designer', 'chatbot-flow-designer', 'Design your WhatsApp or website chatbot flow with AI', 'Map out complete conversation flows for WhatsApp or web chat — welcome messages, menu trees, FAQ paths, escalation triggers, and closing messages — all generated by AI based on your business type.', '["Full conversation flow design","WhatsApp & website chat support","Multi-language flows (EN/BM)","Human escalation triggers","Out-of-hours messages","Implementation notes included"]', 'New', 0, 700.00, 7000.00, 'monthly', 17),
(6, 'WhatsApp Templates Capsule', 'whatsapp-templates-capsule', 'Broadcast messages and campaign templates optimised for WhatsApp', 'Generate high-converting WhatsApp broadcast messages for promotions, announcements, follow-ups, and re-engagement campaigns. Multiple variations per brief with best-practice tips included.', '["Promotional broadcast templates","Follow-up & re-engagement sequences","Multiple variations per campaign","Emoji-optimised formatting","EN & BM language support","Send-time best practice tips"]', 'New', 0, 500.00, 5000.00, 'monthly', 18),
(2, 'Cold Outreach Sequencer', 'cold-outreach-sequencer', 'Multi-touch cold email and LinkedIn sequences that get replies', 'Build complete cold outreach sequences — 3 to 5 touches across email or LinkedIn — that sound human, focus on value, and drive replies. Includes subject lines, body copy, and follow-up timing.', '["3–5 touch outreach sequences","Email & LinkedIn formats","Personalisation placeholders","Subject line variations","Follow-up timing guide","Industry-specific tone"]', NULL, 0, 600.00, 6000.00, 'monthly', 19),
(2, 'Pitch Deck Generator', 'pitch-deck-generator', 'Slide-by-slide pitch deck content that wins investors and clients', 'Generate compelling pitch deck content — all 12 slides — tailored to your business, market, and funding ask. From problem/solution to financials and the ask, fully written and ready to drop into PowerPoint or Canva.', '["All 12 standard pitch slides","Problem & solution narrative","Market size estimation","Business model & traction slides","Competitive differentiation","Financials & funding ask"]', NULL, 0, 400.00, 4000.00, 'monthly', 20),
(4, 'Financial Analysis AI', 'financial-analysis-ai', 'Plain-English analysis of your financials — red flags and recommendations', 'Paste your revenue, expenses, and profit numbers. Get a CFO-level financial health report with key metric analysis, red flags, strengths, top 3 actionable recommendations, and a cash flow outlook.', '["Revenue & margin analysis","Red flag identification","Top 3 recommendations","Cash flow outlook","Expense ratio breakdown","Period-over-period commentary"]', NULL, 0, 600.00, 6000.00, 'monthly', 21),
(5, 'Business Report Generator', 'business-report-generator', 'Professional monthly, quarterly, or annual business reports in minutes', 'Enter your key performance data, highlights, and challenges. Get a complete business report with executive summary, performance analysis, risk commentary, and next-step recommendations — ready to send to stakeholders.', '["Executive summary generation","Performance vs target commentary","Risk & challenge analysis","Recommendations & next steps","Formal report formatting","Monthly / quarterly / annual formats"]', NULL, 0, 500.00, 5000.00, 'monthly', 22),
(8, 'Contract Drafter', 'contract-drafter', 'Draft NDAs, service agreements, and employment contracts in minutes', 'Generate professional legal agreements based on your parties, scope, and key terms. Covers NDAs, service agreements, employment contracts, freelance agreements, partnership deeds, and more.', '["8 contract types supported","Full clause library included","Governing law (MY/SG/General)","Special clause support","Signature block placeholders","Legal disclaimer included"]', NULL, 0, 500.00, 5000.00, 'monthly', 23),
(3, 'Training Material Creator', 'training-material-creator', 'Build full training modules, onboarding guides, and quizzes with AI', 'Enter your course topic, audience, and key points. Get a complete training guide, slide deck outline, or workshop plan — plus an optional 10-question quiz with answers.', '["Training guide & slide outline","Workshop facilitation plans","Video script format option","10-question quiz generation","Learning objective alignment","Multiple duration formats"]', 'New', 0, 500.00, 5000.00, 'monthly', 24);

-- Default settings
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('site_name', 'AiServe'),
('site_tagline', 'The Business Operating System for SMEs'),
('stripe_mode', 'test'),
('currency', 'RM'),
('trial_days', '14'),
('contact_email', 'hello@aiserve.ai'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', '');

-- ============================================================
-- AI Modules Registry
-- Links /modules/*.php files to marketplace Capsule products
-- ============================================================

CREATE TABLE IF NOT EXISTS ai_modules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100)  NOT NULL,
    module_key   VARCHAR(50)   NOT NULL UNIQUE,
    slug         VARCHAR(100)  NOT NULL,
    category     VARCHAR(60)   NOT NULL DEFAULT 'General',
    description  TEXT,
    icon         VARCHAR(50)   DEFAULT 'bi-cpu',
    color        VARCHAR(20)   DEFAULT '#6366f1',
    tags         VARCHAR(200)  DEFAULT '',
    product_id   INT           NULL,
    is_active    TINYINT(1)    DEFAULT 1,
    sort_order   INT           DEFAULT 0,
    created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

INSERT IGNORE INTO ai_modules (name, module_key, slug, category, description, icon, color, tags, product_id, is_active, sort_order) VALUES
-- Communication
('Email Writer',         'email',             'email-writer',         'Communication',       'Write professional emails in seconds — sales, follow-ups, proposals.',                          'bi-envelope-paper',         '#6366f1', 'writing,communication', NULL, 1,  1),
('Social Post Generator','social',            'social-post',          'Communication',       'Platform-ready posts for Facebook, Instagram, LinkedIn & Twitter.',                             'bi-share',                  '#10b981', 'writing,marketing',     13,   1,  2),
('Customer Reply',       'customer_reply',    'customer-reply',       'Communication',       'Reply to complaints, enquiries, and reviews with confidence.',                                  'bi-chat-dots',              '#14b8a6', 'writing,communication', 1,    1,  3),
('Ad Copy Writer',       'ad_copy',           'ad-copy',              'Communication',       'High-converting ad copy for Google, Facebook, TikTok & LinkedIn.',                              'bi-megaphone',              '#ec4899', 'writing,marketing',     6,    1,  4),
-- Sales & Operations
('Sales Proposal',       'sales_proposal',    'sales-proposal',       'Sales & Operations',  'Draft a professional proposal that wins the deal.',                                             'bi-file-earmark-text',      '#f97316', 'writing,sales',         4,    1,  5),
('Invoice Generator',    'invoice',           'invoice-generator',    'Sales & Operations',  'Create professional invoices and print or save as PDF.',                                        'bi-receipt',                '#f59e0b', 'finance,sales',         10,   1,  6),
('Product Description',  'product_description','product-description', 'Sales & Operations',  'SEO-ready product copy for your website, Shopee, or Amazon.',                                  'bi-bag',                    '#6366f1', 'writing,marketing',     13,   1,  7),
('SOP Generator',        'sop',               'sop-generator',        'Sales & Operations',  'Turn rough process notes into a full Standard Operating Procedure.',                            'bi-list-ol',                '#3b82f6', 'hr,writing',            12,   1,  8),
-- HR & People
('Job Description',      'job_description',   'job-description',      'HR & People',         'Create compelling JDs that attract the right talent.',                                          'bi-person-badge',           '#06b6d4', 'hr,writing',            7,    1,  9),
('Leave Request',        'leave_reason',      'leave-request',        'HR & People',         'Submit leave requests with AI-drafted messages and track history.',                             'bi-calendar-check',         '#ef4444', 'hr',                    7,    1, 10),
('Performance Review',   'performance_review','performance-review',   'HR & People',         'Generate balanced, professional reviews for any staff member.',                                 'bi-star-half',              '#84cc16', 'hr,writing',            7,    1, 11),
('Meeting Minutes',      'meeting_minutes',   'meeting-minutes',      'HR & People',         'Transform rough notes into structured meeting minutes instantly.',                              'bi-journal-text',           '#8b5cf6', 'hr,writing',            7,    1, 12),
-- Strategy & Finance
('Financial Analysis',   'financial_analysis','financial-analysis',   'Strategy & Finance',  'Paste your numbers — get plain-English analysis, red flags, and recommendations.',             'bi-bar-chart-line',         '#10b981', 'finance',               21,   1, 13),
('Business Report',      'business_report',   'business-report',      'Strategy & Finance',  'Generate professional monthly, quarterly, or annual business reports.',                         'bi-file-earmark-bar-chart', '#3b82f6', 'finance,writing',       22,   1, 14),
('Pitch Deck Generator', 'pitch_deck',        'pitch-deck',           'Strategy & Finance',  'Slide-by-slide pitch deck content that wins investors and clients.',                            'bi-easel',                  '#f59e0b', 'sales,writing',         20,   1, 15),
('Cold Outreach',        'cold_outreach',     'cold-outreach',        'Strategy & Finance',  'Multi-touch cold email and LinkedIn sequences that get replies.',                               'bi-send',                   '#f97316', 'sales,writing',         19,   1, 16),
-- Automation & Systems
('WhatsApp Templates',   'whatsapp_templates','whatsapp-templates',   'Automation & Systems','Broadcast messages and campaign templates optimised for WhatsApp.',                             'bi-whatsapp',               '#25d366', 'marketing,communication',18,  1, 17),
('Chatbot Flow Designer','chatbot_flow',      'chatbot-flow',         'Automation & Systems','Design your WhatsApp or website chatbot conversation flow with AI.',                            'bi-robot',                  '#06b6d4', 'automation',            17,   1, 18),
('Contract Drafter',     'contract_drafter',  'contract-drafter',     'Automation & Systems','Draft NDAs, service agreements, and employment contracts in minutes.',                          'bi-file-earmark-lock',      '#14b8a6', 'legal',                 23,   1, 19),
('Training Creator',     'training_creator',  'training-creator',     'Automation & Systems','Build full training modules, onboarding guides, and quizzes with AI.',                          'bi-mortarboard',            '#8b5cf6', 'hr,writing',            24,   1, 20);

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
INSERT IGNORE INTO settings (`key`, `value`) VALUES
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

-- AI conversation memory (persists context per user per module)
CREATE TABLE IF NOT EXISTS ai_memory (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  module_key VARCHAR(100) NOT NULL,
  role       ENUM('user','assistant') NOT NULL,
  content    TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_module (user_id, module_key),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Referral Engine
CREATE TABLE IF NOT EXISTS referral_codes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL UNIQUE,
  code       VARCHAR(16) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS referrals (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id    INT NOT NULL,
  referred_id    INT DEFAULT NULL,
  referred_email VARCHAR(200) NOT NULL,
  status         ENUM('pending','converted','rewarded','expired') DEFAULT 'pending',
  reward_amount  DECIMAL(10,2) DEFAULT 0.00,
  notes          TEXT DEFAULT NULL,
  converted_at   DATETIME DEFAULT NULL,
  rewarded_at    DATETIME DEFAULT NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Membership & Tier System
CREATE TABLE IF NOT EXISTS membership_tiers (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(50) NOT NULL UNIQUE,
  name       VARCHAR(50) NOT NULL,
  min_points INT NOT NULL DEFAULT 0,
  color      VARCHAR(20) DEFAULT '#6b7280',
  icon       VARCHAR(50) DEFAULT 'bi-award',
  benefits   TEXT DEFAULT '[]',
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO membership_tiers (slug, name, min_points, color, icon, benefits, sort_order) VALUES
('starter',  'Starter',  0,     '#6b7280', 'bi-circle',      '["Access to all AI tools","14-day free trial"]', 0),
('bronze',   'Bronze',   500,   '#cd7f32', 'bi-award',       '["Priority email support","5% renewal discount","+5 AI tools daily cap"]', 1),
('silver',   'Silver',   2000,  '#94a3b8', 'bi-award-fill',  '["Priority support","10% renewal discount","Extended AI memory (10 exchanges)","Early access to new Capsules"]', 2),
('gold',     'Gold',     5000,  '#f59e0b', 'bi-trophy',      '["Dedicated support","15% renewal discount","Extended AI memory (15 exchanges)","Custom branding options","Quarterly strategy call"]', 3),
('platinum', 'Platinum', 10000, '#6366f1', 'bi-trophy-fill', '["Dedicated account manager","20% renewal discount","Unlimited AI memory","White-label options","Monthly strategy call"]', 4);

CREATE TABLE IF NOT EXISTS user_points (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  points      INT NOT NULL DEFAULT 0,
  type        ENUM('earn','redeem','adjust','expire') DEFAULT 'earn',
  source      VARCHAR(100) DEFAULT '',
  description VARCHAR(255) DEFAULT '',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Individual AI Tool Products — one per built module
-- ============================================================
INSERT IGNORE INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES

-- Content & Writing (category 6)
(6, 'Email Writer', 'email-writer',
    'Write professional emails in seconds',
    'Generate polished sales emails, follow-ups, proposals, apologies, and announcements in any tone and length. Just describe what you need — AI writes it instantly.',
    '["Sales, follow-up & proposal emails","Adjustable tone (professional/friendly/firm)","Short / medium / long length options","Subject line included","Multi-recipient formats","Unlimited generations"]',
    NULL, 0, 300.00, 3000.00, 'monthly', 25),

(6, 'Social Post Generator', 'social-post-generator',
    'Platform-ready posts for every channel',
    'Create engaging, platform-specific posts for Facebook, Instagram, LinkedIn, and Twitter from a single brief. Includes emojis, hashtags, and character-limit formatting per platform.',
    '["Facebook, Instagram, LinkedIn & Twitter","Emoji & hashtag optimisation","Adjustable tone and CTA","Up to 4 platforms per request","Brand voice consistency","Unlimited posts"]',
    NULL, 0, 300.00, 3000.00, 'monthly', 26),

(6, 'Product Description AI', 'product-description-ai',
    'SEO-ready copy for your products in seconds',
    'Generate compelling product descriptions for your website, Shopee, Lazada, or Amazon. Includes headline, short blurb, bullet benefits, full description, and SEO keywords.',
    '["Headline + short + long description","Bullet benefit points","5–8 SEO keywords included","Shopee / Lazada / website formats","Adjustable tone and length","Unlimited descriptions"]',
    NULL, 0, 300.00, 3000.00, 'monthly', 27),

-- Customer Service (category 1)
(1, 'Customer Reply AI', 'customer-reply-ai',
    'Reply to every customer message with confidence',
    'Paste any customer complaint, enquiry, or review and get a professional, empathetic reply in seconds. Handles complaints, returns, billing disputes, and general enquiries.',
    '["Complaint, enquiry & review replies","Empathetic & professional tone","Resolution statement included","Positive closing every time","EN & BM language support","Unlimited replies"]',
    NULL, 0, 400.00, 4000.00, 'monthly', 28),

-- Sales & Marketing (category 2)
(2, 'Ad Copy Writer', 'ad-copy-writer',
    'High-converting ad copy for every platform',
    'Generate platform-specific ad copy for Google, Facebook, Instagram, TikTok, and LinkedIn from a single product brief. Respects character limits and best practices per platform.',
    '["Google Search (headlines + descriptions)","Facebook / Instagram ads","TikTok video script hooks","LinkedIn professional ads","Adjustable objective & tone","Multiple ad variations"]',
    NULL, 0, 400.00, 4000.00, 'monthly', 29),

(2, 'Sales Proposal AI', 'sales-proposal-ai',
    'Win more deals with AI-written proposals',
    'Enter your client details, their pain points, your solution, and pricing. Get a complete, persuasive sales proposal with executive summary, solution outline, timeline, investment, and next steps.',
    '["Full 8-section proposal structure","Client pain-point framing","Solution & timeline narrative","Investment & ROI section","Why choose us positioning","Consultative or direct tone"]',
    NULL, 0, 400.00, 4000.00, 'monthly', 30),

-- Finance & Accounting (category 4)
(4, 'Invoice Generator', 'invoice-generator-tool',
    'Create professional invoices instantly',
    'Generate print-ready HTML invoices with your branding, line items, subtotals, tax, and payment terms. Download or share via link — no accounting software needed.',
    '["Professional HTML invoice layout","Unlimited line items","Subtotal, tax & total auto-calc","Custom payment terms & notes","Multi-currency support","Print & PDF-ready output"]',
    NULL, 0, 300.00, 3000.00, 'monthly', 31),

-- Operations (category 5)
(5, 'SOP Generator', 'sop-generator-tool',
    'Turn rough process notes into a formal SOP',
    'Describe any business process and get a complete, formatted Standard Operating Procedure with purpose, scope, responsibilities, numbered steps, quality checks, and exceptions.',
    '["Full 7-section SOP structure","Department & role assignments","Tools & resources section","Step-by-step procedure detail","Quality checkpoints included","Version control fields"]',
    NULL, 0, 400.00, 4000.00, 'monthly', 32),

-- HR & Recruitment (category 3)
(3, 'Job Description Writer', 'job-description-writer',
    'Write job descriptions that attract the right candidates',
    'Enter the job title, department, responsibilities, and requirements. Get a complete, compelling job description with role overview, key responsibilities, must-have requirements, nice-to-haves, and how to apply.',
    '["Full structured JD in minutes","Key responsibilities section","Must-have vs nice-to-have split","Salary range & perks section","Company culture pitch","EN & BM formats"]',
    NULL, 0, 300.00, 3000.00, 'monthly', 33),

(3, 'Leave Request Manager', 'leave-request-manager',
    'Professional leave request messages in one click',
    'Submit leave requests with a polished, professionally worded message generated by AI. Simply select leave type, dates, and provide a brief reason — the AI writes the rest.',
    '["Annual, sick, unpaid & parental leave","Professional & empathetic wording","Configurable leave duration","Auto-formatted dates","EN & BM language support","Instant generation"]',
    NULL, 0, 200.00, 2000.00, 'monthly', 34),

(3, 'Performance Review AI', 'performance-review-ai',
    'Balanced, constructive performance reviews in minutes',
    'Enter the employee name, role, achievements, areas for improvement, and goals. Get a complete, professional performance review with summary, strengths, development areas, and next-period goals.',
    '["Full review structure (5 sections)","Achievements & strengths narrative","Constructive development feedback","Next-period goal alignment","Annual, mid-year & probation formats","Manager commentary section"]',
    NULL, 0, 300.00, 3000.00, 'monthly', 35),

(3, 'Meeting Minutes AI', 'meeting-minutes-ai',
    'Transform rough notes into structured meeting minutes',
    'Paste your raw meeting notes and get properly formatted minutes with attendees, agenda, discussion points, decisions made, action items with owners, and next meeting details.',
    '["Attendees & agenda formatting","Discussion & decision capture","Action items with owner & deadline","Next meeting section","EN & BM language support","Formal or casual style"]',
    NULL, 0, 200.00, 2000.00, 'monthly', 36);

-- Sync ai_modules → individual tool products by slug
UPDATE ai_modules am JOIN products p ON p.slug = 'email-writer'          SET am.product_id = p.id WHERE am.module_key = 'email';
UPDATE ai_modules am JOIN products p ON p.slug = 'social-post-generator'  SET am.product_id = p.id WHERE am.module_key = 'social';
UPDATE ai_modules am JOIN products p ON p.slug = 'product-description-ai' SET am.product_id = p.id WHERE am.module_key = 'product_description';
UPDATE ai_modules am JOIN products p ON p.slug = 'customer-reply-ai'      SET am.product_id = p.id WHERE am.module_key = 'customer_reply';
UPDATE ai_modules am JOIN products p ON p.slug = 'ad-copy-writer'         SET am.product_id = p.id WHERE am.module_key = 'ad_copy';
UPDATE ai_modules am JOIN products p ON p.slug = 'sales-proposal-ai'      SET am.product_id = p.id WHERE am.module_key = 'sales_proposal';
UPDATE ai_modules am JOIN products p ON p.slug = 'invoice-generator-tool'  SET am.product_id = p.id WHERE am.module_key = 'invoice';
UPDATE ai_modules am JOIN products p ON p.slug = 'sop-generator-tool'      SET am.product_id = p.id WHERE am.module_key = 'sop';
UPDATE ai_modules am JOIN products p ON p.slug = 'job-description-writer'  SET am.product_id = p.id WHERE am.module_key = 'job_description';
UPDATE ai_modules am JOIN products p ON p.slug = 'leave-request-manager'   SET am.product_id = p.id WHERE am.module_key = 'leave_reason';
UPDATE ai_modules am JOIN products p ON p.slug = 'performance-review-ai'   SET am.product_id = p.id WHERE am.module_key = 'performance_review';
UPDATE ai_modules am JOIN products p ON p.slug = 'meeting-minutes-ai'      SET am.product_id = p.id WHERE am.module_key = 'meeting_minutes';
-- ProjectOS — AI Project Management & Execution Operating System
-- ============================================================

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) DEFAULT '',
    client VARCHAR(150) DEFAULT '',
    department VARCHAR(100) DEFAULT '',
    manager_id INT DEFAULT NULL,
    budget DECIMAL(12,2) DEFAULT 0.00,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('planning','active','on_hold','delayed','completed','cancelled') DEFAULT 'planning',
    completion_pct TINYINT DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('manager','member','client','viewer') DEFAULT 'member',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proj_user (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    type ENUM('kickoff','weekly','monthly','progress_review','client','uat','go_live','emergency') DEFAULT 'weekly',
    meeting_date DATE NOT NULL,
    meeting_time TIME DEFAULT NULL,
    venue VARCHAR(200) DEFAULT '',
    objective TEXT DEFAULT NULL,
    raw_notes TEXT DEFAULT NULL,
    ai_minutes LONGTEXT DEFAULT NULL,
    minutes_approved TINYINT(1) DEFAULT 0,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS meeting_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    attended TINYINT(1) DEFAULT 1,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    meeting_id INT DEFAULT NULL,
    decision_ref VARCHAR(20) DEFAULT '',
    summary TEXT NOT NULL,
    made_by VARCHAR(150) DEFAULT '',
    decided_at DATE DEFAULT NULL,
    impact ENUM('low','medium','high') DEFAULT 'medium',
    status ENUM('active','replaced','cancelled') DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS action_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    meeting_id INT DEFAULT NULL,
    task_ref VARCHAR(20) DEFAULT '',
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    owner_id INT DEFAULT NULL,
    owner_name VARCHAR(150) DEFAULT '',
    due_date DATE DEFAULT NULL,
    priority ENUM('low','medium','high','critical') DEFAULT 'medium',
    status ENUM('pending','assigned','in_progress','waiting','completed','cancelled') DEFAULT 'pending',
    completed_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    issue_ref VARCHAR(20) DEFAULT '',
    category VARCHAR(100) DEFAULT '',
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    root_cause TEXT DEFAULT NULL,
    owner_id INT DEFAULT NULL,
    owner_name VARCHAR(150) DEFAULT '',
    status ENUM('open','assigned','in_progress','solved','verified','closed','reopened') DEFAULT 'open',
    resolved_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS issue_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    author_name VARCHAR(150) DEFAULT '',
    comment TEXT NOT NULL,
    status_change VARCHAR(50) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS project_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    completed_date DATE DEFAULT NULL,
    status ENUM('upcoming','active','delayed','completed') DEFAULT 'upcoming',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    project_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    entity_type VARCHAR(50) DEFAULT '',
    entity_id INT DEFAULT NULL,
    action VARCHAR(100) DEFAULT '',
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proj (project_id),
    INDEX idx_ent (entity_type, entity_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS knowledge_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    type ENUM('minutes','decision','sop','lesson','technical','requirement','other') DEFAULT 'other',
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    tags VARCHAR(200) DEFAULT '',
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ProjectOS marketplace product
INSERT IGNORE INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES
(5, 'ProjectOS™', 'project-os',
    'Turn Meetings Into Execution.',
    'ProjectOS is an AI-powered execution operating system that transforms meetings, decisions, issues, and tasks into measurable business outcomes. Every discussion leads to action. Every action has accountability. Every issue has traceable resolution history.',
    '["AI Meeting Minutes Engine (10-section international standard)","Decision Center — no decision ever forgotten","Action Center with priority & owner tracking","Issue Center with severity & root cause tracking","Rollback Engine — full project memory & history","AI Health Score (0–100) per project","AI Project Assistant (copilot)","Milestone & Timeline tracking","Client Portal — share progress without calls","Knowledge Base for SOPs, lessons learned & requirements"]',
    'New', 1, 2000.00, 20000.00, 'monthly', 37);

INSERT IGNORE INTO ai_modules (name, module_key, slug, category, description, icon, color, tags, is_active, sort_order) VALUES
('ProjectOS™', 'project_os', 'project-os', 'Automation & Systems', 'AI-powered project execution OS — meetings, decisions, tasks, issues, milestones, and project memory in one place.', 'bi-kanban', '#8b5cf6', 'project,management,ai', 1, 21);

-- Sync ProjectOS ai_module product_id
UPDATE ai_modules am JOIN products p ON p.slug = 'project-os' SET am.product_id = p.id WHERE am.module_key = 'project_os';

-- ============================================================
-- Blog System
-- ============================================================

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(255),
    author_id INT DEFAULT NULL,
    category VARCHAR(100) DEFAULT 'General',
    tags VARCHAR(200),
    status ENUM('draft','published') DEFAULT 'draft',
    meta_title VARCHAR(255),
    meta_description VARCHAR(300),
    views INT DEFAULT 0,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published (published_at)
);

-- Seed blog posts (6 posts targeting Malaysian SME AI keywords)
INSERT IGNORE INTO blog_posts (title, slug, excerpt, content, category, tags, meta_title, meta_description, status, published_at, author_id) VALUES

(
  '5 Ways Malaysian SMEs Can Use AI to Win More Customers in 2026',
  '5-ways-malaysian-smes-use-ai-win-customers-2026',
  'Malaysian SMEs are discovering that AI is no longer reserved for large corporations. Here are five practical ways to deploy AI today and win more customers.',
  '<p>The competitive landscape for Malaysian SMEs has changed dramatically. Customers expect instant responses, personalised experiences, and seamless service — 24 hours a day, seven days a week. The good news? AI makes all of this achievable even for small and medium businesses with limited budgets and teams.</p>

<p>Here are five practical ways Malaysian SMEs are using AI right now to win more customers and grow faster.</p>

<h2>1. Automate Customer Enquiries on WhatsApp</h2>
<p>WhatsApp is the primary communication channel for most Malaysian consumers. An AI-powered WhatsApp inbox can handle hundreds of simultaneous enquiries, answer FAQs in Bahasa Malaysia and English, qualify leads, and route complex issues to a human agent — all without any staff involved.</p>
<p>Businesses using WhatsApp AI automation report a 70–80% reduction in response time and significantly higher customer satisfaction scores. The key is deploying an AI that understands your products, prices, and policies — not a generic chatbot.</p>

<h2>2. Use AI to Follow Up on Quotes Automatically</h2>
<p>One of the biggest revenue leaks for Malaysian SMEs is failing to follow up on quotes. A salesperson sends a quote and then gets busy. The prospect goes cold. Revenue is lost.</p>
<p>AI Sales Capsules can automatically follow up at the right intervals with personalised messages, track whether the prospect opened the quote, and even suggest a closing script based on previous interactions. Businesses using AI follow-up sequences report 2–3x improvement in quote-to-close rates.</p>

<h2>3. Deploy AI Customer Service to Handle Post-Sale Support</h2>
<p>Post-sale support is expensive and time-consuming. Returns, delivery tracking, complaints, and warranty queries can overwhelm a small team. An AI Customer Service Capsule can handle all of these automatically, escalating only the truly complex cases to humans.</p>
<p>This is especially valuable for e-commerce SMEs and retail businesses that experience high volumes of repetitive enquiries. With AI handling Tier-1 support, your team can focus on building relationships and closing new deals.</p>

<h2>4. Personalise Marketing with AI Segmentation</h2>
<p>Sending the same promotion to your entire customer list is inefficient. AI-powered marketing automation can segment your database by purchase behaviour, engagement level, and demographics — then send the right message to the right customer at the right time.</p>
<p>Malaysian F&B businesses using AI marketing automation report 40–60% higher open rates and significantly better ROI on promotional campaigns compared to bulk broadcasts.</p>

<h2>5. Generate Daily Business Reports Automatically</h2>
<p>Most SME owners spend hours every week compiling reports manually. AI reporting tools can pull data from your CRM, inventory system, and sales records to generate a daily summary automatically — complete with anomaly detection that flags unusual patterns before they become problems.</p>
<p>Imagine waking up every morning to a clear, AI-generated summary of yesterday''s performance, with specific recommendations for action. That''s the power of an AI Business Operating System.</p>

<h2>Getting Started</h2>
<p>The easiest way to start is with a single AI Capsule that solves your biggest immediate pain point. For most Malaysian SMEs, that is either customer service automation or sales follow-up. Once you see the ROI from one Capsule, expanding to others becomes a natural next step.</p>
<p>AiServe offers a 14-day free trial on all Capsules. Deploy your first AI Capsule today and see the difference within your first week.</p>',
  'Sales',
  'AI for business Malaysia, SME customer service, AI sales automation, WhatsApp AI, Malaysian SME',
  '5 Ways Malaysian SMEs Can Use AI to Win More Customers in 2026 | AiServe',
  'Discover 5 practical AI strategies Malaysian SMEs are using right now to win more customers, automate follow-ups, and grow faster in 2026.',
  'published',
  '2026-05-15 09:00:00',
  NULL
),

(
  'WhatsApp AI Automation: The Complete Guide for Malaysian Businesses',
  'whatsapp-ai-automation-complete-guide-malaysian-businesses',
  'WhatsApp is used by 90%+ of Malaysians. Learn how to deploy AI automation on WhatsApp to handle customer enquiries, generate leads, and close sales — 24/7.',
  '<p>With over 90% of Malaysians actively using WhatsApp, it has become the most important business communication channel in the country. Yet most SMEs still handle WhatsApp manually — one message at a time, during business hours only, with no system to track conversations or follow up on leads.</p>

<p>This guide explains exactly how WhatsApp AI automation works, what it can and cannot do, and how to implement it in your Malaysian business.</p>

<h2>What is WhatsApp AI Automation?</h2>
<p>WhatsApp AI automation uses artificial intelligence to handle customer conversations on WhatsApp automatically. Unlike simple chatbots that follow a rigid decision tree, modern AI systems understand natural language in both English and Bahasa Malaysia, can access your product database in real time, and learn from every conversation to improve over time.</p>

<h2>Key Capabilities for Malaysian Businesses</h2>
<p><strong>Multi-language support:</strong> Handle enquiries in English, Bahasa Malaysia, and even Mandarin without hiring additional staff.</p>
<p><strong>24/7 availability:</strong> Never miss a customer enquiry again, even during public holidays (and Malaysia has many).</p>
<p><strong>Lead qualification:</strong> Automatically identify hot leads and escalate them to your sales team with a full conversation history.</p>
<p><strong>Order tracking and support:</strong> Integrate with your order management system to give customers real-time updates without any human involvement.</p>
<p><strong>Appointment scheduling:</strong> For clinics, salons, and service businesses, AI can handle the entire booking process including reminders.</p>

<h2>Use Cases by Industry</h2>
<p><strong>F&B:</strong> Handle table reservations, delivery orders, menu enquiries, and promotional announcements. One Kuala Lumpur restaurant reduced their front-of-house phone calls by 80% after deploying WhatsApp AI.</p>
<p><strong>Property:</strong> Qualify property enquiries, schedule viewings, and send follow-up information packages automatically.</p>
<p><strong>Retail:</strong> Answer product questions, check stock availability, process returns, and send order confirmations.</p>
<p><strong>Healthcare:</strong> Handle appointment bookings, send medication reminders, answer general health FAQs, and manage patient follow-ups.</p>

<h2>Implementation Steps</h2>
<p>Implementing WhatsApp AI for your business involves four key steps: connecting your WhatsApp Business account, training the AI on your product and service information, setting escalation rules for complex issues, and monitoring performance through a dashboard.</p>
<p>With AiServe, the entire setup takes less than 48 hours. Our team handles the technical integration while you focus on providing the business knowledge the AI needs to serve your customers.</p>

<h2>What to Expect: Real Numbers</h2>
<p>Based on deployments across Malaysian SMEs, businesses typically see a 70% reduction in response time, 3x improvement in lead capture rate, 40% reduction in support costs, and 85%+ customer satisfaction scores within the first 30 days.</p>

<h2>Getting Started</h2>
<p>The best way to experience WhatsApp AI automation is to try it yourself. AiServe''s Customer Service Capsule includes full WhatsApp integration as standard. Start your 14-day free trial today.</p>',
  'Customer Service',
  'WhatsApp AI Malaysia, WhatsApp automation, chatbot Malaysia, WhatsApp business automation',
  'WhatsApp AI Automation: Complete Guide for Malaysian Businesses | AiServe',
  'Learn how to deploy WhatsApp AI automation for your Malaysian business. Handle enquiries, qualify leads, and close sales 24/7 — complete step-by-step guide.',
  'published',
  '2026-05-20 09:00:00',
  NULL
),

(
  'How to Cut HR Admin Time by 80% with AI Capsules',
  'cut-hr-admin-time-80-percent-ai-capsules',
  'HR administration consumes enormous time in Malaysian SMEs. AI Capsules can automate leave management, payroll queries, onboarding, and staff FAQs — freeing your HR team to focus on people, not paperwork.',
  '<p>For most Malaysian SMEs, the HR function is a constant battle against paperwork. Leave applications submitted via WhatsApp message, payroll queries handled manually, onboarding checklists sent by email, and staff policy questions answered one by one. Sound familiar?</p>

<p>AI HR automation is changing this fundamentally. Here''s how Malaysian businesses are cutting HR admin time by up to 80% using AI Capsules.</p>

<h2>The Hidden Cost of Manual HR Administration</h2>
<p>A Malaysian SME with 50 employees typically spends 15–25 hours per week on routine HR administration. That''s the equivalent of one full-time employee doing nothing but processing leave forms, answering payroll questions, and managing onboarding paperwork. At an average HR executive salary of RM4,000–6,000 per month, the direct cost is significant — but the opportunity cost is even higher.</p>

<h2>Leave Management Automation</h2>
<p>Leave management is one of the most common HR pain points. Staff submit requests via WhatsApp, email, or paper forms. The HR team checks the leave balance, gets manager approval, updates the system, and notifies the employee — a process that takes 15–30 minutes per request.</p>
<p>An AI Leave Management system automates the entire workflow. Staff submit leave via a simple chatbot interface. The AI checks balances in real time, routes the request for manager approval, updates the system on approval, and notifies everyone involved — all without HR intervention.</p>

<h2>AI Payroll Query Resolution</h2>
<p>The most common HR question in any organisation is "why is my salary different this month?" Answering payroll queries manually requires the HR executive to pull up the payslip, cross-reference deductions, and explain each line item. With AI, staff can query their own payslip anytime, and the AI explains every deduction, overtime calculation, and allowance in plain language.</p>

<h2>Automated Onboarding</h2>
<p>Getting a new employee productive takes weeks of manual coordination — forms to fill, equipment to set up, policies to sign off, and introductions to make. AI onboarding systems automate the entire checklist, send reminders automatically, track completion, and flag anything that is overdue.</p>
<p>Companies using AI onboarding report that new employees reach full productivity 30–40% faster.</p>

<h2>Staff FAQ Automation</h2>
<p>HR teams answer the same questions repeatedly: What is the medical leave entitlement? How do I claim expenses? What is the overtime policy? An AI HR assistant can answer all of these questions instantly, 24/7, in English or Bahasa Malaysia.</p>

<h2>Implementation Guide</h2>
<p>AiServe''s HR & Admin Capsule integrates with your existing HR system and can be deployed in under a week. The AI is pre-trained on Malaysian HR law and common HR policies, then customised for your specific company policies and procedures.</p>
<p>Start with leave management automation — it delivers the fastest and most visible ROI — then expand to payroll queries and onboarding over time.</p>',
  'HR & Operations',
  'HR automation Malaysia, leave management AI, payroll automation, HR AI, SME HR system',
  'How to Cut HR Admin Time by 80% with AI Capsules | AiServe',
  'Discover how Malaysian SMEs are automating leave management, payroll queries, and staff onboarding with AI Capsules — cutting HR admin time by up to 80%.',
  'published',
  '2026-05-25 09:00:00',
  NULL
),

(
  'AI Financial Reporting for SMEs: From Spreadsheets to Daily Insights',
  'ai-financial-reporting-sme-spreadsheets-daily-insights',
  'Most Malaysian SME owners review their financials monthly — if at all. AI financial reporting gives you daily visibility into cash flow, revenue trends, and anomalies before they become problems.',
  '<p>The majority of Malaysian SME owners check their financial numbers once a month, usually when the accountant sends a report. By the time you see a problem in a monthly report, you are already 30 days behind. AI financial reporting changes this completely.</p>

<h2>The Problem with Monthly Financial Reports</h2>
<p>Traditional monthly financial reports have three critical weaknesses for SMEs: they are backward-looking, they arrive too late for meaningful action, and they require an accountant or finance manager to interpret them. In a fast-moving business environment, discovering that last month was bad does nothing to help you fix this month.</p>

<h2>What AI Financial Reporting Looks Like</h2>
<p>Imagine starting every morning with a clear, plain-language summary of your business''s financial health: yesterday''s revenue, today''s expected cash position, overdue invoices that need chasing, and any unusual spending patterns that warrant attention. This is what AI Daily Reporting provides.</p>
<p>The AI connects to your accounting system, point-of-sale, and bank feeds to create a complete picture of your financial position — updated automatically every day without any manual work.</p>

<h2>Key Features for Malaysian SMEs</h2>
<p><strong>Daily P&L snapshot:</strong> Know your gross margin, operating costs, and net position every single day.</p>
<p><strong>Cash flow forecasting:</strong> See your expected cash position 30, 60, and 90 days ahead based on confirmed orders, outstanding invoices, and recurring commitments.</p>
<p><strong>Anomaly detection:</strong> AI flags unusual expenses, unexpected revenue drops, or payment patterns that deviate from your baseline — before they become crises.</p>
<p><strong>Debtor ageing alerts:</strong> Automatic alerts when invoices are approaching or exceeding their due date, with suggested follow-up actions.</p>
<p><strong>GST/SST compliance tracking:</strong> Stay on top of your Malaysian tax obligations with automated tracking of taxable transactions.</p>

<h2>Real Impact for Malaysian Businesses</h2>
<p>A Petaling Jaya-based retail SME implemented AI financial reporting and discovered, within the first week, that three product categories had been operating at a loss for months due to incorrect cost pricing. The AI flagged the anomaly — something the monthly report had obscured in the aggregate numbers.</p>
<p>A Penang F&B group used AI cash flow forecasting to predict a cash shortfall 45 days in advance, giving them time to arrange a credit facility before it became a crisis.</p>

<h2>Integration with Your Existing Systems</h2>
<p>AiServe''s Daily Reporting Capsule integrates with Xero, QuickBooks, SQL Account, and Autocount — the most commonly used accounting systems in Malaysia. Setup typically takes one to two days and requires no changes to your existing accounting workflow.</p>

<h2>From Data to Decisions</h2>
<p>The ultimate goal of AI financial reporting is not just to show you numbers — it is to help you make better decisions faster. With daily financial insights, you can identify problems early, capitalise on opportunities, and run your business with the confidence that comes from knowing your numbers in real time.</p>',
  'Finance',
  'financial reporting Malaysia, AI accounting, cash flow forecasting, SME finance, daily business reports',
  'AI Financial Reporting for Malaysian SMEs: From Spreadsheets to Daily Insights | AiServe',
  'Stop reviewing finances monthly. AI financial reporting gives Malaysian SME owners daily cash flow visibility, anomaly alerts, and forward-looking insights.',
  'published',
  '2026-05-28 09:00:00',
  NULL
),

(
  'Why Your SME Needs an AI Business Operating System, Not Just a Chatbot',
  'sme-needs-ai-business-operating-system-not-chatbot',
  'Chatbots answer questions. An AI Business Operating System runs your entire business. Understand the critical difference and why it matters for Malaysian SMEs in 2026.',
  '<p>The word "chatbot" has become almost synonymous with AI for business. But there is a fundamental difference between a chatbot — which answers questions — and an AI Business Operating System (BOS), which runs your entire business. Understanding this difference is critical for Malaysian SME owners who want to get real value from AI investment.</p>

<h2>What a Chatbot Can Do</h2>
<p>A chatbot is a conversational interface that responds to specific inputs with pre-programmed or AI-generated outputs. Modern chatbots are genuinely useful for answering FAQs, handling basic customer service queries, and providing instant responses outside business hours.</p>
<p>But a chatbot is a single point solution. It answers questions. It does not manage your business, track your performance, automate your workflows, or make your operations more efficient across departments.</p>

<h2>What an AI Business Operating System Does</h2>
<p>An AI BOS is an integrated platform that connects every function of your business and applies AI intelligence across all of them simultaneously. It is not a single tool — it is an operating system that your entire business runs on.</p>
<p>Consider the difference: a chatbot answers a customer''s delivery enquiry. An AI BOS answers the enquiry, updates the CRM, triggers a follow-up sequence if the customer showed interest in a related product, flags the delivery delay to your operations team, and includes the interaction data in your daily performance report.</p>

<h2>The Five Layers of an AI BOS</h2>
<p><strong>Communication Layer:</strong> Centralises all customer communication — WhatsApp, email, social media — into one AI-managed inbox with conversation memory and sentiment analysis.</p>
<p><strong>Operations Layer:</strong> Automates internal workflows — HR approvals, purchase order generation, inventory alerts, payroll processing — without manual intervention.</p>
<p><strong>Data Layer:</strong> Captures structured data from every customer interaction and business activity, building a proprietary dataset that becomes increasingly valuable over time.</p>
<p><strong>Intelligence Layer:</strong> Analyses patterns across all data sources to identify opportunities, predict problems, and recommend specific actions.</p>
<p><strong>Reporting Layer:</strong> Delivers daily, real-time insights to business owners and managers in plain language — not raw data requiring interpretation.</p>

<h2>Why This Matters for Malaysian SMEs</h2>
<p>Malaysian SMEs face a specific set of challenges: high staff turnover, multi-language customer communication, complex compliance requirements (GST/SST, EPF, SOCSO), and intense competition from larger businesses with more resources.</p>
<p>An AI BOS levels the playing field. With the right system, a 10-person Malaysian SME can deliver customer service, operations efficiency, and business intelligence that rivals companies 10 times its size.</p>

<h2>The Capsule Approach: Start Small, Scale Fast</h2>
<p>AiServe''s Capsule model makes AI BOS accessible without overwhelming upfront investment. Start with the Capsule that solves your biggest immediate pain point — usually Customer Service or Sales Automation — and expand as you see results.</p>
<p>Each Capsule is plug-and-play, industry-specific, and designed to deliver ROI within 30 days. As you add Capsules, they connect together into a complete BOS that runs your entire business automatically.</p>
<p>That is the vision. And for Malaysian SMEs ready to compete in 2026, it starts with a single step.</p>',
  'AI Strategy',
  'AI business operating system, BOS Malaysia, SME AI strategy, business automation Malaysia, AI Capsules',
  'Why Your SME Needs an AI Business Operating System, Not Just a Chatbot | AiServe',
  'Chatbots answer questions. An AI Business Operating System runs your entire business. Discover the critical difference and how AiServe''s Capsule model helps Malaysian SMEs compete.',
  'published',
  '2026-06-01 09:00:00',
  NULL
),

(
  'ProjectOS: How AI-Powered Project Management Changes Execution for SMEs',
  'projectos-ai-powered-project-management-sme-execution',
  'Most project management tools track tasks. ProjectOS tracks execution — with AI meeting minutes, a Decision Center, an Issue Center, and a project memory that never forgets.',
  '<p>Malaysian SMEs lose an estimated 20–30% of project value to poor execution: missed deadlines, forgotten decisions, unclear accountability, and issues that resurface because their root causes were never properly resolved. Project management tools help. But most tools only track tasks — they do not actually improve execution.</p>
<p>ProjectOS takes a fundamentally different approach. Built on the insight that execution fails at the meeting-to-action transition, ProjectOS uses AI to ensure that every discussion becomes a documented decision, every decision triggers an action, and every action has a clear owner and deadline.</p>

<h2>The Meeting-to-Action Problem</h2>
<p>Think about your last important meeting. Decisions were made. Actions were assigned. Everyone left with good intentions. A week later, half the actions had not been started, two decisions had been forgotten, and the same issues were being discussed again.</p>
<p>This is not a people problem — it is a systems problem. Without a reliable system to capture what was decided, who is responsible, and when it is due, execution depends entirely on individual memory and discipline. ProjectOS solves this at the system level.</p>

<h2>AI Meeting Minutes Engine</h2>
<p>ProjectOS''s AI Meeting Minutes Engine generates comprehensive meeting documentation automatically — a 10-section international standard format that captures decisions, actions, issues, risks, and key discussion points. What takes a human 45 minutes to write from notes is produced in under 2 minutes.</p>
<p>Every set of minutes is stored permanently in the project memory, searchable, and linked to the specific decisions and actions that resulted from that meeting.</p>

<h2>Decision Center: No Decision Ever Forgotten</h2>
<p>Every important decision made about a project — whether in a meeting, a WhatsApp message, or an email — is captured in the Decision Center with its rationale, the date it was made, and who made it.</p>
<p>When team members ask "why did we decide to do it this way?" the answer is always available. When a client disputes a decision made six months ago, the documentation is there. This single feature eliminates one of the most common causes of project conflict in Malaysian SMEs.</p>

<h2>Issue Center: Structured Problem Resolution</h2>
<p>Issues are inevitable in any project. What matters is how they are managed. The Issue Center in ProjectOS captures every issue with its severity, root cause, assigned owner, resolution steps, and outcome.</p>
<p>The Rollback Engine keeps a complete history of every change to the project — so if a decision turns out to be wrong, you can see exactly what was changed, when, and why, and restore the previous state.</p>

<h2>AI Health Score</h2>
<p>Every project in ProjectOS has an AI Health Score from 0 to 100, calculated daily based on action completion rate, overdue items, decision velocity, issue resolution speed, and milestone progress. A declining health score is an early warning system — it alerts project managers before the project goes off the rails, not after.</p>

<h2>The Result: Measurable Execution</h2>
<p>SMEs using ProjectOS report a 40% reduction in overdue actions, 60% faster issue resolution, and a significant improvement in client confidence thanks to the Client Portal that provides transparent, real-time project progress without requiring a single status call.</p>
<p>If your business runs projects — whether for clients, internal initiatives, or product launches — ProjectOS is the execution layer that turns good intentions into measurable outcomes.</p>',
  'Project Management',
  'project management AI, ProjectOS Malaysia, AI meeting minutes, SME project management, execution system',
  'ProjectOS: How AI-Powered Project Management Changes Execution for Malaysian SMEs | AiServe',
  'Discover how ProjectOS uses AI meeting minutes, a Decision Center, Issue Center, and project memory to eliminate execution failures in Malaysian SME projects.',
  'published',
  '2026-06-02 09:00:00',
  NULL
);

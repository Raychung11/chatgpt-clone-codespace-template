-- ============================================================
-- AiServe Marketplace Update — BOS Capsule Product Lineup
-- Run this entire script in Hostinger phpMyAdmin
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Update categories ─────────────────────────────────────────

UPDATE categories SET
    name        = 'Customer Service',
    description = 'AI Capsules that handle WhatsApp queries, FAQ automation, and customer engagement 24/7',
    icon        = 'bi-headset',
    color       = '#6366f1'
WHERE slug = 'customer-service';

UPDATE categories SET
    name        = 'Sales & Marketing',
    description = 'Capsules that automate follow-ups, lead nurturing, and marketing campaigns',
    icon        = 'bi-graph-up-arrow',
    color       = '#f59e0b'
WHERE slug = 'sales-marketing';

UPDATE categories SET
    name        = 'HR & Recruitment',
    description = 'Automate leave management, payroll queries, candidate screening, and onboarding',
    icon        = 'bi-people',
    color       = '#10b981'
WHERE slug = 'hr-recruitment';

UPDATE categories SET
    name        = 'Finance & Accounting',
    description = 'Smart Capsules for invoicing, collections, and automated financial reporting',
    icon        = 'bi-calculator',
    color       = '#3b82f6'
WHERE slug = 'finance-accounting';

UPDATE categories SET
    name        = 'Operations',
    description = 'Daily reporting, SOP management, and operational workflow automation',
    icon        = 'bi-gear',
    color       = '#8b5cf6'
WHERE slug = 'operations';

UPDATE categories SET
    name        = 'Content & Writing',
    description = 'AI content creation for blogs, social media, WhatsApp broadcasts, and email',
    icon        = 'bi-pencil-square',
    color       = '#ec4899'
WHERE slug = 'content-writing';

UPDATE categories SET
    name        = 'E-Commerce',
    description = 'AI tools for online stores — product Q&A, cart recovery, and order support',
    icon        = 'bi-bag',
    color       = '#f97316'
WHERE slug = 'ecommerce';

UPDATE categories SET
    name        = 'Legal & Compliance',
    description = 'Contract review, compliance checklists, and regulatory update automation',
    icon        = 'bi-shield-check',
    color       = '#14b8a6'
WHERE slug = 'legal-compliance';

-- ── Clear old products ────────────────────────────────────────

DELETE FROM products;
ALTER TABLE products AUTO_INCREMENT = 1;

-- ── Insert AiServe BOS Capsule lineup ────────────────────────

INSERT INTO products
  (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order)
VALUES

-- ── Customer Service ─────────────────────────────────────────
(1,
 'WhatsApp CS Capsule',
 'whatsapp-cs-capsule',
 'Handle every customer query via WhatsApp, 24/7',
 'Deploy an AI customer service agent directly inside WhatsApp. Handles FAQs, processes orders, escalates to human agents, and learns from every conversation — so your team can focus on high-value work.',
 '["24/7 WhatsApp automation","Multi-language support (EN/MY/ZH)","Smart human handoff with context","CRM contact sync","Sentiment detection & alerts","Monthly performance report"]',
 'Popular', 1, 1500.00, 15000.00, 'monthly', 1),

(1,
 'FAQ Automation Capsule',
 'faq-automation-capsule',
 'Instantly answer your top 100 FAQs — without lifting a finger',
 'Train the AI on your business FAQs, pricing, policies, and products. Deploy across WhatsApp, website chat, or email. Handles repetitive queries automatically so your team only deals with complex issues.',
 '["Custom FAQ training (up to 200 Q&As)","Multi-channel deployment","Auto-suggest top answers","Learns from team corrections","Unanswered query alerts","Analytics dashboard"]',
 'New', 0, 800.00, 8000.00, 'monthly', 2),

(1,
 'Live Chat Capsule',
 'live-chat-capsule',
 'Convert website visitors into customers while you sleep',
 'An AI-powered live chat agent for your website that qualifies leads, answers product questions, books appointments, and captures contact details — automatically, around the clock.',
 '["Instant visitor response (<3 seconds)","Lead capture & qualification","Appointment / demo booking","Product recommendation engine","Customisable chat widget","Real-time analytics"]',
 NULL, 0, 1200.00, 12000.00, 'monthly', 3),

-- ── Sales & Marketing ─────────────────────────────────────────
(2,
 'Sales Conversion Capsule',
 'sales-conversion-capsule',
 'Turn leads into customers with AI-powered follow-up',
 'Never lose a lead again. This Capsule automatically follows up with prospects via WhatsApp and email, sends AI-generated quotes, handles objections, and nudges deals to close — without your sales team lifting a finger.',
 '["Auto follow-up sequences (Day 1/3/7/14)","AI quote & proposal generation","Objection handling scripts","Deal stage tracking","Upsell & cross-sell triggers","Weekly sales performance report"]',
 'Hot', 1, 3000.00, 30000.00, 'monthly', 4),

(2,
 'Lead Generation Capsule',
 'lead-generation-capsule',
 'Find, qualify, and nurture leads entirely on autopilot',
 'AI researches your ideal customers, scores inbound enquiries by purchase intent, and routes hot leads to your sales team instantly — complete with background research and a recommended opening script.',
 '["Inbound lead scoring & prioritisation","Prospect research & profiling","Auto-qualification conversation flow","Hot lead instant alerts","CRM pipeline integration","Weekly pipeline report"]',
 'Popular', 1, 2000.00, 20000.00, 'monthly', 5),

(2,
 'Marketing Automation Capsule',
 'marketing-automation-capsule',
 'Run campaigns that feel personal — at massive scale',
 'Segment your customer base, schedule WhatsApp and email broadcasts, and trigger personalised campaigns based on customer behaviour. Set up once and let the Capsule manage your entire marketing calendar.',
 '["WhatsApp broadcast automation","Dynamic customer segmentation","Behaviour-triggered campaigns","A/B campaign testing","Open rate & click analytics","Monthly ROI report"]',
 NULL, 1, 2500.00, 25000.00, 'monthly', 6),

-- ── HR & Recruitment ──────────────────────────────────────────
(3,
 'HR Admin Capsule',
 'hr-admin-capsule',
 'Handle HR queries and leave requests without an HR team',
 'Employees ask questions — the Capsule answers. Leave applications, payroll queries, company policies, and onboarding steps are fully automated, freeing your HR team to focus on people strategy.',
 '["Leave request automation & approval flow","Payroll query handling","Company policy search (AI-powered)","New hire onboarding checklists","HR analytics dashboard","Multi-department support"]',
 NULL, 0, 1500.00, 15000.00, 'monthly', 7),

(3,
 'Recruitment Capsule',
 'recruitment-capsule',
 'Screen 10x more candidates in half the time',
 'Post a job, receive applications, and let AI screen resumes, score candidates against your job criteria, and schedule interviews automatically — delivering a ranked shortlist without reading 200 CVs.',
 '["Resume screening & AI scoring","Automated interview scheduling","Candidate ranking & comparison","Job posting templates (BM/EN)","ATS-ready data export","Time-to-hire analytics"]',
 'New', 0, 2000.00, 20000.00, 'monthly', 8),

-- ── Finance & Accounting ──────────────────────────────────────
(4,
 'Finance Reporting Capsule',
 'finance-reporting-capsule',
 'Your P&L, cash flow, and KPIs delivered every morning',
 'Connect your accounting data and receive automated daily, weekly, and monthly financial reports straight to WhatsApp or email. AI highlights anomalies, flags risks, and summarises performance for non-finance managers.',
 '["Daily P&L snapshots","7-day cash flow forecast","Anomaly & variance alerts","KPI dashboard (revenue, margin, burn)","WhatsApp report delivery","Multi-branch consolidation"]',
 NULL, 0, 1800.00, 18000.00, 'monthly', 9),

(4,
 'Invoice & Collections Capsule',
 'invoice-collections-capsule',
 'Get paid faster with AI-powered invoice automation',
 'Generate professional invoices automatically, send polite payment reminders via WhatsApp, follow up on overdue accounts with escalating sequences, and reconcile payments without touching a spreadsheet.',
 '["Auto invoice generation","WhatsApp payment reminders (Day 1/7/14/30)","Overdue account follow-up","Payment reconciliation","Aged debtor report","Multi-currency support"]',
 NULL, 0, 900.00, 9000.00, 'monthly', 10),

-- ── Operations ────────────────────────────────────────────────
(5,
 'Daily Reporting Capsule',
 'daily-reporting-capsule',
 'Business performance delivered to your phone every morning',
 'Connect your sales, inventory, and finance data. Every morning, receive a plain-language business summary via WhatsApp — sales vs target, stock levels, cash position, and what needs your attention today.',
 '["Daily WhatsApp performance summary","Sales vs target tracking","Low stock & reorder alerts","Cash position snapshot","AI anomaly detection & explanation","Fully customisable report schedule"]',
 NULL, 1, 2000.00, 20000.00, 'monthly', 11),

(5,
 'SOP Management Capsule',
 'sop-management-capsule',
 'Ensure every team member follows the right process, every time',
 'Store all your Standard Operating Procedures in one AI-searchable library. Staff ask via WhatsApp and get the right steps instantly. Managers get notified when procedures are skipped or SOPs are out of date.',
 '["AI-searchable SOP library","WhatsApp process lookup for staff","SOP version control & history","Compliance reminder notifications","Built-in staff training quizzes","Usage & completion analytics"]',
 NULL, 0, 1200.00, 12000.00, 'monthly', 12),

-- ── Content & Writing ─────────────────────────────────────────
(6,
 'Content Creation Capsule',
 'content-creation-capsule',
 'On-brand content for every channel — ready in seconds',
 'Train the AI on your brand voice, products, and audience once. Then generate blog posts, WhatsApp broadcast messages, social captions, product descriptions, and email newsletters on demand — every piece consistent with your brand.',
 '["Blog post generation (SEO-optimised)","WhatsApp broadcast copy","Social media captions (FB/IG/TikTok)","Email newsletter drafts","Brand voice training","Unlimited content requests"]',
 NULL, 0, 1000.00, 10000.00, 'monthly', 13),

-- ── E-Commerce ────────────────────────────────────────────────
(7,
 'E-Commerce Capsule',
 'ecommerce-capsule',
 'AI that handles your online store — from enquiry to repeat purchase',
 'Your AI-powered e-commerce assistant answers product questions, recommends items based on browsing behaviour, tracks order status, manages returns, and recovers abandoned carts — across WhatsApp, web chat, and social DMs.',
 '["Product Q&A automation","Personalised product recommendations","Cart abandonment recovery","Order status self-service","Returns & exchange handling","Shopee / Lazada / WooCommerce integration"]',
 NULL, 0, 2500.00, 25000.00, 'monthly', 14),

-- ── Legal & Compliance ────────────────────────────────────────
(8,
 'Compliance Capsule',
 'compliance-capsule',
 'Stay compliant and legally protected — without a full-time lawyer',
 'AI reviews your contracts, flags compliance risks, drafts standard business agreements, maintains your policy library, and keeps your team updated on regulatory changes relevant to your industry and jurisdiction.',
 '["Contract review & risk flagging","Standard agreement drafting (NDA, SLA, PO)","Compliance checklist automation","Regulatory update alerts (MY law)","Policy document generation","Risk scoring & recommendations"]',
 NULL, 0, 2000.00, 20000.00, 'monthly', 15),

-- ── Premium ───────────────────────────────────────────────────
(5,
 'AI Decision Capsule',
 'ai-decision-capsule',
 'Let AI tell you what to do next — before your competitors figure it out',
 'The most advanced Capsule in the BOS platform. Analyses all your business data to predict sales trends, identify churn risk, recommend pricing strategies, and deliver AI-generated weekly executive briefings you can act on immediately.',
 '["90-day sales forecasting","Customer churn prediction & prevention alerts","Pricing optimisation recommendations","Competitor & market benchmarking","Weekly AI executive briefing","Custom model training on your data"]',
 'Premium', 1, 5000.00, 50000.00, 'monthly', 16);

-- ── Update settings ───────────────────────────────────────────

INSERT INTO settings (`key`, `value`) VALUES ('currency', 'RM')
ON DUPLICATE KEY UPDATE `value` = 'RM';

INSERT INTO settings (`key`, `value`) VALUES ('site_name', 'AiServe')
ON DUPLICATE KEY UPDATE `value` = 'AiServe';

INSERT INTO settings (`key`, `value`) VALUES ('site_tagline', 'The Business Operating System for SMEs')
ON DUPLICATE KEY UPDATE `value` = 'The Business Operating System for SMEs';

INSERT INTO settings (`key`, `value`) VALUES ('contact_email', 'hello@aiserve.ai')
ON DUPLICATE KEY UPDATE `value` = 'hello@aiserve.ai';

SET FOREIGN_KEY_CHECKS = 1;

-- Done! 16 AiServe BOS Capsules across 8 categories are now live.
-- Verify at: /marketplace.php

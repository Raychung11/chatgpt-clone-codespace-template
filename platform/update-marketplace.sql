-- ============================================================
--  AiServe BOS — Marketplace Update Script
--  Run this entire file in Hostinger phpMyAdmin → SQL tab
--  Last updated: 2026-05-02
-- ============================================================
--
--  CAPSULE PRICING SUMMARY
--  ──────────────────────────────────────────────
--  Customer Service (3 capsules):  RM800 – RM1,500/mo
--  Sales & Marketing (3 capsules): RM2,000 – RM3,000/mo
--  HR & Recruitment (2 capsules):  RM1,500 – RM2,000/mo
--  Finance & Accounting (2 caps):  RM900 – RM1,800/mo
--  Operations (2 capsules):        RM1,200 – RM2,000/mo
--  Content & Writing (1 capsule):  RM1,000/mo
--  E-Commerce (1 capsule):         RM2,500/mo
--  Legal & Compliance (1 capsule): RM2,000/mo
--  AI Decision (1 capsule):        RM5,000/mo  ← Premium
--
--  PLAN BUNDLES (see pricing.php)
--  Starter:    RM3,500/mo  (BOS Core + Customer Service)
--  Growth:     RM10,000/mo (BOS Core + CS + Sales + Marketing)
--  Enterprise: RM22,500/mo (All Capsules + AI Decision)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Update category names, icons, and colours ─────────────

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

-- ── 2. Replace old products with AiServe BOS Capsules ────────

DELETE FROM subscriptions;
DELETE FROM purchases;
DELETE FROM products;
ALTER TABLE products AUTO_INCREMENT = 1;

-- ── CUSTOMER SERVICE CAPSULES ─────────────────────────────────

INSERT INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES

(1,
 'WhatsApp CS Capsule',
 'whatsapp-cs-capsule',
 'Handle every customer query via WhatsApp, 24/7',
 'Deploy an AI customer service agent directly inside WhatsApp. Handles FAQs, processes orders, escalates to human agents, and learns from every conversation — so your team can focus on high-value work.',
 '["24/7 WhatsApp automation","Multi-language support (EN / BM / ZH)","Smart human handoff with full context","CRM contact sync","Sentiment detection & escalation alerts","Monthly performance report"]',
 'Popular', 1, 1500.00, 15000.00, 'monthly', 1),

(1,
 'FAQ Automation Capsule',
 'faq-automation-capsule',
 'Instantly answer your top 200 FAQs — without lifting a finger',
 'Train the AI on your business FAQs, pricing, policies, and products. Deploy across WhatsApp, website chat, or email. Handles repetitive queries automatically so your team only deals with complex issues.',
 '["Custom FAQ training (up to 200 Q&As)","Multi-channel deployment (WhatsApp, web, email)","Auto-suggest top answers","Learns from team corrections","Unanswered query alerts","Analytics dashboard"]',
 'New', 0, 800.00, 8000.00, 'monthly', 2),

(1,
 'Live Chat Capsule',
 'live-chat-capsule',
 'Convert website visitors into customers while you sleep',
 'An AI-powered live chat agent for your website that qualifies leads, answers product questions, books appointments, and captures contact details — automatically, around the clock.',
 '["Instant visitor response (under 3 seconds)","Lead capture & qualification flow","Appointment / demo booking","Product recommendation engine","Customisable chat widget","Real-time visitor analytics"]',
 NULL, 0, 1200.00, 12000.00, 'monthly', 3),

-- ── SALES & MARKETING CAPSULES ────────────────────────────────

(2,
 'Sales Conversion Capsule',
 'sales-conversion-capsule',
 'Turn leads into customers with AI-powered follow-up',
 'Never lose a lead again. This Capsule automatically follows up with prospects via WhatsApp and email, sends AI-generated quotes, handles objections, and nudges deals to close — without your sales team lifting a finger.',
 '["Auto follow-up sequences (Day 1 / 3 / 7 / 14)","AI quote & proposal generation","Objection handling scripts","Deal stage tracking in CRM","Upsell & cross-sell triggers","Weekly sales performance report"]',
 'Hot', 1, 3000.00, 30000.00, 'monthly', 4),

(2,
 'Lead Generation Capsule',
 'lead-generation-capsule',
 'Find, qualify, and nurture leads entirely on autopilot',
 'AI scores inbound enquiries by purchase intent and routes hot leads to your sales team instantly — complete with background research and a recommended opening script.',
 '["Inbound lead scoring & prioritisation","Prospect research & profiling","Auto-qualification conversation flow","Hot lead instant alerts to WhatsApp","CRM pipeline integration","Weekly pipeline report"]',
 'Popular', 1, 2000.00, 20000.00, 'monthly', 5),

(2,
 'Marketing Automation Capsule',
 'marketing-automation-capsule',
 'Run campaigns that feel personal — at massive scale',
 'Segment your customer base, schedule WhatsApp and email broadcasts, and trigger personalised campaigns based on customer behaviour. Set up once and let the Capsule run your entire marketing calendar.',
 '["WhatsApp broadcast automation","Dynamic customer segmentation","Behaviour-triggered campaigns","A/B campaign testing","Open rate & click-through analytics","Monthly campaign ROI report"]',
 NULL, 1, 2500.00, 25000.00, 'monthly', 6),

-- ── HR & RECRUITMENT CAPSULES ─────────────────────────────────

(3,
 'HR Admin Capsule',
 'hr-admin-capsule',
 'Handle HR queries and leave requests without an HR team',
 'Employees ask questions — the Capsule answers. Leave applications, payroll queries, company policies, and onboarding steps are fully automated, freeing your HR team to focus on people strategy.',
 '["Leave request automation & approval workflow","Payroll query self-service","Company policy AI search","New hire onboarding checklists","HR analytics dashboard","Multi-department support"]',
 NULL, 0, 1500.00, 15000.00, 'monthly', 7),

(3,
 'Recruitment Capsule',
 'recruitment-capsule',
 'Screen 10x more candidates in half the time',
 'Post a job, receive applications, and let AI screen resumes, score candidates against your criteria, and schedule interviews automatically — delivering a ranked shortlist without reading 200 CVs.',
 '["Resume screening & AI candidate scoring","Automated interview scheduling","Candidate ranking & side-by-side comparison","Job posting templates (BM / EN)","ATS-ready data export","Time-to-hire analytics"]',
 'New', 0, 2000.00, 20000.00, 'monthly', 8),

-- ── FINANCE & ACCOUNTING CAPSULES ────────────────────────────

(4,
 'Finance Reporting Capsule',
 'finance-reporting-capsule',
 'Your P&L, cash flow, and KPIs delivered every morning',
 'Connect your accounting data and receive automated daily, weekly, and monthly financial reports straight to WhatsApp or email. AI highlights anomalies, flags risks, and summarises performance in plain English.',
 '["Daily P&L snapshots via WhatsApp","7-day cash flow forecast","Anomaly & variance alerts","KPI dashboard (revenue, margin, burn rate)","Multi-branch data consolidation","Monthly management report"]',
 NULL, 0, 1800.00, 18000.00, 'monthly', 9),

(4,
 'Invoice & Collections Capsule',
 'invoice-collections-capsule',
 'Get paid faster with AI-powered invoice automation',
 'Generate professional invoices automatically, send polite payment reminders via WhatsApp, follow up on overdue accounts with escalating sequences, and reconcile payments without a spreadsheet.',
 '["Auto invoice generation from orders","WhatsApp payment reminders (Day 1 / 7 / 14 / 30)","Overdue account follow-up sequences","Payment reconciliation automation","Aged debtor report","Multi-currency support (RM / SGD / USD)"]',
 NULL, 0, 900.00, 9000.00, 'monthly', 10),

-- ── OPERATIONS CAPSULES ───────────────────────────────────────

(5,
 'Daily Reporting Capsule',
 'daily-reporting-capsule',
 'Business performance delivered to your phone every morning',
 'Connect your sales, inventory, and finance data. Every morning, receive a plain-language business summary via WhatsApp — sales vs target, stock levels, cash position, and what needs your attention today.',
 '["Daily WhatsApp business summary","Sales vs target tracking","Low stock & reorder alerts","Cash position snapshot","AI anomaly detection & plain-English explanation","Fully customisable report schedule & format"]',
 NULL, 1, 2000.00, 20000.00, 'monthly', 11),

(5,
 'SOP Management Capsule',
 'sop-management-capsule',
 'Ensure every team member follows the right process, every time',
 'Store all your Standard Operating Procedures in one AI-searchable library. Staff ask via WhatsApp and get the right steps instantly. Managers are notified when SOPs are skipped or out of date.',
 '["AI-searchable SOP library (unlimited docs)","WhatsApp process lookup for staff","SOP version control & change history","Compliance reminder notifications","Built-in staff training quizzes","Usage & completion rate analytics"]',
 NULL, 0, 1200.00, 12000.00, 'monthly', 12),

-- ── CONTENT & WRITING CAPSULE ─────────────────────────────────

(6,
 'Content Creation Capsule',
 'content-creation-capsule',
 'On-brand content for every channel — ready in seconds',
 'Train the AI on your brand voice, products, and audience. Then generate blog posts, WhatsApp broadcasts, social captions, product descriptions, and email newsletters on demand — every piece consistent with your brand.',
 '["Blog post generation (SEO-optimised)","WhatsApp broadcast copy","Social media captions (FB / IG / TikTok / LinkedIn)","Email newsletter drafts","Brand voice & tone training","Unlimited content requests"]',
 NULL, 0, 1000.00, 10000.00, 'monthly', 13),

-- ── E-COMMERCE CAPSULE ────────────────────────────────────────

(7,
 'E-Commerce Capsule',
 'ecommerce-capsule',
 'AI that handles your online store — from enquiry to repeat purchase',
 'Your AI-powered e-commerce assistant answers product questions, recommends items based on browsing behaviour, tracks order status, manages returns, and recovers abandoned carts — across WhatsApp, web chat, and social DMs.',
 '["Product Q&A automation","Personalised product recommendations","Cart abandonment recovery (WhatsApp + email)","Order status self-service","Returns & exchange handling","Shopee / Lazada / WooCommerce integration"]',
 NULL, 0, 2500.00, 25000.00, 'monthly', 14),

-- ── LEGAL & COMPLIANCE CAPSULE ────────────────────────────────

(8,
 'Compliance Capsule',
 'compliance-capsule',
 'Stay compliant and legally protected — without a full-time lawyer',
 'AI reviews your contracts, flags compliance risks, drafts standard business agreements, maintains your policy library, and keeps your team updated on regulatory changes relevant to your industry and jurisdiction.',
 '["Contract review & risk flagging","Standard agreement drafting (NDA, SLA, service agreements)","Compliance checklist automation","Regulatory update alerts (Malaysian law)","Policy document generation","Risk scoring & prioritised recommendations"]',
 NULL, 0, 2000.00, 20000.00, 'monthly', 15),

-- ── AI DECISION CAPSULE (PREMIUM) ────────────────────────────

(5,
 'AI Decision Capsule',
 'ai-decision-capsule',
 'Let AI tell you what to do next — before your competitors figure it out',
 'The most advanced Capsule in the BOS platform. Analyses all your business data to predict sales trends, identify churn risk, recommend pricing strategies, and deliver AI-generated weekly executive briefings you can act on immediately.',
 '["90-day sales forecasting (with confidence intervals)","Customer churn prediction & prevention alerts","Pricing optimisation recommendations","Competitor & market benchmarking","Weekly AI executive briefing via WhatsApp","Custom model training on your business data"]',
 'Premium', 1, 5000.00, 50000.00, 'monthly', 16);

-- ── AI TOOL PRODUCTS (Batch 2) — 8 new standalone tools ──────
-- Each maps to a /modules/*.php file; product_id used in ai_modules table

INSERT INTO products (category_id, name, slug, tagline, description, features, badge, is_featured, price_monthly, price_yearly, pricing_model, sort_order) VALUES
(1, 'Chatbot Flow Designer',    'chatbot-flow-designer',    'Design your WhatsApp or website chatbot flow with AI',                    'Map out complete conversation flows for WhatsApp or web chat — welcome messages, menus, FAQ paths, escalation triggers, and out-of-hours replies — all generated by AI.', '["Full conversation flow design","WhatsApp & website chat support","Multi-language flows (EN/BM)","Human escalation triggers","Out-of-hours messages","Implementation notes included"]',                                                                  'New', 0, 700.00,  7000.00,  'monthly', 17),
(6, 'WhatsApp Templates Capsule','whatsapp-templates-capsule','Broadcast messages and campaign templates optimised for WhatsApp',       'Generate high-converting WhatsApp broadcast messages for promotions, announcements, follow-ups, and re-engagement campaigns. Multiple variations per brief with best-practice tips.', '["Promotional broadcast templates","Follow-up & re-engagement sequences","Multiple variations per campaign","Emoji-optimised formatting","EN & BM language support","Send-time best practice tips"]',                                                          'New', 0, 500.00,  5000.00,  'monthly', 18),
(2, 'Cold Outreach Sequencer',  'cold-outreach-sequencer',  'Multi-touch cold email and LinkedIn sequences that get replies',          'Build complete 3–5 touch outreach sequences that sound human, focus on value, and drive replies. Includes subject lines, body copy, and follow-up timing guidance.', '["3–5 touch outreach sequences","Email & LinkedIn formats","Personalisation placeholders","Subject line variations","Follow-up timing guide","Industry-specific tone"]',                                                                                     NULL,  0, 600.00,  6000.00,  'monthly', 19),
(2, 'Pitch Deck Generator',     'pitch-deck-generator',     'Slide-by-slide pitch deck content that wins investors and clients',       'Generate compelling pitch deck content — all 12 standard slides — tailored to your business, market, and funding ask. Ready to drop into PowerPoint or Canva.', '["All 12 standard pitch slides","Problem & solution narrative","Market size estimation","Business model & traction slides","Competitive differentiation","Financials & funding ask"]',                                                                       NULL,  0, 400.00,  4000.00,  'monthly', 20),
(4, 'Financial Analysis AI',    'financial-analysis-ai',    'Plain-English analysis of your financials — red flags and recommendations','Paste your revenue, expenses, and profit numbers. Get a CFO-level financial health report with key metric analysis, red flags, strengths, top 3 recommendations, and a cash flow outlook.', '["Revenue & margin analysis","Red flag identification","Top 3 recommendations","Cash flow outlook","Expense ratio breakdown","Period-over-period commentary"]',                                                                                           NULL,  0, 600.00,  6000.00,  'monthly', 21),
(5, 'Business Report Generator','business-report-generator','Professional monthly, quarterly, or annual business reports in minutes', 'Enter your key performance data, highlights, and challenges. Get a complete business report with executive summary, performance analysis, risk commentary, and next-step recommendations.', '["Executive summary generation","Performance vs target commentary","Risk & challenge analysis","Recommendations & next steps","Formal report formatting","Monthly / quarterly / annual formats"]',                                                             NULL,  0, 500.00,  5000.00,  'monthly', 22),
(8, 'Contract Drafter',         'contract-drafter',         'Draft NDAs, service agreements, and employment contracts in minutes',     'Generate professional legal agreements based on your parties, scope, and key terms. Covers NDAs, service agreements, employment contracts, freelance agreements, partnerships, and more.', '["8 contract types supported","Full clause library included","Governing law (MY / SG / General)","Special clause support","Signature block placeholders","Legal disclaimer included"]',                                                                  NULL,  0, 500.00,  5000.00,  'monthly', 23),
(3, 'Training Material Creator','training-material-creator','Build full training modules, onboarding guides, and quizzes with AI',    'Enter your course topic, audience, and key content points. Get a complete training guide, slide outline, or workshop plan — plus an optional 10-question quiz with answers.', '["Training guide & slide outline","Workshop facilitation plans","Video script format option","10-question quiz generation","Learning objective alignment","Multiple duration formats"]',                                                                      'New', 0, 500.00,  5000.00,  'monthly', 24);

-- ── 3. Set up AI Modules registry (links modules to products) ─

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

-- Clear any existing module entries and re-seed
DELETE FROM ai_modules;
ALTER TABLE ai_modules AUTO_INCREMENT = 1;

INSERT INTO ai_modules (name, module_key, slug, category, description, icon, color, tags, product_id, is_active, sort_order) VALUES
('Email Writer',         'email',             'email-writer',         'Communication',       'Write professional emails in seconds — sales, follow-ups, proposals.',                         'bi-envelope-paper',         '#6366f1', 'writing,communication',  NULL, 1,  1),
('Social Post Generator','social',            'social-post',          'Communication',       'Platform-ready posts for Facebook, Instagram, LinkedIn & Twitter.',                            'bi-share',                  '#10b981', 'writing,marketing',      13,   1,  2),
('Customer Reply',       'customer_reply',    'customer-reply',       'Communication',       'Reply to complaints, enquiries, and reviews with confidence.',                                 'bi-chat-dots',              '#14b8a6', 'writing,communication',  1,    1,  3),
('Ad Copy Writer',       'ad_copy',           'ad-copy',              'Communication',       'High-converting ad copy for Google, Facebook, TikTok & LinkedIn.',                             'bi-megaphone',              '#ec4899', 'writing,marketing',      6,    1,  4),
('Sales Proposal',       'sales_proposal',    'sales-proposal',       'Sales & Operations',  'Draft a professional proposal that wins the deal.',                                            'bi-file-earmark-text',      '#f97316', 'writing,sales',          4,    1,  5),
('Invoice Generator',    'invoice',           'invoice-generator',    'Sales & Operations',  'Create professional invoices and print or save as PDF.',                                       'bi-receipt',                '#f59e0b', 'finance,sales',          10,   1,  6),
('Product Description',  'product_description','product-description', 'Sales & Operations',  'SEO-ready product copy for your website, Shopee, or Amazon.',                                 'bi-bag',                    '#6366f1', 'writing,marketing',      13,   1,  7),
('SOP Generator',        'sop',               'sop-generator',        'Sales & Operations',  'Turn rough process notes into a full Standard Operating Procedure.',                           'bi-list-ol',                '#3b82f6', 'hr,writing',             12,   1,  8),
('Job Description',      'job_description',   'job-description',      'HR & People',         'Create compelling JDs that attract the right talent.',                                         'bi-person-badge',           '#06b6d4', 'hr,writing',             7,    1,  9),
('Leave Request',        'leave_reason',      'leave-request',        'HR & People',         'Submit leave requests with AI-drafted messages and track history.',                            'bi-calendar-check',         '#ef4444', 'hr',                     7,    1, 10),
('Performance Review',   'performance_review','performance-review',   'HR & People',         'Generate balanced, professional reviews for any staff member.',                                'bi-star-half',              '#84cc16', 'hr,writing',             7,    1, 11),
('Meeting Minutes',      'meeting_minutes',   'meeting-minutes',      'HR & People',         'Transform rough notes into structured meeting minutes instantly.',                             'bi-journal-text',           '#8b5cf6', 'hr,writing',             7,    1, 12),
('Financial Analysis',   'financial_analysis','financial-analysis',   'Strategy & Finance',  'Paste your numbers — get plain-English analysis, red flags, and recommendations.',            'bi-bar-chart-line',         '#10b981', 'finance',                21,   1, 13),
('Business Report',      'business_report',   'business-report',      'Strategy & Finance',  'Generate professional monthly, quarterly, or annual business reports.',                        'bi-file-earmark-bar-chart', '#3b82f6', 'finance,writing',        22,   1, 14),
('Pitch Deck Generator', 'pitch_deck',        'pitch-deck',           'Strategy & Finance',  'Slide-by-slide pitch deck content that wins investors and clients.',                           'bi-easel',                  '#f59e0b', 'sales,writing',          20,   1, 15),
('Cold Outreach',        'cold_outreach',     'cold-outreach',        'Strategy & Finance',  'Multi-touch cold email and LinkedIn sequences that get replies.',                              'bi-send',                   '#f97316', 'sales,writing',          19,   1, 16),
('WhatsApp Templates',   'whatsapp_templates','whatsapp-templates',   'Automation & Systems','Broadcast messages and campaign templates optimised for WhatsApp.',                            'bi-whatsapp',               '#25d366', 'marketing,communication', 18,  1, 17),
('Chatbot Flow Designer','chatbot_flow',      'chatbot-flow',         'Automation & Systems','Design your WhatsApp or website chatbot conversation flow with AI.',                           'bi-robot',                  '#06b6d4', 'automation',             17,   1, 18),
('Contract Drafter',     'contract_drafter',  'contract-drafter',     'Automation & Systems','Draft NDAs, service agreements, and employment contracts in minutes.',                         'bi-file-earmark-lock',      '#14b8a6', 'legal',                  23,   1, 19),
('Training Creator',     'training_creator',  'training-creator',     'Automation & Systems','Build full training modules, onboarding guides, and quizzes with AI.',                         'bi-mortarboard',            '#8b5cf6', 'hr,writing',             24,   1, 20);

-- ── 4. Update platform settings ──────────────────────────────

INSERT INTO settings (`key`, `value`) VALUES ('currency', 'RM')
ON DUPLICATE KEY UPDATE `value` = 'RM';

INSERT INTO settings (`key`, `value`) VALUES ('site_name', 'AiServe')
ON DUPLICATE KEY UPDATE `value` = 'AiServe';

INSERT INTO settings (`key`, `value`) VALUES ('site_tagline', 'The Business Operating System for SMEs')
ON DUPLICATE KEY UPDATE `value` = 'The Business Operating System for SMEs';

INSERT INTO settings (`key`, `value`) VALUES ('contact_email', 'hello@aiserve.ai')
ON DUPLICATE KEY UPDATE `value` = 'hello@aiserve.ai';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  Done! 16 AiServe BOS Capsules across 8 categories.
--
--  VERIFY AT:
--    /marketplace.php                  ← customer-facing store
--    /admin/products.php               ← admin product list
--    /admin/pricing.php                ← admin price manager
-- ============================================================

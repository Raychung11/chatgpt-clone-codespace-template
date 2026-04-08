-- ============================================================
-- PlotGold Malaysia - Seed Data
-- Version: 1.0.0
-- ============================================================

USE plotgold;

-- Roles
INSERT INTO roles (name, label, description) VALUES
('guest',                'Guest',                'Unauthenticated visitor'),
('buyer',               'Buyer',                'Registered buyer / planner'),
('seller',              'Seller',               'Plot / niche seller'),
('provider',            'Service Provider',     'Funeral service provider'),
('support_officer',     'Support Officer',      'Customer support team member'),
('verification_officer','Verification Officer', 'Handles listing verification'),
('admin',               'Administrator',        'Platform administrator'),
('super_admin',         'Super Admin',          'Full platform access');

-- Religion Categories
INSERT INTO religion_categories (name, slug, label_en, label_zh, sort_order) VALUES
('buddhist',      'buddhist',         'Buddhist',         '佛教',    1),
('taoist',        'taoist',           'Taoist',           '道教',    2),
('christian',     'christian',        'Christian',        '基督教',  3),
('catholic',      'catholic',         'Catholic',         '天主教',  4),
('muslim',        'muslim',           'Muslim / Islamic', '伊斯兰',  5),
('hindu',         'hindu',            'Hindu',            '兴都教',  6),
('non_religious', 'non-religious',    'Non-Religious',    '非宗教',  7),
('multi_faith',   'multi-faith',      'Multi-Faith',      '多元信仰',8),
('other',         'other',            'Other',            '其他',    9);

-- Listing Types
INSERT INTO listing_types (name, slug, label_en, label_zh, sort_order) VALUES
('burial_plot',     'burial-plot',      'Burial Plot',          '墓地',       1),
('family_lot',      'family-lot',       'Family Lot',           '家庭墓地',   2),
('urn_burial',      'urn-burial',       'Urn Burial Plot',      '骨灰葬地',   3),
('columbarium',     'columbarium',      'Columbarium Niche',    '骨灰龛',     4),
('mausoleum_unit',  'mausoleum-unit',   'Mausoleum Unit',       '地上墓室',   5),
('garden_burial',   'garden-burial',    'Garden Burial',        '花园葬',     6),
('lawn_burial',     'lawn-burial',      'Lawn Burial',          '草坪葬',     7);

-- Service Categories
INSERT INTO service_categories (name, slug, label_en, label_zh, icon, sort_order) VALUES
('burial_plot',       'burial-plot',      'Burial Plot / Niche',      '墓地/骨灰龛',  'fa-mountain',      1),
('transport',         'transport',        'Transport / Hearse',        '运输/灵车',    'fa-car',           2),
('coffin_casket',     'coffin-casket',    'Coffin / Casket',           '棺材',         'fa-box',           3),
('wake_hall',         'wake-hall',        'Hall / Wake Service',       '灵堂服务',     'fa-building',      4),
('clergy_ritual',     'clergy-ritual',    'Clergy / Ritual Service',   '宗教/仪式',    'fa-pray',          5),
('flowers',           'flowers',          'Floral Arrangements',       '花卉',         'fa-seedling',      6),
('obituary',          'obituary',         'Obituary / Announcement',   '讣告',         'fa-newspaper',     7),
('paperwork',         'paperwork',        'Paperwork / Admin Support', '文件/行政',    'fa-file-alt',      8),
('catering',          'catering',         'Catering',                  '餐饮',         'fa-utensils',      9),
('photography',       'photography',      'Photography / Videography', '摄影/录影',    'fa-camera',       10),
('custom',            'custom',           'Custom Add-On',             '自定义项目',   'fa-plus-circle',  11);

-- Funeral Services (base catalog)
INSERT INTO funeral_services (category_id, name, slug, description, base_price, price_type, unit_label, is_recommended) VALUES
(1, 'Burial Plot (Standard)',       'burial-plot-standard',     'Standard ground burial plot at partner memorial parks', 8000.00, 'fixed', NULL, 1),
(1, 'Columbarium Niche (Standard)', 'columbarium-niche-std',    'Single columbarium niche unit',                        3500.00, 'fixed', NULL, 1),
(2, 'Hearse Service (Local)',       'hearse-local',             'Local hearse transportation within city',               500.00, 'fixed', NULL, 1),
(2, 'Hearse Service (Interstate)',  'hearse-interstate',        'Interstate hearse transportation',                     1200.00, 'fixed', NULL, 0),
(2, 'Ambulance / Mortuary Van',     'ambulance-van',            'Emergency body transfer service',                       350.00, 'fixed', NULL, 0),
(3, 'Economy Coffin',               'coffin-economy',           'Basic solid wood coffin',                              1500.00, 'fixed', NULL, 0),
(3, 'Standard Coffin',              'coffin-standard',          'Polished hardwood coffin',                             3000.00, 'fixed', NULL, 1),
(3, 'Premium Coffin',               'coffin-premium',           'Premium coffin with velvet interior',                  6000.00, 'fixed', NULL, 0),
(3, 'Cremation Casket',             'casket-cremation',         'Casket designed for cremation',                         800.00, 'fixed', NULL, 0),
(4, 'Wake Hall Rental (1 Day)',     'wake-hall-1d',             'Air-conditioned funeral hall rental for 1 day',        1200.00, 'per_day','day', 1),
(4, 'Home Wake Setup',              'wake-home-setup',          'Complete home wake decoration and setup',              2000.00, 'fixed', NULL, 0),
(4, 'Funeral Director Services',    'funeral-director',         'Professional funeral director full-day coordination',  1500.00, 'fixed', NULL, 1),
(5, 'Buddhist Monk Chanting',       'monk-chanting',            'Buddhist monk chanting session (per session)',          800.00, 'per_unit','session', 0),
(5, 'Taoist Ritual Ceremony',       'taoist-ceremony',          'Full Taoist funeral ritual ceremony',                  2500.00, 'fixed', NULL, 0),
(5, 'Christian Prayer Service',     'christian-service',        'Christian prayer and eulogy service',                   500.00, 'fixed', NULL, 0),
(6, 'Floral Wreath (Standard)',     'floral-wreath-std',        'Standard funeral floral wreath',                        150.00, 'per_unit','piece', 1),
(6, 'Table Flower Arrangement',     'floral-table',             'Table centrepiece floral arrangement',                  250.00, 'per_unit','piece', 0),
(6, 'Casket Flowers',               'casket-flowers',           'Full casket floral spray',                              600.00, 'fixed', NULL, 0),
(7, 'Newspaper Obituary Notice',    'obituary-newspaper',       'Full obituary notice in local newspaper',               400.00, 'fixed', NULL, 1),
(7, 'Online Memorial Page',         'obituary-online',          'Hosted online memorial page (1 year)',                  200.00, 'fixed', NULL, 0),
(8, 'Death Certificate Processing', 'paperwork-death-cert',     'Assistance with death certificate and burial permit',   350.00, 'fixed', NULL, 1),
(8, 'Estate Administration Support','paperwork-estate',         'Basic estate and probate guidance',                     500.00, 'fixed', NULL, 0);

-- Memorial Parks (Klang Valley / Selangor)
INSERT INTO memorial_parks (slug, name, name_zh, description, city, state, postcode, latitude, longitude, phone, supported_religions, is_active, is_featured, meta_title, meta_description) VALUES
('nirvana-memorial-park-semenyih',
 'Nirvana Memorial Park (Semenyih)',
 '天堂纪念公园 (双文丹)',
 'One of Malaysia''s premier memorial parks offering beautiful garden landscapes, multiple religion sections, and modern columbarium facilities.',
 'Semenyih', 'Selangor', '43500',
 2.9408, 101.8578,
 '+603-8724 2288',
 'buddhist,taoist,christian,non_religious',
 1, 1,
 'Nirvana Memorial Park Semenyih | Buy & Sell Burial Plots',
 'Browse verified resale listings for burial plots and columbarium niches at Nirvana Memorial Park Semenyih.'),

('nilai-memorial-park',
 'Nilai Memorial Park',
 '宁宜纪念公园',
 'A serene memorial park in Nilai offering Chinese-tradition burial plots and modern columbarium niches.',
 'Nilai', 'Negeri Sembilan', '71800',
 2.8285, 101.7979,
 '+606-799 1234',
 'buddhist,taoist,christian',
 1, 1,
 'Nilai Memorial Park | Burial Plot Resale Listings',
 'Find verified resale burial plots and niches at Nilai Memorial Park. Compare prices and contact verified sellers.'),

('cheras-batu-11-cemetery',
 'Cheras Batu 11 Chinese Cemetery',
 '茨厂街华人义山',
 'Established Chinese cemetery in Cheras with freehold plots and various sections.',
 'Cheras', 'Kuala Lumpur', '43200',
 3.0834, 101.7567,
 NULL,
 'buddhist,taoist',
 1, 0,
 'Cheras Batu 11 Cemetery | Plot Listings',
 'Browse resale burial plot listings at Cheras Batu 11 Chinese Cemetery.'),

('kajang-memorial-park',
 'Kajang Memorial Park',
 '加影纪念公园',
 'A peaceful memorial park serving Kajang and surrounding areas with multiple burial options.',
 'Kajang', 'Selangor', '43000',
 2.9940, 101.7940,
 '+603-8737 5678',
 'buddhist,taoist,christian,non_religious',
 1, 0,
 'Kajang Memorial Park | Burial Plot & Niche Listings',
 'Compare burial plot and columbarium niche listings at Kajang Memorial Park.');

-- Settings
INSERT INTO settings (setting_key, setting_value, setting_type, label, group_name, is_public) VALUES
('site_name',               'PlotGold Malaysia',                    'string', 'Site Name',                    'general',  1),
('site_tagline',            'Trusted Burial Plot Marketplace',      'string', 'Site Tagline',                 'general',  1),
('site_email',              'hello@plotgold.my',                    'string', 'Contact Email',                'general',  1),
('site_phone',              '+60 11-1234 5678',                     'string', 'Contact Phone',                'general',  1),
('whatsapp_number',         '601112345678',                         'string', 'WhatsApp Number',              'general',  1),
('default_currency',        'MYR',                                  'string', 'Default Currency',             'general',  1),
('listing_expiry_days',     '90',                                   'int',    'Listing Expiry (Days)',        'listings', 0),
('max_listing_images',      '10',                                   'int',    'Max Images Per Listing',       'listings', 0),
('max_upload_mb',           '5',                                    'int',    'Max Upload Size (MB)',         'uploads',  0),
('allowed_image_types',     'jpg,jpeg,png,webp',                    'string', 'Allowed Image Types',          'uploads',  0),
('allowed_doc_types',       'pdf,jpg,jpeg,png',                     'string', 'Allowed Document Types',       'uploads',  0),
('smtp_host',               '',                                     'string', 'SMTP Host',                    'email',    0),
('smtp_port',               '587',                                  'int',    'SMTP Port',                    'email',    0),
('smtp_user',               '',                                     'string', 'SMTP Username',                'email',    0),
('smtp_pass',               '',                                     'encrypted', 'SMTP Password',             'email',    0),
('google_maps_key',         '',                                     'string', 'Google Maps API Key',          'integrations', 0),
('recaptcha_site_key',      '',                                     'string', 'reCAPTCHA Site Key',           'integrations', 1),
('recaptcha_secret_key',    '',                                     'encrypted', 'reCAPTCHA Secret Key',      'integrations', 0),
('maintenance_mode',        '0',                                    'bool',   'Maintenance Mode',             'general',  0),
('new_listing_notify_email','admin@plotgold.my',                    'string', 'New Listing Notification Email','email',   0),
('commission_rate_default', '3.00',                                 'string', 'Default Commission Rate (%)',  'commercial',0),
('listing_fee_enabled',     '0',                                    'bool',   'Enable Listing Fee',           'commercial',0),
('featured_listing_7d_price','99.00',                               'string', 'Featured Listing 7-Day Price', 'commercial',0),
('featured_listing_30d_price','299.00',                             'string', 'Featured Listing 30-Day Price','commercial',0),
('gold_price_refresh_hours','24',                                   'int',    'Gold Price Cache Hours',       'integrations',0);

-- FAQs
INSERT INTO faqs (question, answer, category, sort_order) VALUES
('What is PlotGold Malaysia?',
 'PlotGold Malaysia is a trusted marketplace for verified resale burial plots, family lots, and columbarium niches in Malaysia. We also offer DIY funeral planning tools to help families prepare with dignity.',
 'general', 1),

('How do I list my burial plot for sale?',
 'Click "Sell My Plot" to register as a seller, submit your listing details and upload ownership documents. Our verification team reviews your submission within 3-5 business days.',
 'sellers', 2),

('Is it legal to resell a burial plot in Malaysia?',
 'Transferability depends on the specific memorial park''s rules and the ownership type. Some plots are fully transferable, while others require park approval or have restrictions. Our verification team checks this for each listing.',
 'sellers', 3),

('What documents are needed to list a plot?',
 'Typically you need your identity card copy, ownership certificate or receipt from the memorial park, and maintenance fee payment proof. Additional documents may be required based on the listing type.',
 'sellers', 4),

('How long does verification take?',
 'Standard verification takes 3-5 business days. You will be notified by email and WhatsApp at each stage.',
 'general', 5),

('What is the DIY Funeral Planner?',
 'Our DIY Funeral Planner lets you build a personalised funeral service checklist, compare service providers, and request itemised quotes — all at your own pace, without any sales pressure.',
 'planner', 6),

('Do you offer urgent funeral assistance?',
 'Yes. Click "Urgent Help" or WhatsApp us directly for immediate priority assistance. Our team and partner providers are available to help within hours.',
 'general', 7),

('What is the PlotGold Gold Planning Tool?',
 'This is a partner-linked planning tool that helps you estimate and track contributions towards a funeral fund, with optional gold price references. It is not a licensed financial product — please consult a licensed advisor for regulated financial products.',
 'planner', 8),

('How are listings verified?',
 'Each listing goes through a multi-point verification checklist including identity verification, ownership document review, maintenance status check, and pricing benchmark review. Verified listings receive a trust badge.',
 'buyers', 9),

('What are the fees for buyers?',
 'Browsing and enquiring is completely free for buyers. Transactional fees, if applicable, are disclosed transparently before any commitment.',
 'buyers', 10);

-- CMS Blocks (Homepage)
INSERT INTO cms_blocks (block_key, block_type, title, content, section, sort_order) VALUES
('hero_headline',       'text', 'Hero Headline',    'Malaysia''s Trusted Burial Plot Marketplace', 'hero', 1),
('hero_subheadline',    'text', 'Hero Sub',         'Buy, sell, and plan with verified listings and compassionate guidance.', 'hero', 2),
('trust_badge_1',       'json', 'Trust Badge 1',    '{"icon":"fa-shield-alt","title":"Verified Listings","desc":"Every listing goes through a rigorous verification process."}', 'trust', 1),
('trust_badge_2',       'json', 'Trust Badge 2',    '{"icon":"fa-handshake","title":"Trusted Sellers","desc":"Sellers are identity-verified before listing."}', 'trust', 2),
('trust_badge_3',       'json', 'Trust Badge 3',    '{"icon":"fa-headset","title":"Compassionate Support","desc":"Our team understands what you''re going through."}', 'trust', 3),
('footer_tagline',      'text', 'Footer Tagline',   'PlotGold Malaysia — Planning ahead, with care.', 'footer', 1),
('urgency_banner',      'html', 'Urgency Banner',   '<strong>Need urgent help?</strong> Our team is available now.', 'global', 1);

-- Admin user (password: Admin@123 — change immediately)
INSERT INTO users (uuid, email, phone, password_hash, status, email_verified_at) VALUES
(UUID(), 'admin@plotgold.my', '+60112345678',
 '$2y$12$examplehashedpassword.changethisimmediately.xxxxx',
 'active', NOW());

SET @admin_user_id = LAST_INSERT_ID();

INSERT INTO user_profiles (user_id, full_name, city, state) VALUES
(@admin_user_id, 'PlotGold Admin', 'Kuala Lumpur', 'Kuala Lumpur');

INSERT INTO user_role_map (user_id, role_id)
SELECT @admin_user_id, id FROM roles WHERE name = 'super_admin';

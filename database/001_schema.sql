-- ─────────────────────────────────────────────────────────────────────────────
-- SilverDeals MY — Full Database Schema (Phase 1 + 2 + 3 + 4 foundations)
-- Operated by SLV Lifestyle Sdn Bhd | Powered by SLV Group Sdn Bhd
-- MySQL 8+ | utf8mb4 | Run once on a fresh database
-- ─────────────────────────────────────────────────────────────────────────────

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── 1. USERS (identity/auth) ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    email           VARCHAR(191)        NOT NULL,
    phone           VARCHAR(20)         NOT NULL,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('member','merchant','community','admin','superadmin') NOT NULL DEFAULT 'member',
    status          ENUM('pending','active','suspended','banned')             NOT NULL DEFAULT 'pending',
    email_verified  TINYINT(1)          NOT NULL DEFAULT 0,
    phone_verified  TINYINT(1)          NOT NULL DEFAULT 0,
    avatar          VARCHAR(255)        NULL,
    last_login_at   DATETIME            NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME            NULL,
    UNIQUE KEY uq_email (email),
    UNIQUE KEY uq_phone (phone),
    INDEX idx_role   (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 2. PASSWORD RESET TOKENS ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token       VARCHAR(100)    NOT NULL,
    expires_at  DATETIME        NOT NULL,
    used        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token   (token),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 3. REMEMBER-ME TOKENS ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS remember_tokens (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)     NOT NULL,
    expires_at  DATETIME        NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 4. MEMBER PROFILES ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS member_profiles (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED     NOT NULL,
    member_number       VARCHAR(30)         NULL,
    date_of_birth       DATE                NULL,
    gender              ENUM('male','female','prefer_not_to_say') NULL,
    ic_number           VARCHAR(20)         NULL COMMENT 'MyKad or passport (stored hashed or masked)',
    address_line1       VARCHAR(255)        NULL,
    address_line2       VARCHAR(255)        NULL,
    city                VARCHAR(100)        NULL,
    state               VARCHAR(100)        NULL,
    postcode            VARCHAR(10)         NULL,
    country             VARCHAR(80)         NOT NULL DEFAULT 'Malaysia',
    referral_code       VARCHAR(30)         NULL,
    referred_by         BIGINT UNSIGNED     NULL COMMENT 'user_id of referrer',
    profile_completed   TINYINT(1)          NOT NULL DEFAULT 0,
    created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_id       (user_id),
    UNIQUE KEY uq_member_number (member_number),
    UNIQUE KEY uq_referral_code (referral_code),
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 5. SENIOR VERIFICATIONS ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS senior_verifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    document_type   ENUM('ic','passport','other') NOT NULL DEFAULT 'ic',
    document_front  VARCHAR(255)    NULL COMMENT 'Uploaded file path',
    document_back   VARCHAR(255)    NULL,
    selfie          VARCHAR(255)    NULL,
    birth_year      YEAR            NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by     BIGINT UNSIGNED NULL COMMENT 'admin user_id',
    reviewed_at     DATETIME        NULL,
    reject_reason   VARCHAR(500)    NULL,
    submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status  (status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 6. MEMBER CARDS ─────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS member_cards (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED     NOT NULL,
    card_number     VARCHAR(30)         NOT NULL,
    qr_code_data    TEXT                NULL COMMENT 'Encoded QR payload',
    tier            ENUM('free','silver','gold') NOT NULL DEFAULT 'free',
    issued_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME            NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    UNIQUE KEY uq_user_id    (user_id),
    UNIQUE KEY uq_card_number (card_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 7. REFERRALS ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS referrals (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id     BIGINT UNSIGNED NOT NULL COMMENT 'user_id of person who referred',
    referred_id     BIGINT UNSIGNED NOT NULL COMMENT 'user_id of new signup',
    referral_code   VARCHAR(30)     NOT NULL,
    status          ENUM('pending','qualified','rewarded','rejected') NOT NULL DEFAULT 'pending',
    qualified_at    DATETIME        NULL,
    rewarded_at     DATETIME        NULL,
    reject_reason   VARCHAR(255)    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referred_id (referred_id),
    INDEX idx_status      (status),
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 8. POINTS WALLETS ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS points_wallets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    balance         INT UNSIGNED    NOT NULL DEFAULT 0,
    lifetime_earned INT UNSIGNED    NOT NULL DEFAULT 0,
    lifetime_spent  INT UNSIGNED    NOT NULL DEFAULT 0,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 9. POINTS TRANSACTIONS ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS points_transactions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    type            ENUM('earn','spend','adjust','expire') NOT NULL,
    amount          INT             NOT NULL COMMENT 'Positive = earn, Negative = spend/expire',
    balance_after   INT UNSIGNED    NOT NULL,
    source          VARCHAR(80)     NOT NULL COMMENT 'referral|welcome|verification|redemption|admin_adjust|campaign',
    reference_id    BIGINT UNSIGNED NULL COMMENT 'FK to source record (referral_id, redemption_id, etc.)',
    description     VARCHAR(255)    NULL,
    expires_at      DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id   (user_id),
    INDEX idx_type      (type),
    INDEX idx_source    (source),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 10. REWARD CATALOG ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS reward_catalog (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200)    NOT NULL,
    description     TEXT            NULL,
    image           VARCHAR(255)    NULL,
    points_cost     INT UNSIGNED    NOT NULL,
    stock           INT             NOT NULL DEFAULT -1 COMMENT '-1 = unlimited',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    valid_from      DATE            NULL,
    valid_until     DATE            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 11. REWARD REDEMPTIONS (from catalog) ───────────────────────────────────

CREATE TABLE IF NOT EXISTS reward_redemptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    reward_id       BIGINT UNSIGNED NOT NULL,
    points_spent    INT UNSIGNED    NOT NULL,
    status          ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
    redeemed_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fulfilled_at    DATETIME        NULL,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES reward_catalog(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 12. MERCHANTS ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS merchants (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL COMMENT 'Owner user account',
    business_name   VARCHAR(200)    NOT NULL,
    slug            VARCHAR(200)    NOT NULL,
    category_id     INT UNSIGNED    NULL,
    logo            VARCHAR(255)    NULL,
    cover_image     VARCHAR(255)    NULL,
    description     TEXT            NULL,
    website         VARCHAR(255)    NULL,
    email           VARCHAR(191)    NULL,
    phone           VARCHAR(20)     NULL,
    ssm_number      VARCHAR(50)     NULL COMMENT 'SSM registration number',
    status          ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
    commission_pct  DECIMAL(5,2)    NOT NULL DEFAULT 5.00 COMMENT 'Platform commission %',
    approved_by     BIGINT UNSIGNED NULL,
    approved_at     DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    UNIQUE KEY uq_slug    (slug),
    INDEX idx_user_id     (user_id),
    INDEX idx_status      (status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 13. MERCHANT BRANCHES ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS merchant_branches (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    branch_name     VARCHAR(150)    NOT NULL,
    address_line1   VARCHAR(255)    NULL,
    address_line2   VARCHAR(255)    NULL,
    city            VARCHAR(100)    NULL,
    state           VARCHAR(100)    NULL,
    postcode        VARCHAR(10)     NULL,
    lat             DECIMAL(10,7)   NULL,
    lng             DECIMAL(10,7)   NULL,
    phone           VARCHAR(20)     NULL,
    is_primary      TINYINT(1)      NOT NULL DEFAULT 0,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merchant_id (merchant_id),
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 14. DEAL CATEGORIES ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS deal_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    icon        VARCHAR(100) NULL COMMENT 'CSS class or emoji',
    sort_order  INT          NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO deal_categories (name, slug, icon, sort_order) VALUES
('Food & Dining',     'food-dining',     '🍜', 1),
('Health & Wellness', 'health-wellness', '💊', 2),
('Retail & Shopping', 'retail-shopping', '🛍️', 3),
('Travel & Leisure',  'travel-leisure',  '✈️',  4),
('Beauty & Grooming', 'beauty-grooming', '💆', 5),
('Home & Lifestyle',  'home-lifestyle',  '🏠', 6),
('Financial Services','financial',       '💳', 7),
('Education',         'education',       '📚', 8),
('Others',            'others',          '🎁', 9);


-- ─── 15. DEALS ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS deals (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED    NULL,
    title           VARCHAR(200)    NOT NULL,
    slug            VARCHAR(200)    NOT NULL,
    short_desc      VARCHAR(500)    NULL,
    description     TEXT            NULL,
    original_price  DECIMAL(10,2)   NULL,
    deal_price      DECIMAL(10,2)   NULL,
    discount_pct    DECIMAL(5,2)    NULL COMMENT 'Can be used instead of/alongside deal_price',
    points_required INT UNSIGNED    NOT NULL DEFAULT 0,
    deal_type       ENUM('discount','voucher','freebie','event','offer') NOT NULL DEFAULT 'discount',
    status          ENUM('draft','pending','active','paused','expired','rejected') NOT NULL DEFAULT 'draft',
    is_featured     TINYINT(1)      NOT NULL DEFAULT 0,
    is_members_only TINYINT(1)      NOT NULL DEFAULT 0,
    max_redemptions INT             NOT NULL DEFAULT -1 COMMENT '-1 = unlimited',
    redemption_count INT UNSIGNED   NOT NULL DEFAULT 0,
    terms_conditions TEXT           NULL,
    valid_from      DATE            NULL,
    valid_until     DATE            NULL,
    approved_by     BIGINT UNSIGNED NULL,
    approved_at     DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    UNIQUE KEY uq_slug          (slug),
    INDEX idx_merchant_id       (merchant_id),
    INDEX idx_category_id       (category_id),
    INDEX idx_status            (status),
    INDEX idx_is_featured       (is_featured),
    INDEX idx_valid_until       (valid_until),
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES deal_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 16. DEAL IMAGES ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS deal_images (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deal_id     BIGINT UNSIGNED NOT NULL,
    image_path  VARCHAR(255)    NOT NULL,
    is_primary  TINYINT(1)      NOT NULL DEFAULT 0,
    sort_order  INT             NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_deal_id (deal_id),
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 17. REDEMPTIONS (deal vouchers) ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS redemptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    deal_id         BIGINT UNSIGNED NOT NULL,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    voucher_code    VARCHAR(30)     NOT NULL,
    status          ENUM('active','used','expired','cancelled') NOT NULL DEFAULT 'active',
    redeemed_at     DATETIME        NULL COMMENT 'When member used the voucher at merchant',
    redeemed_at_branch BIGINT UNSIGNED NULL,
    verified_by     BIGINT UNSIGNED NULL COMMENT 'merchant_user or admin who validated',
    expires_at      DATETIME        NULL,
    points_earned   INT             NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voucher_code (voucher_code),
    INDEX idx_user_id    (user_id),
    INDEX idx_deal_id    (deal_id),
    INDEX idx_merchant_id (merchant_id),
    INDEX idx_status     (status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (deal_id)     REFERENCES deals(id) ON DELETE RESTRICT,
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 18. COMMISSIONS ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS commissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    redemption_id   BIGINT UNSIGNED NOT NULL,
    deal_price      DECIMAL(10,2)   NOT NULL,
    commission_pct  DECIMAL(5,2)    NOT NULL,
    commission_amt  DECIMAL(10,2)   NOT NULL,
    status          ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merchant_id   (merchant_id),
    INDEX idx_redemption_id (redemption_id),
    FOREIGN KEY (merchant_id)   REFERENCES merchants(id) ON DELETE RESTRICT,
    FOREIGN KEY (redemption_id) REFERENCES redemptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 19. PAYOUTS ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS payouts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    bank_name       VARCHAR(100)    NULL,
    bank_account    VARCHAR(50)     NULL,
    reference       VARCHAR(100)    NULL,
    status          ENUM('requested','processing','paid','rejected') NOT NULL DEFAULT 'requested',
    requested_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at    DATETIME        NULL,
    notes           TEXT            NULL,
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 20. COMMUNITY PARTNERS ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS community_partners (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    org_name        VARCHAR(200)    NOT NULL,
    slug            VARCHAR(200)    NOT NULL,
    org_type        ENUM('jmb','condo','koperasi','retirement','club','other') NOT NULL DEFAULT 'other',
    logo            VARCHAR(255)    NULL,
    description     TEXT            NULL,
    address         VARCHAR(500)    NULL,
    city            VARCHAR(100)    NULL,
    state           VARCHAR(100)    NULL,
    phone           VARCHAR(20)     NULL,
    email           VARCHAR(191)    NULL,
    status          ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    approved_by     BIGINT UNSIGNED NULL,
    approved_at     DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug   (slug),
    INDEX idx_user_id    (user_id),
    INDEX idx_status     (status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 21. COMMUNITY EVENTS ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS community_events (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id    BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(200)    NOT NULL,
    description     TEXT            NULL,
    image           VARCHAR(255)    NULL,
    event_date      DATETIME        NULL,
    location        VARCHAR(300)    NULL,
    status          ENUM('draft','published','cancelled') NOT NULL DEFAULT 'draft',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_community_id (community_id),
    FOREIGN KEY (community_id) REFERENCES community_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 22. COMMUNITY REFERRALS ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS community_referrals (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id    BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL COMMENT 'Member who joined via this community',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_community_id (community_id),
    INDEX idx_user_id      (user_id),
    FOREIGN KEY (community_id) REFERENCES community_partners(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 23. MEMBERSHIP PLANS ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS membership_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    slug            VARCHAR(100)    NOT NULL,
    price_myr       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    billing_cycle   ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'yearly',
    points_bonus    INT UNSIGNED    NOT NULL DEFAULT 0,
    features        JSON            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 0,
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO membership_plans (name, slug, price_myr, billing_cycle, points_bonus, sort_order) VALUES
('Free',   'free',   0.00,  'lifetime', 0,   1),
('Silver', 'silver', 49.00, 'yearly',   500, 2),
('Gold',   'gold',   99.00, 'yearly',   1500,3);


-- ─── 24. SUBSCRIPTIONS ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS subscriptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    plan_id         INT UNSIGNED    NOT NULL,
    status          ENUM('active','cancelled','expired','past_due') NOT NULL DEFAULT 'active',
    starts_at       DATETIME        NOT NULL,
    ends_at         DATETIME        NULL,
    cancelled_at    DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status  (status),
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id)  REFERENCES membership_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 25. PAYMENTS ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    currency        CHAR(3)         NOT NULL DEFAULT 'MYR',
    gateway         VARCHAR(50)     NOT NULL DEFAULT 'manual' COMMENT 'manual|billplz|stripe|etc',
    gateway_ref     VARCHAR(200)    NULL,
    status          ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status  (status),
    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 26. BANNERS ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS banners (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200)    NOT NULL,
    image       VARCHAR(255)    NOT NULL,
    link_url    VARCHAR(500)    NULL,
    position    ENUM('hero','homepage_mid','deals_top','member_dashboard') NOT NULL DEFAULT 'hero',
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order  INT             NOT NULL DEFAULT 0,
    valid_from  DATE            NULL,
    valid_until DATE            NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 27. TESTIMONIALS ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS testimonials (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    location    VARCHAR(100)    NULL,
    avatar      VARCHAR(255)    NULL,
    quote       TEXT            NOT NULL,
    rating      TINYINT(1)      NOT NULL DEFAULT 5,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order  INT             NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO testimonials (name, location, quote, rating) VALUES
('Puan Rohani, 63',  'Petaling Jaya',  'SilverDeals MY has helped me save so much on my groceries and health supplements. The QR card is so easy to use!', 5),
('Encik Subramaniam, 58', 'Ipoh',      'I referred three friends and earned enough points for a free health screening. Wonderful platform for our community.', 5),
('Datin Lily Tan, 61', 'Subang Jaya',  'The deals are genuine and the merchants are trustworthy. I feel valued as a senior member.', 5);


-- ─── 28. FAQS ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS faqs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question    VARCHAR(500)    NOT NULL,
    answer      TEXT            NOT NULL,
    category    VARCHAR(100)    NULL,
    sort_order  INT             NOT NULL DEFAULT 0,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO faqs (question, answer, category, sort_order) VALUES
('Who can join SilverDeals MY?',
 'SilverDeals MY is designed for Malaysians aged 50 and above. Family members can also register on behalf of their seniors.',
 'membership', 1),
('Is membership free?',
 'Yes! Basic membership is free. We also offer Silver and Gold premium tiers with more exclusive deals and higher reward points.',
 'membership', 2),
('How do I redeem a deal?',
 'Simply browse deals, click "Get Voucher", and show your digital QR code to the merchant. It''s that simple.',
 'deals', 3),
('How do referral rewards work?',
 'Share your unique referral link. Once your friend registers and completes verification, both of you receive bonus points.',
 'rewards', 4),
('Is my personal information safe?',
 'Absolutely. We follow strict data protection practices and never share your information with third parties without consent.',
 'security', 5);


-- ─── 29. CONTACT INQUIRIES ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS contact_inquiries (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)    NOT NULL,
    email       VARCHAR(191)    NOT NULL,
    phone       VARCHAR(20)     NULL,
    subject     VARCHAR(200)    NULL,
    message     TEXT            NOT NULL,
    type        ENUM('general','merchant','community','support','complaint') NOT NULL DEFAULT 'general',
    status      ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
    replied_by  BIGINT UNSIGNED NULL,
    replied_at  DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 30. SPONSORED LISTINGS ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS sponsored_listings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id     BIGINT UNSIGNED NOT NULL,
    deal_id         BIGINT UNSIGNED NULL,
    position        ENUM('homepage_featured','deals_top','category_top','search_top') NOT NULL,
    amount_myr      DECIMAL(10,2)   NOT NULL,
    starts_at       DATE            NOT NULL,
    ends_at         DATE            NOT NULL,
    status          ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merchant_id (merchant_id),
    INDEX idx_position    (position),
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT,
    FOREIGN KEY (deal_id)     REFERENCES deals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 31. NOTIFICATIONS ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(200)    NOT NULL,
    body            TEXT            NULL,
    type            VARCHAR(80)     NOT NULL DEFAULT 'info' COMMENT 'info|reward|deal|verification|system',
    reference_id    BIGINT UNSIGNED NULL,
    reference_type  VARCHAR(80)     NULL,
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 32. AUDIT LOGS ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    action          VARCHAR(100)    NOT NULL COMMENT 'e.g. member.login, admin.approve_merchant',
    target_type     VARCHAR(80)     NULL,
    target_id       BIGINT UNSIGNED NULL,
    old_data        JSON            NULL,
    new_data        JSON            NULL,
    ip_address      VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id    (user_id),
    INDEX idx_action     (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 33. FRAUD FLAGS ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS fraud_flags (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    flag_type       VARCHAR(100)    NOT NULL COMMENT 'duplicate_referral|suspicious_redemption|etc',
    description     TEXT            NULL,
    status          ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     DATETIME        NULL,
    resolved_by     BIGINT UNSIGNED NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status  (status),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 34. PAGES (CMS-lite) ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS pages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(100)    NOT NULL,
    title       VARCHAR(200)    NOT NULL,
    content     LONGTEXT        NULL,
    meta_title  VARCHAR(200)    NULL,
    meta_desc   VARCHAR(500)    NULL,
    is_published TINYINT(1)     NOT NULL DEFAULT 1,
    updated_by  BIGINT UNSIGNED NULL,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── 35. SUPERADMIN SEED ─────────────────────────────────────────────────────
-- Default admin password: Admin@123 — CHANGE IMMEDIATELY after first login

INSERT INTO users (name, email, phone, password_hash, role, status, email_verified)
VALUES (
    'SilverDeals Admin',
    'admin@silverdeals.my',
    '60100000000',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@123
    'superadmin',
    'active',
    1
) ON DUPLICATE KEY UPDATE name = name;

SET FOREIGN_KEY_CHECKS = 1;
-- ─── End of Schema ────────────────────────────────────────────────────────────

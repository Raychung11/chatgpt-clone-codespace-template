-- ============================================================
-- PlotGold Malaysia - Complete Database Schema
-- Version: 1.0.0
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS plotgold CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plotgold;

-- ============================================================
-- AUTH & USER TABLES
-- ============================================================

CREATE TABLE roles (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','suspended','pending','deleted') DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL,
    phone_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    login_attempts TINYINT UNSIGNED DEFAULT 0,
    locked_until TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE user_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    ic_number VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    nationality VARCHAR(100) DEFAULT 'Malaysian',
    preferred_language ENUM('en','zh','ms') DEFAULT 'en',
    avatar_path VARCHAR(500),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postcode VARCHAR(10),
    country VARCHAR(100) DEFAULT 'Malaysia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_role_map (
    user_id INT UNSIGNED NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT UNSIGNED NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

CREATE TABLE sessions_logins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    device_type ENUM('desktop','mobile','tablet','unknown') DEFAULT 'unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- SELLER / BUYER / PROVIDER TABLES
-- ============================================================

CREATE TABLE sellers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    company_name VARCHAR(200),
    seller_type ENUM('individual','agent','developer','estate') DEFAULT 'individual',
    verification_status ENUM('unverified','pending','verified','rejected') DEFAULT 'unverified',
    verified_at TIMESTAMP NULL,
    verified_by INT UNSIGNED NULL,
    total_listings INT UNSIGNED DEFAULT 0,
    active_listings INT UNSIGNED DEFAULT 0,
    sold_count INT UNSIGNED DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE buyers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    buyer_type ENUM('individual','family','corporate') DEFAULT 'individual',
    purchase_intent ENUM('immediate','within_6mo','within_year','planning','browsing') DEFAULT 'browsing',
    budget_min DECIMAL(12,2),
    budget_max DECIMAL(12,2),
    preferred_location VARCHAR(200),
    religion_preference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE providers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    business_name VARCHAR(200) NOT NULL,
    business_reg_no VARCHAR(100),
    provider_type ENUM('funeral_home','transport','florist','memorial_park','catering','clergy','admin_support','multipurpose') DEFAULT 'funeral_home',
    approval_status ENUM('pending','approved','suspended','rejected') DEFAULT 'pending',
    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,
    description TEXT,
    website VARCHAR(300),
    whatsapp VARCHAR(20),
    logo_path VARCHAR(500),
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT UNSIGNED DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE provider_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    doc_type ENUM('business_reg','license','insurance','certification','other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE provider_service_areas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    state VARCHAR(100) NOT NULL,
    city VARCHAR(100),
    postcode VARCHAR(10),
    radius_km TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    INDEX idx_state_city (state, city)
) ENGINE=InnoDB;

-- ============================================================
-- MEMORIAL PARK / INVENTORY TABLES
-- ============================================================

CREATE TABLE memorial_parks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(200) NOT NULL UNIQUE,
    name VARCHAR(300) NOT NULL,
    name_zh VARCHAR(300),
    description TEXT,
    description_zh TEXT,
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postcode VARCHAR(10),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    phone VARCHAR(20),
    email VARCHAR(200),
    website VARCHAR(300),
    google_maps_url VARCHAR(500),
    logo_path VARCHAR(500),
    banner_path VARCHAR(500),
    gallery_json TEXT,
    operating_hours TEXT,
    established_year YEAR,
    total_capacity INT UNSIGNED,
    available_capacity INT UNSIGNED,
    supported_religions VARCHAR(300),
    amenities_json TEXT,
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_city_state (city, state),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE park_sections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50),
    section_type ENUM('ground_burial','columbarium','mausoleum','garden','lawn','urn_garden','other') DEFAULT 'ground_burial',
    religion_category VARCHAR(100),
    total_plots INT UNSIGNED,
    available_plots INT UNSIGNED,
    price_range_min DECIMAL(12,2),
    price_range_max DECIMAL(12,2),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (park_id) REFERENCES memorial_parks(id) ON DELETE CASCADE,
    INDEX idx_park (park_id)
) ENGINE=InnoDB;

CREATE TABLE listing_types (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    label_en VARCHAR(150),
    label_zh VARCHAR(150),
    description TEXT,
    icon VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    sort_order TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE religion_categories (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    label_en VARCHAR(150),
    label_zh VARCHAR(150),
    is_active TINYINT(1) DEFAULT 1,
    sort_order TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE listings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    listing_code VARCHAR(30) NOT NULL UNIQUE,
    seller_id INT UNSIGNED NOT NULL,
    park_id INT UNSIGNED,
    section_id INT UNSIGNED,
    listing_type_id TINYINT UNSIGNED,
    religion_id TINYINT UNSIGNED,

    -- Location details
    city VARCHAR(100),
    state VARCHAR(100),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),

    -- Plot identification
    block_no VARCHAR(50),
    row_no VARCHAR(50),
    lot_no VARCHAR(50),
    unit_no VARCHAR(50),
    level_no VARCHAR(50),

    -- Listing info
    title VARCHAR(300) NOT NULL,
    slug VARCHAR(300) NOT NULL UNIQUE,
    public_description TEXT,
    internal_notes TEXT,
    orientation_notes TEXT,
    fengshui_notes TEXT,

    -- Pricing
    asking_price DECIMAL(12,2),
    market_estimate DECIMAL(12,2),
    transfer_fee DECIMAL(12,2),
    annual_maintenance_fee DECIMAL(12,2),
    maintenance_status ENUM('paid','overdue','unknown','na') DEFAULT 'unknown',

    -- Ownership
    ownership_type ENUM('freehold','leasehold','perpetual','renewable','unknown') DEFAULT 'unknown',
    tenure_years SMALLINT UNSIGNED,
    is_transferable TINYINT(1) DEFAULT 0,
    transfer_notes TEXT,

    -- Seller intent & urgency
    seller_intent ENUM('direct_sale','open_to_offers','inquiry_only') DEFAULT 'direct_sale',
    urgency_level ENUM('standard','moderate','urgent','immediate') DEFAULT 'standard',

    -- Status & verification
    status ENUM('draft','pending_review','active','under_offer','reserved','sold','expired','withdrawn','rejected') DEFAULT 'draft',
    verification_score TINYINT UNSIGNED DEFAULT 0,
    doc_completeness_score TINYINT UNSIGNED DEFAULT 0,
    badge_status ENUM('none','pending','partial','verified','transfer_check') DEFAULT 'none',
    risk_level ENUM('low','medium','high','critical') DEFAULT 'medium',

    -- Display
    is_featured TINYINT(1) DEFAULT 0,
    feature_expires_at TIMESTAMP NULL,
    view_count INT UNSIGNED DEFAULT 0,
    inquiry_count INT UNSIGNED DEFAULT 0,
    favourite_count INT UNSIGNED DEFAULT 0,

    -- Dates
    listed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    sold_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    FOREIGN KEY (seller_id) REFERENCES sellers(id),
    FOREIGN KEY (park_id) REFERENCES memorial_parks(id) ON DELETE SET NULL,
    FOREIGN KEY (section_id) REFERENCES park_sections(id) ON DELETE SET NULL,
    FOREIGN KEY (listing_type_id) REFERENCES listing_types(id) ON DELETE SET NULL,
    FOREIGN KEY (religion_id) REFERENCES religion_categories(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_city_state (city, state),
    INDEX idx_price (asking_price),
    INDEX idx_featured (is_featured, status),
    INDEX idx_seller (seller_id),
    INDEX idx_park (park_id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE listing_media (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    media_type ENUM('image','video','document','floorplan') DEFAULT 'image',
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    mime_type VARCHAR(100),
    file_size INT UNSIGNED,
    caption VARCHAR(300),
    sort_order TINYINT UNSIGNED DEFAULT 0,
    is_primary TINYINT(1) DEFAULT 0,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    INDEX idx_listing (listing_id)
) ENGINE=InnoDB;

CREATE TABLE listing_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    doc_type ENUM('ownership_cert','ic_copy','park_receipt','transfer_form','maintenance_receipt','death_cert','probate','other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    mime_type VARCHAR(100),
    file_size INT UNSIGNED,
    is_sensitive TINYINT(1) DEFAULT 1,
    status ENUM('pending','approved','rejected','redacted') DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE listing_verification_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    check_key VARCHAR(100) NOT NULL,
    check_label VARCHAR(200),
    weight TINYINT UNSIGNED DEFAULT 10,
    status ENUM('pending','passed','failed','na','skipped') DEFAULT 'pending',
    checked_by INT UNSIGNED NULL,
    checked_at TIMESTAMP NULL,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_listing_check (listing_id, check_key),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE listing_status_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    changed_by INT UNSIGNED NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE listing_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    note_type ENUM('admin','verification','communication','system') DEFAULT 'admin',
    content TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE listing_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    old_price DECIMAL(12,2),
    new_price DECIMAL(12,2),
    changed_by INT UNSIGNED,
    reason VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- BUYER INTERACTION TABLES
-- ============================================================

CREATE TABLE favourites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    buyer_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fav (buyer_id, listing_id),
    FOREIGN KEY (buyer_id) REFERENCES buyers(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE comparisons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    buyer_id INT UNSIGNED NULL,
    session_id VARCHAR(128),
    listing_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE enquiries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    enquiry_code VARCHAR(30) NOT NULL UNIQUE,
    buyer_id INT UNSIGNED NULL,
    listing_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    enquiry_type ENUM('listing','provider','general','urgent','planning') DEFAULT 'listing',
    subject VARCHAR(300),
    message TEXT NOT NULL,
    contact_name VARCHAR(200),
    contact_email VARCHAR(200),
    contact_phone VARCHAR(20),
    preferred_contact ENUM('email','phone','whatsapp') DEFAULT 'whatsapp',
    status ENUM('new','assigned','in_progress','resolved','closed','spam') DEFAULT 'new',
    priority ENUM('normal','high','urgent') DEFAULT 'normal',
    assigned_to INT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL,
    source ENUM('web','whatsapp','ai','phone','admin') DEFAULT 'web',
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_status (status),
    INDEX idx_type (enquiry_type),
    INDEX idx_buyer (buyer_id)
) ENGINE=InnoDB;

CREATE TABLE enquiry_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NULL,
    sender_type ENUM('buyer','seller','admin','support','system','ai') DEFAULT 'buyer',
    message TEXT NOT NULL,
    attachments_json TEXT,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
    INDEX idx_enquiry (enquiry_id)
) ENGINE=InnoDB;

CREATE TABLE offers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    buyer_id INT UNSIGNED NOT NULL,
    offer_amount DECIMAL(12,2) NOT NULL,
    offer_note TEXT,
    status ENUM('pending','accepted','rejected','countered','withdrawn','expired') DEFAULT 'pending',
    counter_amount DECIMAL(12,2),
    counter_note TEXT,
    expires_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id),
    FOREIGN KEY (buyer_id) REFERENCES buyers(id)
) ENGINE=InnoDB;

CREATE TABLE reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    buyer_id INT UNSIGNED NOT NULL,
    reservation_code VARCHAR(30) NOT NULL UNIQUE,
    deposit_amount DECIMAL(12,2),
    deposit_paid TINYINT(1) DEFAULT 0,
    status ENUM('pending','confirmed','cancelled','completed','forfeited') DEFAULT 'pending',
    notes TEXT,
    reserved_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id),
    FOREIGN KEY (buyer_id) REFERENCES buyers(id)
) ENGINE=InnoDB;

-- ============================================================
-- FUNERAL PLANNER TABLES
-- ============================================================

CREATE TABLE service_categories (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    slug VARCHAR(150) NOT NULL UNIQUE,
    label_en VARCHAR(200),
    label_zh VARCHAR(200),
    icon VARCHAR(100),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE funeral_services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    base_price DECIMAL(12,2),
    price_type ENUM('fixed','per_unit','per_day','quote_required') DEFAULT 'fixed',
    unit_label VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    is_recommended TINYINT(1) DEFAULT 0,
    sort_order TINYINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (category_id) REFERENCES service_categories(id)
) ENGINE=InnoDB;

CREATE TABLE provider_services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    price DECIMAL(12,2),
    price_type ENUM('fixed','per_unit','per_day','quote_required') DEFAULT 'fixed',
    min_order TINYINT UNSIGNED DEFAULT 1,
    description TEXT,
    is_available TINYINT(1) DEFAULT 1,
    lead_time_hours TINYINT UNSIGNED DEFAULT 24,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_service (provider_id, service_id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES funeral_services(id)
) ENGINE=InnoDB;

CREATE TABLE carts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    buyer_id INT UNSIGNED NULL,
    session_id VARCHAR(128),
    mode ENUM('standard','emergency','bundle') DEFAULT 'standard',
    status ENUM('active','saved','converted','abandoned') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_session (session_id)
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    listing_id INT UNSIGNED NULL,
    provider_service_id INT UNSIGNED NULL,
    item_name VARCHAR(300) NOT NULL,
    item_description TEXT,
    unit_price DECIMAL(12,2),
    quantity SMALLINT UNSIGNED DEFAULT 1,
    subtotal DECIMAL(12,2),
    custom_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES funeral_services(id) ON DELETE SET NULL,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE quotations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    quote_code VARCHAR(30) NOT NULL UNIQUE,
    cart_id INT UNSIGNED NULL,
    buyer_id INT UNSIGNED NULL,
    contact_name VARCHAR(200),
    contact_email VARCHAR(200),
    contact_phone VARCHAR(20),
    event_date DATE NULL,
    event_location VARCHAR(300),
    religion VARCHAR(100),
    notes TEXT,
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    discount DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('draft','submitted','in_review','quoted','accepted','rejected','expired') DEFAULT 'draft',
    source ENUM('diy_planner','admin','provider','ai','api') DEFAULT 'diy_planner',
    mode ENUM('standard','emergency') DEFAULT 'standard',
    pdf_path VARCHAR(500),
    valid_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_status (status),
    INDEX idx_buyer (buyer_id),
    INDEX idx_code (quote_code)
) ENGINE=InnoDB;

CREATE TABLE quotation_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quotation_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(300) NOT NULL,
    item_description TEXT,
    category VARCHAR(150),
    provider_id INT UNSIGNED NULL,
    unit_price DECIMAL(12,2),
    quantity SMALLINT UNSIGNED DEFAULT 1,
    subtotal DECIMAL(12,2),
    sort_order TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quote_status_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quotation_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    changed_by INT UNSIGNED NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- PAYMENT / COMMERCIAL TABLES
-- ============================================================

CREATE TABLE payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    payment_code VARCHAR(40) NOT NULL UNIQUE,
    payer_id INT UNSIGNED NULL,
    payable_type ENUM('reservation','premium_listing','quotation','commission','other') NOT NULL,
    payable_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) DEFAULT 'MYR',
    gateway ENUM('manual','stripe','billplz','toyyibpay','fpx','other') DEFAULT 'manual',
    gateway_ref VARCHAR(200),
    status ENUM('pending','processing','paid','failed','refunded','cancelled') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_status (status),
    INDEX idx_payable (payable_type, payable_id)
) ENGINE=InnoDB;

CREATE TABLE payment_transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NOT NULL,
    transaction_ref VARCHAR(200),
    gateway_response TEXT,
    amount DECIMAL(12,2),
    status ENUM('success','failure','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE commissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED NULL,
    seller_id INT UNSIGNED NOT NULL,
    sale_price DECIMAL(12,2),
    commission_rate DECIMAL(5,2),
    commission_amount DECIMAL(12,2),
    status ENUM('pending','payable','paid','disputed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id),
    FOREIGN KEY (seller_id) REFERENCES sellers(id)
) ENGINE=InnoDB;

CREATE TABLE payouts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    account_name VARCHAR(200),
    status ENUM('pending','processing','paid','failed') DEFAULT 'pending',
    reference VARCHAR(200),
    notes TEXT,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE premium_listing_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    package_type ENUM('featured_7d','featured_30d','homepage_spot','top_search','bundle') DEFAULT 'featured_7d',
    amount DECIMAL(12,2),
    payment_id INT UNSIGNED NULL,
    status ENUM('pending','active','expired','cancelled') DEFAULT 'pending',
    starts_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (listing_id) REFERENCES listings(id),
    FOREIGN KEY (seller_id) REFERENCES sellers(id)
) ENGINE=InnoDB;

CREATE TABLE reserve_deposits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_id INT UNSIGNED NULL,
    status ENUM('pending','received','applied','refunded','forfeited') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)
) ENGINE=InnoDB;

-- ============================================================
-- PLANNING / PARTNER TABLES
-- ============================================================

CREATE TABLE planning_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    profile_name VARCHAR(200) DEFAULT 'My Plan',
    plan_type ENUM('self','spouse','parent','sibling','other') DEFAULT 'self',
    target_amount DECIMAL(12,2),
    currency CHAR(3) DEFAULT 'MYR',
    start_date DATE,
    target_date DATE,
    current_savings DECIMAL(12,2) DEFAULT 0.00,
    monthly_contribution DECIMAL(12,2) DEFAULT 0.00,
    gold_reference_grams DECIMAL(10,3),
    partner_product_code VARCHAR(100),
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    reminder_frequency ENUM('none','weekly','monthly') DEFAULT 'monthly',
    partner_disclaimer_accepted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE planning_transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    planning_profile_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('contribution','withdrawal','adjustment','opening') DEFAULT 'contribution',
    amount DECIMAL(12,2) NOT NULL,
    gold_price_at_time DECIMAL(12,2),
    gold_grams DECIMAL(10,4),
    note TEXT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (planning_profile_id) REFERENCES planning_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE partner_integrations (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_code VARCHAR(50) NOT NULL UNIQUE,
    partner_name VARCHAR(200) NOT NULL,
    api_endpoint VARCHAR(500),
    api_key_encrypted VARCHAR(500),
    product_type ENUM('gold_savings','insurance','takaful','trust_deed','other') DEFAULT 'gold_savings',
    is_active TINYINT(1) DEFAULT 1,
    config_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE partner_rate_cache (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id TINYINT UNSIGNED NOT NULL,
    rate_type VARCHAR(100) NOT NULL,
    rate_value DECIMAL(15,6),
    currency CHAR(3) DEFAULT 'MYR',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    raw_response TEXT,
    PRIMARY KEY (id),
    FOREIGN KEY (partner_id) REFERENCES partner_integrations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- AI / CRM TABLES
-- ============================================================

CREATE TABLE ai_leads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    lead_code VARCHAR(30) NOT NULL UNIQUE,
    lead_type ENUM('seller','buyer','provider','urgent','planning','general') DEFAULT 'general',
    contact_name VARCHAR(200),
    contact_email VARCHAR(200),
    contact_phone VARCHAR(20),
    channel ENUM('web_form','whatsapp','facebook','telegram','api','email') DEFAULT 'web_form',
    intent_summary TEXT,
    raw_transcript TEXT,
    ai_score TINYINT UNSIGNED DEFAULT 0,
    ai_tags VARCHAR(500),
    urgency_flag TINYINT(1) DEFAULT 0,
    assigned_to INT UNSIGNED NULL,
    crm_status ENUM('new','contacted','qualified','proposal','closed_won','closed_lost','invalid') DEFAULT 'new',
    user_id INT UNSIGNED NULL,
    created_listing_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_status (crm_status),
    INDEX idx_type (lead_type),
    INDEX idx_urgency (urgency_flag)
) ENGINE=InnoDB;

CREATE TABLE ai_conversations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id INT UNSIGNED NULL,
    session_key VARCHAR(128) NOT NULL UNIQUE,
    channel ENUM('web','whatsapp','api') DEFAULT 'web',
    flow_type ENUM('seller_intake','buyer_intake','urgent','planning','provider','general') DEFAULT 'general',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    status ENUM('active','completed','abandoned','escalated') DEFAULT 'active',
    total_messages TINYINT UNSIGNED DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (lead_id) REFERENCES ai_leads(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ai_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id INT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content TEXT NOT NULL,
    metadata_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    INDEX idx_conv (conversation_id)
) ENGINE=InnoDB;

CREATE TABLE lead_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id INT UNSIGNED NOT NULL,
    assigned_to INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    note TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (lead_id) REFERENCES ai_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE crm_status_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    changed_by INT UNSIGNED NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (lead_id) REFERENCES ai_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CMS / SETTINGS TABLES
-- ============================================================

CREATE TABLE cms_pages (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(200) NOT NULL UNIQUE,
    title VARCHAR(300) NOT NULL,
    meta_title VARCHAR(200),
    meta_description VARCHAR(500),
    content LONGTEXT,
    is_published TINYINT(1) DEFAULT 0,
    published_at TIMESTAMP NULL,
    author_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE cms_blocks (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    block_key VARCHAR(100) NOT NULL UNIQUE,
    block_type ENUM('html','text','json','image','link') DEFAULT 'html',
    title VARCHAR(200),
    content LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    section VARCHAR(100),
    sort_order SMALLINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE seo_pages (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_type ENUM('listing','park','city','static','category') NOT NULL,
    reference_id INT UNSIGNED NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    meta_title VARCHAR(200),
    meta_description VARCHAR(500),
    og_title VARCHAR(200),
    og_description VARCHAR(500),
    og_image VARCHAR(500),
    schema_markup LONGTEXT,
    canonical_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE faqs (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100),
    sort_order SMALLINT UNSIGNED DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE settings (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(150) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string','int','bool','json','encrypted') DEFAULT 'string',
    label VARCHAR(200),
    description TEXT,
    group_name VARCHAR(100),
    is_public TINYINT(1) DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT UNSIGNED NULL,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE admin_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_flagged TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

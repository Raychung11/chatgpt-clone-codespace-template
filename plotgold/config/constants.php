<?php
/**
 * PlotGold Malaysia — Application Constants
 */

defined('PLOTGOLD') or die('Direct access not permitted.');

// ── User Roles ──────────────────────────────────────────────────────────────
const ROLE_GUEST                = 'guest';
const ROLE_BUYER                = 'buyer';
const ROLE_SELLER               = 'seller';
const ROLE_PROVIDER             = 'provider';
const ROLE_SUPPORT              = 'support_officer';
const ROLE_VERIFICATION         = 'verification_officer';
const ROLE_ADMIN                = 'admin';
const ROLE_SUPER_ADMIN          = 'super_admin';

// ── Listing Statuses ─────────────────────────────────────────────────────────
const LISTING_DRAFT             = 'draft';
const LISTING_PENDING           = 'pending_review';
const LISTING_ACTIVE            = 'active';
const LISTING_UNDER_OFFER       = 'under_offer';
const LISTING_RESERVED          = 'reserved';
const LISTING_SOLD              = 'sold';
const LISTING_EXPIRED           = 'expired';
const LISTING_WITHDRAWN         = 'withdrawn';
const LISTING_REJECTED          = 'rejected';

// ── Verification Badge Labels ─────────────────────────────────────────────────
const BADGE_NONE                = 'none';
const BADGE_PENDING             = 'pending';
const BADGE_PARTIAL             = 'partial';
const BADGE_VERIFIED            = 'verified';
const BADGE_TRANSFER_CHECK      = 'transfer_check';

// ── Verification Check Keys (weighted) ───────────────────────────────────────
const VERIFICATION_CHECKS = [
    'identity_verified'         => ['label' => 'Identity Verified',            'weight' => 20],
    'ownership_proof_uploaded'  => ['label' => 'Ownership Proof Uploaded',     'weight' => 20],
    'park_proof_uploaded'       => ['label' => 'Park/Unit Proof Uploaded',     'weight' => 15],
    'maintenance_evidence'      => ['label' => 'Maintenance Fee Evidence',     'weight' => 10],
    'transfer_eligibility'      => ['label' => 'Transfer Eligibility Checked', 'weight' => 15],
    'pricing_benchmark'         => ['label' => 'Pricing Benchmark Reviewed',   'weight' => 5],
    'location_confirmed'        => ['label' => 'Location/Unit Confirmed',      'weight' => 5],
    'media_reviewed'            => ['label' => 'Media Reviewed',               'weight' => 5],
    'legal_notes_checked'       => ['label' => 'Legal Notes Checked',         'weight' => 3],
    'reviewer_approved'         => ['label' => 'Reviewer Approved',           'weight' => 2],
];

// ── Enquiry Types ─────────────────────────────────────────────────────────────
const ENQUIRY_LISTING           = 'listing';
const ENQUIRY_PROVIDER          = 'provider';
const ENQUIRY_GENERAL           = 'general';
const ENQUIRY_URGENT            = 'urgent';
const ENQUIRY_PLANNING          = 'planning';

// ── States in Malaysia ────────────────────────────────────────────────────────
const MY_STATES = [
    'Kuala Lumpur', 'Selangor', 'Putrajaya', 'Negeri Sembilan',
    'Johor', 'Pahang', 'Perak', 'Kedah', 'Kelantan', 'Terengganu',
    'Perlis', 'Penang', 'Melaka', 'Sabah', 'Sarawak', 'Labuan',
];

// ── Currency ──────────────────────────────────────────────────────────────────
const CURRENCY_MYR              = 'MYR';
const CURRENCY_SYMBOL           = 'RM';

// ── Image Size Limits ─────────────────────────────────────────────────────────
const IMG_SIZES = [
    'thumb'   => [300,  200],
    'medium'  => [800,  600],
    'large'   => [1200, 900],
];

// ── Flash Message Types ───────────────────────────────────────────────────────
const FLASH_SUCCESS             = 'success';
const FLASH_ERROR               = 'danger';
const FLASH_WARNING             = 'warning';
const FLASH_INFO                = 'info';

// ── AI/Lead Sources ───────────────────────────────────────────────────────────
const LEAD_SOURCE_WEB           = 'web_form';
const LEAD_SOURCE_WHATSAPP      = 'whatsapp';
const LEAD_SOURCE_AI            = 'ai';
const LEAD_SOURCE_API           = 'api';

// ── Planning ──────────────────────────────────────────────────────────────────
const PARTNER_DISCLAIMER = 'This planning tool is for personal estimation purposes only and is not a licensed financial product. Figures are indicative. Consult a licensed financial advisor for regulated savings or investment products.';

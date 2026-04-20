-- ============================================================
-- PlotGold Malaysia — Gold Vault FAQ Seed
-- Migration: 005_gold_vault_faqs.sql
-- Run once: mysql -u user -p plotgold < 005_gold_vault_faqs.sql
-- ============================================================

USE plotgold;

INSERT IGNORE INTO faqs (question, answer, category, sort_order, is_active) VALUES

-- ── GENERAL GOLD VAULT ────────────────────────────────────────────────────────

('What is the Gold Vault storage service?',
 'Our Gold Vault service lets you purchase physical gold and store it in our insured, fireproof, audited vault at absolutely no charge. You own the gold — we just keep it safe for you. Think of it like free parking at a shop: you own your car, we provide the space.',
 'Gold Vault', 1, 1),

('Who actually owns the gold stored in the vault?',
 'You do — 100%. When you purchase gold from us, full legal ownership transfers to you immediately. PlotGold Malaysia holds no beneficial interest in your gold. Your ownership is recorded on a digital certificate linked to a specific serial or lot number. We are simply your custodian, not the owner.',
 'Gold Vault', 2, 1),

('Is there a storage fee?',
 'No. Vault storage is completely free for all gold purchased through PlotGold Malaysia. There are no monthly fees, no management charges, and no hidden costs. The only potential fee is a delivery charge if you choose to redeem your gold as a physical wafer — and that fee is disclosed upfront before you confirm.',
 'Gold Vault', 3, 1),

('How is my gold stored?',
 'Your gold is stored in fireproof, audited vaults insured for up to RM 10,000,000. Each customer\'s holding is individually tracked and is not mixed or pooled with other customers\' gold. You can request our current insurance certificates at any time.',
 'Gold Vault', 4, 1),

('What is the minimum amount I need to start?',
 'You can start from as little as RM 500. For example, RM 1,500 buys you approximately 4.23g of physical gold (subject to daily gold prices), stored free in our vault. You can top up your holding at any time.',
 'Gold Vault', 5, 1),

-- ── REDEMPTION ───────────────────────────────────────────────────────────────

('How do I get my physical gold back?',
 'Simply submit a redemption request through your dashboard. The minimum redemption quantity is 1 gram. We will process your request within 7 business days. You can choose to collect in person or have it delivered (delivery fee applies). There is no lock-in period — you can redeem at any time.',
 'Gold Vault', 6, 1),

('Can I sell my gold back to PlotGold?',
 'Yes. You may sell your gold back to us at the prevailing market buy-back price displayed on our platform. Proceeds will be credited to your registered bank account within 3–5 business days. The buy-back price reflects the live gold spot price less a small spread.',
 'Gold Vault', 7, 1),

('Can I keep my gold digitally forever without redeeming?',
 'Yes. There is no obligation to ever take physical delivery. You can hold your gold digitally on our platform indefinitely, track its live market value, and redeem only when you choose to. Your gold remains yours regardless of how long it is stored.',
 'Gold Vault', 8, 1),

-- ── LEGAL & COMPLIANCE ───────────────────────────────────────────────────────

('What licences does PlotGold hold for the Gold Vault service?',
 'Our gold vault storage service operates under the retail sale model — the same model used by Kasih AP Gold, Public Gold, and Quantum Metal — and requires only an SSM business registration and premise licence. It is not a regulated investment product under the Capital Markets and Services Act 2007, so no Securities Commission (SC) or Bank Negara Malaysia (BNM) licence is required. We sell physical gold; you store it free. That\'s it.',
 'Gold Vault', 9, 1),

('Is the Gold Vault service Shariah compliant?',
 'Yes. Our gold storage operates on a Qabdh (constructive possession) basis consistent with Bank Negara Malaysia\'s Shariah Standards on Gold. Your holding is allocated to a specific serial/lot number at the point of purchase — no pooling, no riba, no deferred payment. The structure has been reviewed against BNM Shariah standards and is suitable for Muslim customers.',
 'Gold Vault', 10, 1),

('Does PlotGold guarantee the value of my gold?',
 'No — and this is intentional. We do not promise fixed returns, capital guarantees, or any yield. Gold price risk is entirely yours as the owner. We are a custodian, not a fund manager. This is precisely what keeps the service SSM-only (no SC licence needed) and Shariah compliant. If someone promises guaranteed gold returns, that is a regulated — or potentially illegal — product.',
 'Gold Vault', 11, 1),

('Is my data protected?',
 'Yes. All personal data collected for your gold account is processed under the Personal Data Protection Act 2010 (PDPA). We collect only what is necessary: your name, IC/passport number, contact, and delivery address. We do not share your data with third parties without consent. Records are retained for a minimum of 7 years as required by SSM.',
 'Gold Vault', 12, 1),

('What happens to my gold if PlotGold ceases operations?',
 'Because you legally own the gold (not us), it cannot be claimed by our creditors. In the event PlotGold Malaysia ceases operations, stored gold will be returned to customers in physical form or transferred to a nominated third-party custodian. This ownership structure is clearly stated in our Terms of Service (Section 17) and your purchase certificate.',
 'Gold Vault', 13, 1),

-- ── PLATFORM & TRACKING ──────────────────────────────────────────────────────

('Can I see my gold balance and its value online?',
 'Yes. Your dashboard shows your total gold weight (in grams), the current live market price, and the approximate MYR value of your holding in real time. You can also view your full transaction history, purchase certificates, and redemption records.',
 'Gold Vault', 14, 1),

('What type of gold do you sell?',
 'We sell PAMP-certified investment-grade physical gold (999.9 purity). PAMP is one of the world\'s most recognised gold refiners and its products are accepted globally. Gold is stored in bar or wafer form and can be redeemed as 1g, 5g, 10g, or 50g PAMP wafers.',
 'Gold Vault', 15, 1),

('Is there a limit to how much gold I can store?',
 'There is no upper limit on how much gold you can purchase and store. For very large holdings (above RM 200,000), we may contact you to complete enhanced due diligence in accordance with anti-money-laundering (AML) requirements.',
 'Gold Vault', 16, 1);

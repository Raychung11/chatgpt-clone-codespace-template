-- =====================================================
-- F&B Loyalty Platform – Seed Data
-- /database/seed.sql
-- Run AFTER schema.sql
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------
-- SETTINGS
-- ------------------------------------------------
INSERT INTO `settings` (`key`, `value`, `group`) VALUES
('loyalty_points_per_myr', '1', 'loyalty'),
('tier_silver_threshold', '500', 'loyalty'),
('tier_gold_threshold', '2000', 'loyalty'),
('tier_platinum_threshold', '5000', 'loyalty'),
('referral_reward_points', '100', 'referral'),
('referral_referee_points', '50', 'referral'),
('reservation_points', '10', 'reservation'),
('currency', 'MYR', 'general'),
('tax_rate', '0.06', 'general'),
('brand_name', 'F&B Loyalty Platform', 'general'),
('brand_logo', '', 'general')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ------------------------------------------------
-- OUTLET (1 main branch)
-- ------------------------------------------------
INSERT INTO `outlets` (`id`, `name`, `slug`, `address`, `city`, `state`, `postcode`, `phone`, `email`, `status`) VALUES
(1, 'Main Branch', 'main-branch', 'No. 1, Jalan Utama, Taman Maju', 'Kuala Lumpur', 'Wilayah Persekutuan', '50000', '0312345678', 'main@fnbplatform.com', 'active'),
(2, 'Mid Valley Outlet', 'mid-valley', 'Level 2, Mid Valley City, Lingkaran Syed Putra', 'Kuala Lumpur', 'Wilayah Persekutuan', '59200', '0398765432', 'midvalley@fnbplatform.com', 'active'),
(3, 'Sunway Outlet', 'sunway', 'No. 5, Jalan PJS 11/15, Bandar Sunway', 'Subang Jaya', 'Selangor', '47500', '0378901234', 'sunway@fnbplatform.com', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ------------------------------------------------
-- MENU CATEGORIES
-- ------------------------------------------------
INSERT INTO `menu_categories` (`id`, `outlet_id`, `name`, `sort_order`, `status`) VALUES
(1, NULL, 'Beverages', 1, 'active'),
(2, NULL, 'Main Course', 2, 'active'),
(3, NULL, 'Appetizers', 3, 'active'),
(4, NULL, 'Desserts', 4, 'active'),
(5, NULL, 'Snacks', 5, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ------------------------------------------------
-- MENU ITEMS
-- ------------------------------------------------
INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`, `price`, `is_available`, `is_featured`, `sort_order`) VALUES
-- Beverages
(1,  1, 'Teh Tarik', 'Rich pulled milk tea, the Malaysian classic', 3.50, 1, 1, 1),
(2,  1, 'Iced Milo', 'Cold Milo with condensed milk over ice', 4.00, 1, 0, 2),
(3,  1, 'Fresh Coconut', 'Young coconut water served chilled', 6.00, 1, 1, 3),
(4,  1, 'Lemon Barley', 'Refreshing barley with fresh lemon', 4.50, 1, 0, 4),
(5,  1, 'Hot Coffee', 'Kopi O or Kopi Susu, brewed daily', 3.00, 1, 0, 5),
-- Main Course
(6,  2, 'Nasi Lemak Special', 'Fragrant coconut rice with sambal, egg, peanuts, anchovies & rendang', 14.90, 1, 1, 1),
(7,  2, 'Char Kway Teow', 'Wok-fried flat rice noodles with prawns, egg & bean sprouts', 12.90, 1, 1, 2),
(8,  2, 'Chicken Rice', 'Steamed or roasted chicken with fragrant rice & soup', 11.90, 1, 0, 3),
(9,  2, 'Beef Rendang', 'Slow-cooked dry beef curry with lemongrass & galangal', 18.90, 1, 1, 4),
(10, 2, 'Mee Goreng Mamak', 'Spicy fried yellow noodles with egg, tofu & tomato', 10.90, 1, 0, 5),
-- Appetizers
(11, 3, 'Chicken Wings (6pcs)', 'Crispy fried wings with spicy dipping sauce', 12.90, 1, 1, 1),
(12, 3, 'Spring Rolls (4pcs)', 'Crispy rolls stuffed with vegetables & glass noodles', 7.90, 1, 0, 2),
(13, 3, 'Satay (10 sticks)', 'Grilled meat skewers with peanut sauce & ketupat', 13.90, 1, 1, 3),
(14, 3, 'Prawn Fritters', 'Lightly battered fresh prawns, served hot', 14.90, 1, 0, 4),
-- Desserts
(15, 4, 'Cendol', 'Shaved ice with pandan jelly, red bean & gula melaka', 6.50, 1, 1, 1),
(16, 4, 'Ais Kacang', 'Rainbow shaved ice with sweet corn, jelly & syrup', 6.50, 1, 0, 2),
(17, 4, 'Kuih Bakar', 'Baked coconut pandan cake, soft & fragrant', 3.50, 1, 0, 3),
(18, 4, 'Pisang Goreng', 'Golden deep-fried banana fritters with honey drizzle', 5.50, 1, 1, 4),
-- Snacks
(19, 5, 'Roti Canai', 'Flaky flatbread with dhal curry & sambal', 2.50, 1, 1, 1),
(20, 5, 'Curry Puff (2pcs)', 'Golden pastry filled with spiced potato & chicken', 4.50, 1, 0, 2)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price` = VALUES(`price`);

-- ------------------------------------------------
-- REWARDS
-- ------------------------------------------------
INSERT INTO `rewards` (`id`, `name`, `description`, `points_required`, `reward_type`, `discount_value`, `discount_type`, `stock`, `status`) VALUES
(1, 'Free Teh Tarik', 'Redeem for one glass of our signature Teh Tarik',         100, 'free_item',  NULL,  NULL,   50, 'active'),
(2, 'RM5 Off Voucher', 'Get RM5 off your next order, minimum spend RM20',          200, 'voucher',    5.00,  'fixed',  NULL, 'active'),
(3, 'Free Roti Canai', 'Enjoy a free Roti Canai with any beverage purchase',       150, 'free_item',  NULL,  NULL,  100, 'active'),
(4, 'RM10 Off Voucher','Get RM10 off your next order, minimum spend RM40',          400, 'voucher',   10.00, 'fixed',  NULL, 'active'),
(5, '10% Discount',    '10% off your entire bill, valid for dine-in only',          500, 'discount',  10.00, 'percent',NULL, 'active'),
(6, 'Free Dessert',    'Choose any dessert from our menu, complimentary',           350, 'free_item',  NULL,  NULL,   30, 'active'),
(7, 'Birthday Meal',   'Free main course on your birthday month',                  800, 'free_item',  NULL,  NULL,   20, 'active'),
(8, 'VIP Experience',  'Private dining experience for 2 with complimentary drinks', 2000, 'experience',NULL, NULL,    5, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;

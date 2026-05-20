-- LUCA GLOW PRODUCTION SEED SCRIPT
-- Copy and run this script in your Supabase SQL Editor to populate the database tables instantly!

-- Disable triggers/constraints for seeding
SET session_replication_role = 'replica';

-- 1. Seed Customer Groups
DELETE FROM customer_groups;
INSERT INTO customer_groups (id, name, color, discount_pct, auto_upgrade_threshold, created_at, updated_at) VALUES
(1, 'Regular', '#94a3b8', 0.00, NULL, NOW(), NOW()),
(2, 'VIP', '#f59e0b', 5.00, 10000, NOW(), NOW()),
(3, 'Wholesale', '#8b5cf6', 15.00, NULL, NOW(), NOW()),
(4, 'First-Time', '#10b981', 0.00, NULL, NOW(), NOW());

-- 2. Seed Categories
DELETE FROM categories;
INSERT INTO categories (id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at) VALUES
(1, NULL, 'Skincare & Face', 'skincare-face', 'Premium natural skincare and face creams for clean beauty.', 0, TRUE, NOW(), NOW()),
(2, NULL, 'Cleansing Soaps', 'cleansing-soaps', 'Organic cleansing soaps infused with natural oils.', 1, TRUE, NOW(), NOW()),
(3, NULL, 'Lykha Makeup', 'lykha-makeup', 'Luxury clean makeup collection by Lykha.', 2, TRUE, NOW(), NOW()),
(4, NULL, 'Fragrances', 'fragrances', 'Captivating clean fragrances made with natural essences.', 3, TRUE, NOW(), NOW()),
(5, NULL, 'Body & Hair Care', 'body-hair-care', 'Nourishing care routines for hair and body.', 4, TRUE, NOW(), NOW()),
(6, 5, 'For Men', 'men', 'Specially formulated grooming essentials for men.', 0, TRUE, NOW(), NOW());

-- 3. Seed Products
DELETE FROM products;
INSERT INTO products (id, category_id, sku, name, slug, type, price_inr, special_price, stock_quantity, low_stock_threshold, is_active, created_at, updated_at) VALUES
(1, 1, 'LG-FC-001', 'Luca Face Cream', 'luca-face-cream', 'simple', 1899, 1599, 234, 10, TRUE, NOW(), NOW()),
(2, 1, 'LG-SP-002', 'Sun Protection SPF 50+', 'sun-protection-spf-50', 'simple', 999, NULL, 112, 10, TRUE, NOW(), NOW()),
(3, 1, 'LG-KF-003', 'Kojic Facewash', 'kojic-facewash', 'simple', 349, NULL, 8, 10, TRUE, NOW(), NOW()),
(4, 2, 'LG-GS-004', 'Gluta Soap', 'gluta-soap', 'simple', 350, 299, 545, 10, TRUE, NOW(), NOW()),
(5, 2, 'LG-KS-005', 'Kojic Soap', 'kojic-soap', 'simple', 350, NULL, 321, 10, TRUE, NOW(), NOW()),
(6, 3, 'LY-FR-006', 'Lykha Foundation — Rosy Brown', 'lykha-foundation-rosy-brown', 'simple', 2499, NULL, 67, 10, TRUE, NOW(), NOW()),
(7, 3, 'LY-FD-007', 'Lykha Foundation — Dust', 'lykha-foundation-dust', 'simple', 2499, NULL, 42, 10, TRUE, NOW(), NOW()),
(8, 3, 'LY-LS-008', 'Lykha 4-in-1 Lipstick', 'lykha-4-in-1-lipstick', 'simple', 999, NULL, 189, 10, TRUE, NOW(), NOW()),
(9, 4, 'LG-PF-009', 'Luca Perfume (Full Size)', 'luca-perfume-full-size', 'simple', 999, NULL, 0, 10, TRUE, NOW(), NOW()),
(10, 4, 'LG-PP-010', 'Luca Pocket Perfume', 'luca-pocket-perfume', 'simple', 399, NULL, 301, 10, TRUE, NOW(), NOW()),
(11, 5, 'LG-BO-011', 'Luca Beard Oil', 'luca-beard-oil', 'simple', 1299, NULL, 156, 10, FALSE, NOW(), NOW()),
(12, 5, 'LG-HO-012', 'Luca Nourishing Hair Oil', 'luca-nourishing-hair-oil', 'simple', 799, NULL, 278, 10, TRUE, NOW(), NOW());

-- 4. Seed Coupons
DELETE FROM coupons;
INSERT INTO coupons (id, code, type, value, min_cart_value, usage_limit, usage_per_customer, used_count, is_active, starts_at, expires_at, created_at, updated_at) VALUES
(1, 'GLOW2026', 'percentage', 15, 999, 500, 1, 0, TRUE, NOW(), '2026-12-31 23:59:59', NOW(), NOW()),
(2, 'WELCOME100', 'fixed', 100, 599, 1000, 1, 0, TRUE, NOW(), '2026-12-31 23:59:59', NOW(), NOW()),
(3, 'LYKHA10', 'percentage', 10, 2000, 300, 1, 0, TRUE, NOW(), '2026-12-31 23:59:59', NOW(), NOW()),
(4, 'FREESHIP', 'fixed', 79, 499, NULL, 1, 0, TRUE, NOW(), NULL, NOW(), NOW());

-- 5. Seed Sliders
DELETE FROM sliders;
INSERT INTO sliders (id, title, subtitle, button_text, link_url, sort_order, is_active, created_at, updated_at) VALUES
(1, 'Feel The Change', 'Clean Beauty for Everyone', 'Shop Now', '/catalog', 1, TRUE, NOW(), NOW()),
(2, 'Glow Beyond Limits', 'Lykha Makeup Collection', 'Explore', '/collections/lykha-makeup', 2, TRUE, NOW(), NOW()),
(3, 'Refined Grooming', 'For the Modern Man', 'Discover', '/collections/men', 3, TRUE, NOW(), NOW());

-- 6. Seed Global Settings
DELETE FROM settings;
INSERT INTO settings (id, key, value, "group", created_at, updated_at) VALUES
(1, 'store_name', 'Luca Glow', 'general', NOW(), NOW()),
(2, 'tagline', 'Feel The Change', 'general', NOW(), NOW()),
(3, 'support_email', 'hello@lucaglow.com', 'general', NOW(), NOW()),
(4, 'support_phone', '+91 95670 46209', 'general', NOW(), NOW()),
(5, 'address', 'Kozhikode, Kerala, India — 673001', 'general', NOW(), NOW()),
(6, 'maintenance', '0', 'general', NOW(), NOW());

-- Enable triggers/constraints back
SET session_replication_role = 'origin';

-- Adjust PostgreSQL sequences so that future auto-increments continue correctly
SELECT setval('customer_groups_id_seq', (SELECT MAX(id) FROM customer_groups));
SELECT setval('categories_id_seq', (SELECT MAX(id) FROM categories));
SELECT setval('products_id_seq', (SELECT MAX(id) FROM products));
SELECT setval('coupons_id_seq', (SELECT MAX(id) FROM coupons));
SELECT setval('sliders_id_seq', (SELECT MAX(id) FROM sliders));
SELECT setval('settings_id_seq', (SELECT MAX(id) FROM settings));

-- Commit complete message
SELECT '✅ Luca Glow database seed executed successfully!' AS status;

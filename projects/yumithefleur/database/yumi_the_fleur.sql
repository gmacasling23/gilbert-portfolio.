-- ============================================================
-- YUMI THE FLEUR - RECOVERED / UPDATED DATABASE SCHEMA
-- ============================================================
-- Based on the latest Yumi database export currently recovered
-- from the saved project files.
--
-- Database used by the local project:
--     yumithefleur2_db
--
-- IMPORTANT:
-- 1. This script is designed to preserve existing data.
-- 2. It creates missing tables but does NOT delete users/orders.
-- 3. It upgrades the order status list so the recovered admin
--    pages can use: Pending, In Progress, Completed, Cancelled.
-- 4. Uploaded commission reference images remain stored in the
--    orders.details JSON field.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `yumithefleur2_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `yumithefleur2_db`;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `order_type` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `details` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user_id` (`user_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created_at` (`created_at`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UPGRADE EXISTING ORDERS TABLE
-- ============================================================
-- The recovered export only allowed Pending, Completed and
-- Cancelled, while the recovered admin interface also supports
-- In Progress. This ALTER synchronizes the database with the admin.
ALTER TABLE `orders`
  MODIFY COLUMN `status`
  ENUM('Pending','In Progress','Completed','Cancelled')
  NOT NULL DEFAULT 'Pending';

-- ============================================================
-- OPTIONAL INDEXES
-- ============================================================
-- Safe because the CREATE TABLE above already creates these for
-- a fresh installation. Existing installations may already have
-- them, so these are intentionally omitted to avoid duplicate-index
-- errors.

-- ============================================================
-- DATA FORMAT USED BY orders.details
-- ============================================================
-- The commission submission PHP stores JSON similar to:
-- {
--   "customer": {
--      "name": "Customer Name",
--      "email": "customer@example.com"
--   },
--   "character": {
--      "name": "Character Name",
--      "gender": "...",
--      "description": "..."
--   },
--   "commission": {
--      "style": "...",
--      "pose": "...",
--      "background": "...",
--      "palette": ["...", "..."]
--   },
--   "references": [
--      "uploads/ref_....png",
--      "uploads/ref_....jpg"
--   ]
-- }
--
-- The References admin page reads the "references" array from this
-- JSON column and displays the uploaded images.

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================
-- Run these after importing to check the recovered structure.
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users','orders')
ORDER BY TABLE_NAME;

SHOW COLUMNS FROM `users`;
SHOW COLUMNS FROM `orders`;

SELECT COUNT(*) AS total_users FROM `users`;
SELECT COUNT(*) AS total_orders FROM `orders`;
SELECT status, COUNT(*) AS total
FROM `orders`
GROUP BY status
ORDER BY status;

-- Check which orders contain uploaded references.
SELECT
  id,
  user_id,
  order_type,
  status,
  JSON_LENGTH(JSON_EXTRACT(details, '$.references')) AS reference_count,
  created_at
FROM `orders`
ORDER BY id DESC;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- EMBEDDED ADMIN ACCOUNT
-- ============================================================
-- Password is intentionally stored only as a one-way password hash.
-- Existing admin password is preserved if the account already exists.
INSERT INTO `users` (`username`, `email`, `password`, `is_admin`)
VALUES ('admin', 'admin@yumithefleur.shop', '$2y$12$kQfQzbn.mNjfqgUu6OCXcewuvvH2bMfFdHOGRDq8AmRjdYRnG1IKG', 1)
ON DUPLICATE KEY UPDATE
  `is_admin` = 1,
  `email` = VALUES(`email`);


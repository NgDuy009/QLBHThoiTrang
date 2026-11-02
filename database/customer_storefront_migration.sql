-- WinG Customer Storefront migration
USE fsms_db;
ALTER TABLE customers
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER email,
  ADD COLUMN account_status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER password_hash;
ALTER TABLE orders MODIFY user_id INT NULL;

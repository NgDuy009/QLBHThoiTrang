-- PATCH for WinG FSMS PRO: create only new checkout settings table.
-- BACKUP fsms_db before importing. Safe to run more than once.
-- DO NOT reimport database/fsms.sql, which contains DROP TABLE commands.
USE fsms_db;
CREATE TABLE IF NOT EXISTS wing_payment_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  bank_id VARCHAR(12) NOT NULL,
  account_number VARCHAR(25) NOT NULL,
  account_holder VARCHAR(120) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

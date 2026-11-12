-- WinG mobile confirmation: run once on existing fsms_db. Does not delete existing data.
-- Requires the order/payment and wing_payment_settings migrations from PRO/checkout fix.
USE fsms_db;

CREATE TABLE IF NOT EXISTS wing_mobile_payment_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  base_url VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wing_payment_mobile_tokens (
  order_id INT NOT NULL PRIMARY KEY,
  token CHAR(48) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wing_mobile_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

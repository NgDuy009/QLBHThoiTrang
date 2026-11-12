-- WinG Pro upgrade, BACK UP fsms_db and import ONCE after existing migrations.
-- NEVER import fsms.sql on an existing site: it drops data.
USE fsms_db;

-- Reject duplicate customer email registrations at database level. Resolve any existing duplicates before import.
ALTER TABLE customers ADD UNIQUE KEY uq_wing_customer_email(email);

CREATE TABLE IF NOT EXISTS customer_cart_items (
  customer_id INT NOT NULL,
  variant_id INT NOT NULL,
  quantity INT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id, variant_id),
  CONSTRAINT fk_wing_cart_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_wing_cart_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  CHECK(quantity BETWEEN 1 AND 20)
) ENGINE=InnoDB;

ALTER TABLE orders
  MODIFY COLUMN status ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending',
  ADD COLUMN payment_reference VARCHAR(8) NULL,
  ADD COLUMN payment_claimed_at DATETIME NULL,
  ADD COLUMN payment_verified_at DATETIME NULL,
  ADD COLUMN payment_verified_by INT NULL,
  ADD COLUMN shipping_carrier VARCHAR(100) NULL,
  ADD COLUMN tracking_code VARCHAR(100) NULL,
  ADD UNIQUE KEY uq_order_payment_reference(payment_reference),
  ADD CONSTRAINT fk_wing_payment_reviewer FOREIGN KEY(payment_verified_by) REFERENCES users(id) ON DELETE SET NULL;

-- Assign reference codes to bank-transfer orders created before this upgrade.
UPDATE orders SET payment_reference=UPPER(SUBSTRING(SHA2(CONCAT(order_code,'-',id,'-WING-PRO'),256),1,8))
WHERE payment_method='bank_transfer' AND payment_reference IS NULL;


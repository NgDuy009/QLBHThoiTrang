-- WinG customer account and wishlist enhancements; safe to run on existing FSMS.
USE fsms_db;
CREATE TABLE IF NOT EXISTS customer_wishlist (
 id INT AUTO_INCREMENT PRIMARY KEY,
 customer_id INT NOT NULL,
 product_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_customer_product (customer_id, product_id),
 CONSTRAINT fk_wishlist_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_wishlist_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

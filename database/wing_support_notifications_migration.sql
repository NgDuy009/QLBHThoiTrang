-- WinG support desk, notification center and variant image support.
-- Run once after wing_commerce_upgrade.sql and wing_catalog_reviews_migration.sql.
USE fsms_db;

ALTER TABLE product_variants ADD COLUMN image_url VARCHAR(500) NULL AFTER color;

CREATE TABLE IF NOT EXISTS wing_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 recipient_type ENUM('customer','user') NOT NULL,
 recipient_id INT NOT NULL,
 kind VARCHAR(40) NOT NULL,
 title VARCHAR(180) NOT NULL,
 body VARCHAR(500) NOT NULL,
 link VARCHAR(500) NOT NULL,
 dedupe_key VARCHAR(180) NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_wing_notification_dedupe(dedupe_key),
 KEY idx_wing_notification_recipient(recipient_type,recipient_id,is_read,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_code VARCHAR(32) NOT NULL UNIQUE,
 customer_id INT NOT NULL,
 subject VARCHAR(180) NOT NULL,
 category VARCHAR(40) NOT NULL,
 priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
 status ENUM('new','assigned','waiting_customer','escalated_admin','resolved') NOT NULL DEFAULT 'new',
 assigned_staff_id INT NULL,
 handled_by_admin_id INT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_support_customer(customer_id,created_at),
 KEY idx_support_queue(status,assigned_staff_id,updated_at),
 CONSTRAINT fk_support_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_support_staff FOREIGN KEY(assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_support_admin FOREIGN KEY(handled_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('customer','user') NOT NULL,
 sender_id INT NOT NULL,
 sender_name VARCHAR(120) NOT NULL,
 sender_role VARCHAR(30) NOT NULL,
 message TEXT NOT NULL,
 is_internal TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_support_messages_ticket(ticket_id,created_at),
 CONSTRAINT fk_support_message_ticket FOREIGN KEY(ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing completed purchases also receive a review invitation, once per order/product.
INSERT IGNORE INTO wing_notifications(recipient_type,recipient_id,kind,title,body,link,dedupe_key)
SELECT 'customer',o.customer_id,'review_request','Bạn có thể đánh giá sản phẩm đã mua',
       CONCAT('Đơn ',o.order_code,' đã hoàn thành. Hãy chia sẻ trải nghiệm về ',d.product_name,'.'),
       CONCAT('index.php?page=product&id=',d.product_id,'&review_order_id=',o.id,'#customer-reviews'),
       CONCAT('review:',o.id,':',d.product_id)
FROM orders o
JOIN order_details d ON d.order_id=o.id
LEFT JOIN product_reviews r ON r.order_id=o.id AND r.product_id=d.product_id
WHERE o.status='completed' AND o.customer_id IS NOT NULL AND r.id IS NULL;

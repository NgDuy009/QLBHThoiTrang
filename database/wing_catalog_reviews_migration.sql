-- WinG catalog categories and verified customer reviews. Run once after wing_commerce_upgrade.sql.
USE fsms_db;
INSERT IGNORE INTO categories(name,description) VALUES
('Áo Khoác','Blazer, jacket, trench coat, gile và outerwear.'),
('Áo Thun','T-shirt, polo và áo casual.'),
('Áo Thể Thao','Trang phục tập luyện và vận động.'),
('Áo Sơ Mi','Sơ mi công sở và smart casual.'),
('Quần','Quần jeans, quần tây, chino, cargo và short.'),
('Váy & Đầm','Váy và đầm nữ.'),
('Phụ Kiện','Túi, ví, nón, khăn và phụ kiện.');
UPDATE products p JOIN categories c ON c.name=CASE
 WHEN p.name LIKE '%Khoác%' OR p.name LIKE '%Blazer%' OR p.name LIKE '%Trench%' OR p.name LIKE '%Cardigan%' OR p.name LIKE '%Gile%' THEN 'Áo Khoác'
 WHEN p.name LIKE '%Thể Thao%' OR p.name LIKE '%Sport%' OR p.name LIKE '%Training%' THEN 'Áo Thể Thao'
 WHEN p.name LIKE '%Thun%' OR p.name LIKE '%Polo%' THEN 'Áo Thun'
 WHEN p.name LIKE '%Sơ Mi%' OR p.name LIKE '%Blouse%' THEN 'Áo Sơ Mi'
 WHEN p.name LIKE '%Quần%' OR p.name LIKE '%Jeans%' THEN 'Quần'
 WHEN p.name LIKE '%Đầm%' OR p.name LIKE '%Váy%' OR p.name LIKE '%Chân Váy%' THEN 'Váy & Đầm'
 WHEN p.name LIKE '%Túi%' OR p.name LIKE '%Ví%' OR p.name LIKE '%Nón%' OR p.name LIKE '%Khăn%' OR p.name LIKE '%Thắt Lưng%' THEN 'Phụ Kiện'
 ELSE 'Phụ Kiện' END
 SET p.category_id=c.id
 WHERE p.id>0 AND (
 p.name LIKE '%Khoác%' OR p.name LIKE '%Blazer%' OR p.name LIKE '%Trench%' OR p.name LIKE '%Cardigan%' OR p.name LIKE '%Gile%' OR
 p.name LIKE '%Thể Thao%' OR p.name LIKE '%Sport%' OR p.name LIKE '%Training%' OR
 p.name LIKE '%Thun%' OR p.name LIKE '%Polo%' OR p.name LIKE '%Sơ Mi%' OR p.name LIKE '%Blouse%' OR
 p.name LIKE '%Quần%' OR p.name LIKE '%Jeans%' OR p.name LIKE '%Đầm%' OR p.name LIKE '%Váy%' OR
 p.name LIKE '%Túi%' OR p.name LIKE '%Ví%' OR p.name LIKE '%Nón%' OR p.name LIKE '%Khăn%' OR p.name LIKE '%Thắt Lưng%');
CREATE TABLE IF NOT EXISTS product_reviews (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL,
 customer_id INT NOT NULL,
 order_id INT NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 review_text VARCHAR(1000) NULL,
 status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_review_order_product(order_id,product_id),
 KEY idx_review_product_status(product_id,status),
 CONSTRAINT fk_review_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 CONSTRAINT fk_review_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_review_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CHECK(rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

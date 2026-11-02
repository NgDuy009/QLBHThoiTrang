CREATE DATABASE IF NOT EXISTS fsms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fsms_db;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS stock_transactions;
DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS product_variants;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 username VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 description TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
 id INT AUTO_INCREMENT PRIMARY KEY,
 category_id INT NULL,
 sku VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 price DECIMAL(12,2) NOT NULL DEFAULT 0,
 cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
 stock_quantity INT NOT NULL DEFAULT 0,
 image_url VARCHAR(500) NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_products_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL,
 CHECK(price>=0), CHECK(cost_price>=0), CHECK(stock_quantity>=0)
) ENGINE=InnoDB;

CREATE TABLE product_variants (
 id INT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL,
 sku VARCHAR(60) NOT NULL UNIQUE,
 size VARCHAR(30) NOT NULL,
 color VARCHAR(50) NOT NULL,
 stock_quantity INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_variant_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 CHECK(stock_quantity>=0)
) ENGINE=InnoDB;

CREATE TABLE customers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 phone VARCHAR(30) NULL,
 email VARCHAR(150) NULL,
 password_hash VARCHAR(255) NULL,
 account_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 address VARCHAR(255) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE orders (
 id INT AUTO_INCREMENT PRIMARY KEY,
 order_code VARCHAR(40) NOT NULL UNIQUE,
 customer_id INT NULL,
 user_id INT NULL,
 total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_order_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_order_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_details (
 id INT AUTO_INCREMENT PRIMARY KEY,
 order_id INT NOT NULL,
 product_id INT NULL,
 product_name VARCHAR(180) NOT NULL,
 sku VARCHAR(60) NOT NULL,
 quantity INT NOT NULL,
 unit_price DECIMAL(12,2) NOT NULL,
 line_total DECIMAL(12,2) NOT NULL,
 CONSTRAINT fk_detail_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_detail_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 CHECK(quantity>0)
) ENGINE=InnoDB;

CREATE TABLE stock_transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL,
 user_id INT NOT NULL,
 transaction_type ENUM('in','out') NOT NULL,
 quantity INT NOT NULL,
 note VARCHAR(255) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_stock_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_stock_user FOREIGN KEY(user_id) REFERENCES users(id),
 CHECK(quantity>0)
) ENGINE=InnoDB;

INSERT INTO categories(name,description) VALUES
('Áo','Áo thun, sơ mi, áo kiểu'),
('Quần','Quần jeans, quần tây, quần short'),
('Váy & Đầm','Váy và đầm thời trang'),
('Phụ kiện','Túi, thắt lưng và phụ kiện');

INSERT INTO products(category_id,sku,name,description,price,cost_price,stock_quantity,image_url,status) VALUES
(1,'AT001','Áo thun Essential','Áo thun cotton form regular.',249000,125000,35,'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=700','active'),
(1,'SM002','Sơ mi Linen Classic','Sơ mi linen phong cách tối giản.',459000,245000,18,'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=700','active'),
(2,'QJ003','Quần Jeans Straight','Quần jeans ống đứng.',599000,320000,12,'https://images.unsplash.com/photo-1542272604-787c3835535d?w=700','active'),
(3,'VD004','Đầm Midi Minimal','Đầm midi thanh lịch.',799000,420000,7,'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=700','active'),
(4,'PK005','Túi Canvas Daily','Túi canvas dùng hằng ngày.',329000,150000,4,'https://images.unsplash.com/photo-1544816155-12df9643f363?w=700','active');

INSERT INTO product_variants(product_id,sku,size,color,stock_quantity) VALUES
(1,'AT001-W-S','S','Trắng',10),(1,'AT001-W-M','M','Trắng',15),(1,'AT001-B-M','M','Đen',10),
(2,'SM002-W-M','M','Trắng',8),(2,'SM002-B-L','L','Be',10),
(3,'QJ003-B-30','30','Xanh denim',6),(3,'QJ003-B-32','32','Xanh denim',6),
(4,'VD004-B-S','S','Đen',4),(4,'VD004-B-M','M','Đen',3);

INSERT INTO customers(full_name,phone,email,address) VALUES
('Nguyễn Minh Anh','0900000001','minhanh@example.test','Cà Mau'),
('Trần Gia Hân','0900000002','giahan@example.test','Bạc Liêu'),
('Lê Hoàng Nam','0900000003','hoangnam@example.test','Cần Thơ');

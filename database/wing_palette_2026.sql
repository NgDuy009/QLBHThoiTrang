-- WinG 2026 COLOR PALETTE: backup fsms_db before importing.
-- Safe to import again: only adds the column when it is missing.
SET @wing_hex_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND COLUMN_NAME = 'color_hex');
SET @wing_hex_sql := IF(@wing_hex_exists = 0,
 'ALTER TABLE `product_variants` ADD COLUMN `color_hex` CHAR(7) NULL AFTER `color`',
 'SELECT 1');
PREPARE wing_hex_stmt FROM @wing_hex_sql;
EXECUTE wing_hex_stmt;
DEALLOCATE PREPARE wing_hex_stmt;

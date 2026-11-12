-- WinG FSMS: form nhập nội dung 8 ký tự -> cấp mã 8 ký tự MỚI -> dán vào đơn hàng.
-- Chỉ bổ sung bảng. KHÔNG xóa dữ liệu, đơn hàng hoặc cấu hình ngân hàng hiện tại.
-- Yêu cầu đã cài migration wing_mobile_payment_2026.sql trước đó.
USE fsms_db;

CREATE TABLE IF NOT EXISTS wing_payment_confirmation_codes (
  order_id INT NOT NULL PRIMARY KEY,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  claim_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until DATETIME NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wing_confirmation_code_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

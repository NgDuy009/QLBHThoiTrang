# WinG – Ma trận phân quyền & đồng bộ dữ liệu

## 1. Một nguồn dữ liệu chung
- Admin và Staff sử dụng cùng database `fsms_db`; các thay đổi sản phẩm, danh mục, tồn kho, khách hàng và đơn hàng được các tài khoản quản trị nhìn thấy ở lần tải trang tiếp theo.
- Khách hàng chỉ truy cập cửa hàng công khai và dữ liệu cá nhân của chính tài khoản.
- Đơn online lưu `customer_id`, để xem đơn và chi tiết đơn hệ thống luôn lọc theo khách đang đăng nhập.
- Đơn tại quầy lưu `user_id` của nhân viên tạo đơn. Đơn online có `source=online`, đơn quầy `source=pos`.

## 2. Ma trận quyền

| Chức năng | Admin | Staff | Customer |
|---|---|---|---|
| Dashboard | Toàn bộ KPI | KPI vận hành | Không |
| Sản phẩm | Tạo/sửa/xóa, giá, ảnh, công khai/ẩn | Xem dữ liệu mới nhất | Xem sản phẩm đã công khai |
| Danh mục | Tạo/sửa/xóa | Xem | Lọc/xem |
| Size & màu | Tạo/sửa/xóa biến thể | Xem | Chọn biến thể còn hàng |
| Nhân viên | Quản lý tài khoản/vai trò | Không | Không |
| Khách hàng | Xem/sửa/xóa | Xem/tạo hồ sơ bán hàng | Chỉ hồ sơ của mình |
| Đơn hàng | Xem mọi nguồn, đổi trạng thái | Tạo đơn POS, xem và cập nhật trạng thái | Tạo đơn online, xem/hủy đơn pending của mình |
| Kho hàng | Nhập/xuất/điều chỉnh theo biến thể | Nhập/xuất theo nghiệp vụ | Không thấy tồn kho nội bộ |
| Báo cáo | Toàn bộ doanh thu và hiệu suất | Báo cáo vận hành | Không |
| Cài đặt | Toàn quyền | Không | Không |
| Yêu thích | Không áp dụng | Không áp dụng | Danh sách riêng |
| Giỏ hàng/checkout | Không áp dụng | Không áp dụng | Đặt hàng COD/chuyển khoản demo |

## 3. Nguồn tồn kho
- `product_variants.stock_quantity` là tồn kho chuẩn theo size/màu.
- `products.stock_quantity` là tổng tồn được đồng bộ từ tổng biến thể.
- Đặt hàng online/POS trừ đúng biến thể trong transaction.
- Hủy đơn chưa hoàn thành sẽ hoàn lại đúng biến thể một lần; đơn đã hủy không thể mở lại bằng luồng cơ bản.

## 4. Công khai sản phẩm
- Admin quản lý `products.status` (hoạt động/ngừng) và `products.is_published` (công khai/bản nháp).
- Khách chỉ thấy sản phẩm có `status='active' AND is_published=1`.
- Khi Admin sửa giá, tên, ảnh, mô tả, bộ sưu tập hoặc trạng thái công khai, Staff và cửa hàng dùng cùng dữ liệu khi tải lại trang.

## 5. Đăng nhập & tách phiên
- Cổng chung: `index.php?page=login`.
- Admin/Staff dùng username trong bảng `users`.
- Customer dùng email trong bảng `customers`.
- Đăng nhập vai trò mới sẽ thay thế phiên vai trò trước đó trong cùng trình duyệt.
- Mọi thao tác ghi dữ liệu đều kiểm tra CSRF và quyền phía PHP; không chỉ ẩn nút ở giao diện.


## 6. Hỗ trợ khách hàng & thông báo
- Customer chỉ tạo/xem/phản hồi ticket có `customer_id` đúng bằng phiên đăng nhập; không xem hồ sơ khách khác.
- Staff chỉ nhận ticket mới chưa được gán hoặc ticket đã gán cho chính mình; không đóng ticket.
- Staff có thể trả lời khách và tạo ghi chú nội bộ để chuyển Admin.
- Admin xem toàn bộ ticket, đọc ghi chú nội bộ, phản hồi và đóng yêu cầu.
- `wing_notifications` tách người nhận bằng `recipient_type` + `recipient_id`; customer, staff và admin chỉ đọc thông báo của chính tài khoản.
- Lời mời đánh giá được tạo khi đơn chuyển sang `completed`, chỉ cho sản phẩm có trong `order_details` và chưa có review; khách gửi đánh giá phải sở hữu đơn hoàn thành đó.

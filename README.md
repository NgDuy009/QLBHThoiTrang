# Website Quản Lý Bán Hàng Thời Trang (QLBHThoiTrang)

Dự án đồ án môn học xây dựng hệ thống website hỗ trợ quản lý kinh doanh và bán hàng thời trang trực tuyến, giúp số hóa quy trình quản lý sản phẩm, đơn hàng và báo cáo doanh thu.

## Công nghệ sử dụng
- **Backend:** PHP thuần
- **Frontend:** HTML5, CSS3, JavaScript
- **Cơ sở dữ liệu:** MySQL
- **Môi trường phát triển:** XAMPP (Apache & MySQL), Visual Studio Code
- **Quản lý mã nguồn:** Git & GitHub

## Cấu trúc thư mục chính
- `index.php`: Trang điều hướng chính của hệ thống.
- `config/`: Chứa tệp cấu hình kết nối cơ sở dữ liệu MySQL.
- `database/`: Chứa tệp `.sql` khởi tạo bảng và dữ liệu mẫu.
- `includes/`: Các thành phần giao diện dùng chung (Header, Navbar, Footer).
- `pages/`: Các trang chức năng chính (Sản phẩm, Giỏ hàng, Thanh toán, Thống kê doanh thu `thong_ke.php`).
- `assets/` & `uploads/`: Lưu trữ tệp định dạng CSS, JS và hình ảnh sản phẩm.

## Chức năng nổi bật
1. **Phân hệ Khách hàng:** Đăng ký, đăng nhập, xem danh sách sản phẩm theo danh mục, tìm kiếm sản phẩm, quản lý giỏ hàng và đặt hàng trực tuyến.
2. **Phân hệ Quản trị viên:** Quản lý danh mục, thêm/sửa/xóa sản phẩm, cập nhật trạng thái đơn hàng.
3. **Báo cáo & Thống kê:** Hiển thị biểu đồ doanh thu trực quan theo tháng, lọc dữ liệu nhanh và hỗ trợ xuất báo cáo ra file Excel (CSV) hoặc in PDF.

## Hướng dẫn cài đặt chạy trên Localhost
1. Cài đặt **XAMPP** và khởi động hai dịch vụ **Apache** và **MySQL**.
2. Đặt mã nguồn vào thư mục `C:\xampp\htdocs\QLBHThoiTrang`.
3. Mở `http://localhost/phpmyadmin`, tạo database mới và Import tệp SQL trong thư mục `database/`.
4. Truy cập website tại địa chỉ: `http://localhost/QLBHThoiTrang`.

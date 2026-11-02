# FSMS – Fashion Store Management System

Ứng dụng quản lý bán hàng thời trang phục vụ đồ án môn Quản trị dự án CNTT.

## 1. Công nghệ
- HTML5, CSS3, JavaScript
- PHP 8.x (PDO)
- MySQL 8.x / MariaDB
- XAMPP, Visual Studio Code, Git/GitHub

## 2. Chức năng
1. Đăng nhập/đăng xuất và phân quyền admin/staff
2. Dashboard tổng quan
3. Quản lý sản phẩm
4. Quản lý danh mục
5. Quản lý biến thể size/màu
6. Quản lý khách hàng
7. Quản lý nhân viên (admin)
8. Quản lý đơn hàng, chi tiết đơn và trừ tồn kho khi tạo đơn
9. Nhập/xuất kho và lịch sử giao dịch
10. Báo cáo doanh thu và sản phẩm bán chạy

## 3. Cài đặt bằng XAMPP
1. Cài XAMPP có Apache, MySQL và PHP.
2. Giải nén thư mục `FSMS` vào `C:\xampp\htdocs\FSMS`.
3. Mở XAMPP Control Panel, bật **Apache** và **MySQL**.
4. Mở `http://localhost/phpmyadmin`.
5. Chọn **Import**, chọn file `database/fsms.sql`, nhấn Import. Script sẽ tạo database `fsms_db`, bảng và dữ liệu mẫu.
6. Mở `config/config.php`. Mặc định XAMPP thường dùng:
   - DB_HOST: `127.0.0.1`
   - DB_NAME: `fsms_db`
   - DB_USER: `root`
   - DB_PASS: chuỗi rỗng (chỉ phù hợp môi trường XAMPP local mặc định).
7. Truy cập `http://localhost/FSMS/setup.php` để tạo tài khoản quản trị đầu tiên.
8. Đăng nhập tại `http://localhost/FSMS/index.php?page=login`.
9. **Xóa file `setup.php` ngay sau khi tạo admin.**

## 4. Tài khoản và dữ liệu demo
Tài khoản Admin được tạo trong lần chạy `setup.php`. Hãy tự đặt mật khẩu mạnh (ít nhất 10 ký tự) và tạo các tài khoản kiểm thử riêng trong môi trường local. Không lưu hoặc công khai mật khẩu trong README, source code hoặc repository.
Dữ liệu danh mục, sản phẩm, biến thể và khách hàng mẫu được nạp từ SQL. Đơn hàng ban đầu để trống; chỉ tạo đơn kiểm thử trong môi trường local.

## 5. Cấu trúc thư mục
```text
FSMS/
├── assets/css, assets/js
├── config/
├── database/fsms.sql
├── includes/
├── pages/
├── uploads/
├── index.php
├── setup.php
└── README.md
```

## 6. Bảo mật cơ bản đã áp dụng
- PDO prepared statements cho truy vấn có dữ liệu đầu vào.
- `password_hash()` và `password_verify()`.
- Session cookie HttpOnly, SameSite=Lax; regenerate session ID khi đăng nhập.
- CSRF token cho biểu mẫu POST.
- Escape dữ liệu hiển thị bằng `htmlspecialchars`.
- Kiểm tra đăng nhập và quyền admin ở phía máy chủ.
- Giao dịch DB và khóa hàng khi tạo đơn/điều chỉnh tồn kho.
- Không commit mật khẩu hoặc cấu hình nhạy cảm.

## 7. Lưu ý và giới hạn phiên bản học tập
- Chưa tích hợp cổng thanh toán, vận chuyển, hóa đơn điện tử hoặc email.
- Biến thể size/màu được quản lý riêng; phiên bản hiện tại trừ tồn kho cấp sản phẩm khi tạo đơn. Nếu nhóm muốn tồn kho theo từng biến thể, cần bổ sung luồng chọn variant vào đơn hàng.
- Trạng thái đơn hiện cho phép admin cập nhật; hủy đơn chưa tự động hoàn tồn kho. Cần bổ sung nghiệp vụ hoàn kho trước khi dùng thực tế.
- Ảnh sản phẩm đang dùng URL; chưa có upload file an toàn.
- Cần kiểm thử trên môi trường PHP/MySQL cụ thể của nhóm.

## 8. Chia việc GitHub gợi ý
- TV1: quản trị dự án, README, rà soát tích hợp.
- TV2: đặc tả yêu cầu, Use Case, Activity, Sequence.
- TV3: Figma, design system, đối chiếu UI.
- TV4: PHP, MySQL, bảo mật, nghiệp vụ.
- TV5: HTML/CSS/JS, test case, kiểm thử và báo lỗi.

Mỗi thành viên cần commit phần việc thực tế bằng tài khoản GitHub riêng. Không tạo commit hình thức.


## WinG Customer Storefront

### URL khách hàng
- Cửa hàng: `http://localhost/FSMS/index.php?page=shop`
- Đăng ký: `http://localhost/FSMS/index.php?page=customer_register`
- Đăng nhập: `http://localhost/FSMS/index.php?page=customer_login`
- Túi đồ: `http://localhost/FSMS/index.php?page=cart`
- Đơn hàng của tôi: `http://localhost/FSMS/index.php?page=customer_orders`
- Cổng nhân viên: `http://localhost/FSMS/index.php?page=login`

### Database
Nếu cài mới, import `database/fsms.sql` (đã bao gồm trường tài khoản khách và cho phép đơn online không gắn nhân viên tạo đơn).

Nếu đã có database FSMS đang chạy, chỉ import **một lần** file `database/customer_storefront_migration.sql` trong phpMyAdmin. Không import lại `fsms.sql` vì file đó khởi tạo lại schema và dữ liệu demo.

### Tài khoản khách
Không dùng tài khoản Admin để đăng nhập cửa hàng. Khách tự đăng ký tại trang Đăng ký; mật khẩu được lưu bằng `password_hash()`. Sau khi đăng nhập, khách có thể thêm sản phẩm vào túi đồ, đặt hàng COD và xem lịch sử đơn hàng.

Đơn khách đặt được lưu vào bảng `orders` với trạng thái `pending`, liên kết tới `customers`, chi tiết lưu trong `order_details`, đồng thời trừ tồn kho trong cùng transaction. Đơn online xuất hiện trong trang quản trị Đơn hàng với nhân viên hiển thị là `Online Store`.

### Ghi chú triển khai
Đây là bản demo học thuật, chưa tích hợp cổng thanh toán, vận chuyển hoặc email xác nhận. Cần kiểm thử trên XAMPP trước khi sử dụng thực tế.

\n## WinG Customer experience update\n\nFor an existing installation, import `database/customer_wing_features_migration.sql` once in phpMyAdmin after the original customer account migration has already been applied. It creates the persistent wishlist table.\n\nCustomer URLs: `?page=shop`, `?page=customer_profile`, `?page=customer_orders`, `?page=wishlist`. Customers can edit profile/contact information, optionally change password, save favorites, view order details, and cancel orders while their status is pending. Cancelling a pending customer order restores product stock within a database transaction.\n\nStorefront navigation no longer includes the Story section. Search, category filters, sort by latest/price/name, sticky header, reveal animations and reduced-motion support are included.\n
## WinG storefront UX update (November 2026)
- Removed the "Câu chuyện" navigation and section; navigation now focuses on shop, collections and new arrivals.
- Rebalanced the hero area and brought catalog controls/products higher in the page.
- Added catalog sort (newest, price ascending/descending, name A–Z), existing category/search filters, and favorite buttons.
- Added customer account profile editing (name, phone, address, optional password update), saved favorites, order history, order details, and cancellation for pending orders.
- Cancelling a pending customer order restores product stock in the same transaction.
- Added a compact customer account dropdown, responsive account sidebar, product reveal transitions and reduced-motion support.


## WinG unified sign-in and role separation

- Shared sign-in page: `http://localhost/FSMS/index.php?page=login`.
- Admin/Staff sign in with their `users.username`.
- Customers sign in with their `customers.email`.
- After authentication, Admin and Staff enter the management area; Customers are redirected to the public shop.
- Customer registration is available from the shared sign-in page and the shop account area.
- Role access is documented in `docs/ROLE_PERMISSION_MATRIX.md` and enforced in PHP routes and POST handlers.
- Admin: all management modules, employees, settings, catalogue editing and stock adjustments.
- Staff: daily dashboard, read-only catalogue/variants, create customer records, create/view orders, view stock and operational reports. No employee/settings access, product/catalogue editing or manual stock adjustment.
- Customer: public catalogue, cart, COD order, own profile, own order history, pending-order cancellation and personal wishlist. Customer sessions cannot open the Admin workspace.
- Cinematic WinG introduction: ~3 seconds on first entry in each browser tab, then suppressed while navigating within that tab. The intro uses CSS glow/ember effects and respects reduced-motion preferences.

### Create local demo accounts

Copy `create_demo_accounts.php` into the project root and open `http://localhost/FSMS/create_demo_accounts.php` locally. It creates or refreshes:
- Staff username `wingstaff`.
- Customer email `customer@wing.test`.

The script displays the demo passwords after successful execution. Delete `create_demo_accounts.php` immediately after using it. Do not use demo credentials on a public server.

\n## WinG cinematic unified login refresh\n- Removed the Admin / Staff / Client role tiles from the shared sign-in form.\n- Replaced the centered login card with a full-height editorial split layout, cinematic fashion photograph, dark forest overlay, champagne accents and Cormorant Garamond typography.\n- Added accessible password visibility toggle and a single registration link for customer accounts.\n- Existing shared identity detection and role routing are unchanged.\n\n## WinG responsive editorial refresh (November 2026)\n- The storefront and management dashboard use the local `assets/images/wing-hero-luxury.png` brand image without cropping or overlaying text on its logo.\n- Storefront navigation supports a responsive menu, Escape-to-close, visible keyboard focus and reduced-motion preferences.\n- Customer and management routes, database schema and commerce workflows remain unchanged.\n
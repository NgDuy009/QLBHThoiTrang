<?php
/** Only admins may configure the destination account used for VietQR. */
declare(strict_types=1);
require_admin();
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/mobile_payment.php';
$errorMessage='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        if (($_POST['settings_kind'] ?? '') === 'mobile') {
            $url=rtrim(trim((string)($_POST['mobile_base_url']??'')), '/');
            if (!wing_valid_mobile_base_url($url)) throw new RuntimeException('Địa chỉ truy cập trên điện thoại phải là http(s)://IP-LAN/FSMS hoặc HTTPS domain hợp lệ; không dùng localhost.');
            db()->prepare('INSERT INTO wing_mobile_payment_settings(id,base_url) VALUES(1,?) ON DUPLICATE KEY UPDATE base_url=VALUES(base_url)')->execute([$url]);
            flash('success','Đã lưu địa chỉ truy cập trên điện thoại. Quay lại đơn chuyển khoản để lấy link form xác nhận.');
            redirect('payment_settings');
        }
        $bankId=strtoupper(trim((string)($_POST['bank_id']??'')));
        $bankAccount=trim((string)($_POST['account_number']??''));
        $bankHolder=mb_strtoupper(trim((string)($_POST['account_holder']??'')));
        if(!preg_match('/^[A-Z0-9]{2,12}$/D',$bankId))throw new RuntimeException('Mã ngân hàng phải gồm 2–12 ký tự chữ/số, ví dụ MB hoặc VCB.');
        if(!preg_match('/^[0-9]{6,25}$/D',$bankAccount))throw new RuntimeException('Số tài khoản phải gồm 6–25 chữ số, không có khoảng trắng.');
        if(mb_strlen($bankHolder)<2||mb_strlen($bankHolder)>120)throw new RuntimeException('Tên chủ tài khoản phải gồm 2–120 ký tự.');
        db()->prepare('INSERT INTO wing_payment_settings(id,bank_id,account_number,account_holder) VALUES(1,?,?,?) ON DUPLICATE KEY UPDATE bank_id=VALUES(bank_id),account_number=VALUES(account_number),account_holder=VALUES(account_holder)')->execute([$bankId,$bankAccount,$bankHolder]);
        flash('success','Đã lưu thông tin ngân hàng. Đơn chuyển khoản mới và đang chờ sẽ có QR VietQR sau khi tải lại.');
        redirect('payment_settings');
    } catch (PDOException $ex) {
        error_log('WinG bank settings: '.$ex->getMessage());
        $errorMessage=(($_POST['settings_kind']??'')==='mobile')?'Không lưu được địa chỉ điện thoại. Hãy import database/wing_mobile_payment_2026.sql và kiểm tra MySQL.':'Không lưu được ngân hàng. Hãy import database/wing_checkout_fix_2026.sql và kiểm tra MySQL.';
    } catch (RuntimeException $ex) {$errorMessage=$ex->getMessage();}
}
$bank=wing_bank_details();
$mobileBaseUrl='';
try { $mobileBaseUrl=(string)(db()->query('SELECT base_url FROM wing_mobile_payment_settings WHERE id=1')->fetchColumn() ?: ''); }
catch (PDOException $ignored) {}

?>
<section class="wing-payment-settings card">
 <span class="eyebrow">WING / PAYMENT GATEWAY</span>
 <h2>Ngân hàng nhận tiền &amp; mã QR VietQR</h2>
 <p>Chỉ Admin được chỉnh tài khoản nhận tiền. Chọn ngân hàng, nhập số tài khoản và tên người thụ hưởng thật. Không nhập tài khoản của người khác.</p>
 <?php if($errorMessage): ?><div class="alert error"><?= e($errorMessage) ?></div><?php endif; ?>
 <?php if(wing_bank_ready()): ?><div class="alert success">✓ Ngân hàng đang được cấu hình. Khách có thể đặt hàng và mở QR chuyển khoản.</div><?php else: ?><div class="alert error">Chưa có ngân hàng hợp lệ. Khách vẫn có thể tạo đơn chờ chuyển khoản nhưng QR chưa khả dụng.</div><?php endif; ?>
 <form method="post" class="form-stack wing-bank-settings-form">
   <?= csrf_field() ?>
   <label>MÃ NGÂN HÀNG (VIETQR) <input name="bank_id" maxlength="12" required pattern="[A-Za-z0-9]{2,12}" placeholder="MB, VCB, ACB, BIDV..." value="<?= e($bank['bank_id']) ?>"></label>
   <label>SỐ TÀI KHOẢN NHẬN TIỀN <input name="account_number" type="text" inputmode="numeric" required minlength="6" maxlength="25" pattern="[0-9]{6,25}" placeholder="Nhập số tài khoản ngân hàng" value="<?= e($bank['account_number']) ?>"></label>
   <label>TÊN CHỦ TÀI KHOẢN <input name="account_holder" required minlength="2" maxlength="120" placeholder="NGUYEN VAN A" value="<?= e($bank['account_holder']) ?>"></label>
   <button class="btn primary" type="submit">LƯU CẤU HÌNH NGÂN HÀNG ↗</button>
 </form>
 <p class="payment-disclaimer">VietQR tạo mã qua dịch vụ img.vietqr.io (cần Internet trên máy khách). Mã QR chỉ tạo yêu cầu chuyển tiền, không thể tự xác nhận ngân hàng đã nhận tiền. Admin vẫn phải đối soát thủ công trước khi đánh dấu đã thanh toán.</p>
 <p class="payment-disclaimer">Nếu bạn chưa import SQL mới, hãy vào phpMyAdmin → fsms_db → Import → chọn <code>database/wing_checkout_fix_2026.sql</code> một lần. Không dùng <code>fsms.sql</code> vì có lệnh xóa bảng.</p>

 <div class="wing-mobile-settings">
  <span class="eyebrow">MOBILE CONFIRMATION / LAN</span>
  <h3>Mở form nhập mã 8 ký tự trên điện thoại</h3>
  <p>Website chỉ hiển thị <strong>một mã QR VietQR ngân hàng</strong>. Khách dùng nút mở form hoặc sao chép link để nhập mã trên iPhone. Camera iPhone không thể biến QR ngân hàng thành trang web tùy chỉnh.</p>
  <form method="post" class="wing-bank-settings-form">
   <?= csrf_field() ?>
   <input type="hidden" name="settings_kind" value="mobile">
   <label>ĐƯỜNG DẪN WEBSITE CÓ THỂ MỞ TỪ ĐIỆN THOẠI
    <input type="url" required name="mobile_base_url" value="<?= e($mobileBaseUrl) ?>" placeholder="http://192.168.1.10/FSMS">
   </label>
   <button class="btn primary">LƯU ĐỊA CHỈ CHO ĐIỆN THOẠI ↗</button>
  </form>
  <p class="payment-disclaimer">Trên Windows chạy <code>ipconfig</code> để lấy IPv4 Wi-Fi/LAN của máy XAMPP. Điện thoại và máy tính phải chung mạng, Apache phải cho phép truy cập từ LAN. Trên điện thoại thử mở <code>http://IP-MAY-TINH/FSMS/index.php?page=shop</code> trước khi mở link xác nhận. Nếu website chỉ mở được qua localhost thì điện thoại không truy cập được.</p>
  <p class="payment-disclaimer">Mã 8 ký tự chỉ ghi nhận yêu cầu đối soát. Không tự xác nhận đã nhận tiền. WinG chỉ đánh dấu đã thanh toán khi Admin kiểm tra tài khoản ngân hàng.</p>
 </div>
</section>

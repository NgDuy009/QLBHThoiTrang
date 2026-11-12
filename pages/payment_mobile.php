<?php
declare(strict_types=1);
/** Small mobile-friendly WinG reference check. No money is marked received here. */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/mobile_payment.php';
$pdo=db();
$token=strtolower(trim((string)($_GET['token'] ?? $_POST['token'] ?? '')));
$validToken=(bool)preg_match('/^[a-f0-9]{48}$/D',$token);
$mobileError=$validToken?'':'Liên kết xác nhận không hợp lệ.';
$mobileOrder=null;
if (!$validToken) http_response_code(404);
if ($validToken && $_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        if (($_POST['action']??'')!=='verify_transfer_reference') throw new RuntimeException('Yêu cầu không hợp lệ.');
        $reference=strtoupper(trim((string)($_POST['payment_reference']??'')));
        if (!preg_match('/^[A-Z0-9]{8}$/D',$reference)) throw new RuntimeException('Nhập đủ 8 chữ hoặc số theo nội dung chuyển khoản.');
        $pdo->beginTransaction();
        $st=$pdo->prepare('SELECT m.order_id,m.failed_attempts,m.blocked_until,o.payment_reference,o.payment_method,o.payment_status,o.payment_claimed_at,o.status FROM wing_payment_mobile_tokens m JOIN orders o ON o.id=m.order_id WHERE m.token=? AND m.expires_at>NOW() FOR UPDATE');
        $st->execute([$token]);$order=$st->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new RuntimeException('Liên kết đã hết hạn. Hãy quay lại đơn hàng để mở form mới.');
        if ($order['payment_method']!=='bank_transfer'||$order['status']==='cancelled') throw new RuntimeException('Đơn không còn cho phép xác nhận chuyển khoản.');
        if ($order['payment_status']==='paid'||!empty($order['payment_claimed_at'])) throw new RuntimeException('Đơn đã gửi xác nhận hoặc đã được đối soát.');
        if (!wing_bank_ready()) throw new RuntimeException('Chưa có tài khoản ngân hàng nhận tiền được cấu hình.');
        if ($order['blocked_until'] && strtotime((string)$order['blocked_until'])>time()) throw new RuntimeException('Nhập sai quá 5 lần. Vui lòng thử lại sau 15 phút.');
        if (!hash_equals((string)$order['payment_reference'],$reference)) {
            $failed=(int)$order['failed_attempts']+1;
            $pdo->prepare('UPDATE wing_payment_mobile_tokens SET failed_attempts=?,blocked_until=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL) WHERE token=?')->execute([$failed,$failed,$token]);
            $pdo->commit();
            throw new RuntimeException('Nội dung chuyển khoản không khớp với đơn hàng. Vui lòng kiểm tra lại 8 ký tự.');
        }
        // A correct reference submits a payment claim, NOT confirmation of bank receipt.
        // One transaction guards against a duplicate claim and keeps the order state consistent.
        $pdo->prepare('UPDATE orders SET payment_claimed_at=NOW() WHERE id=? AND payment_claimed_at IS NULL')->execute([(int)$order['order_id']]);
        $pdo->prepare('UPDATE wing_payment_mobile_tokens SET failed_attempts=0,blocked_until=NULL WHERE token=?')->execute([$token]);
        $pdo->commit();
        try {
            wing_notify_roles($pdo,['admin'],'payment_claimed','Khách gửi mã đối soát','Đơn chuyển khoản #'.(int)$order['order_id'].' đang chờ kiểm tra giao dịch thực tế.','index.php?page=order_detail&id='.(int)$order['order_id'],'payment-claim:'.(int)$order['order_id']);
        } catch (Throwable $ignored) { /* Notification failure must not rollback a submitted claim. */ }
        header('Location: '.BASE_URL.'/index.php?page=payment_mobile&token='.$token.'&step=success');
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mobileError=$ex instanceof RuntimeException?$ex->getMessage():'Không kiểm tra được mã. Vui lòng thử lại.';
        error_log('WinG verification code: '.($ex instanceof RuntimeException?'Reference validation issue':$ex->getMessage()));
    }
}
if ($validToken) {
    try {
        $st=$pdo->prepare('SELECT o.id order_id,o.order_code,o.total_amount,o.payment_method,o.payment_status,o.payment_claimed_at,o.status,m.blocked_until FROM wing_payment_mobile_tokens m JOIN orders o ON o.id=m.order_id WHERE m.token=? AND m.expires_at>NOW() LIMIT 1');
        $st->execute([$token]);$mobileOrder=$st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$mobileOrder) { http_response_code(404);$mobileError='Liên kết đã hết hạn. Hãy quay lại chi tiết đơn hàng.'; }
    } catch (PDOException $ex) {
        http_response_code(503);$mobileError='Admin cần cài SQL xác nhận trên điện thoại.';
        error_log('WinG mobile verify screen: '.$ex->getMessage());
    }
}
$claimed=$mobileOrder && ($mobileOrder['payment_status']==='paid'||!empty($mobileOrder['payment_claimed_at']));
$tracking=$claimed && (($_GET['step']??'')==='tracking' || ($_GET['step']??'')==='');
$success=$claimed && (($_GET['step']??'')==='success');
$statusLabels=['pending'=>'Đã đặt hàng','confirmed'=>'Đã xác nhận','shipping'=>'Đang giao hàng','completed'=>'Hoàn thành','cancelled'=>'Đã hủy'];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta http-equiv="Cache-Control" content="no-store"><title>WinG · Kiểm tra mã chuyển khoản</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=wing-phone-direct-2026">
<style>
.wing-mobile-body{margin:0;min-height:100vh;background:#f3efe6;color:#20362a;font-family:Arial,sans-serif;padding:clamp(12px,3vw,28px)}
.wing-mobile-card{max-width:430px;margin:clamp(8px,3vh,25px) auto;background:#fffdfa;border:1px solid #ded5c6;padding:clamp(20px,5vw,32px);box-shadow:0 12px 44px #19322615}
.wing-mobile-brand{display:flex;align-items:center;gap:12px;letter-spacing:3px;font-weight:800;margin-bottom:24px}.wing-mobile-brand span{font:700 26px Georgia,serif;border:1px solid #b99a61;color:#ae8d54;padding:7px 12px}
.wing-mobile-brand small{display:block;font-size:8px;letter-spacing:2px;margin-top:4px}
.wing-mobile-kicker{font-size:9px;letter-spacing:2px;color:#9b804f;font-weight:800}
.wing-mobile-card h1{font:500 clamp(27px,7vw,37px)/1.12 Georgia,serif;margin:12px 0}
.wing-mobile-card p{font-size:13px;line-height:1.75;color:#647165}
.wing-mobile-info{background:#eee9df;display:grid;gap:6px;padding:16px;margin:20px 0;font-size:12px}
.wing-mobile-info strong{font-size:17px}.wing-mobile-info small{font-size:10px;letter-spacing:1px;color:#7c806f}
.wing-mobile-field{display:grid;gap:9px;margin:20px 0;font-size:11px;font-weight:800;letter-spacing:.6px}
.wing-mobile-field input,.wing-issued-code{width:100%;box-sizing:border-box;background:white;border:1px solid #c8bda6;padding:13px;text-align:center;letter-spacing:3px;font-size:22px;font-weight:800;color:#1e3527;text-transform:uppercase}
.wing-mobile-submit{width:100%;display:block;text-align:center;box-sizing:border-box;border:0;background:#1e3225;color:white;padding:16px 12px;cursor:pointer;text-decoration:none;font-size:12px;font-weight:800;letter-spacing:.7px;margin:10px 0}
.wing-mobile-outline{background:transparent;border:1px solid #25402e;color:#25402e}
.wing-mobile-notice{border-left:3px solid #b89a60;background:#f3ebdd;padding:13px;margin:19px 0;font-size:12px;line-height:1.65;color:#735e40}
.wing-mobile-notice.error{background:#fae9e6;border-color:#b65343;color:#a24334}
.wing-mobile-success{text-align:center}.wing-mobile-success .check{font-size:38px;color:#41754e}
.wing-mobile-steps{display:grid;gap:10px;margin:20px 0}.wing-mobile-steps>div{padding:12px;background:#eee9df;border:1px solid #e2d9c8;font-size:12px;line-height:1.5}.wing-mobile-steps>div.done{background:#eaf2e7;border-color:#b2c5ae}.wing-mobile-steps b{display:block;margin-bottom:5px}
.wing-mobile-footer{margin:24px 0 0!important;text-align:center;font-size:10px!important;color:#8f8d83!important}
</style></head><body class="wing-mobile-body"><main class="wing-mobile-card">
<div class="wing-mobile-brand"><span>W</span><div>WinG<small>THE FASHION HOUSE</small></div></div>
<span class="wing-mobile-kicker">PAYMENT REFERENCE / WIN G</span>
<?php if (!$mobileOrder): ?>
 <h1>Không thể mở form.</h1><div class="wing-mobile-notice error" role="alert"><?= e($mobileError) ?></div>
<?php elseif ($success): ?>
 <section class="wing-mobile-success" role="status"><div class="check">✓</div><h1>Đã gửi xác nhận thành công</h1>
 <p>Mã chuyển khoản khớp với đơn <b><?= e($mobileOrder['order_code']) ?></b>. WinG đã nhận yêu cầu đối soát; đây chưa phải xác nhận ngân hàng đã nhận tiền.</p>
 <div class="wing-mobile-info"><small>ĐƠN HÀNG</small><strong><?= e($mobileOrder['order_code']) ?></strong><small>SỐ TIỀN</small><strong><?= money($mobileOrder['total_amount']) ?></strong><small>TRẠNG THÁI THANH TOÁN</small><strong><?= $mobileOrder['payment_status']==='paid'?'Đã thanh toán':'Chờ WinG kiểm tra giao dịch' ?></strong></div>
 <p>Đang chuyển sang theo dõi đơn hàng...</p>
 <a class="wing-mobile-submit" href="<?= BASE_URL ?>/index.php?page=payment_mobile&amp;token=<?= e($token) ?>&amp;step=tracking">THEO DÕI ĐƠN HÀNG ↗</a>
 </section>
 <script>window.setTimeout(()=>window.location.replace(<?= json_encode(BASE_URL.'/index.php?page=payment_mobile&token='.$token.'&step=tracking',JSON_UNESCAPED_SLASHES) ?>),2200);</script>
<?php elseif ($tracking): ?>
 <div class="wing-mobile-success"><div class="check">✓</div><h1><?= $mobileOrder['payment_status']==='paid'?'Thanh toán thành công':'Đã gửi xác nhận thành công' ?></h1></div><p><?= $mobileOrder['payment_status']==='paid'?'WinG đã đối soát tiền thực nhận. Đơn đang được xử lý theo tiến trình bên dưới.':'Đã xác nhận mã đơn hàng. Đang chờ WinG kiểm tra tiền về ngân hàng; không cần chuyển khoản lại.' ?></p><div class="wing-mobile-info"><small>MÃ ĐƠN</small><strong><?= e($mobileOrder['order_code']) ?></strong><small>TỔNG TIỀN</small><strong><?= money($mobileOrder['total_amount']) ?></strong></div>
 <div class="wing-mobile-steps"><div class="done"><b>1 · Đặt hàng</b>Đơn đã được tạo.</div><div class="done"><b>2 · Gửi mã xác nhận</b>WinG đã nhận yêu cầu đối soát.</div><div class="<?= $mobileOrder['payment_status']==='paid'?'done':'' ?>"><b>3 · Kiểm tra tiền đã nhận</b><?= $mobileOrder['payment_status']==='paid'?'Admin đã kiểm tra sao kê và xác nhận.':'Chờ Admin xác thực giao dịch ngân hàng.' ?></div><div class="<?= in_array($mobileOrder['status'],['shipping','completed'],true)?'done':'' ?>"><b>4 · Vận chuyển</b><?= e($statusLabels[$mobileOrder['status']]??'Đang xử lý') ?></div></div>
 <?php if (!in_array($mobileOrder['status'],['completed','cancelled'],true)): ?><script>setTimeout(()=>location.reload(),20000);</script><?php endif; ?>
<?php elseif ($claimed): ?>
 <h1>Đã gửi mã xác nhận</h1><p>WinG đang kiểm tra thông tin chuyển khoản của đơn <?= e($mobileOrder['order_code']) ?>.</p><a class="wing-mobile-submit" href="<?= BASE_URL ?>/index.php?page=payment_mobile&amp;token=<?= e($token) ?>&amp;step=tracking">XEM TIẾN TRÌNH ĐƠN HÀNG ↗</a>
<?php else: ?>
 <h1>Nhập nội dung chuyển khoản</h1>
 <p>Sau khi chuyển khoản theo thông tin tài khoản ngân hàng của đơn hàng, nhập đúng 8 ký tự trong phần nội dung chuyển khoản. Khi hợp lệ, WinG sẽ tự ghi nhận yêu cầu và chuyển bạn tới trang theo dõi đơn.</p>
 <div class="wing-mobile-info"><small>MÃ ĐƠN HÀNG</small><strong><?= e($mobileOrder['order_code']) ?></strong><small>SỐ TIỀN ĐƠN HÀNG</small><strong><?= money($mobileOrder['total_amount']) ?></strong></div>
 <?php if ($mobileError): ?><div class="wing-mobile-notice error" role="alert"><?= e($mobileError) ?></div><?php endif; ?>
 <?php if($mobileOrder['status']==='cancelled'): ?><div class="wing-mobile-notice error">Đơn đã hủy, không gửi xác nhận.</div>
 <?php elseif(!wing_bank_ready()): ?><div class="wing-mobile-notice error">Ngân hàng chưa cấu hình. Hãy liên hệ cửa hàng.</div>
 <?php elseif($mobileOrder['blocked_until'] && strtotime((string)$mobileOrder['blocked_until'])>time()): ?><div class="wing-mobile-notice error">Bạn nhập sai nhiều lần. Vui lòng quay lại sau 15 phút.</div>
 <?php else: ?><form method="post" action="<?= BASE_URL ?>/index.php?page=payment_mobile&amp;token=<?= e($token) ?>">
 <?= csrf_field() ?><input type="hidden" name="action" value="verify_transfer_reference"><input type="hidden" name="token" value="<?= e($token) ?>">
 <label class="wing-mobile-field">NỘI DUNG CHUYỂN KHOẢN (8 KÝ TỰ)<input name="payment_reference" required pattern="[a-zA-Z0-9]{8}" minlength="8" maxlength="8" placeholder="XXXXXXXX" autocapitalize="characters" autocomplete="off"></label>
 <button class="wing-mobile-submit" type="submit">GỬI XÁC NHẬN &amp; THEO DÕI ĐƠN ↗</button></form><?php endif; ?>
 <div class="wing-mobile-notice">Form chỉ so khớp mã theo đơn hàng, <b>không tra cứu tiền đã đến ngân hàng</b>. Admin đối soát giao dịch thật trước khi đánh dấu đã thanh toán.</div>
<?php endif; ?>
<p class="wing-mobile-footer">© <?= date('Y') ?> WinG Fashion House · Không yêu cầu mật khẩu ngân hàng hoặc OTP.</p>
</main></body></html>

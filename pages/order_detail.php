<?php
$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT o.*,c.full_name customer,c.email customer_email,c.phone customer_phone,u.full_name staff FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN users u ON u.id=o.user_id WHERE o.id=?");
$st->execute([$id]);$order=$st->fetch();
if(!$order){echo '<div class="card">Không tìm thấy đơn hàng.</div>';return;}
$st=db()->prepare("SELECT * FROM order_details WHERE order_id=?");$st->execute([$id]);$items=$st->fetchAll();
?>
<section class="page-intro"><div><span class="eyebrow">ORDER DETAILS / <?= e(strtoupper($order['source']??'POS')) ?></span><h2><?= e($order['order_code']) ?></h2><p class="muted">Thông tin khách, giao nhận, biến thể và trạng thái đơn.</p></div><a class="btn light" href="<?= BASE_URL ?>/index.php?page=orders">← Quay lại</a></section>
<section class="card">
<div class="detail-grid"><div><small>Khách hàng</small><b><?= e($order['delivery_name']?:($order['customer']??'Khách lẻ')) ?></b></div><div><small>Điện thoại</small><b><?= e($order['delivery_phone']?:($order['customer_phone']??'—')) ?></b></div><div><small>Email</small><b><?= e($order['customer_email']??'—') ?></b></div><div><small>Nhân viên</small><b><?= e($order['staff']?:'Online Store') ?></b></div><div><small>Nguồn đơn</small><b><?= ($order['source']??'pos')==='online'?'Website':'Tại quầy' ?></b></div><div><small>Thanh toán</small><b><?= ($order['payment_method']??'cod')==='cod'?'COD':'Chuyển khoản' ?> · <?= ($order['payment_status']??'unpaid')==='paid'?'Đã thanh toán':'Chưa thanh toán' ?></b></div><div><small>Ngày tạo</small><b><?= e($order['created_at']) ?></b></div><div><small>Trạng thái</small><b><?= e(order_status_label($order['status'])) ?></b></div></div>
<?php if(!empty($order['delivery_address'])): ?><div class="order-address"><b>ĐỊA CHỈ GIAO HÀNG</b><p><?= e($order['delivery_address']) ?></p><?php if(!empty($order['customer_note'])): ?><small>Ghi chú: <?= e($order['customer_note']) ?></small><?php endif; ?></div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Sản phẩm</th><th>SKU</th><th>Size</th><th>Màu</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><?= e($i['product_name']) ?></td><td><?= e($i['sku']) ?></td><td><?= e($i['variant_size']??'—') ?></td><td><?= e($i['variant_color']??'—') ?></td><td><?= (int)$i['quantity'] ?></td><td><?= money($i['unit_price']) ?></td><td><?= money($i['line_total']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="grand-total">Tổng cộng: <?= money($order['total_amount']) ?></div>
</section>

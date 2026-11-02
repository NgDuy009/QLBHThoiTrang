<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $variantId=(int)($_POST['variant_id']??0);$qty=(int)($_POST['quantity']??0);
    $type=(string)($_POST['type']??'in');$note=trim((string)($_POST['note']??''));
    if($variantId<=0||$qty<=0||!in_array($type,['in','out'],true)){flash('error','Thông tin giao dịch kho không hợp lệ.');redirect('inventory');}
    try{
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT v.id,v.product_id,v.stock_quantity,p.name product_name,v.size,v.color FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? FOR UPDATE");
        $st->execute([$variantId]);$variant=$st->fetch();
        if(!$variant)throw new RuntimeException('Không tìm thấy biến thể sản phẩm.');
        $new=(int)$variant['stock_quantity']+($type==='in'?$qty:-$qty);
        if($new<0)throw new RuntimeException('Số lượng xuất vượt quá tồn kho của biến thể.');
        $pdo->prepare("UPDATE product_variants SET stock_quantity=? WHERE id=?")->execute([$new,$variantId]);
        refresh_product_stock($pdo,(int)$variant['product_id']);
        $pdo->prepare("INSERT INTO stock_transactions(product_id,user_id,transaction_type,quantity,note) VALUES(?,?,?,?,?)")
            ->execute([(int)$variant['product_id'],current_user()['id'],$type,$qty,$note?:($type==='in'?'Nhập kho biến thể':'Xuất kho biến thể')]);
        $pdo->commit();flash('success','Đã cập nhật tồn kho size/màu và tổng tồn sản phẩm.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'Không thể cập nhật tồn kho.');}
    redirect('inventory');
}
$variants=$pdo->query("SELECT v.id,v.sku variant_sku,v.size,v.color,v.stock_quantity,p.id product_id,p.name,p.sku product_sku FROM product_variants v JOIN products p ON p.id=v.product_id ORDER BY p.name,v.size,v.color")->fetchAll();
$logs=$pdo->query("SELECT s.*,p.name product,u.full_name staff FROM stock_transactions s JOIN products p ON p.id=s.product_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.id DESC LIMIT 100")->fetchAll();
$totalStock=(int)$pdo->query("SELECT COALESCE(SUM(stock_quantity),0) FROM products")->fetchColumn();
$lowCount=(int)$pdo->query("SELECT COUNT(*) FROM product_variants WHERE stock_quantity<=2")->fetchColumn();
?>
<section class="page-intro"><div><span class="eyebrow">INVENTORY CONTROL / LIVE DATA</span><h2>Quản lý kho hàng</h2><p class="muted">Tồn kho theo từng biến thể size và màu. Tổng tồn sản phẩm được đồng bộ tự động.</p></div></section>
<div class="stats-grid three"><article class="stat-card"><div class="stat-top">TỔNG TỒN KHO</div><strong><?= number_format($totalStock) ?></strong><small>Sản phẩm quy đổi từ biến thể</small></article><article class="stat-card"><div class="stat-top">BIẾN THỂ</div><strong><?= number_format(count($variants)) ?></strong><small>Size và màu đang quản lý</small></article><article class="stat-card"><div class="stat-top">SẮP HẾT HÀNG</div><strong><?= number_format($lowCount) ?></strong><small>Biến thể còn không quá 2 sản phẩm</small></article></div>
<section class="card form-card"><div class="card-heading"><div><span class="eyebrow">STOCK MOVEMENT</span><h3>Nhập / xuất kho theo size và màu</h3></div><span class="pill active">Live inventory</span></div>
<form method="post" class="form-grid"><?= csrf_field() ?><label>Biến thể sản phẩm<select name="variant_id" required><option value="">-- Chọn sản phẩm · size · màu --</option><?php foreach($variants as $v): ?><option value="<?= (int)$v['id'] ?>"><?= e($v['name'].' · '.$v['size'].' · '.$v['color'].' · tồn '.$v['stock_quantity']) ?></option><?php endforeach; ?></select></label><label>Loại giao dịch<select name="type"><option value="in">Nhập kho</option><option value="out">Xuất kho</option></select></label><label>Số lượng<input type="number" name="quantity" min="1" max="9999" required></label><label>Ghi chú<input name="note" maxlength="255" placeholder="Nhập lý do giao dịch"></label><div class="form-actions span-2"><button class="btn primary">Lưu giao dịch kho</button></div></form></section>
<section class="card"><div class="card-heading"><div><span class="eyebrow">VARIANT INVENTORY</span><h3>Tồn kho theo biến thể</h3></div></div><div class="table-wrap"><table><thead><tr><th>Sản phẩm</th><th>SKU</th><th>Size</th><th>Màu</th><th>Tồn kho</th><th>Tình trạng</th></tr></thead><tbody><?php foreach($variants as $v): ?><tr><td><?= e($v['name']) ?></td><td><?= e($v['variant_sku']) ?></td><td><?= e($v['size']) ?></td><td><?= e($v['color']) ?></td><td><b><?= (int)$v['stock_quantity'] ?></b></td><td><span class="pill <?= (int)$v['stock_quantity']<=2?'warning':'active' ?>"><?= (int)$v['stock_quantity']<=2?'Sắp hết':'Ổn định' ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><div class="card-heading"><div><span class="eyebrow">AUDIT TRAIL</span><h3>Lịch sử nhập / xuất</h3></div></div><div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Sản phẩm</th><th>Loại</th><th>Số lượng</th><th>Nhân viên</th><th>Ghi chú</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['product']) ?></td><td><?= $l['transaction_type']==='in'?'Nhập':'Xuất' ?></td><td><?= (int)$l['quantity'] ?></td><td><?= e($l['staff']??'Online Store') ?></td><td><?= e($l['note']??'—') ?></td></tr><?php endforeach; ?><?php if(!$logs): ?><tr><td colspan="6" class="empty">Chưa có giao dịch.</td></tr><?php endif; ?></tbody></table></div></section>

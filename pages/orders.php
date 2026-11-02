<?php
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(($_POST['action']??'')==='create'){
        $customerId=(int)($_POST['customer_id']??0);
        $variantIds=$_POST['variant_id']??[];$qtys=$_POST['quantity']??[];
        if(!is_array($variantIds)||!is_array($qtys)||!count($variantIds)){flash('error','Vui lòng chọn ít nhất một biến thể.');redirect('orders');}
        try{
            $pdo->beginTransaction();$items=[];$total=0;
            foreach($variantIds as $i=>$rawId){
                $variantId=(int)$rawId;$qty=(int)($qtys[$i]??0);
                if($variantId<=0||$qty<=0)throw new RuntimeException('Biến thể hoặc số lượng không hợp lệ.');
                $st=$pdo->prepare("SELECT v.*,p.name,p.sku product_sku,p.price,p.status FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? FOR UPDATE");
                $st->execute([$variantId]);$v=$st->fetch();
                if(!$v||$v['status']!=='active')throw new RuntimeException('Sản phẩm không còn hoạt động.');
                if((int)$v['stock_quantity']<$qty)throw new RuntimeException('Không đủ tồn kho cho '.$v['name'].' · '.$v['size'].' · '.$v['color'].'.');
                $items[]=['v'=>$v,'qty'=>$qty];$total+=(float)$v['price']*$qty;
            }
            $code='WG-POS-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $customer=$customerId?:null;
            $st=$pdo->prepare("INSERT INTO orders(order_code,customer_id,user_id,total_amount,status,source,payment_method,payment_status) VALUES(?,?,?,?,'confirmed','pos','cod','unpaid')");
            $st->execute([$code,$customer,current_user()['id'],$total]);$orderId=(int)$pdo->lastInsertId();
            $detail=$pdo->prepare("INSERT INTO order_details(order_id,product_id,variant_id,product_name,sku,variant_size,variant_color,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $update=$pdo->prepare("UPDATE product_variants SET stock_quantity=stock_quantity-? WHERE id=?");
            $log=$pdo->prepare("INSERT INTO stock_transactions(product_id,user_id,transaction_type,quantity,note) VALUES(?,?,'out',?,?)");
            $affected=[];
            foreach($items as $it){$v=$it['v'];$q=$it['qty'];$line=(float)$v['price']*$q;
                $detail->execute([$orderId,$v['product_id'],$v['id'],$v['name'],$v['sku'],$v['size'],$v['color'],$q,$v['price'],$line]);
                $update->execute([$q,$v['id']]);$affected[(int)$v['product_id']]=true;
                $log->execute([$v['product_id'],current_user()['id'],$q,'Bán tại quầy · '.$code]);
            }
            foreach(array_keys($affected) as $pid)refresh_product_stock($pdo,(int)$pid);
            $pdo->commit();flash('success',"Đã tạo đơn tại quầy {$code}.");
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'Không thể tạo đơn. Kiểm tra dữ liệu và tồn kho.');}
        redirect('orders');
    }
    if(($_POST['action']??'')==='status'){
        $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        try{
            set_order_status($pdo,$id,$status);
            $st=$pdo->prepare("SELECT order_code,customer_id FROM orders WHERE id=?");$st->execute([$id]);$updatedOrder=$st->fetch();
            if($updatedOrder&&$updatedOrder['customer_id']){
                $statusText=['pending'=>'đang chờ xác nhận','confirmed'=>'đã được xác nhận','completed'=>'đã hoàn thành','cancelled'=>'đã bị hủy'][$status]??$status;
                wing_notify($pdo,'customer',(int)$updatedOrder['customer_id'],'order_status','Cập nhật đơn hàng '.$updatedOrder['order_code'],'Đơn hàng của bạn '.$statusText.'.','index.php?page=customer_order_detail&id='.$id,'order-status:'.$id.':'.$status);
                if($status==='completed')wing_notify_completed_order_reviews($pdo,$id);
            }
            flash('success','Đã cập nhật trạng thái đơn hàng và đồng bộ tồn kho nếu đơn bị hủy.');
        }catch(Throwable $e){flash('error',$e->getMessage());}
        redirect('orders');
    }
}
$variants=$pdo->query("SELECT v.id,v.sku,v.size,v.color,v.stock_quantity,p.name,p.price FROM product_variants v JOIN products p ON p.id=v.product_id WHERE p.status='active' AND v.stock_quantity>0 ORDER BY p.name,v.size,v.color")->fetchAll();
$customers=$pdo->query("SELECT id,full_name,phone FROM customers ORDER BY full_name")->fetchAll();
$orders=$pdo->query("SELECT o.*,c.full_name customer,u.full_name staff FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 150")->fetchAll();
?>
<section class="page-intro"><div><span class="eyebrow">SALES / OMNICHANNEL OPERATIONS</span><h2>Quản lý đơn hàng</h2><p class="muted">Đơn tại quầy và đơn website được lưu chung, có nguồn đơn và người phụ trách rõ ràng.</p></div></section>
<section class="card form-card"><div class="card-heading"><div><span class="eyebrow">POINT OF SALE</span><h3>Tạo đơn bán hàng tại cửa hàng</h3></div><span class="pill active">POS</span></div>
<form method="post" id="orderForm"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<div class="form-grid"><label>Khách hàng<select name="customer_id"><option value="">Khách lẻ</option><?php foreach($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['full_name'].' · '.$c['phone']) ?></option><?php endforeach; ?></select></label><label>Nhân viên phụ trách<input value="<?= e(current_user()['full_name']) ?>" disabled></label></div>
<div id="orderItems" class="order-items"><div class="order-item"><select name="variant_id[]" required><option value="">-- Sản phẩm · size · màu --</option><?php foreach($variants as $v): ?><option value="<?= (int)$v['id'] ?>" data-price="<?= (float)$v['price'] ?>" data-stock="<?= (int)$v['stock_quantity'] ?>"><?= e($v['name'].' · '.$v['size'].' · '.$v['color'].' · tồn '.$v['stock_quantity']) ?></option><?php endforeach; ?></select><input name="quantity[]" type="number" min="1" value="1" required><button type="button" class="remove-item" aria-label="Xóa dòng">×</button></div></div>
<div class="order-bottom"><button type="button" class="btn light" id="addOrderItem">+ Thêm sản phẩm</button><div class="total-preview">Tổng tạm tính <strong id="orderTotal">0 ₫</strong></div></div>
<button class="btn primary" type="submit">Xác nhận đơn tại quầy →</button></form></section>
<section class="card"><div class="card-heading"><div><span class="eyebrow">CENTRAL ORDER REGISTER</span><h3>Tất cả đơn hàng</h3></div><span class="pill active"><?= count($orders) ?> đơn gần nhất</span></div><div class="table-wrap"><table><thead><tr><th>Mã đơn</th><th>Nguồn</th><th>Khách hàng</th><th>Phụ trách</th><th>Ngày tạo</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach($orders as $o): ?><tr><td><b><?= e($o['order_code']) ?></b></td><td><span class="pill <?= $o['source']==='online'?'confirmed':'active' ?>"><?= $o['source']==='online'?'Website':'Tại quầy' ?></span></td><td><?= e($o['customer']??'Khách lẻ') ?></td><td><?= e($o['staff']?:'Online Store') ?></td><td><?= date('d/m/Y H:i',strtotime($o['created_at'])) ?></td><td><?= money($o['total_amount']) ?></td><td><span class="pill <?= e($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td><td class="actions"><a href="<?= BASE_URL ?>/index.php?page=order_detail&id=<?= (int)$o['id'] ?>">Chi tiết</a><?php if($o['status']!=='cancelled'&&$o['status']!=='completed'): ?><form method="post" class="inline-status"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><select name="status"><option value="confirmed" <?= $o['status']==='confirmed'?'selected':'' ?>>Xác nhận</option><option value="completed">Hoàn thành</option><option value="cancelled">Hủy</option></select><button class="link-danger">Lưu</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$orders): ?><tr><td colspan="8" class="empty">Chưa có đơn hàng.</td></tr><?php endif; ?></tbody></table></div></section>

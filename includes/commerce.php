<?php
declare(strict_types=1);

function refresh_product_stock(PDO $pdo, int $productId): void {
    $stmt=$pdo->prepare("UPDATE products SET stock_quantity=(SELECT COALESCE(SUM(stock_quantity),0) FROM product_variants WHERE product_id=?) WHERE id=?");
    $stmt->execute([$productId,$productId]);
}

/**
 * Change an order status safely. Cancellation restores the exact variant stock
 * once only; cancelled orders cannot be reopened through this basic workflow.
 */
function set_order_status(PDO $pdo, int $orderId, string $newStatus): void {
    $allowed=['pending','confirmed','completed','cancelled'];
    if(!in_array($newStatus,$allowed,true)) throw new RuntimeException('Trạng thái đơn hàng không hợp lệ.');
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare("SELECT id,status FROM orders WHERE id=? FOR UPDATE");
        $st->execute([$orderId]);$order=$st->fetch();
        if(!$order) throw new RuntimeException('Không tìm thấy đơn hàng.');
        if($order['status']==='cancelled' && $newStatus!=='cancelled') throw new RuntimeException('Đơn đã hủy không thể mở lại; hãy tạo đơn mới.');
        if($newStatus==='cancelled' && $order['status']==='completed') throw new RuntimeException('Đơn đã hoàn thành không thể hủy trong quy trình cơ bản.');
        if($newStatus==='cancelled' && $order['status']!=='cancelled') {
            $st=$pdo->prepare("SELECT product_id,variant_id,quantity FROM order_details WHERE order_id=?");
            $st->execute([$orderId]);$affected=[];
            foreach($st->fetchAll() as $line) {
                $qty=(int)$line['quantity'];$variantId=(int)($line['variant_id']??0);$productId=(int)($line['product_id']??0);
                if($variantId>0) {
                    $upd=$pdo->prepare("UPDATE product_variants SET stock_quantity=stock_quantity+? WHERE id=?");
                    $upd->execute([$qty,$variantId]);
                } elseif($productId>0) {
                    // Legacy order line: restore into the product's first variant when one exists.
                    $find=$pdo->prepare("SELECT id FROM product_variants WHERE product_id=? ORDER BY id LIMIT 1");
                    $find->execute([$productId]);$legacyVariant=(int)$find->fetchColumn();
                    if($legacyVariant>0){$upd=$pdo->prepare("UPDATE product_variants SET stock_quantity=stock_quantity+? WHERE id=?");$upd->execute([$qty,$legacyVariant]);}
                    else{$upd=$pdo->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?");$upd->execute([$qty,$productId]);}
                }
                if($productId>0)$affected[$productId]=true;
                if($productId>0){
                    $log=$pdo->prepare("INSERT INTO stock_transactions(product_id,user_id,transaction_type,quantity,note) VALUES(?,?,'in',?,?)");
                    $actor=current_user()['id']??null;
                    $log->execute([$productId,$actor,$qty,'Hoàn kho do hủy đơn '.$orderId]);
                }
            }
            foreach(array_keys($affected) as $pid) {
                $check=$pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id=?");$check->execute([$pid]);
                if((int)$check->fetchColumn()>0) refresh_product_stock($pdo,(int)$pid);
            }
        }
        $pdo->prepare("UPDATE orders SET status=?,payment_status=IF(?='cancelled' AND payment_status='paid','refunded',payment_status) WHERE id=?")->execute([$newStatus,$newStatus,$orderId]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

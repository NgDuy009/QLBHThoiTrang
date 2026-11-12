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
    $allowed=['pending','confirmed','shipping','completed','cancelled'];
    if(!in_array($newStatus,$allowed,true)) throw new RuntimeException('Trạng thái đơn hàng không hợp lệ.');
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare("SELECT id,status,payment_status,payment_method FROM orders WHERE id=? FOR UPDATE");
        $st->execute([$orderId]);$order=$st->fetch();
        if(!$order) throw new RuntimeException('Không tìm thấy đơn hàng.');
        $transitions=[
            'pending'=>['confirmed','cancelled'],
            'confirmed'=>['shipping','completed','cancelled'],
            'shipping'=>['completed'],
            'completed'=>[], 'cancelled'=>[],
        ];
        if($newStatus===$order['status']){$pdo->commit();return;}
        if(!in_array($newStatus,$transitions[$order['status']]??[],true))
            throw new RuntimeException('Không thể chuyển trạng thái đơn hàng theo quy trình hiện tại.');
        if($newStatus==='cancelled' && $order['payment_status']==='paid')
            throw new RuntimeException('Đơn đã thanh toán cần hoàn tiền/đối soát trước khi hủy.');
        if(in_array($newStatus,['shipping','completed'],true) && $order['payment_method']==='bank_transfer' && $order['payment_status']!=='paid')
            throw new RuntimeException('Vui lòng kiểm tra và xác nhận tiền đã nhận trước khi giao/hoàn tất đơn chuyển khoản.');
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
        $pdo->prepare("UPDATE orders SET status=?,payment_status=CASE WHEN ?='completed' AND payment_method='cod' THEN 'paid' ELSE payment_status END WHERE id=?")->execute([$newStatus,$newStatus,$orderId]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

/** A customer's shopping bag is persisted under their ID, never shared by browser session. */
function wing_cart_get(PDO $pdo, ?array $customer): array {
    if ($customer) {
        $st=$pdo->prepare('SELECT variant_id, quantity FROM customer_cart_items WHERE customer_id=?');
        $st->execute([(int)$customer['id']]);
        $items=[];
        foreach ($st->fetchAll() as $item) $items[(int)$item['variant_id']] = (int)$item['quantity'];
        return $items;
    }
    return $_SESSION['wing_guest_cart'] ?? [];
}
function wing_cart_set(PDO $pdo, ?array $customer, int $variantId, int $qty): void {
    if ($variantId < 1 || $qty < 0 || $qty > 20) throw new RuntimeException('Số lượng không hợp lệ.');
    if ($customer) {
        if ($qty === 0) {
            $pdo->prepare('DELETE FROM customer_cart_items WHERE customer_id=? AND variant_id=?')
                ->execute([(int)$customer['id'],$variantId]);
        } else {
            $pdo->prepare('INSERT INTO customer_cart_items(customer_id,variant_id,quantity) VALUES(?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)')
                ->execute([(int)$customer['id'],$variantId,$qty]);
        }
    } elseif ($qty === 0) unset($_SESSION['wing_guest_cart'][$variantId]);
    else $_SESSION['wing_guest_cart'][$variantId] = $qty;
}
function wing_cart_clear(PDO $pdo, ?array $customer): void {
    if ($customer) $pdo->prepare('DELETE FROM customer_cart_items WHERE customer_id=?')->execute([(int)$customer['id']]);
    else unset($_SESSION['wing_guest_cart']);
}
function wing_payment_reference(): string {
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code=''; for ($i=0; $i<8; $i++) $code.=$alphabet[random_int(0,strlen($alphabet)-1)];
    return $code;
}

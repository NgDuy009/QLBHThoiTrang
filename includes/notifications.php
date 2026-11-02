<?php
declare(strict_types=1);

function wing_notify(PDO $pdo, string $recipientType, int $recipientId, string $kind, string $title, string $body, string $link, ?string $dedupeKey=null): void {
    if(!in_array($recipientType,['customer','user'],true)||$recipientId<=0)return;
    $stmt=$pdo->prepare("INSERT IGNORE INTO wing_notifications(recipient_type,recipient_id,kind,title,body,link,dedupe_key) VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([$recipientType,$recipientId,$kind,$title,$body,$link,$dedupeKey]);
}
function wing_notify_roles(PDO $pdo, array $roles, string $kind, string $title, string $body, string $link, ?string $dedupePrefix=null): void {
    if(!$roles)return;
    $marks=implode(',',array_fill(0,count($roles),'?'));
    $stmt=$pdo->prepare("SELECT id FROM users WHERE status='active' AND role IN ($marks)");
    $stmt->execute($roles);
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id){
        wing_notify($pdo,'user',(int)$id,$kind,$title,$body,$link,$dedupePrefix?$dedupePrefix.':'.$id:null);
    }
}
function wing_notify_completed_order_reviews(PDO $pdo, int $orderId): void {
    $stmt=$pdo->prepare("SELECT o.id,o.customer_id,o.order_code,d.product_id,d.product_name
      FROM orders o JOIN order_details d ON d.order_id=o.id
      LEFT JOIN product_reviews r ON r.order_id=o.id AND r.product_id=d.product_id
      WHERE o.id=? AND o.status='completed' AND o.customer_id IS NOT NULL AND r.id IS NULL");
    $stmt->execute([$orderId]);
    foreach($stmt->fetchAll() as $row){
        $pid=(int)$row['product_id'];$oid=(int)$row['id'];$cid=(int)$row['customer_id'];
        wing_notify($pdo,'customer',$cid,'review_request','Bạn có thể đánh giá sản phẩm đã mua',
            'Đơn '.$row['order_code'].' đã hoàn thành. Hãy chia sẻ trải nghiệm về '.$row['product_name'].'.',
            'index.php?page=product&id='.$pid.'&review_order_id='.$oid.'#customer-reviews',
            'review:'.$oid.':'.$pid);
    }
}
function wing_notification_unread(PDO $pdo,string $type,int $id): int {
    $st=$pdo->prepare("SELECT COUNT(*) FROM wing_notifications WHERE recipient_type=? AND recipient_id=? AND is_read=0");
    $st->execute([$type,$id]);return (int)$st->fetchColumn();
}

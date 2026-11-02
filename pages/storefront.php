<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/commerce.php';
$pdo = db();
$customer = null;
if (!empty($_SESSION['customer_id'])) {
    $st=$pdo->prepare("SELECT id,full_name,email,phone,address FROM customers WHERE id=? AND account_status='active' AND password_hash IS NOT NULL");
    $st->execute([(int)$_SESSION['customer_id']]); $customer=$st->fetch() ?: null;
    if(!$customer) unset($_SESSION['customer_id']);
}
if (in_array($page, ['customer_profile','customer_orders','customer_order_detail','support','notifications'], true) && !$customer) redirect('login');
$cart = $_SESSION['wing_cart_v2'] ?? [];
$action = (string)($_POST['action'] ?? '');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try {
        if($action==='register'){
            $name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
            $phone=trim((string)($_POST['phone']??''));$address=trim((string)($_POST['address']??''));$password=(string)($_POST['password']??'');
            if(mb_strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10)throw new RuntimeException('Vui lòng nhập họ tên, email hợp lệ và mật khẩu ít nhất 10 ký tự.');
            $st=$pdo->prepare("SELECT id FROM customers WHERE email=? AND password_hash IS NOT NULL LIMIT 1");$st->execute([$email]);
            if($st->fetch())throw new RuntimeException('Email này đã có tài khoản. Hãy đăng nhập.');
            $st=$pdo->prepare("INSERT INTO customers(full_name,email,phone,address,password_hash,account_status) VALUES(?,?,?,?,?,'active')");
            $st->execute([$name,$email,$phone,$address,password_hash($password,PASSWORD_DEFAULT)]);
            session_regenerate_id(true);if(!empty($_SESSION['user']))unset($_SESSION['wing_cart_v2']);unset($_SESSION['user']);$_SESSION['customer_id']=(int)$pdo->lastInsertId();
            flash('success','Tài khoản WinG của bạn đã được tạo.');redirect('shop');
        }
        if($action==='customer_login'){
            $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
            $st=$pdo->prepare("SELECT id,password_hash,account_status FROM customers WHERE email=? AND password_hash IS NOT NULL LIMIT 1");$st->execute([$email]);$row=$st->fetch();
            if(!$row||$row['account_status']!=='active'||!password_verify($password,$row['password_hash']))throw new RuntimeException('Email hoặc mật khẩu không chính xác.');
            session_regenerate_id(true);if(!empty($_SESSION['user']))unset($_SESSION['wing_cart_v2']);unset($_SESSION['user']);$_SESSION['customer_id']=(int)$row['id'];redirect('shop');
        }
        if($action==='create_support_ticket'){
            if(!$customer)redirect('login');
            $subject=trim((string)($_POST['subject']??''));$category=(string)($_POST['category']??'other');
            $message=trim((string)($_POST['message']??''));$priority=(string)($_POST['priority']??'normal');
            $validCategories=['order','product','payment','shipping','return','account','other'];
            if(mb_strlen($subject)<5||mb_strlen($subject)>180||mb_strlen($message)<10||mb_strlen($message)>4000)throw new RuntimeException('Tiêu đề cần 5–180 ký tự và nội dung ít nhất 10 ký tự.');
            if(!in_array($category,$validCategories,true))$category='other';
            if(!in_array($priority,['normal','high'],true))$priority='normal';
            $pdo->beginTransaction();
            $code='WG-HD-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $st=$pdo->prepare("INSERT INTO support_tickets(ticket_code,customer_id,subject,category,priority,status) VALUES(?,?,?,?,?,'new')");
            $st->execute([$code,$customer['id'],$subject,$category,$priority]);$ticketId=(int)$pdo->lastInsertId();
            $st=$pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,sender_type,sender_id,sender_name,sender_role,message) VALUES(?,'customer',?,?, 'customer',?)");
            $st->execute([$ticketId,$customer['id'],$customer['full_name'],$message]);
            wing_notify($pdo,'customer',(int)$customer['id'],'support_received','WinG đã nhận yêu cầu hỗ trợ',
                'Yêu cầu '.$code.' đã được gửi đến đội ngũ chăm sóc khách hàng.','index.php?page=support&id='.$ticketId,'support-created:'.$ticketId);
            $staff=$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND status='active'")->fetchColumn();
            if((int)$staff>0) wing_notify_roles($pdo,['staff'],'support_new','Có yêu cầu hỗ trợ mới',
                $code.' · '.$subject,'index.php?page=support&id='.$ticketId,'support-new:'.$ticketId);
            else wing_notify_roles($pdo,['admin'],'support_new','Yêu cầu hỗ trợ mới (chưa có nhân viên)',
                $code.' · '.$subject,'index.php?page=support&id='.$ticketId,'support-new-admin:'.$ticketId);
            $pdo->commit();flash('success','Đã gửi yêu cầu '.$code.'. Nhân viên WinG sẽ tiếp nhận.');
            header('Location: '.BASE_URL.'/index.php?page=support&id='.$ticketId);exit;
        }
        if($action==='customer_support_reply'){
            if(!$customer)redirect('login');
            $ticketId=(int)($_POST['ticket_id']??0);$message=trim((string)($_POST['message']??''));
            if(mb_strlen($message)<2||mb_strlen($message)>4000)throw new RuntimeException('Nội dung phản hồi từ 2 đến 4.000 ký tự.');
            $st=$pdo->prepare("SELECT * FROM support_tickets WHERE id=? AND customer_id=?");$st->execute([$ticketId,$customer['id']]);$ticket=$st->fetch();
            if(!$ticket)throw new RuntimeException('Không tìm thấy yêu cầu hỗ trợ.');
            if($ticket['status']==='resolved')throw new RuntimeException('Yêu cầu đã đóng. Hãy tạo yêu cầu mới nếu cần hỗ trợ thêm.');
            $pdo->beginTransaction();
            $st=$pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,sender_type,sender_id,sender_name,sender_role,message) VALUES(?,'customer',?,?, 'customer',?)");
            $st->execute([$ticketId,$customer['id'],$customer['full_name'],$message]);
            $newStatus=($ticket['status']==='escalated_admin'||!empty($ticket['handled_by_admin_id']))?'escalated_admin':($ticket['assigned_staff_id']?'assigned':'new');
            $pdo->prepare("UPDATE support_tickets SET status=? WHERE id=?")->execute([$newStatus,$ticketId]);
            if($ticket['status']==='escalated_admin'||$ticket['handled_by_admin_id']){
                wing_notify_roles($pdo,['admin'],'support_customer_reply','Khách hàng phản hồi yêu cầu hỗ trợ',$ticket['ticket_code'].' · '.$ticket['subject'],'index.php?page=support&id='.$ticketId,'support-customer-reply:'.$ticketId.':'.time());
            } elseif($ticket['assigned_staff_id']){
                wing_notify($pdo,'user',(int)$ticket['assigned_staff_id'],'support_customer_reply','Khách hàng vừa phản hồi',$ticket['ticket_code'].' · '.$ticket['subject'],'index.php?page=support&id='.$ticketId,'support-customer-reply:'.$ticketId.':'.time());
            } else {
                wing_notify_roles($pdo,['staff'],'support_customer_reply','Khách hàng vừa phản hồi',$ticket['ticket_code'].' · '.$ticket['subject'],'index.php?page=support&id='.$ticketId,'support-customer-reply:'.$ticketId.':'.time());
            }
            $pdo->commit();flash('success','Đã gửi phản hồi cho đội ngũ hỗ trợ.');header('Location: '.BASE_URL.'/index.php?page=support&id='.$ticketId);exit;
        }
        if($action==='mark_customer_notifications_read'){
            if(!$customer)redirect('login');
            $ids=$_POST['notification_ids']??[];
            if(is_array($ids)&&$ids){$ids=array_values(array_filter(array_map('intval',$ids),fn($v)=>$v>0));if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$params=array_merge([(int)$customer['id']],$ids);$pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE recipient_type='customer' AND recipient_id=? AND id IN ($marks)")->execute($params);}}
            else $pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE recipient_type='customer' AND recipient_id=?")->execute([(int)$customer['id']]);
            redirect('notifications');
        }
        if($action==='update_profile'){
            if(!$customer)redirect('login');
            $name=trim((string)($_POST['full_name']??''));$phone=trim((string)($_POST['phone']??''));$address=trim((string)($_POST['address']??''));
            $newPassword=(string)($_POST['new_password']??'');
            if(mb_strlen($name)<2)throw new RuntimeException('Họ tên cần có ít nhất 2 ký tự.');
            if($newPassword!=='' && strlen($newPassword)<10)throw new RuntimeException('Mật khẩu mới cần ít nhất 10 ký tự.');
            if($newPassword!==''){
                $st=$pdo->prepare("UPDATE customers SET full_name=?,phone=?,address=?,password_hash=? WHERE id=?");
                $st->execute([$name,$phone,$address,password_hash($newPassword,PASSWORD_DEFAULT),$customer['id']]);
            }else{
                $st=$pdo->prepare("UPDATE customers SET full_name=?,phone=?,address=? WHERE id=?");
                $st->execute([$name,$phone,$address,$customer['id']]);
            }
            flash('success','Thông tin tài khoản đã được cập nhật.');redirect('customer_profile');
        }
        if($action==='add_review'){
            if(!$customer)redirect('login');
            $productId=(int)($_POST['product_id']??0);$orderId=(int)($_POST['order_id']??0);
            $rating=(int)($_POST['rating']??0);$reviewText=trim((string)($_POST['review_text']??''));
            if($rating<1||$rating>5)throw new RuntimeException('Vui lòng chọn mức đánh giá từ 1 đến 5 sao.');
            if(mb_strlen($reviewText)>1000)throw new RuntimeException('Nội dung đánh giá tối đa 1.000 ký tự.');
            $check=$pdo->prepare("SELECT o.id FROM orders o JOIN order_details d ON d.order_id=o.id WHERE o.id=? AND o.customer_id=? AND o.status='completed' AND d.product_id=? LIMIT 1");
            $check->execute([$orderId,$customer['id'],$productId]);
            if(!$check->fetch())throw new RuntimeException('Chỉ khách đã mua và nhận đơn hoàn thành mới được đánh giá sản phẩm.');
            $ins=$pdo->prepare("INSERT INTO product_reviews(product_id,customer_id,order_id,rating,review_text) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),review_text=VALUES(review_text),status='visible'");
            $ins->execute([$productId,$customer['id'],$orderId,$rating,$reviewText?:null]);
            $pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE recipient_type='customer' AND recipient_id=? AND dedupe_key=?")->execute([(int)$customer['id'],'review:'.$orderId.':'.$productId]);
            flash('success','Cảm ơn bạn đã đánh giá sản phẩm.');
            header('Location: '.BASE_URL.'/index.php?page=product&id='.$productId.'#customer-reviews');exit;
        }
        if($action==='wishlist_toggle'){
            if(!$customer)redirect('login');
            $pid=(int)($_POST['product_id']??0);
            $st=$pdo->prepare("SELECT id FROM products WHERE id=? AND status='active' AND is_published=1");$st->execute([$pid]);
            if(!$st->fetch())throw new RuntimeException('Không tìm thấy sản phẩm.');
            $st=$pdo->prepare("SELECT id FROM customer_wishlist WHERE customer_id=? AND product_id=?");
            $st->execute([$customer['id'],$pid]);$existing=$st->fetch();
            if($existing){$pdo->prepare("DELETE FROM customer_wishlist WHERE id=?")->execute([$existing['id']]);flash('success','Đã xóa khỏi danh sách yêu thích.');}
            else{$pdo->prepare("INSERT INTO customer_wishlist(customer_id,product_id) VALUES(?,?)")->execute([$customer['id'],$pid]);flash('success','Đã thêm vào danh sách yêu thích.');}
            $back=(string)($_POST['return_to']??'shop');
            if($back==='product'){header('Location: '.BASE_URL.'/index.php?page=product&id='.$pid);exit;}
            redirect(in_array($back,['shop','wishlist'],true)?$back:'shop');
        }
        if($action==='cancel_order'){
            if(!$customer)redirect('login');
            $orderId=(int)($_POST['order_id']??0);
            try{
                $st=$pdo->prepare("SELECT status FROM orders WHERE id=? AND customer_id=?");
                $st->execute([$orderId,$customer['id']]);$ownedOrder=$st->fetch();
                if(!$ownedOrder)throw new RuntimeException('Không tìm thấy đơn hàng thuộc tài khoản của bạn.');
                if($ownedOrder['status']!=='pending')throw new RuntimeException('Chỉ có thể hủy đơn đang chờ xác nhận.');
                set_order_status($pdo,$orderId,'cancelled');
                flash('success','Đơn hàng đã được hủy. Tồn kho size/màu đã được hoàn lại.');
            }catch(Throwable $ex){throw $ex;}
            redirect('customer_orders');
        }
        if($action==='add_cart'){
            $productId=(int)($_POST['product_id']??0);$variantId=(int)($_POST['variant_id']??0);
            $qty=(int)($_POST['quantity']??1);if($qty<1||$qty>20)throw new RuntimeException('Số lượng phải từ 1 đến 20.');
            $st=$pdo->prepare("SELECT v.id,v.stock_quantity,p.id product_id,p.status,p.is_published,p.name,v.size,v.color FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? AND p.id=?");
            $st->execute([$variantId,$productId]);$row=$st->fetch();
            if(!$row||$row['status']!=='active'||(int)$row['is_published']!==1)throw new RuntimeException('Sản phẩm hiện không được trưng bày.');
            $existing=(int)($_SESSION['wing_cart_v2'][$variantId]??0);
            if((int)$row['stock_quantity']<$existing+$qty)throw new RuntimeException('Không đủ tồn kho cho lựa chọn '.$row['size'].' · '.$row['color'].'.');
            $_SESSION['wing_cart_v2'][$variantId]=$existing+$qty;
            flash('success','Đã thêm '.$row['name'].' · '.$row['size'].' · '.$row['color'].' vào túi đồ.');
            redirect('cart');
        }
        if($action==='update_cart'){
            foreach(($_POST['qty']??[]) as $variantId=>$qty){
                $variantId=(int)$variantId;$qty=(int)$qty;
                if($qty<=0){unset($_SESSION['wing_cart_v2'][$variantId]);continue;}
                $st=$pdo->prepare("SELECT stock_quantity FROM product_variants WHERE id=?");$st->execute([$variantId]);$stock=(int)$st->fetchColumn();
                if($stock<=0)unset($_SESSION['wing_cart_v2'][$variantId]);
                else $_SESSION['wing_cart_v2'][$variantId]=min($stock,$qty);
            }
            flash('success','Túi đồ đã được cập nhật theo tồn kho hiện tại.');redirect('cart');
        }
        if($action==='place_order'){
            if(!$customer)redirect('login');
            $cart=$_SESSION['wing_cart_v2']??[];if(!$cart)throw new RuntimeException('Túi đồ đang trống.');
            $deliveryName=trim((string)($_POST['delivery_name']??$customer['full_name']));
            $deliveryPhone=trim((string)($_POST['delivery_phone']??$customer['phone']));
            $deliveryAddress=trim((string)($_POST['delivery_address']??$customer['address']));
            $customerNote=trim((string)($_POST['customer_note']??''));
            $paymentMethod=(string)($_POST['payment_method']??'cod');
            if(mb_strlen($deliveryName)<2||strlen($deliveryPhone)<8||mb_strlen($deliveryAddress)<8)throw new RuntimeException('Vui lòng nhập đầy đủ họ tên, số điện thoại và địa chỉ nhận hàng.');
            if(!in_array($paymentMethod,['cod','bank_transfer'],true))$paymentMethod='cod';

            $pdo->beginTransaction();$items=[];$total=0;$affected=[];
            foreach($cart as $variantId=>$qty){
                $st=$pdo->prepare("SELECT v.id variant_id,v.product_id,v.sku variant_sku,v.size,v.color,v.stock_quantity,p.sku product_sku,p.name,p.price,p.status,p.is_published FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? FOR UPDATE");
                $st->execute([(int)$variantId]);$variant=$st->fetch();
                if(!$variant||$variant['status']!=='active'||(int)$variant['is_published']!==1)throw new RuntimeException('Một sản phẩm trong túi đồ đã ngừng trưng bày.');
                if((int)$variant['stock_quantity']<(int)$qty)throw new RuntimeException('Không đủ tồn kho cho '.$variant['name'].' · '.$variant['size'].' · '.$variant['color'].'.');
                $items[]=[$variant,(int)$qty];$total+=(float)$variant['price']*(int)$qty;$affected[(int)$variant['product_id']]=true;
            }
            $code='WG'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $shipping=0;
            $st=$pdo->prepare("INSERT INTO orders(order_code,customer_id,user_id,total_amount,status,source,delivery_name,delivery_phone,delivery_address,customer_note,payment_method,payment_status,shipping_fee) VALUES(?,?,NULL,?,'pending','online',?,?,?,?,?,'unpaid',?)");
            $st->execute([$code,$customer['id'],$total,$deliveryName,$deliveryPhone,$deliveryAddress,$customerNote?:null,$paymentMethod,$shipping]);
            $orderId=(int)$pdo->lastInsertId();
            $detail=$pdo->prepare("INSERT INTO order_details(order_id,product_id,variant_id,product_name,sku,variant_size,variant_color,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $stock=$pdo->prepare("UPDATE product_variants SET stock_quantity=stock_quantity-? WHERE id=?");
            $log=$pdo->prepare("INSERT INTO stock_transactions(product_id,user_id,transaction_type,quantity,note) VALUES(?,NULL,'out',?,?)");
            foreach($items as [$v,$qty]){
                $line=(float)$v['price']*$qty;
                $detail->execute([$orderId,$v['product_id'],$v['variant_id'],$v['name'],$v['variant_sku'],$v['size'],$v['color'],$qty,$v['price'],$line]);
                $stock->execute([$qty,$v['variant_id']]);
                $log->execute([$v['product_id'],$qty,'Đơn website '.$code.' · '.$v['size'].' · '.$v['color']]);
            }
            foreach(array_keys($affected) as $pid)refresh_product_stock($pdo,(int)$pid);
            $pdo->commit();$_SESSION['wing_cart_v2']=[];$_SESSION['wing_last_order']=$code;
            try{wing_notify_roles($pdo,['staff','admin'],'new_online_order','Có đơn hàng mới từ website',$code.' · '.money($total),'index.php?page=orders','online-order:'.$orderId);}catch(Throwable $notifyError){error_log('WinG order notification: '.$notifyError->getMessage());}
            flash('success','Đặt hàng thành công. Mã đơn: '.$code);redirect('customer_orders');
        }
    } catch(Throwable $ex) {
        if($pdo->inTransaction())$pdo->rollBack();
        flash('error',$ex instanceof RuntimeException?$ex->getMessage():'Có lỗi xảy ra. Vui lòng thử lại.');
        if($action==='add_cart'&&!empty($_POST['product_id'])){header('Location: '.BASE_URL.'/index.php?page=product&id='.(int)$_POST['product_id']);exit;}
        $fallback=match($action){'register'=>'customer_register','customer_login'=>'login','add_cart'=>'product','place_order'=>'checkout','cancel_order'=>'customer_orders','update_profile'=>'customer_profile','wishlist_toggle'=>'shop','create_support_ticket'=>'support','customer_support_reply'=>'support','mark_customer_notifications_read'=>'notifications',default=>'cart'};redirect($fallback);
    }
}
if($page==='customer_logout'){logout_user();header('Location: '.BASE_URL.'/index.php?page=login');exit;}
if($page==='notifications'&&$customer&&!empty($_GET['read'])){
 $st=$pdo->prepare("SELECT link,kind FROM wing_notifications WHERE id=? AND recipient_type='customer' AND recipient_id=?");$st->execute([(int)$_GET['read'],(int)$customer['id']]);$notificationTarget=$st->fetch();
 $target=(string)($notificationTarget['link']??'');
 $pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE id=? AND recipient_type='customer' AND recipient_id=?")->execute([(int)$_GET['read'],(int)$customer['id']]);
 if($target!==''&&!preg_match('~^(https?:)?//~i',$target)){
   if(($notificationTarget['kind']??'')==='review_request'){
    $parts=explode('#',$target,2);$target=$parts[0].(str_contains($parts[0],'?')?'&':'?').'review_notification_id='.(int)$_GET['read'].(isset($parts[1])?'#'.$parts[1]:'');
   }
   header('Location: '.BASE_URL.'/'.ltrim($target,'/'));exit;
 }
}
if($customer&&!empty($_GET['review_notification_id'])){$pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE id=? AND recipient_type='customer' AND recipient_id=? AND kind='review_request'")->execute([(int)$_GET['review_notification_id'],(int)$customer['id']]);}
$flash=get_flash();$cart=$_SESSION['wing_cart_v2']??[];$cartCount=array_sum($cart);
$products=[];$categories=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
if(in_array($page,['shop','product','cart','checkout'],true)){
 if($page==='shop'){
  $where=["p.status='active'","p.is_published=1"];$args=[];
  if(!empty($_GET['category'])){$where[]='p.category_id=?';$args[]=(int)$_GET['category'];}
  if(!empty($_GET['q'])){$where[]='(p.name LIKE ? OR p.sku LIKE ? OR p.collection_name LIKE ?)';$term='%'.trim((string)$_GET['q']).'%';$args[]=$term;$args[]=$term;$args[]=$term;}
  if(!empty($_GET['gender'])&&in_array($_GET['gender'],['men','women','unisex','kids'],true)){$where[]='p.gender=?';$args[]=$_GET['gender'];}
  if(isset($_GET['min_price'])&&is_numeric($_GET['min_price'])){$where[]='p.price>=?';$args[]=(float)$_GET['min_price'];}
  if(isset($_GET['max_price'])&&is_numeric($_GET['max_price'])&&(float)$_GET['max_price']>0){$where[]='p.price<=?';$args[]=(float)$_GET['max_price'];}
  $sort=(string)($_GET['sort']??'newest');
  $orderBy=match($sort){'price_low'=>'p.price ASC','price_high'=>'p.price DESC','name'=>'p.name ASC','popular'=>'p.id DESC',default=>'p.id DESC'};
  $st=$pdo->prepare("SELECT p.*,c.name category,COALESCE(rv.avg_rating,0) avg_rating,COALESCE(rv.review_count,0) review_count FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN (SELECT product_id,AVG(rating) avg_rating,COUNT(*) review_count FROM product_reviews WHERE status='visible' GROUP BY product_id) rv ON rv.product_id=p.id WHERE ".implode(' AND ',$where)." ORDER BY ".$orderBy);$st->execute($args);$products=$st->fetchAll();
 }
}
$selected=null;
if($page==='product'){$st=$pdo->prepare("SELECT p.*,c.name category,COALESCE(rv.avg_rating,0) avg_rating,COALESCE(rv.review_count,0) review_count FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN (SELECT product_id,AVG(rating) avg_rating,COUNT(*) review_count FROM product_reviews WHERE status='visible' GROUP BY product_id) rv ON rv.product_id=p.id WHERE p.id=? AND p.status='active' AND p.is_published=1");$st->execute([(int)($_GET['id']??0)]);$selected=$st->fetch();if(!$selected){http_response_code(404);} }
$productVariants=[];
if($selected){$st=$pdo->prepare("SELECT id,sku,size,color,stock_quantity,image_url FROM product_variants WHERE product_id=? ORDER BY size,color");$st->execute([$selected['id']]);$productVariants=$st->fetchAll();}
$productReviews=[];$reviewableOrders=[];
if($selected){
 $st=$pdo->prepare("SELECT r.rating,r.review_text,r.created_at,c.full_name FROM product_reviews r JOIN customers c ON c.id=r.customer_id WHERE r.product_id=? AND r.status='visible' ORDER BY r.created_at DESC LIMIT 30");
 $st->execute([$selected['id']]);$productReviews=$st->fetchAll();
 if($customer){$st=$pdo->prepare("SELECT DISTINCT o.id,o.order_code FROM orders o JOIN order_details d ON d.order_id=o.id JOIN wing_notifications n ON n.recipient_type='customer' AND n.recipient_id=o.customer_id AND n.dedupe_key=CONCAT('review:',o.id,':',d.product_id) LEFT JOIN product_reviews r ON r.order_id=o.id AND r.product_id=d.product_id WHERE o.customer_id=? AND o.status='completed' AND d.product_id=? AND r.id IS NULL AND (?=0 OR o.id=?) ORDER BY o.id DESC");
 $reviewOrderId=(int)($_GET['review_order_id']??0);$st->execute([$customer['id'],$selected['id'],$reviewOrderId,$reviewOrderId]);$reviewableOrders=$st->fetchAll();}
}
$cartProducts=[];
if(in_array($page,['cart','checkout'],true)&&$cart){$ids=array_map('intval',array_keys($cart));$marks=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT v.id variant_id,v.sku variant_sku,v.size,v.color,v.stock_quantity variant_stock,p.* FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id IN ($marks)");$st->execute($ids);$cartProducts=$st->fetchAll();}
$orders=[];$favoriteIds=[];
if($customer){
 $st=$pdo->prepare("SELECT product_id FROM customer_wishlist WHERE customer_id=?");$st->execute([$customer['id']]);$favoriteIds=array_map('intval',array_column($st->fetchAll(),'product_id'));
}
$wishProducts=[];
if($page==='wishlist'&&$customer){
 $st=$pdo->prepare("SELECT p.*,c.name category FROM customer_wishlist w JOIN products p ON p.id=w.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE w.customer_id=? AND p.status='active' AND p.is_published=1 ORDER BY w.created_at DESC");
 $st->execute([$customer['id']]);$wishProducts=$st->fetchAll();
}
if($page==='customer_orders'&&$customer){$st=$pdo->prepare("SELECT * FROM orders WHERE customer_id=? ORDER BY id DESC");$st->execute([$customer['id']]);$orders=$st->fetchAll();}
$orderDetail=null;$orderLines=[];
if($page==='customer_order_detail'&&$customer){
 $st=$pdo->prepare("SELECT * FROM orders WHERE id=? AND customer_id=?");$st->execute([(int)($_GET['id']??0),$customer['id']]);$orderDetail=$st->fetch();
 if($orderDetail){$st=$pdo->prepare("SELECT * FROM order_details WHERE order_id=?");$st->execute([$orderDetail['id']]);$orderLines=$st->fetchAll();}
}
$customerNotifications=[];$customerUnreadCount=0;
if($customer){
 $customerUnreadCount=wing_notification_unread($pdo,'customer',(int)$customer['id']);
 if($page==='notifications'){$st=$pdo->prepare("SELECT * FROM wing_notifications WHERE recipient_type='customer' AND recipient_id=? ORDER BY created_at DESC LIMIT 100");$st->execute([$customer['id']]);$customerNotifications=$st->fetchAll();}
}
$customerTickets=[];$customerTicket=null;$customerTicketMessages=[];
if($customer&&$page==='support'){
 $st=$pdo->prepare("SELECT * FROM support_tickets WHERE customer_id=? ORDER BY updated_at DESC LIMIT 50");$st->execute([$customer['id']]);$customerTickets=$st->fetchAll();
 if(!empty($_GET['id'])){$st=$pdo->prepare("SELECT * FROM support_tickets WHERE id=? AND customer_id=?");$st->execute([(int)$_GET['id'],$customer['id']]);$customerTicket=$st->fetch();if($customerTicket){$st=$pdo->prepare("SELECT * FROM support_ticket_messages WHERE ticket_id=? AND is_internal=0 ORDER BY created_at ASC");$st->execute([$customerTicket['id']]);$customerTicketMessages=$st->fetchAll();}}
}
function wing_img(?string $url):string{
 if(!$url)return 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=85';
 if(preg_match('~^https?://~i',$url))return $url;
 return BASE_URL.'/'.ltrim($url,'/');
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>WinG | The Fashion House</title>
<link rel="icon" type="image/png" sizes="128x128" href="<?= BASE_URL ?>/assets/images/wing-favicon.png?v=wing-v4">
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/wing-favicon.ico?v=wing-v4">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/wing-favicon.png?v=wing-v4">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=wing-v5-editorial"></head><body class="storefront">
<?php require __DIR__ . '/../includes/wing_intro.php'; ?>
<header class="store-header">
 <a class="store-brand" href="<?= BASE_URL ?>/index.php?page=shop"><span>W</span><b>WinG<small>THE FASHION HOUSE</small></b></a>
 <button type="button" class="store-mobile-menu" id="storeMenuToggle" aria-label="Mở menu" aria-controls="storeNav storeTools" aria-expanded="false"><span aria-hidden="true">☰</span></button>
 <nav id="storeNav"><a href="<?= BASE_URL ?>/index.php?page=shop">CỬA HÀNG</a><div class="collection-nav"><a href="<?= BASE_URL ?>/index.php?page=shop" class="collection-nav-trigger">BỘ SƯU TẬP <span>⌄</span></a><div class="collection-mega"><div class="collection-mega-heading"><small>EXPLORE WING</small><b>Khám phá theo danh mục</b></div><div class="collection-mega-grid"><?php foreach($categories as $cat): ?><a href="<?= BASE_URL ?>/index.php?page=shop&category=<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?><span>↗</span></a><?php endforeach; ?></div><a class="collection-all" href="<?= BASE_URL ?>/index.php?page=shop">XEM TOÀN BỘ CỬA HÀNG →</a></div></div><a href="<?= BASE_URL ?>/index.php?page=support">LIÊN HỆ & HỖ TRỢ</a></nav>
 <div class="store-tools" id="storeTools"><a href="<?= BASE_URL ?>/index.php?page=wishlist">YÊU THÍCH ♡</a><a class="notification-nav" href="<?= BASE_URL ?>/index.php?page=<?= $customer?'notifications':'login' ?>" aria-label="Thông báo">♧ THÔNG BÁO<?php if($customer&&$customerUnreadCount): ?><em><?= $customerUnreadCount>9?'9+':$customerUnreadCount ?></em><?php endif; ?></a><a href="<?= BASE_URL ?>/index.php?page=cart">TÚI ĐỒ (<?= (int)$cartCount ?>)</a><?php if($customer): ?><div class="account-menu"><a href="<?= BASE_URL ?>/index.php?page=customer_profile">TÀI KHOẢN⌄</a><div class="account-dropdown"><a href="<?= BASE_URL ?>/index.php?page=customer_profile">Hồ sơ của tôi</a><a href="<?= BASE_URL ?>/index.php?page=customer_orders">Đơn hàng</a><a href="<?= BASE_URL ?>/index.php?page=wishlist">Sản phẩm yêu thích</a><a href="<?= BASE_URL ?>/index.php?page=support">Hỗ trợ của tôi</a><a href="<?= BASE_URL ?>/index.php?page=notifications">Thông báo</a><a href="<?= BASE_URL ?>/index.php?page=logout">Đăng xuất</a></div></div><?php elseif(current_user()): ?><a href="<?= BASE_URL ?>/index.php?page=dashboard">CỔNG QUẢN TRỊ ↗</a><?php else: ?><a href="<?= BASE_URL ?>/index.php?page=login">ĐĂNG NHẬP</a><?php endif; ?></div>
 <button type="button" class="store-menu-backdrop" id="storeMenuBackdrop" aria-label="Đóng menu" hidden></button>
</header>
<?php if($flash): ?><div class="store-flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if($page==='shop'): ?>
<section class="store-hero">
 <img src="<?= BASE_URL ?>/assets/images/wing-hero-luxury.png" alt="WinG Fashion House — bộ sưu tập thời trang cao cấp" width="1672" height="941" fetchpriority="high" decoding="async">
 <div class="store-hero-shade"></div>
 <div class="store-hero-copy"><span>THE NEW EXPRESSION · 2026</span><h1>Elegance,<br><i>redefined.</i></h1><p>Những lựa chọn tinh giản. Một dấu ấn riêng biệt.</p><a href="#collections">KHÁM PHÁ BỘ SƯU TẬP <b>↗</b></a></div>
</section>
<section class="store-section" id="collections">
 <div class="store-section-head"><div><span class="store-kicker">THE WING SELECTION</span><h2>Find your signature.</h2><p>Bộ sưu tập được tuyển chọn cho phong cách vượt thời gian.</p></div>
 <form class="store-search" method="get"><input type="hidden" name="page" value="shop"><input name="q" placeholder="Tìm sản phẩm, mã SKU..." value="<?= e($_GET['q']??'') ?>"><button aria-label="Tìm kiếm">⌕</button></form></div>
 <form method="get" class="catalog-filter"><input type="hidden" name="page" value="shop"><?php if(!empty($_GET['q'])): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>
  <div class="category-pills"><a class="<?= empty($_GET['category'])?'chosen':'' ?>" href="<?= BASE_URL ?>/index.php?page=shop">Tất cả</a><?php foreach($categories as $c): ?><a class="<?= (int)($_GET['category']??0)===(int)$c['id']?'chosen':'' ?>" href="<?= BASE_URL ?>/index.php?page=shop&category=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a><?php endforeach; ?></div>
  <div class="catalog-filter-fields">
   <label>ĐỐI TƯỢNG<select name="gender"><option value="">Tất cả</option><option value="women" <?= ($_GET['gender']??'')==='women'?'selected':'' ?>>Nữ</option><option value="men" <?= ($_GET['gender']??'')==='men'?'selected':'' ?>>Nam</option><option value="unisex" <?= ($_GET['gender']??'')==='unisex'?'selected':'' ?>>Unisex</option><option value="kids" <?= ($_GET['gender']??'')==='kids'?'selected':'' ?>>Trẻ em</option></select></label>
   <label>GIÁ TỪ<input type="number" name="min_price" min="0" step="50000" placeholder="0" value="<?= e($_GET['min_price']??'') ?>"></label>
   <label>ĐẾN GIÁ<input type="number" name="max_price" min="0" step="50000" placeholder="Không giới hạn" value="<?= e($_GET['max_price']??'') ?>"></label>
   <label>SẮP XẾP<select name="sort"><option value="newest" <?= ($_GET['sort']??'newest')==='newest'?'selected':'' ?>>Mới nhất</option><option value="price_low" <?= ($_GET['sort']??'')==='price_low'?'selected':'' ?>>Giá tăng dần</option><option value="price_high" <?= ($_GET['sort']??'')==='price_high'?'selected':'' ?>>Giá giảm dần</option><option value="name" <?= ($_GET['sort']??'')==='name'?'selected':'' ?>>Tên A–Z</option></select></label>
   <button class="store-filter-submit">ÁP DỤNG</button>
  </div>
 </form>
 <div class="product-grid" id="new-arrivals">
 <?php foreach($products as $prod): ?><article class="store-product reveal">
  <div class="product-photo"><a href="<?= BASE_URL ?>/index.php?page=product&id=<?= (int)$prod['id'] ?>"><img loading="lazy" src="<?= e(wing_img($prod['image_url'])) ?>" alt="<?= e($prod['name']) ?>" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=85';"></a>
   <span class="product-view">KHÁM PHÁ SẢN PHẨM ↗</span>
   <?php if($customer): ?><form method="post" class="wishlist-form"><?= csrf_field() ?><input type="hidden" name="action" value="wishlist_toggle"><input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>"><input type="hidden" name="return_to" value="shop"><button class="wish-button <?= in_array((int)$prod['id'],$favoriteIds,true)?'saved':'' ?>" title="Yêu thích" aria-label="Yêu thích"><?= in_array((int)$prod['id'],$favoriteIds,true)?'♥':'♡' ?></button></form><?php else: ?><a class="wish-button" href="<?= BASE_URL ?>/index.php?page=login" title="Đăng nhập để lưu yêu thích">♡</a><?php endif; ?>
  </div>
  <div class="product-meta"><span><?= e($prod['collection_name']?:($prod['category']??'WING COLLECTION')) ?> · <?= ['women'=>'NỮ','men'=>'NAM','unisex'=>'UNISEX','kids'=>'TRẺ EM'][$prod['gender']]??'WING' ?></span><small><?= e($prod['sku']) ?></small></div>
  <a class="product-name" href="<?= BASE_URL ?>/index.php?page=product&id=<?= (int)$prod['id'] ?>"><?= e($prod['name']) ?></a>
  <div class="product-rating"><span><?= (int)round((float)$prod['avg_rating'])>0?str_repeat('★',(int)round((float)$prod['avg_rating'])).str_repeat('☆',5-(int)round((float)$prod['avg_rating'])):'☆☆☆☆☆' ?></span><small><?= (int)$prod['review_count'] ?> đánh giá</small></div>
  <div class="product-bottom"><b><?= money($prod['price']) ?></b><span><?= (int)$prod['stock_quantity']>0?'AVAILABLE':'SOLD OUT' ?></span></div>
 </article><?php endforeach; ?>
 <?php if(!$products): ?><p class="empty">Không tìm thấy sản phẩm phù hợp. Hãy thử từ khóa hoặc danh mục khác.</p><?php endif; ?>
 </div>
</section>
<section class="store-benefits"><div><span>✳</span><b>CURATED QUALITY</b><small>Lựa chọn kỹ lưỡng</small></div><div><span>◇</span><b>THOUGHTFUL DETAILS</b><small>Chú trọng từng chi tiết</small></div><div><span>↗</span><b>PERSONAL SERVICE</b><small>Trải nghiệm tận tâm</small></div></section>
<?php elseif($page==='product'&&$selected): ?>
<section class="product-detail product-detail-rich">
 <div class="detail-image"><img id="productMainImage" data-default-src="<?= e(wing_img($selected['image_url'])) ?>" src="<?= e(wing_img($selected['image_url'])) ?>" alt="<?= e($selected['name']) ?>" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=85';"></div>
 <div class="detail-copy">
  <span class="store-kicker"><?= e($selected['collection_name']?:($selected['category']??'WING COLLECTION')) ?> · <?= e($selected['sku']) ?></span>
  <div class="detail-gender"><?= ['women'=>'WOMEN','men'=>'MEN','unisex'=>'UNISEX','kids'=>'KIDS'][$selected['gender']]??'WING COLLECTION' ?></div>
  <h1><?= e($selected['name']) ?></h1>
  <strong class="detail-price"><?= money($selected['price']) ?></strong>
  <a class="detail-rating-link" href="#customer-reviews"><span><?= (float)$selected['avg_rating']>0?str_repeat('★',(int)round((float)$selected['avg_rating'])).str_repeat('☆',5-(int)round((float)$selected['avg_rating'])):'☆☆☆☆☆' ?></span> <?= number_format((float)$selected['avg_rating'],1) ?>/5 · <?= (int)$selected['review_count'] ?> đánh giá</a>
  <p><?= nl2br(e($selected['description']?:'Một thiết kế được hoàn thiện với sự chú trọng dành cho chất liệu, phom dáng và từng chi tiết.')) ?></p>
  <?php if(!empty($selected['material'])): ?><p class="detail-material"><b>CHẤT LIỆU</b> · <?= e($selected['material']) ?></p><?php endif; ?>
  <?php if($productVariants): ?>
  <form method="post" class="add-cart-form variant-buy-form" id="variantBuyForm"><?= csrf_field() ?>
   <input type="hidden" name="action" value="add_cart"><input type="hidden" name="product_id" value="<?= (int)$selected['id'] ?>">
   <input type="hidden" name="variant_id" id="selectedVariantId" value="">
   <div class="variant-picker">
    <div class="picker-heading"><b>KÍCH CỠ / SIZE</b><span id="selectedSizeLabel">Chưa chọn size</span></div>
    <div class="size-button-list" id="variantSizeButtons"></div>
    <div class="picker-heading color-picker-heading"><b>MÀU SẮC / COLOR</b><span id="selectedColorLabel">Chưa chọn màu</span></div>
    <div class="color-swatch-list" id="variantColorButtons"></div>
    <input type="hidden" id="variantSize" value=""><input type="hidden" id="variantColor" value="">
   </div>
   <div class="size-guide-row"><span id="variantStock">Chọn kích cỡ và màu sắc để xem tồn kho.</span><button type="button" class="size-guide-trigger" id="sizeGuideOpen">HƯỚNG DẪN CHỌN SIZE ↗</button></div>
   <label>SỐ LƯỢNG<input id="variantQuantity" type="number" name="quantity" min="1" max="1" value="1" required></label>
   <button class="store-cta" id="addToBagButton" type="submit" disabled>THÊM VÀO TÚI ĐỒ ↗</button>
  </form>
  <div class="size-guide-modal" id="sizeGuideModal" aria-hidden="true"><div class="size-guide-panel"><button type="button" id="sizeGuideClose" class="size-guide-close">×</button><span class="store-kicker">WING FIT GUIDE</span><h3>Hướng dẫn chọn kích cỡ</h3><p>Size có thể khác nhau theo phom dáng từng thiết kế. Hãy kiểm tra mô tả và liên hệ cửa hàng để được tư vấn khi cần.</p><table><thead><tr><th>SIZE</th><th>GỢI Ý</th></tr></thead><tbody><tr><td>XS – S</td><td>Phom nhỏ</td></tr><tr><td>M</td><td>Phom tiêu chuẩn</td></tr><tr><td>L – XL</td><td>Phom rộng hơn</td></tr><tr><td>One Size</td><td>Phụ kiện / kích cỡ chung</td></tr></tbody></table></div></div>
  <script>
  (()=>{const variants=<?= json_encode(array_map(function($v){$v['image_url']=$v['image_url']?wing_img($v['image_url']):null;return $v;},$productVariants),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
   const size=document.getElementById('variantSize'),color=document.getElementById('variantColor'),id=document.getElementById('selectedVariantId'),stock=document.getElementById('variantStock'),qty=document.getElementById('variantQuantity'),btn=document.getElementById('addToBagButton');
   const sizeBox=document.getElementById('variantSizeButtons'),colorBox=document.getElementById('variantColorButtons');
   const sizeLabel=document.getElementById('selectedSizeLabel'),colorLabel=document.getElementById('selectedColorLabel'),mainImage=document.getElementById('productMainImage');
   const colorMap={'đen':'#171717','trắng':'#f6f4ed','be':'#c8b99c','kem':'#e8dfcd','đỏ':'#a52d32','xanh navy':'#1e304b','navy':'#1e304b','xanh':'#58745c','xanh lá':'#58745c','nâu':'#75533b','hồng':'#d8a8ad','xám':'#8b8d8a','mặc định':'#c9c2b4'};
   const sizes=[...new Set(variants.map(v=>v.size))];
   function renderSizes(){sizeBox.innerHTML='';sizes.forEach(s=>{const available=variants.some(v=>v.size===s&&Number(v.stock_quantity)>0);const b=document.createElement('button');b.type='button';b.className='size-choice';b.textContent=s;b.disabled=!available;b.setAttribute('aria-pressed',size.value===s?'true':'false');b.addEventListener('click',()=>{size.value=s;sizeLabel.textContent=s;color.value='';id.value='';renderSizes();renderColors();syncVariant();});sizeBox.appendChild(b);});}
   function renderColors(){colorBox.innerHTML='';const rows=variants.filter(v=>v.size===size.value);const colors=[...new Set(rows.map(v=>v.color))];if(!colors.includes(color.value)){const first=rows.find(v=>Number(v.stock_quantity)>0);color.value=first?first.color:'';colorLabel.textContent=color.value||'Không còn màu';}colors.forEach(c=>{const v=rows.find(x=>x.color===c);const b=document.createElement('button');b.type='button';b.className='color-choice';b.disabled=!v||Number(v.stock_quantity)<1;b.setAttribute('aria-label',c);b.setAttribute('aria-pressed',color.value===c?'true':'false');const dot=document.createElement('i');dot.style.backgroundColor=colorMap[String(c).toLowerCase()]||'#b6b1a5';b.appendChild(dot);const label=document.createElement('span');label.textContent=c;b.appendChild(label);b.addEventListener('click',()=>{color.value=c;colorLabel.textContent=c;renderColors();syncVariant();});colorBox.appendChild(b);});}
   function syncVariant(){const v=variants.find(x=>x.size===size.value&&x.color===color.value);id.value=v?v.id:'';if(mainImage){mainImage.src=(v&&v.image_url)?v.image_url:mainImage.dataset.defaultSrc;}const available=v&&Number(v.stock_quantity)>0;stock.textContent=v?(available?'Còn '+v.stock_quantity+' sản phẩm':'Tạm hết hàng'):'Chọn một màu có sẵn cho kích cỡ này.';qty.max=v?Math.max(1,Number(v.stock_quantity)):1;if(v&&Number(qty.value)>Number(v.stock_quantity))qty.value=v.stock_quantity;btn.disabled=!available;}
   renderSizes();if(sizes.length){size.value=sizes[0];sizeLabel.textContent=sizes[0];renderSizes();renderColors();syncVariant();}
   const modal=document.getElementById('sizeGuideModal');document.getElementById('sizeGuideOpen')?.addEventListener('click',()=>{modal.classList.add('open');modal.setAttribute('aria-hidden','false')});document.getElementById('sizeGuideClose')?.addEventListener('click',()=>{modal.classList.remove('open');modal.setAttribute('aria-hidden','true')});modal?.addEventListener('click',e=>{if(e.target===modal){modal.classList.remove('open');modal.setAttribute('aria-hidden','true')}});
  })();
  </script>
  <?php else: ?><p class="detail-stock">Sản phẩm chưa có biến thể để đặt hàng. Vui lòng liên hệ cửa hàng.</p><?php endif; ?>
  <?php if($customer): ?><form method="post" class="detail-wishlist"><?= csrf_field() ?><input type="hidden" name="action" value="wishlist_toggle"><input type="hidden" name="product_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="return_to" value="product"><button class="store-outline"><?= in_array((int)$selected['id'],$favoriteIds,true)?'♥ ĐÃ LƯU YÊU THÍCH':'♡ THÊM VÀO YÊU THÍCH' ?></button></form><?php else: ?><a class="store-outline detail-wishlist" href="<?= BASE_URL ?>/index.php?page=login">♡ ĐĂNG NHẬP ĐỂ LƯU YÊU THÍCH</a><?php endif; ?>
  <section class="customer-reviews" id="customer-reviews">
   <div class="reviews-heading"><div><span class="store-kicker">CUSTOMER VOICES</span><h2>Đánh giá từ khách hàng</h2><div class="review-average"><b><?= number_format((float)$selected['avg_rating'],1) ?></b><span>★</span><small>/ 5 · <?= (int)$selected['review_count'] ?> đánh giá đã xác thực</small></div></div></div>
   <?php if($customer&&$reviewableOrders): ?><div class="review-invite-note">✦ Lời mời đánh giá dành riêng cho đơn hàng bạn đã mua và nhận thành công.</div><form method="post" class="review-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_review"><input type="hidden" name="product_id" value="<?= (int)$selected['id'] ?>"><label>ĐƠN HÀNG ĐÃ HOÀN THÀNH<select name="order_id" required><?php foreach($reviewableOrders as $ro): ?><option value="<?= (int)$ro['id'] ?>"><?= e($ro['order_code']) ?></option><?php endforeach; ?></select></label><label>ĐÁNH GIÁ CỦA BẠN</label><div class="review-star-picker"><input type="hidden" name="rating" id="reviewRating" value="5"><?php for($star=1;$star<=5;$star++): ?><button type="button" data-rating="<?= $star ?>" aria-label="<?= $star ?> sao">★</button><?php endfor; ?></div><textarea name="review_text" rows="3" maxlength="1000" placeholder="Chia sẻ trải nghiệm về chất liệu, phom dáng và chất lượng..."></textarea><button class="store-cta" type="submit">GỬI ĐÁNH GIÁ</button></form><?php elseif(!$customer): ?><p class="reviews-hint"><a href="<?= BASE_URL ?>/index.php?page=login">Đăng nhập</a> để theo dõi lời mời đánh giá sau khi mua hàng.</p><?php else: ?><p class="reviews-hint">Biểu mẫu đánh giá chỉ xuất hiện trong thông báo dành cho khách đã mua sản phẩm và đơn hàng đã hoàn thành.</p><?php endif; ?>
   <div class="review-list"><?php foreach($productReviews as $review): ?><article class="review-item"><div class="review-item-top"><b><?= e($review['full_name']) ?></b><span><?= str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating']) ?></span><time><?= date('d/m/Y',strtotime($review['created_at'])) ?></time></div><p><?= e($review['review_text']?:'Khách hàng đã đánh giá sản phẩm này.') ?></p><small>✓ Đã mua hàng · Đơn đã hoàn thành</small></article><?php endforeach; ?><?php if(!$productReviews): ?><p class="reviews-hint">Sản phẩm chưa có đánh giá. Hãy là người đầu tiên chia sẻ trải nghiệm sau khi mua hàng.</p><?php endif; ?></div>
  </section>
  <script>
  (()=>{const input=document.getElementById('reviewRating');document.querySelectorAll('.review-star-picker button').forEach(btn=>btn.addEventListener('click',()=>{input.value=btn.dataset.rating;document.querySelectorAll('.review-star-picker button').forEach(b=>b.classList.toggle('chosen',Number(b.dataset.rating)<=Number(input.value)));}));document.querySelectorAll('.review-star-picker button').forEach(b=>b.classList.toggle('chosen',Number(b.dataset.rating)<=5));})();
  </script>
  <div class="product-service-notes"><span>✦ Sản phẩm chính hãng WinG</span><span>↗ Hỗ trợ đổi trả theo chính sách cửa hàng</span><span>◇ Đóng gói chỉn chu</span></div>
  <a class="back-shop" href="<?= BASE_URL ?>/index.php?page=shop">← TIẾP TỤC KHÁM PHÁ</a>
 </div>
</section>
<?php elseif($page==='cart'): ?>
<section class="store-section narrow"><span class="store-kicker">YOUR SELECTION / WING BAG</span><h1 class="store-page-title">Túi đồ của bạn</h1>
<?php if(!$cartProducts): ?><div class="empty-cart"><h2>Túi đồ đang trống</h2><p>Hãy khám phá bộ sưu tập WinG và chọn thiết kế yêu thích.</p><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=shop">KHÁM PHÁ CỬA HÀNG</a></div>
<?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_cart"><div class="cart-list"><?php $grand=0;foreach($cartProducts as $prod):$qty=(int)($cart[$prod['variant_id']]??0);$line=$qty*(float)$prod['price'];$grand+=$line; ?>
<div class="cart-row"><img src="<?= e(wing_img($prod['image_url'])) ?>" alt=""><div><b><?= e($prod['name']) ?></b><small><?= e($prod['sku']) ?> · <?= e($prod['size']) ?> · <?= e($prod['color']) ?></small><span><?= money($prod['price']) ?></span></div><label>SL<input type="number" min="0" max="<?= (int)$prod['variant_stock'] ?>" name="qty[<?= (int)$prod['variant_id'] ?>]" value="<?= $qty ?>"></label><strong><?= money($line) ?></strong></div><?php endforeach; ?></div>
<div class="cart-summary"><button class="store-outline">CẬP NHẬT TÚI ĐỒ</button><div><small>TẠM TÍNH</small><b><?= money($grand) ?></b><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=checkout">TIẾN HÀNH ĐẶT HÀNG ↗</a></div></div></form><?php endif; ?></section>
<?php elseif($page==='checkout'): ?>
<section class="store-section narrow checkout-layout"><div class="checkout-heading"><span class="store-kicker">FINAL STEP / WING CHECKOUT</span><h1 class="store-page-title">Hoàn tất đơn hàng</h1><p>Kiểm tra lựa chọn và điền thông tin giao nhận của bạn.</p></div>
<?php if(!$cartProducts): ?><div class="empty-cart"><h2>Túi đồ đang trống</h2><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=shop">QUAY LẠI CỬA HÀNG</a></div>
<?php elseif(!$customer): ?><div class="empty-cart"><h2>Đăng nhập để tiếp tục</h2><p>Đơn hàng sẽ được liên kết riêng với tài khoản của bạn.</p><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=login">ĐĂNG NHẬP WIN G ↗</a></div>
<?php else: ?><div class="checkout-columns"><form method="post" class="checkout-form"><?= csrf_field() ?><input type="hidden" name="action" value="place_order">
<h2>Thông tin nhận hàng</h2>
<label>HỌ VÀ TÊN<input name="delivery_name" required minlength="2" value="<?= e($customer['full_name']) ?>"></label>
<label>SỐ ĐIỆN THOẠI<input name="delivery_phone" required minlength="8" value="<?= e($customer['phone']) ?>"></label>
<label>ĐỊA CHỈ GIAO HÀNG<textarea name="delivery_address" rows="3" required minlength="8"><?= e($customer['address']) ?></textarea></label>
<label>GHI CHÚ ĐƠN HÀNG <small>(Không bắt buộc)</small><textarea name="customer_note" rows="2" maxlength="500" placeholder="Ghi chú về thời gian hoặc hướng dẫn giao hàng"></textarea></label>
<h2>Phương thức thanh toán</h2><label class="payment-choice"><input type="radio" name="payment_method" value="cod" checked> Thanh toán khi nhận hàng (COD)</label><label class="payment-choice"><input type="radio" name="payment_method" value="bank_transfer"> Chuyển khoản (demo – chưa xác nhận tự động)</label>
<button class="store-cta checkout-submit" id="checkoutSubmit">ĐẶT HÀNG · THANH TOÁN COD ↗</button><script>document.querySelectorAll('input[name="payment_method"]').forEach(r=>r.addEventListener('change',()=>{document.getElementById('checkoutSubmit').textContent=r.value==='cod'?'ĐẶT HÀNG · THANH TOÁN COD ↗':'ĐẶT HÀNG · CHUYỂN KHOẢN ↗'}));</script></form>
<aside class="checkout-summary"><span class="store-kicker">ORDER SUMMARY</span><h2>Tóm tắt đơn hàng</h2><?php $grand=0;foreach($cartProducts as $prod):$qty=(int)($cart[$prod['variant_id']]??0);$line=$qty*(float)$prod['price'];$grand+=$line; ?><div class="checkout-line"><img src="<?= e(wing_img($prod['image_url'])) ?>" alt=""><div><b><?= e($prod['name']) ?></b><small><?= e($prod['size']) ?> · <?= e($prod['color']) ?> · SL <?= $qty ?></small></div><strong><?= money($line) ?></strong></div><?php endforeach; ?>
<div class="checkout-total"><span>Tạm tính</span><b><?= money($grand) ?></b></div><div class="checkout-total"><span>Phí giao hàng</span><b>Miễn phí (demo)</b></div><div class="checkout-grand"><span>Tổng cộng</span><b><?= money($grand) ?></b></div><p>Đơn hàng sẽ chuyển đến Admin/Staff để xác nhận và xử lý.</p></aside></div><?php endif; ?></section>
<?php elseif($page==='customer_login'||$page==='customer_register'): ?>
<section class="customer-auth"><div class="auth-editorial"><span>WING / MEMBERSHIP</span><h1>Personal<br><i>expression.</i></h1><p>Gia nhập WinG để lưu giữ lựa chọn và theo dõi hành trình đơn hàng của bạn.</p></div><div class="customer-auth-form"><span class="store-kicker"><?= $page==='customer_login'?'MEMBER ACCESS':'JOIN THE HOUSE' ?></span><h2><?= $page==='customer_login'?'Chào mừng trở lại':'Tạo tài khoản WinG' ?></h2><?php if($page==='customer_login'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="customer_login"><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Mật khẩu<input type="password" name="password" required autocomplete="current-password"></label><button class="store-cta">ĐĂNG NHẬP ↗</button></form><p>Chưa có tài khoản? <a href="<?= BASE_URL ?>/index.php?page=customer_register">Đăng ký tại đây</a></p><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="register"><label>Họ và tên<input name="full_name" required minlength="2"></label><label>Email<input type="email" name="email" required></label><label>Số điện thoại<input name="phone"></label><label>Địa chỉ nhận hàng<input name="address"></label><label>Mật khẩu <small>(ít nhất 10 ký tự)</small><input type="password" name="password" minlength="10" required></label><button class="store-cta">TẠO TÀI KHOẢN ↗</button></form><p>Đã có tài khoản? <a href="<?= BASE_URL ?>/index.php?page=login">Đăng nhập</a></p><?php endif; ?></div></section>
<?php elseif($page==='support'): ?>
<section class="store-section narrow support-customer-page">
 <div class="support-customer-heading"><span class="store-kicker">WING CLIENT CARE</span><h1 class="store-page-title">Liên hệ & hỗ trợ</h1><p>Đội ngũ WinG tiếp nhận yêu cầu, cập nhật tiến độ và chuyển đến quản trị viên khi cần.</p></div>
 <?php if($customerTicket): ?>
  <div class="customer-ticket-detail">
   <a class="back-shop" href="<?= BASE_URL ?>/index.php?page=support">← TẤT CẢ YÊU CẦU</a>
   <div class="ticket-heading"><div><span class="store-kicker"><?= e($customerTicket['ticket_code']) ?></span><h2><?= e($customerTicket['subject']) ?></h2><small>Gửi ngày <?= date('d/m/Y H:i',strtotime($customerTicket['created_at'])) ?></small></div><span class="support-status status-<?= e($customerTicket['status']) ?>"><?= ['new'=>'Mới tiếp nhận','assigned'=>'Nhân viên đang xử lý','waiting_customer'=>'Chờ phản hồi của bạn','escalated_admin'=>'Đang chuyển Admin xử lý','resolved'=>'Đã xử lý'][$customerTicket['status']]??e($customerTicket['status']) ?></span></div>
   <div class="support-customer-thread"><?php foreach($customerTicketMessages as $msg): ?><article class="customer-thread-message <?= $msg['sender_type']==='customer'?'mine':'theirs' ?>"><div><b><?= e($msg['sender_name']) ?></b><time><?= date('d/m/Y H:i',strtotime($msg['created_at'])) ?></time></div><p><?= nl2br(e($msg['message'])) ?></p></article><?php endforeach; ?></div>
   <?php if($customerTicket['status']!=='resolved'): ?><form method="post" class="customer-ticket-reply"><?= csrf_field() ?><input type="hidden" name="action" value="customer_support_reply"><input type="hidden" name="ticket_id" value="<?= (int)$customerTicket['id'] ?>"><label>GỬI THÊM THÔNG TIN<textarea name="message" rows="3" required minlength="2" maxlength="4000" placeholder="Bổ sung thông tin hoặc phản hồi cho nhân viên WinG..."></textarea></label><button class="store-cta">GỬI PHẢN HỒI ↗</button></form><?php else: ?><p class="support-closed-note">Yêu cầu này đã đóng. Hãy tạo yêu cầu mới nếu bạn cần hỗ trợ thêm.</p><?php endif; ?>
  </div>
 <?php else: ?>
  <div class="support-customer-grid">
   <form method="post" class="support-create-form"><?= csrf_field() ?><input type="hidden" name="action" value="create_support_ticket"><span class="store-kicker">HOW CAN WE HELP?</span><h2>Gửi yêu cầu hỗ trợ</h2>
    <label>CHỦ ĐỀ<input name="subject" required minlength="5" maxlength="180" placeholder="Ví dụ: Cần hỗ trợ về đơn hàng WG..."></label>
    <div class="support-form-row"><label>NHÓM VẤN ĐỀ<select name="category"><option value="order">Đơn hàng</option><option value="product">Sản phẩm / Size / Màu</option><option value="payment">Thanh toán</option><option value="shipping">Giao hàng</option><option value="return">Đổi trả</option><option value="account">Tài khoản</option><option value="other">Khác</option></select></label><label>MỨC ƯU TIÊN<select name="priority"><option value="normal">Thông thường</option><option value="high">Cần hỗ trợ sớm</option></select></label></div>
    <label>NỘI DUNG<textarea name="message" rows="6" required minlength="10" maxlength="4000" placeholder="Mô tả vấn đề, mã đơn hàng hoặc thông tin liên quan..."></textarea></label>
    <div class="support-form-foot"><small>Yêu cầu sẽ được chuyển đến hàng đợi nhân viên WinG. Trường hợp cần thiết, nhân viên sẽ trình Admin xử lý.</small><button class="store-cta">GỬI YÊU CẦU ↗</button></div>
   </form>
   <aside class="customer-ticket-list"><span class="store-kicker">YOUR REQUESTS</span><h2>Yêu cầu của tôi</h2>
    <?php foreach($customerTickets as $ticket): ?><a href="<?= BASE_URL ?>/index.php?page=support&id=<?= (int)$ticket['id'] ?>" class="customer-ticket-item"><div><b><?= e($ticket['ticket_code']) ?></b><span class="support-status status-<?= e($ticket['status']) ?>"><?= ['new'=>'Mới','assigned'=>'Đang xử lý','waiting_customer'=>'Chờ bạn','escalated_admin'=>'Chờ Admin','resolved'=>'Đã đóng'][$ticket['status']]??e($ticket['status']) ?></span></div><strong><?= e($ticket['subject']) ?></strong><small><?= date('d/m/Y H:i',strtotime($ticket['updated_at'])) ?></small></a><?php endforeach; ?>
    <?php if(!$customerTickets): ?><div class="ticket-list-empty">Bạn chưa gửi yêu cầu hỗ trợ nào.</div><?php endif; ?>
   </aside>
  </div>
 <?php endif; ?>
</section>
<?php elseif($page==='notifications'): ?>
<section class="store-section narrow customer-notifications-page">
 <div class="store-section-head"><div><span class="store-kicker">WING MEMBER UPDATES</span><h1 class="store-page-title">Thông báo</h1><p>Cập nhật đơn hàng, hỗ trợ và lời mời đánh giá sản phẩm đã mua.</p></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_customer_notifications_read"><button class="store-outline" <?= $customerUnreadCount?'':'disabled' ?>>ĐÁNH DẤU ĐÃ ĐỌC</button></form></div>
 <div class="customer-notification-list">
 <?php foreach($customerNotifications as $n): $target=$n['link'];if($n['kind']==='review_request'){$parts=explode('#',$target,2);$target=$parts[0].(str_contains($parts[0],'?')?'&':'?').'review_notification_id='.(int)$n['id'].(isset($parts[1])?'#'.$parts[1]:'');} ?>
 <a class="customer-notification <?= (int)$n['is_read']===0?'unread':'' ?>" href="<?= BASE_URL ?>/index.php?page=notifications&read=<?= (int)$n['id'] ?>"><span class="customer-notification-icon"><?= $n['kind']==='review_request'?'★':(str_contains($n['kind'],'support')?'✧':'◇') ?></span><span class="customer-notification-copy"><b><?= e($n['title']) ?></b><small><?= e($n['body']) ?></small><time><?= date('d/m/Y H:i',strtotime($n['created_at'])) ?></time></span><?php if(!(int)$n['is_read']): ?><i></i><?php endif; ?></a>
 <?php endforeach; ?>
 <?php if(!$customerNotifications): ?><div class="empty-account"><span>W</span><h2>Chưa có thông báo</h2><p>Khi đơn hàng hoàn thành hoặc có phản hồi hỗ trợ, WinG sẽ gửi thông báo tại đây.</p></div><?php endif; ?>
 </div>
</section>
<?php elseif($page==='customer_orders'): ?>
<section class="store-section narrow account-layout"><aside class="account-sidebar"><span class="store-kicker">MY WING</span><h3><?= e($customer['full_name']??'Tài khoản') ?></h3><a href="<?= BASE_URL ?>/index.php?page=customer_profile">Thông tin cá nhân</a><a class="active" href="<?= BASE_URL ?>/index.php?page=customer_orders">Đơn hàng của tôi</a><a href="<?= BASE_URL ?>/index.php?page=wishlist">Sản phẩm yêu thích</a><a href="<?= BASE_URL ?>/index.php?page=support">Liên hệ & hỗ trợ</a><a href="<?= BASE_URL ?>/index.php?page=notifications">Thông báo<?= $customerUnreadCount?' · '.$customerUnreadCount:'' ?></a><a href="<?= BASE_URL ?>/index.php?page=logout">Đăng xuất</a></aside><div class="account-main"><span class="store-kicker">ORDER HISTORY</span><h1 class="store-page-title">Đơn hàng của tôi</h1><?php foreach($orders as $o): ?><article class="customer-order"><div><b><?= e($o['order_code']) ?></b><small><?= date('d.m.Y H:i',strtotime($o['created_at'])) ?></small></div><span class="pill <?= e($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span><strong><?= money($o['total_amount']) ?></strong><a href="<?= BASE_URL ?>/index.php?page=customer_order_detail&id=<?= (int)$o['id'] ?>">CHI TIẾT ↗</a></article><?php endforeach; ?><?php if(!$orders): ?><div class="empty-account"><span>W</span><h2>Chưa có đơn hàng nào</h2><p>Những lựa chọn đầu tiên của bạn đang chờ được khám phá.</p><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=shop">KHÁM PHÁ CỬA HÀNG ↗</a></div><?php endif; ?></div></section>
<?php elseif($page==='customer_profile'): ?>
<section class="store-section narrow account-layout"><aside class="account-sidebar"><span class="store-kicker">MY WING</span><h3><?= e($customer['full_name']??'Tài khoản') ?></h3><a class="active" href="<?= BASE_URL ?>/index.php?page=customer_profile">Thông tin cá nhân</a><a href="<?= BASE_URL ?>/index.php?page=customer_orders">Đơn hàng của tôi</a><a href="<?= BASE_URL ?>/index.php?page=wishlist">Sản phẩm yêu thích</a><a href="<?= BASE_URL ?>/index.php?page=support">Liên hệ & hỗ trợ</a><a href="<?= BASE_URL ?>/index.php?page=notifications">Thông báo<?= $customerUnreadCount?' · '.$customerUnreadCount:'' ?></a><a href="<?= BASE_URL ?>/index.php?page=logout">Đăng xuất</a></aside><div class="account-main"><span class="store-kicker">PERSONAL DETAILS</span><h1 class="store-page-title">Thông tin cá nhân</h1><p class="account-intro">Quản lý thông tin liên hệ và bảo mật tài khoản WinG của bạn.</p><form method="post" class="profile-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_profile"><label>HỌ VÀ TÊN<input name="full_name" required value="<?= e($customer['full_name']??'') ?>"></label><label>EMAIL<input value="<?= e($customer['email']??'') ?>" disabled><small>Email đăng nhập hiện chưa hỗ trợ thay đổi.</small></label><label>SỐ ĐIỆN THOẠI<input name="phone" value="<?= e($customer['phone']??'') ?>"></label><label>ĐỊA CHỈ NHẬN HÀNG<input name="address" value="<?= e($customer['address']??'') ?>"></label><label>MẬT KHẨU MỚI <small>Để trống nếu không muốn thay đổi</small><input type="password" name="new_password" minlength="10" autocomplete="new-password"></label><button class="store-cta">LƯU THAY ĐỔI ↗</button></form></div></section>
<?php elseif($page==='wishlist'): ?>
<section class="store-section narrow"><div class="store-section-head"><div><span class="store-kicker">SAVED FOR LATER</span><h2>Sản phẩm yêu thích</h2><p>Những thiết kế bạn muốn lưu lại.</p></div></div><?php if(!$customer): ?><div class="empty-account"><span>♡</span><h2>Đăng nhập để xem danh sách yêu thích</h2><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=login">ĐĂNG NHẬP ↗</a></div><?php elseif(!$wishProducts): ?><div class="empty-account"><span>♡</span><h2>Danh sách đang trống</h2><p>Lưu những sản phẩm bạn yêu thích để quay lại bất cứ lúc nào.</p><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=shop">KHÁM PHÁ SẢN PHẨM ↗</a></div><?php else: ?><div class="product-grid"><?php foreach($wishProducts as $prod): ?><article class="store-product"><a class="product-photo" href="<?= BASE_URL ?>/index.php?page=product&id=<?= (int)$prod['id'] ?>"><img loading="lazy" src="<?= e(wing_img($prod['image_url'])) ?>" alt="<?= e($prod['name']) ?>" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=800&q=85';"></a><div class="product-meta"><span><?= e($prod['category']??'WING COLLECTION') ?></span><small><?= e($prod['sku']) ?></small></div><a class="product-name" href="<?= BASE_URL ?>/index.php?page=product&id=<?= (int)$prod['id'] ?>"><?= e($prod['name']) ?></a><div class="product-bottom"><b><?= money($prod['price']) ?></b><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="wishlist_toggle"><input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>"><input type="hidden" name="return_to" value="wishlist"><button class="remove-wish">BỎ LƯU ×</button></form></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif($page==='customer_order_detail'&&$orderDetail): ?>
<section class="store-section narrow"><span class="store-kicker">ORDER DETAILS</span><h1 class="store-page-title"><?= e($orderDetail['order_code']) ?></h1><div class="order-detail-top"><span class="pill <?= e($orderDetail['status']) ?>"><?= e(order_status_label($orderDetail['status'])) ?></span><small>Đặt ngày <?= date('d.m.Y H:i',strtotime($orderDetail['created_at'])) ?></small><span class="pill confirmed"><?= ($orderDetail['payment_method']??'cod')==='cod'?'COD':'Chuyển khoản' ?></span></div><div class="order-address"><b>THÔNG TIN GIAO NHẬN</b><p><?= e($orderDetail['delivery_name']??$customer['full_name']) ?> · <?= e($orderDetail['delivery_phone']??$customer['phone']) ?></p><p><?= e($orderDetail['delivery_address']??$customer['address']) ?></p></div><div class="order-lines"><?php foreach($orderLines as $line): ?><div><span><?= e($line['product_name']) ?><small><?= e($line['sku']) ?> · <?= e($line['variant_size']??'') ?> · <?= e($line['variant_color']??'') ?> · SL <?= (int)$line['quantity'] ?></small></span><b><?= money($line['line_total']) ?></b></div><?php endforeach; ?></div><div class="order-total-line"><span>TỔNG CỘNG</span><b><?= money($orderDetail['total_amount']) ?></b></div><?php if($orderDetail['status']==='pending'): ?><form method="post" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_order"><input type="hidden" name="order_id" value="<?= (int)$orderDetail['id'] ?>"><button class="cancel-order">HỦY ĐƠN HÀNG</button></form><?php endif; ?><a class="back-shop" href="<?= BASE_URL ?>/index.php?page=customer_orders">← QUAY LẠI ĐƠN HÀNG</a></section>
<?php elseif($page==='customer_order_detail'): ?><section class="store-section narrow"><div class="empty-account"><h2>Không tìm thấy đơn hàng</h2><a class="store-cta" href="<?= BASE_URL ?>/index.php?page=customer_orders">QUAY LẠI ĐƠN HÀNG</a></div></section>
<?php endif; ?>
<footer class="store-footer"><a class="store-brand" href="<?= BASE_URL ?>/index.php?page=shop"><span>W</span><b>WinG<small>THE FASHION HOUSE</small></b></a><p>TIMELESS STYLE. CONSIDERED DETAILS.</p><small>© <?= date('Y') ?> WING FASHION HOUSE</small><a href="<?= BASE_URL ?>/index.php?page=login">STAFF PORTAL ↗</a></footer>
<script src="<?= BASE_URL ?>/assets/js/wing-intro.js?v=wing-intro-v3"></script><script src="<?= BASE_URL ?>/assets/js/app.js?v=wing-v5-editorial"></script><script>
(()=>{const trigger=document.querySelector('.collection-nav-trigger'),menu=document.querySelector('.collection-nav');if(!trigger||!menu)return;trigger.addEventListener('click',e=>{if(window.matchMedia('(hover: none)').matches){e.preventDefault();menu.classList.toggle('menu-open');}});document.addEventListener('click',e=>{if(!menu.contains(e.target))menu.classList.remove('menu-open');});})();
</script>
</body></html>

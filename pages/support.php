<?php
$pdo=db();$me=current_user();$admin=is_admin();$userId=(int)$me['id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=(string)($_POST['action']??'');$ticketId=(int)($_POST['ticket_id']??0);
    try{
        $st=$pdo->prepare("SELECT * FROM support_tickets WHERE id=?");$st->execute([$ticketId]);$ticket=$st->fetch();
        if(!$ticket)throw new RuntimeException('Không tìm thấy yêu cầu hỗ trợ.');
        if($action==='accept'){
            if($admin)throw new RuntimeException('Admin có thể xử lý trực tiếp, không cần nhận việc như Staff.');
            if($ticket['status']!=='new'||($ticket['assigned_staff_id']!==null&&(int)$ticket['assigned_staff_id']!==$userId))throw new RuntimeException('Yêu cầu này đã được nhân viên khác tiếp nhận.');
            $pdo->prepare("UPDATE support_tickets SET assigned_staff_id=?,status='assigned' WHERE id=?")->execute([$userId,$ticketId]);
            wing_notify($pdo,'customer',(int)$ticket['customer_id'],'support_assigned','Nhân viên WinG đã tiếp nhận yêu cầu',$ticket['ticket_code'].' đang được '.$me['full_name'].' xử lý.','index.php?page=support&id='.$ticketId,'support-assigned:'.$ticketId.':'.$userId);
            flash('success','Bạn đã tiếp nhận yêu cầu '.$ticket['ticket_code'].'.');
        }elseif($action==='reply'){
            if(!$admin&&((int)($ticket['assigned_staff_id']??0)!==$userId||!empty($ticket['handled_by_admin_id'])))throw new RuntimeException('Yêu cầu đã được chuyển cho Admin hoặc chưa được bạn tiếp nhận.');
            $message=trim((string)($_POST['message']??''));if(mb_strlen($message)<2||mb_strlen($message)>4000)throw new RuntimeException('Nội dung phản hồi từ 2 đến 4.000 ký tự.');
            $pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,sender_type,sender_id,sender_name,sender_role,message) VALUES(?,'user',?,?,?,?)")
                ->execute([$ticketId,$userId,$me['full_name'],$me['role'],$message]);
            $pdo->prepare("UPDATE support_tickets SET status='waiting_customer',handled_by_admin_id=? WHERE id=?")->execute([$admin?$userId:$ticket['handled_by_admin_id'],$ticketId]);
            wing_notify($pdo,'customer',(int)$ticket['customer_id'],'support_reply',$admin?'Quản trị viên WinG đã phản hồi':'Nhân viên WinG đã phản hồi',$ticket['ticket_code'].' · '.$ticket['subject'],'index.php?page=support&id='.$ticketId,'support-reply:'.$ticketId.':'.time());
            flash('success','Đã gửi phản hồi cho khách hàng.');
        }elseif($action==='escalate'){
            if($admin)throw new RuntimeException('Tác vụ chuyển cấp dành cho nhân viên.');
            if((int)($ticket['assigned_staff_id']??0)!==$userId||!empty($ticket['handled_by_admin_id']))throw new RuntimeException('Bạn cần đang phụ trách yêu cầu và chưa chuyển quyền xử lý cho Admin.');
            $summary=trim((string)($_POST['message']??''));
            if(mb_strlen($summary)<10)throw new RuntimeException('Vui lòng trình bày tóm tắt ít nhất 10 ký tự để Admin xử lý.');
            $pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,sender_type,sender_id,sender_name,sender_role,message,is_internal) VALUES(?,'user',?,?,?,?,1)")
                ->execute([$ticketId,$userId,$me['full_name'],'staff','ĐỀ XUẤT CHUYỂN ADMIN: '.$summary]);
            $pdo->prepare("UPDATE support_tickets SET status='escalated_admin' WHERE id=?")->execute([$ticketId]);
            wing_notify_roles($pdo,['admin'],'support_escalated','Yêu cầu hỗ trợ cần Admin xử lý',$ticket['ticket_code'].' · '.$ticket['subject'],'index.php?page=support&id='.$ticketId,'support-escalated:'.$ticketId);
            wing_notify($pdo,'customer',(int)$ticket['customer_id'],'support_escalated_customer','Yêu cầu đang được chuyển đến quản trị viên',$ticket['ticket_code'].' đã được chuyển cấp để xử lý tiếp.','index.php?page=support&id='.$ticketId,'support-escalated-customer:'.$ticketId);
            flash('success','Đã chuyển hồ sơ và phần trình bày đến Admin.');
        }elseif($action==='resolve'){
            if(!$admin)throw new RuntimeException('Chỉ Admin mới có quyền đóng yêu cầu hỗ trợ.');
            $message=trim((string)($_POST['message']??''));
            if($message!=='')$pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,sender_type,sender_id,sender_name,sender_role,message) VALUES(?,'user',?,?, 'admin',?)")->execute([$ticketId,$userId,$me['full_name'],$message]);
            else $pdo->prepare("INSERT INTO support_ticket_messages(ticket_id,sender_type,sender_id,sender_name,sender_role,message) VALUES(?,'user',?,?, 'admin','Yêu cầu đã được quản trị viên xử lý và đóng.')")->execute([$ticketId,$userId,$me['full_name']]);
            $pdo->prepare("UPDATE support_tickets SET status='resolved',handled_by_admin_id=? WHERE id=?")->execute([$userId,$ticketId]);
            wing_notify($pdo,'customer',(int)$ticket['customer_id'],'support_resolved','Yêu cầu hỗ trợ đã được xử lý',$ticket['ticket_code'].' đã được đóng bởi quản trị viên WinG.','index.php?page=support&id='.$ticketId,'support-resolved:'.$ticketId);
            flash('success','Đã hoàn tất và đóng yêu cầu hỗ trợ.');
        }else throw new RuntimeException('Tác vụ không hợp lệ.');
    }catch(Throwable $e){flash('error',$e instanceof RuntimeException?$e->getMessage():'Không thể cập nhật yêu cầu hỗ trợ.');}
    header('Location: '.BASE_URL.'/index.php?page=support&id='.$ticketId);exit;
}
if(!empty($_GET['read'])){$pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE id=? AND recipient_type='user' AND recipient_id=?")->execute([(int)$_GET['read'],$userId]);}
if($admin){
    $st=$pdo->query("SELECT t.*,c.full_name customer,c.email FROM support_tickets t JOIN customers c ON c.id=t.customer_id ORDER BY FIELD(t.status,'escalated_admin','new','assigned','waiting_customer','resolved'),t.updated_at DESC LIMIT 150");
}else{
    $st=$pdo->prepare("SELECT t.*,c.full_name customer,c.email FROM support_tickets t JOIN customers c ON c.id=t.customer_id WHERE (t.status='new' AND t.assigned_staff_id IS NULL) OR t.assigned_staff_id=? ORDER BY FIELD(t.status,'new','assigned','waiting_customer','escalated_admin','resolved'),t.updated_at DESC LIMIT 150");
    $st->execute([$userId]);
}
$tickets=$st->fetchAll();$active=null;$messages=[];
if(!empty($_GET['id'])){
    $st=$pdo->prepare("SELECT t.*,c.full_name customer,c.email,c.phone FROM support_tickets t JOIN customers c ON c.id=t.customer_id WHERE t.id=?");$st->execute([(int)$_GET['id']]);$active=$st->fetch();
    if($active&&!$admin&&(int)($active['assigned_staff_id']??0)!==$userId&&$active['status']!=='new'){$active=null;http_response_code(403);}
    if($active){$st=$pdo->prepare("SELECT * FROM support_ticket_messages WHERE ticket_id=? ORDER BY created_at");$st->execute([$active['id']]);$messages=$st->fetchAll();}
}
$labels=['new'=>'Mới','assigned'=>'Đang xử lý','waiting_customer'=>'Chờ khách hàng','escalated_admin'=>'Chờ Admin','resolved'=>'Đã đóng'];
?>
<section class="page-intro"><div><span class="eyebrow">WING CARE / SERVICE DESK</span><h2>Liên hệ & hỗ trợ</h2><p class="muted"><?= $admin?'Tiếp nhận hồ sơ đã chuyển cấp, phản hồi và đóng yêu cầu.':'Tiếp nhận yêu cầu khách hàng, phản hồi ban đầu và trình Admin xử lý khi cần.' ?></p></div><span class="pill active"><?= count($tickets) ?> hồ sơ trong hàng đợi</span></section>
<div class="support-workspace">
 <aside class="support-queue card"><div class="card-heading"><div><span class="eyebrow">SUPPORT QUEUE</span><h3><?= $admin?'Hàng đợi quản trị':'Hàng đợi nhân viên' ?></h3></div></div>
 <?php foreach($tickets as $t): ?><a class="support-queue-item <?= $active&&(int)$active['id']===(int)$t['id']?'selected':'' ?>" href="<?= BASE_URL ?>/index.php?page=support&id=<?= (int)$t['id'] ?>"><div><b><?= e($t['ticket_code']) ?></b><span class="pill <?= $t['status']==='escalated_admin'?'warning':($t['status']==='resolved'?'active':'pending') ?>"><?= e($labels[$t['status']]??$t['status']) ?></span></div><strong><?= e($t['subject']) ?></strong><small><?= e($t['customer']) ?> · <?= date('d/m H:i',strtotime($t['updated_at'])) ?></small></a><?php endforeach; ?>
 <?php if(!$tickets): ?><p class="empty">Chưa có yêu cầu cần xử lý.</p><?php endif; ?></aside>
 <section class="support-detail card">
 <?php if(!$active): ?><div class="support-empty"><span>W</span><h3>Không gian chăm sóc khách hàng</h3><p>Chọn một hồ sơ ở hàng đợi để xem nội dung, nhận xử lý hoặc chuyển cấp.</p></div>
 <?php else: ?><div class="card-heading"><div><span class="eyebrow"><?= e($active['ticket_code']) ?> · <?= e(strtoupper($active['category'])) ?></span><h3><?= e($active['subject']) ?></h3><small><?= e($active['customer']) ?> · <?= e($active['email']) ?> · <?= e($active['phone']??'') ?></small></div><span class="pill <?= $active['status']==='escalated_admin'?'warning':'active' ?>"><?= e($labels[$active['status']]??$active['status']) ?></span></div>
 <div class="support-conversation"><?php foreach($messages as $msg): ?><article class="support-message <?= (int)$msg['is_internal']===1?'internal-message':($msg['sender_type']==='user'?'staff-message':'customer-message') ?>"><div><b><?= e($msg['sender_name']) ?><?= (int)$msg['is_internal']===1?' · GHI CHÚ NỘI BỘ':'' ?></b><small><?= e($msg['sender_role']) ?> · <?= date('d/m/Y H:i',strtotime($msg['created_at'])) ?></small></div><p><?= nl2br(e($msg['message'])) ?></p></article><?php endforeach; ?></div>
 <?php if(!$admin&&$active['status']==='new'&&empty($active['assigned_staff_id'])): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="action" value="accept"><button class="btn primary">TIẾP NHẬN HỒ SƠ ↗</button></form>
 <?php elseif($admin&&$active['status']!=='resolved'): ?><form method="post" class="support-action-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="action" value="reply"><label>PHẢN HỒI KHÁCH HÀNG<textarea name="message" rows="3" required placeholder="Nhập nội dung phản hồi..."></textarea></label><button class="btn primary">GỬI PHẢN HỒI</button></form><form method="post" class="support-resolve-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="action" value="resolve"><label>GHI CHÚ KẾT QUẢ (không bắt buộc)<textarea name="message" rows="2" placeholder="Tóm tắt cách xử lý..."></textarea></label><button class="btn light">ĐÓNG YÊU CẦU</button></form>
 <?php elseif(!$admin&&(int)($active['assigned_staff_id']??0)===$userId&&empty($active['handled_by_admin_id'])&&$active['status']!=='resolved'&&$active['status']!=='escalated_admin'): ?><form method="post" class="support-action-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="action" value="reply"><label>PHẢN HỒI KHÁCH HÀNG<textarea name="message" rows="3" required placeholder="Giải đáp hoặc cập nhật cho khách..."></textarea></label><button class="btn primary">GỬI PHẢN HỒI</button></form><form method="post" class="support-resolve-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="action" value="escalate"><label>TRÌNH BÀY VỚI ADMIN<textarea name="message" rows="3" required minlength="10" placeholder="Tóm tắt vấn đề, thông tin đã kiểm tra và đề xuất xử lý..."></textarea></label><button class="btn light">CHUYỂN ADMIN XỬ LÝ ↗</button></form>
 <?php endif; ?><?php endif; ?>
 </section>
</div>

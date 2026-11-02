<?php
$pdo=db();$me=current_user();$userId=(int)$me['id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(($_POST['action']??'')==='mark_all_read'){
        $pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE recipient_type='user' AND recipient_id=?")->execute([$userId]);
        flash('success','Đã đánh dấu toàn bộ thông báo là đã đọc.');
    }elseif(($_POST['action']??'')==='mark_read'){
        $id=(int)($_POST['notification_id']??0);
        $pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE id=? AND recipient_type='user' AND recipient_id=?")->execute([$id,$userId]);
    }
    redirect('notifications');
}
if(!empty($_GET['read'])){
    $st=$pdo->prepare("SELECT link FROM wing_notifications WHERE id=? AND recipient_type='user' AND recipient_id=?");
    $st->execute([(int)$_GET['read'],$userId]);$target=(string)$st->fetchColumn();
    $pdo->prepare("UPDATE wing_notifications SET is_read=1 WHERE id=? AND recipient_type='user' AND recipient_id=?")->execute([(int)$_GET['read'],$userId]);
    if($target!==''&&!preg_match('~^(https?:)?//~i',$target)){header('Location: '.BASE_URL.'/'.ltrim($target,'/'));exit;}
    redirect('notifications');
}
$st=$pdo->prepare("SELECT * FROM wing_notifications WHERE recipient_type='user' AND recipient_id=? ORDER BY created_at DESC LIMIT 100");$st->execute([$userId]);$items=$st->fetchAll();
$unread=wing_notification_unread($pdo,'user',$userId);
?>
<section class="page-intro"><div><span class="eyebrow">WING / NOTIFICATION CENTER</span><h2>Thông báo</h2><p class="muted">Thông tin đơn hàng, yêu cầu hỗ trợ và công việc cần chú ý.</p></div><span class="pill <?= $unread?'warning':'active' ?>"><?= $unread ?> chưa đọc</span></section>
<section class="notification-center card">
 <div class="card-heading"><div><span class="eyebrow">YOUR UPDATES</span><h3>Hộp thư WinG</h3></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read"><button class="btn light" <?= $unread?'':'disabled' ?>>ĐÁNH DẤU ĐÃ ĐỌC</button></form></div>
 <?php foreach($items as $item): ?><a class="notification-item <?= (int)$item['is_read']===0?'unread':'' ?>" href="<?= BASE_URL ?>/index.php?page=notifications&read=<?= (int)$item['id'] ?>"><span class="notification-icon"><?= $item['kind']==='support_escalated'?'!':($item['kind']==='new_online_order'?'▤':'✧') ?></span><span class="notification-copy"><b><?= e($item['title']) ?></b><small><?= e($item['body']) ?></small><time><?= date('d/m/Y H:i',strtotime($item['created_at'])) ?></time></span><?php if(!(int)$item['is_read']): ?><i class="notification-dot"></i><?php endif; ?></a><?php endforeach; ?>
 <?php if(!$items): ?><div class="support-empty"><span>W</span><h3>Chưa có thông báo</h3><p>Khi có đơn hàng hoặc yêu cầu hỗ trợ mới, thông báo sẽ xuất hiện tại đây.</p></div><?php endif; ?>
</section>

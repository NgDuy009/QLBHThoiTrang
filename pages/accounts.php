<?php
require_admin();
$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (($_POST['action']??'')==='repair_customer') {
        $id=(int)($_POST['user_id']??0);
        try {
            $pdo->beginTransaction();
            $st=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$st->execute([$id]);$record=$st->fetch();
            if (!$record || in_array($record['role'], ['admin','staff'], true) || !filter_var($record['username'],FILTER_VALIDATE_EMAIL))
                throw new RuntimeException('Chỉ chuyển các bản ghi có vai trò lỗi và username là email.');
            $st=$pdo->prepare('SELECT id FROM customers WHERE LOWER(email)=LOWER(?) LIMIT 1');
            $st->execute([$record['username']]);
            if ($st->fetch()) throw new RuntimeException('Email đã tồn tại ở khách hàng. Cần đối chiếu thủ công trước khi hợp nhất.');
            $pdo->prepare("INSERT INTO customers(full_name,email,password_hash,account_status) VALUES(?,?,?,'active')")
                ->execute([$record['full_name'],$record['username'],$record['password_hash']]);
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            $pdo->commit(); flash('success','Đã chuyển tài khoản nhập nhầm sang Khách hàng (giữ mật khẩu cũ).');
        } catch(Throwable $ex) { if($pdo->inTransaction())$pdo->rollBack(); flash('error', $ex instanceof RuntimeException?$ex->getMessage():'Không thể chuyển vì tài khoản còn liên kết dữ liệu.'); }
        redirect('accounts');
    }
}
$staff=$pdo->query('SELECT id,full_name,username,role,status FROM users ORDER BY id')->fetchAll();
$customers=$pdo->query('SELECT id,full_name,email,account_status,created_at FROM customers ORDER BY id DESC')->fetchAll();
?>
<section class="page-intro"><div><span class="eyebrow">IDENTITY / ROLE CONTROL</span><h2>Phân quyền tài khoản</h2><p class="muted">Admin và Nhân viên nằm trong users. Khách hàng nằm trong customers, mỗi hồ sơ và đơn hàng liên kết theo customer_id.</p></div></section>
<div class="identity-columns"><section class="card"><div class="card-heading"><h3>Quản trị & nhân viên · <?= count($staff) ?></h3></div><div class="table-wrap"><table><thead><tr><th>Họ tên</th><th>Tên đăng nhập</th><th>Vai trò</th><th>Trạng thái</th><th></th></tr></thead><tbody>
<?php foreach($staff as $u): $valid=in_array($u['role'],['admin','staff'],true); ?><tr><td><?= e($u['full_name']) ?></td><td><?= e($u['username']) ?></td><td><span class="pill <?= $valid?'active':'warning' ?>"><?= $valid?($u['role']==='admin'?'ADMIN':'STAFF'):'VAI TRÒ LỖI' ?></span></td><td><?= e($u['status']?:'Chưa đặt') ?></td><td><?php if(!$valid&&filter_var($u['username'],FILTER_VALIDATE_EMAIL)): ?><form method="post" onsubmit="return confirm('Chuyển tài khoản nhập nhầm thành khách hàng? Kiểm tra email trước khi tiếp tục.')"><?= csrf_field() ?><input type="hidden" name="action" value="repair_customer"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="btn light">Chuyển thành khách</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section><section class="card"><div class="card-heading"><h3>Khách hàng · <?= count($customers) ?></h3></div><div class="table-wrap"><table><thead><tr><th>Khách hàng</th><th>Email</th><th>Loại</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($customers as $c): ?><tr><td><?= e($c['full_name']) ?></td><td><?= e($c['email']?:'—') ?></td><td><span class="pill confirmed">CUSTOMER</span></td><td><?= e($c['account_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>

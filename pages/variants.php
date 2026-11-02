<?php
$cfg = array('title' => 'Size & màu sắc', 'desc' => 'Quản lý các biến thể theo sản phẩm, kích cỡ và màu sắc.', 'table' => 'product_variants', 'search' => 'size LIKE :q OR color LIKE :q OR sku LIKE :q', 'columns' => array(array('sku', 'SKU biến thể'), array('product_name', 'Sản phẩm'), array('size', 'Size'), array('color', 'Màu'), array('stock_quantity', 'Tồn kho')), 'select' => 'SELECT v.*, p.name AS product_name FROM product_variants v JOIN products p ON p.id=v.product_id', 'fields' => array(array('product_id', 'Sản phẩm', 'product', true), array('sku', 'SKU biến thể', 'text', true), array('size', 'Kích cỡ', 'text', true), array('color', 'Màu sắc', 'text', true), array('image_url', 'Ảnh riêng cho màu (URL, tùy chọn)', 'text', false), array('stock_quantity', 'Số lượng', 'number', true)), 'admin' => false);
$pdo = db();
$editId = (int)($_GET['edit'] ?? 0);
$action = $_POST['action'] ?? '';
function module_options(string $type): array {
    if ($type === 'category') return db()->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
    if ($type === 'product') return db()->query("SELECT id,name FROM products ORDER BY name")->fetchAll();
    return [];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($action === 'delete') {
        if (!is_admin()) { flash('error','Chỉ quản trị viên được phép xóa dữ liệu.'); redirect($page); }
        $id=(int)($_POST['id']??0);
        $pcheck=$pdo->prepare("SELECT product_id FROM product_variants WHERE id=?");$pcheck->execute([$id]);$parentProduct=(int)$pcheck->fetchColumn();
        if ($cfg['table']==='users' && $id===(int)current_user()['id']) { flash('error','Không thể tự xóa tài khoản đang đăng nhập.'); redirect($page); }
        $stmt=$pdo->prepare("DELETE FROM {$cfg['table']} WHERE id=?");
        try {$stmt->execute([$id]); if($cfg['table']==='product_variants' && $parentProduct>0) refresh_product_stock($pdo,$parentProduct); flash('success','Đã xóa dữ liệu.');}
        catch(Throwable $e){flash('error','Không thể xóa: dữ liệu đang được tham chiếu hoặc có lỗi.');}
        redirect($page);
    }
    if ($action === 'save') {
        if (!is_admin()) { flash('error','Chỉ quản trị viên được chỉnh sửa biến thể và tồn kho theo size/màu.'); redirect($page); }
        $id=(int)($_POST['id']??0); $data=[];
        foreach($cfg['fields'] as [$field,$label,$type,$required]) {
            if ($field==='password') continue;
            $value=trim((string)($_POST[$field]??''));
            if ($required && $value==='') { flash('error',"Vui lòng nhập {$label}."); redirect($page . ($id?'&edit='.$id:'')); }
            if ($field==='image_url' && $value!=='') {
                $parts=parse_url($value);
                if(!filter_var($value,FILTER_VALIDATE_URL)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)) {
                    flash('error','Ảnh riêng cho màu cần là URL HTTP/HTTPS hợp lệ.');redirect($page.($id?'&edit='.$id:''));
                }
            }
            if (in_array($field,['price','cost_price','stock_quantity'],true) && $value!=='' && (!is_numeric($value)|| (float)$value<0)) { flash('error',"{$label} không hợp lệ."); redirect($page); }
            if ($field==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) { flash('error','Email không hợp lệ.'); redirect($page); }
            $data[$field]=$value===''?null:$value;
        }
        try {
            if ($cfg['table']==='users') {
                if ($id) {
                    $sql="UPDATE users SET full_name=:full_name,username=:username,role=:role,status=:status";
                    if (!empty($_POST['password'])) {$data['password_hash']=password_hash((string)$_POST['password'],PASSWORD_DEFAULT);$sql.=",password_hash=:password_hash";}
                    $data['id']=$id; $pdo->prepare($sql." WHERE id=:id")->execute($data);
                } else {
                    $data['password_hash']=password_hash((string)($_POST['password']??''),PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users(full_name,username,password_hash,role,status) VALUES(:full_name,:username,:password_hash,:role,:status)")->execute($data);
                }
            } else {
                if ($id) {
                    if($cfg['table']==='product_variants'){$oldParentStmt=$pdo->prepare("SELECT product_id FROM product_variants WHERE id=?");$oldParentStmt->execute([$id]);$oldParentId=(int)$oldParentStmt->fetchColumn();}
                    $sets=[]; foreach(array_keys($data) as $k)$sets[]="$k=:$k";
                    $data['id']=$id; $pdo->prepare("UPDATE {$cfg['table']} SET ".implode(',',$sets)." WHERE id=:id")->execute($data);
                } else {
                    $cols=array_keys($data); $sql="INSERT INTO {$cfg['table']}(".implode(',',$cols).") VALUES(:".implode(',:',$cols).")";
                    $pdo->prepare($sql)->execute($data);
                }
            }
            if($cfg['table']==='product_variants') {
                $parentId=(int)($data['product_id']??0);
                if($id>0){$q=$pdo->prepare("SELECT product_id FROM product_variants WHERE id=?");$q->execute([$id]);$parentId=(int)$q->fetchColumn();}
                if($parentId>0) refresh_product_stock($pdo,$parentId);
                if(!empty($oldParentId)&&$oldParentId!==$parentId) refresh_product_stock($pdo,(int)$oldParentId);
            }
            flash('success',$id?'Đã cập nhật dữ liệu.':'Đã thêm dữ liệu.');
            redirect($page);
        } catch(Throwable $e) { flash('error','Không thể lưu. Kiểm tra dữ liệu trùng hoặc ràng buộc liên quan.'); redirect($page); }
    }
}
$record=null;
if($editId){$st=$pdo->prepare("SELECT * FROM {$cfg['table']} WHERE id=?");$st->execute([$editId]);$record=$st->fetch();}
$q=trim((string)($_GET['q']??''));
$sql=$cfg['select']; $params=[];
if($q!==''){$sql.=" WHERE ".$cfg['search'];$params['q']="%".$q."%";}
$sql.=" ORDER BY id DESC LIMIT 150";
$st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
?>
<section class="page-intro"><div><span class="eyebrow">CATALOGUE / OPERATIONS</span><h2><?= e($cfg['title']) ?></h2><p class="muted"><?= e($cfg['desc']) ?> Tổng tồn sản phẩm được tính từ các biến thể.</p></div><?php if(is_admin()): ?><a class="btn primary" href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>&new=1">+ Thêm mới</a><?php endif; ?></section>
<?php if(is_admin() && ($editId || isset($_GET['new']))): ?>
<section class="card form-card"><div class="card-heading"><h3><?= $record?'Cập nhật':'Thêm mới' ?> · <?= e($cfg['title']) ?></h3><a class="text-link" href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>">Đóng ×</a></div>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($record['id']??0) ?>">
<?php foreach($cfg['fields'] as [$field,$label,$type,$required]): $val=$record[$field]??''; ?>
<label class="<?= $type==='textarea'?'span-2':'' ?>"><?= e($label) ?><?php if($type==='textarea'): ?><textarea name="<?= e($field) ?>" rows="3"><?= e($val) ?></textarea>
<?php elseif($type==='category'||$type==='product'): ?><select name="<?= e($field) ?>" <?= $required?'required':'' ?>><option value="">-- Chọn --</option><?php foreach(module_options($type) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$val===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
<?php elseif($type==='status'): ?><select name="<?= e($field) ?>"><option value="active" <?= $val==='active'?'selected':'' ?>>Hoạt động</option><option value="inactive" <?= $val==='inactive'?'selected':'' ?>>Ngừng</option></select>
<?php elseif($type==='role'): ?><select name="role"><option value="staff" <?= $val==='staff'?'selected':'' ?>>Nhân viên</option><option value="admin" <?= $val==='admin'?'selected':'' ?>>Quản trị viên</option></select>
<?php else: ?><input type="<?= e($type) ?>" name="<?= e($field) ?>" value="<?= e($val) ?>" <?= $required && !($field==='password' && $record)?'required':'' ?> <?= $type==='number'?'min="0" step="1"':'' ?> placeholder="Nhập <?= e(mb_strtolower($label)) ?>"><?php endif; ?></label>
<?php endforeach; ?>
<div class="form-actions span-2"><button class="btn primary" type="submit">Lưu dữ liệu</button><a class="btn light" href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>">Hủy</a></div>
</form></section>
<?php endif; ?>
<section class="card"><div class="card-heading"><div><span class="eyebrow">DATA MANAGEMENT</span><h3>Danh sách dữ liệu</h3></div><form class="search-form" method="get"><input type="hidden" name="page" value="<?= e($page) ?>"><input name="q" value="<?= e($q) ?>" placeholder="Tìm kiếm..."><button class="btn light">Tìm</button></form></div>
<div class="table-wrap"><table><thead><tr><?php foreach($cfg['columns'] as [$field,$label]): ?><th><?= e($label) ?></th><?php endforeach; ?><?php if(is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><?php foreach($cfg['columns'] as [$field,$label]): ?><td><?php if($field==='price'): ?><?= money($row[$field]) ?><?php elseif($field==='status'): ?><span class="pill <?= e($row[$field]) ?>"><?= $row[$field]==='active'?'Hoạt động':'Ngừng' ?></span><?php elseif($field==='created_at'): ?><?= e(date('d/m/Y',strtotime($row[$field]))) ?><?php else: ?><?= e($row[$field]??'—') ?><?php endif; ?></td><?php endforeach; ?><?php if(is_admin()): ?><td class="actions"><a href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>&edit=<?= (int)$row['id'] ?>">Sửa</a><form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="link-danger">Xóa</button></form></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="<?= count($cfg['columns'])+(is_admin()?1:0) ?>" class="empty">Không tìm thấy dữ liệu.</td></tr><?php endif; ?>
</tbody></table></div><div class="table-foot">Hiển thị tối đa 150 bản ghi gần nhất.</div></section>

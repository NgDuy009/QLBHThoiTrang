<?php
$cfg = array(
 'title' => 'Quản lý sản phẩm',
 'desc' => '',
 'table' => 'products',
 'search' => 'name LIKE :q OR sku LIKE :q OR collection_name LIKE :q',
 'columns' => array(
   array('image_url','Ảnh'),array('sku','Mã SKU'),array('name','Tên sản phẩm'),array('category_name','Danh mục'),
   array('gender','Đối tượng'),array('price','Giá bán'),array('stock_quantity','Tồn kho'),
   array('is_published','Website'),array('status','Trạng thái')
 ),
 'select' => 'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id',
 'fields' => array(
   array('sku','Mã SKU','text',true),array('name','Tên sản phẩm','text',true),
   array('category_id','Danh mục','category',true),array('gender','Đối tượng','gender',true),
   array('collection_name','Bộ sưu tập','text',false),array('material','Chất liệu','text',false),
   array('description','Mô tả','textarea',false),array('price','Giá bán (VNĐ)','number',true),
   array('cost_price','Giá nhập (VNĐ)','number',false),array('image_url','Hình ảnh sản phẩm','image',false),
   array('is_published','Trưng bày trên website','published',true),array('status','Trạng thái nội bộ','status',true)
 ),
 'admin' => true
);
$pdo = db();
require_once __DIR__.'/../includes/palette.php';
if(!function_exists('wing_admin_product_image')){
    function wing_admin_product_image(?string $src): string {
        $src=trim((string)$src);
        if($src==='') return BASE_URL.'/assets/images/wing-favicon.png';
        if(preg_match('~^https?://~i',$src)) return $src;
        return BASE_URL.'/'.ltrim($src,'/');
    }
}
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
        if ($cfg['table']==='users' && $id===(int)current_user()['id']) { flash('error','Không thể tự xóa tài khoản đang đăng nhập.'); redirect($page); }
        $stmt=$pdo->prepare("DELETE FROM {$cfg['table']} WHERE id=?");
        try {$stmt->execute([$id]); flash('success','Đã xóa dữ liệu.');}
        catch(Throwable $e){flash('error','Không thể xóa: dữ liệu đang được tham chiếu hoặc có lỗi.');}
        redirect($page);
    }
    if ($action === 'save') {
        if (!is_admin()) { flash('error','Nhân viên chỉ được xem danh mục và thông tin sản phẩm.'); redirect($page); }
        $id=(int)($_POST['id']??0); $data=[];
        try {
        foreach($cfg['fields'] as [$field,$label,$type,$required]) {
            if ($field==='password') continue;
            if ($type==='image') {
                $value=trim((string)($_POST['image_url']??''));
                $oldImage='';
                if($id>0){$oldStmt=$pdo->prepare("SELECT image_url FROM products WHERE id=?");$oldStmt->execute([$id]);$oldImage=(string)$oldStmt->fetchColumn();}
                if(isset($_FILES['image_file']) && ($_FILES['image_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                    $file=$_FILES['image_file'];
                    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Không thể tải ảnh lên. Hãy thử lại.');
                    if(($file['size']??0)>5*1024*1024) throw new RuntimeException('Ảnh tối đa 5MB.');
                    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);
                    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    if(!isset($allowed[$mime])) throw new RuntimeException('Chỉ nhận ảnh JPG, PNG hoặc WEBP.');
                    $uploadDir=__DIR__.'/../uploads/products';
                    if(!is_dir($uploadDir)&&!mkdir($uploadDir,0755,true)&&!is_dir($uploadDir)) throw new RuntimeException('Không tạo được thư mục lưu ảnh.');
                    $filename='wing-'.date('YmdHis').'-'.bin2hex(random_bytes(6)).'.'.$allowed[$mime];
                    if(!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$filename)) throw new RuntimeException('Lưu ảnh thất bại.');
                    $value='uploads/products/'.$filename;
                } elseif($value==='' && $oldImage!=='') { $value=$oldImage; }
                if($value!=='' && $value!==$oldImage && !preg_match('~^uploads/products/[A-Za-z0-9._-]+$~',$value)) {
                    $urlParts=parse_url($value);
                    if(!filter_var($value,FILTER_VALIDATE_URL)||!in_array(strtolower((string)($urlParts['scheme']??'')),['http','https'],true))
                        throw new RuntimeException('URL ảnh phải bắt đầu bằng http:// hoặc https://.');
                }
                $data[$field]=$value===''?null:$value;
                continue;
            }
            $value=trim((string)($_POST[$field]??''));
            if ($required && $value==='') { flash('error',"Vui lòng nhập {$label}."); redirect($page . ($id?'&edit='.$id:'')); }
            if (in_array($field,['price','cost_price','stock_quantity'],true) && $value!=='' && (!is_numeric($value)|| (float)$value<0)) { flash('error',"{$label} không hợp lệ."); redirect($page); }
            if ($field==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) { flash('error','Email không hợp lệ.'); redirect($page); }
            $data[$field]=$value===''?null:$value;
        }
        } catch (RuntimeException $e) {
            flash('error',$e->getMessage());
            header('Location: '.BASE_URL.'/index.php?page=products'.($id?'&edit='.$id:'&new=1'));
            exit;
        }
        $createdPaletteImages=[];
        try {
            $pdo->beginTransaction();
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
                    $sets=[]; foreach(array_keys($data) as $k)$sets[]="$k=:$k";
                    $data['id']=$id; $pdo->prepare("UPDATE {$cfg['table']} SET ".implode(',',$sets)." WHERE id=:id")->execute($data);
                } else {
                    $cols=array_keys($data); $sql="INSERT INTO {$cfg['table']}(".implode(',',$cols).") VALUES(:".implode(',:',$cols).")";
                    $pdo->prepare($sql)->execute($data);
                }
            }
            $savedId=$id ?: (int)$pdo->lastInsertId();
            [$paletteCreated,$paletteUpdated]=wing_palette_save($pdo,$savedId,(string)($data['sku']??''),$_POST,$_FILES['palette_image_file']??[],$createdPaletteImages);
            $pdo->commit();
            flash('success',($id?'Đã cập nhật sản phẩm.':'Đã tạo sản phẩm.')." Thêm $paletteCreated tổ hợp size/màu, cập nhật $paletteUpdated nhóm màu.");
            header('Location: '.BASE_URL.'/index.php?page=products&edit='.$savedId);exit;
         } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            foreach($createdPaletteImages as $createdImage) if(is_file($createdImage))@unlink($createdImage);
            flash('error',$e instanceof RuntimeException?$e->getMessage():'Không thể lưu. Kiểm tra dữ liệu trùng hoặc ràng buộc liên quan (đã import SQL bảng màu chưa?).');
            header('Location: '.BASE_URL.'/index.php?page=products'.($id?'&edit='.$id:'&new=1'));exit;
         }
    }
}
$record=null;
if($editId){$st=$pdo->prepare("SELECT * FROM {$cfg['table']} WHERE id=?");$st->execute([$editId]);$record=$st->fetch();}
$q=trim((string)($_GET['q']??''));
$editing = is_admin() && ($editId > 0 || isset($_GET['new']));
$rows=[];
if(!$editing){
    $sql=$cfg['select']; $params=[];
    if($q!==''){$sql.=" WHERE ".$cfg['search'];$params['q']="%".$q."%";}
    $sql.=" ORDER BY id DESC LIMIT 150";
    $st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
}
?>
<section class="page-intro"><div><span class="eyebrow">CATALOGUE / OPERATIONS</span><h2><?= e($cfg['title']) ?></h2><?php if($cfg['desc']): ?><p class="muted"><?= e($cfg['desc']) ?></p><?php endif; ?></div><?php if(is_admin()): ?><a class="btn primary" href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>&new=1">+ Thêm mới</a><?php endif; ?></section>
<?php if(is_admin() && ($editId || isset($_GET['new']))): ?>
<section class="card form-card" id="productGeneral"><div class="card-heading"><h3><?= $record?'Cập nhật':'Thêm mới' ?> · <?= e($cfg['title']) ?></h3><a class="text-link" href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>">Đóng ×</a></div>
<div class="product-editor-shortcuts"><a href="#productGeneral">01 · Thông tin chung</a><a href="#productPalette">02 · Bảng màu, size & ảnh</a></div>
<form method="post" enctype="multipart/form-data" class="form-grid product-editor-form"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($record['id']??0) ?>">
<?php foreach($cfg['fields'] as [$field,$label,$type,$required]): $val=$record[$field]??''; ?>
<label class="<?= $type==='textarea'?'span-2':'' ?>"><?= e($label) ?><?php if($type==='textarea'): ?><textarea name="<?= e($field) ?>" rows="3"><?= e($val) ?></textarea>
<?php elseif($type==='category'||$type==='product'): ?><select name="<?= e($field) ?>" <?= $required?'required':'' ?>><option value="">-- Chọn --</option><?php foreach(module_options($type) as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (string)$val===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
<?php elseif($type==='status'): ?><select name="<?= e($field) ?>"><option value="active" <?= $val==='active'?'selected':'' ?>>Hoạt động</option><option value="inactive" <?= $val==='inactive'?'selected':'' ?>>Ngừng</option></select>
<?php elseif($type==='gender'): ?><select name="<?= e($field) ?>" required><option value="women" <?= $val==='women'?'selected':'' ?>>Nữ</option><option value="men" <?= $val==='men'?'selected':'' ?>>Nam</option><option value="unisex" <?= $val==='unisex'?'selected':'' ?>>Unisex</option><option value="kids" <?= $val==='kids'?'selected':'' ?>>Trẻ em</option></select>
<?php elseif($type==='published'): ?><select name="<?= e($field) ?>"><option value="1" <?= (string)$val==='1'?'selected':'' ?>>Công khai trên website</option><option value="0" <?= (string)$val==='0'?'selected':'' ?>>Bản nháp · Ẩn khỏi website</option></select>
<?php elseif($type==='image'): ?><div class="image-source-control">
  <div class="image-source-tabs" role="group" aria-label="Nguồn ảnh sản phẩm">
    <button type="button" class="image-source-tab active" data-target="imageUploadPane" aria-pressed="true">Tải ảnh từ máy tính</button>
    <button type="button" class="image-source-tab" data-target="imageUrlPane" aria-pressed="false">Dán URL (tùy chọn)</button>
  </div>
  <div id="imageUploadPane"><input type="file" name="image_file" id="productImageFile" accept="image/jpeg,image/png,image/webp"><small>JPG · PNG · WEBP (5 MB)</small></div>
  <div id="imageUrlPane" hidden><input type="text" name="image_url" id="productImageUrl" value="<?= e($val) ?>" placeholder="Dán link HTTP(S) hoặc để trống" autocomplete="off" spellcheck="false"></div>
  <div class="image-preview-wrap"><img id="productImagePreview" src="<?= e($val ? (preg_match('~^https?://~i',$val)?$val:BASE_URL.'/'.ltrim($val,'/')) : '') ?>" alt="Xem trước ảnh" <?= $val?'':'hidden' ?>><span id="productImageEmpty" <?= $val?'hidden':'' ?>>Chưa có ảnh</span></div>
</div>
<?php elseif($type==='role'): ?><select name="role"><option value="staff" <?= $val==='staff'?'selected':'' ?>>Nhân viên</option><option value="admin" <?= $val==='admin'?'selected':'' ?>>Quản trị viên</option></select>
<?php else: ?><input type="<?= e($type) ?>" name="<?= e($field) ?>" value="<?= e($val) ?>" <?= $required && !($field==='password' && $record)?'required':'' ?> <?= $type==='number'?'min="0" step="1"':'' ?> placeholder="Nhập <?= e(mb_strtolower($label)) ?>"><?php endif; ?></label>
<?php endforeach; ?>
<div class="span-2"><?php require __DIR__.'/../includes/palette_editor.php'; ?></div>
<div class="form-actions span-2 wing-editor-actions"><button class="btn primary wing-save-product" type="submit"><span class="wing-save-icon" aria-hidden="true">✓</span><span class="wing-save-label">LƯU SẢN PHẨM</span></button><span class="wing-save-state" aria-live="polite" hidden>Đang lưu...</span><a class="btn light" href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>">Hủy</a></div>
<script>
(()=>{const url=document.getElementById('productImageUrl'),file=document.getElementById('productImageFile'),preview=document.getElementById('productImagePreview'),empty=document.getElementById('productImageEmpty');
const show=(src)=>{if(!src){preview.hidden=true;empty.hidden=false;return;}preview.src=src;preview.hidden=false;empty.hidden=true;};
const initialImage=preview?.getAttribute('src')||'';
let tempImageUrl=null;
url?.addEventListener('input',()=>{if(file?.files?.length)return;show(url.value.trim()||initialImage);});
file?.addEventListener('change',()=>{
 const f=file.files?.[0];
 if(tempImageUrl){URL.revokeObjectURL(tempImageUrl);tempImageUrl=null;}
 if(!f){show(url?.value.trim()||initialImage);return;}
 if(f.size>5*1024*1024){alert('Ảnh tối đa 5MB');file.value='';show(url?.value.trim()||initialImage);return;}
 if(!['image/jpeg','image/png','image/webp'].includes(f.type)){alert('Chỉ nhận JPG, PNG hoặc WEBP');file.value='';show(url?.value.trim()||initialImage);return;}
 tempImageUrl=URL.createObjectURL(f);show(tempImageUrl);
});
document.querySelectorAll('.image-source-tab').forEach(btn=>btn.addEventListener('click',()=>{
 document.querySelectorAll('.image-source-tab').forEach(x=>{const active=x===btn;x.classList.toggle('active',active);x.setAttribute('aria-pressed',String(active));});
 document.getElementById('imageUrlPane').hidden=btn.dataset.target!=='imageUrlPane';
 document.getElementById('imageUploadPane').hidden=btn.dataset.target!=='imageUploadPane';
}));
const productForm=document.querySelector('.product-editor-form');
productForm?.addEventListener('submit',(event)=>{
 if(!productForm.checkValidity())return;
 const submit=productForm.querySelector('.wing-save-product');
 if(!submit)return;
 if(submit.dataset.submitted==='true'){
   event.preventDefault();return;
 }
 submit.dataset.submitted='true';
 submit.classList.add('is-saving');
 const label=submit.querySelector('.wing-save-label');
 if(label)label.textContent='ĐANG LƯU';
 const state=productForm.querySelector('.wing-save-state');
 if(state)state.hidden=false;
 submit.setAttribute('aria-disabled','true');
}, {capture:true});
window.addEventListener('pageshow',()=>{
 const submit=productForm?.querySelector('.wing-save-product');
 if(!submit)return;
 delete submit.dataset.submitted;
 submit.classList.remove('is-saving');
 submit.removeAttribute('aria-disabled');
 const label=submit.querySelector('.wing-save-label');
 if(label)label.textContent='LƯU SẢN PHẨM';
 const state=productForm.querySelector('.wing-save-state');
 if(state)state.hidden=true;
});
})();
</script>
</form></section>
<?php endif; ?>
<?php if(!$editing): ?>
<section class="card"><div class="card-heading"><div><span class="eyebrow">DATA MANAGEMENT</span><h3>Danh sách dữ liệu</h3></div><form class="search-form" method="get"><input type="hidden" name="page" value="<?= e($page) ?>"><input name="q" value="<?= e($q) ?>" placeholder="Tìm kiếm..."><button class="btn light">Tìm</button></form></div>
<div class="table-wrap"><table><thead><tr><?php foreach($cfg['columns'] as [$field,$label]): ?><th><?= e($label) ?></th><?php endforeach; ?><?php if(is_admin()): ?><th>Thao tác</th><?php endif; ?></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><?php foreach($cfg['columns'] as [$field,$label]): ?><td><?php if($field==='image_url'): ?><img class="product-admin-thumb" loading="lazy" src="<?= e(wing_admin_product_image($row['image_url']??'')) ?>" alt="<?= e($row['name']??'Sản phẩm') ?>" onerror="this.onerror=null;this.src='<?= e(BASE_URL) ?>/assets/images/wing-favicon.png';"><?php elseif($field==='price'): ?><?= money($row[$field]) ?><?php elseif($field==='status'): ?><span class="pill <?= e($row[$field]) ?>"><?= $row[$field]==='active'?'Hoạt động':'Ngừng' ?></span><?php elseif($field==='created_at'): ?><?= e(date('d/m/Y',strtotime($row[$field]))) ?><?php elseif($field==='gender'): ?><?= ['women'=>'Nữ','men'=>'Nam','unisex'=>'Unisex','kids'=>'Trẻ em'][$row[$field]]??'—' ?><?php elseif($field==='is_published'): ?><span class="pill <?= (int)$row[$field]===1?'active':'warning' ?>"><?= (int)$row[$field]===1?'Đang trưng bày':'Bản nháp' ?></span><?php else: ?><?= e($row[$field]??'—') ?><?php endif; ?></td><?php endforeach; ?><?php if(is_admin()): ?><td class="actions"><a href="<?= BASE_URL ?>/index.php?page=<?= e($page) ?>&edit=<?= (int)$row['id'] ?>">Sửa</a><form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="link-danger">Xóa</button></form></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="<?= count($cfg['columns'])+(is_admin()?1:0) ?>" class="empty">Không tìm thấy dữ liệu.</td></tr><?php endif; ?>
</tbody></table></div><div class="table-foot">Hiển thị tối đa 150 bản ghi gần nhất.</div></section>
<?php endif; ?>

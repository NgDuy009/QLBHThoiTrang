<?php
// Included from pages/products.php, inside the product form.
$paletteByColor=[];
if($record){
    $ps=$pdo->prepare('SELECT id,color,color_hex,image_url,size,stock_quantity FROM product_variants WHERE product_id=? ORDER BY color,size');
    $ps->execute([(int)$record['id']]);
    foreach($ps->fetchAll(PDO::FETCH_ASSOC) as $pv)$paletteByColor[$pv['color']][]=$pv;
}
?>
<section class="product-palette" id="productPalette">
 <div class="product-palette-heading">
  <div><span class="eyebrow">SẢN PHẨM / BIẾN THỂ</span><h3>Size & bảng màu</h3></div>
  <button type="button" class="btn primary" id="paletteAddColor">+ THÊM MÀU</button>
 </div>
 <input type="hidden" name="palette_enabled" value="1">
 <div class="palette-row-list" id="paletteRows">
 <?php $paletteIndex=0; foreach($paletteByColor as $name=>$variants):
   $first=$variants[0];$paletteImage=(string)($first['image_url']??'');
   $hex=(string)($first['color_hex']??'');if(!preg_match('/^#[0-9a-fA-F]{6}$/',$hex))$hex=wing_palette_hex($name);
   $paletteSizes=array_column($variants,'size');
 ?>
 <article class="palette-color-card" data-palette-index="<?= $paletteIndex ?>">
  <div class="palette-color-top"><span class="palette-swatch-live" style="--palette-color:<?= e($hex) ?>"></span><div><b><?= e($name) ?></b></div><a href="<?= BASE_URL ?>/index.php?page=variants&q=<?= urlencode($name) ?>" class="text-link">BIẾN THỂ NÂNG CAO ↗</a></div>
  <input type="hidden" name="palette[<?= $paletteIndex ?>][origin]" value="<?= e($name) ?>">
  <div class="palette-color-fields">
   <label>TÊN MÀU <input name="palette[<?= $paletteIndex ?>][color]" value="<?= e($name) ?>" readonly></label>
   <label>MÀU ĐẠI DIỆN <div class="palette-hex-control"><input type="color" class="palette-color-input" value="<?= e($hex) ?>" aria-label="Chọn mã màu"><input name="palette[<?= $paletteIndex ?>][hex]" class="palette-hex-input" value="<?= e(strtoupper($hex)) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required></div></label>
   <label>SIZE <input name="palette[<?= $paletteIndex ?>][sizes]" value="<?= e(implode(', ', $paletteSizes)) ?>" required placeholder="S, M, L, XL"></label>
   <label>TỒN KHO CHO SIZE THÊM MỚI <input type="number" name="palette[<?= $paletteIndex ?>][new_stock]" value="0" min="0" max="9999" required></label>
   <label class="palette-span-2">ẢNH RIÊNG CHO MÀU (URL) <input type="text" class="palette-url-input" name="palette[<?= $paletteIndex ?>][image_url]" value="<?= e($paletteImage) ?>" placeholder="https://... hoặc uploads/products/..."></label>
   <label class="palette-span-2">HOẶC TẢI ẢNH MÀU TỪ MÁY <input type="file" class="palette-file-input" name="palette_image_file[<?= $paletteIndex ?>]" accept="image/jpeg,image/png,image/webp"></label>
  </div>
  <div class="palette-bottom"><div class="palette-photo-preview"><img class="palette-preview-image" src="<?= e($paletteImage?wing_admin_product_image($paletteImage):($record['image_url']?wing_admin_product_image($record['image_url']):BASE_URL.'/assets/images/wing-favicon.png')) ?>" alt="Xem trước ảnh màu <?= e($name) ?>"></div><div class="palette-size-stock"><b>Tồn kho theo size hiện có</b><div class="palette-stock-chips"><?php foreach($variants as $pv): ?><label><?= e($pv['size']) ?> <input type="number" name="palette_stock[<?= (int)$pv['id'] ?>]" value="<?= (int)$pv['stock_quantity'] ?>" min="0" max="9999" required></label><?php endforeach; ?></div></div></div>
 </article>
 <?php $paletteIndex++;endforeach; ?>
 </div>
 <template id="paletteRowTemplate">
  <article class="palette-color-card" data-palette-index="__INDEX__">
   <div class="palette-color-top"><span class="palette-swatch-live" style="--palette-color:#F8F7F2"></span><div><b>Màu mới</b></div><button type="button" class="palette-remove" aria-label="Bỏ màu này">×</button></div>
   <div class="palette-color-fields">
    <label>TÊN MÀU <input name="palette[__INDEX__][color]" class="palette-name-input" maxlength="50" placeholder="Trắng, Đen, Be, Xanh navy..." required></label>
    <label>MÀU ĐẠI DIỆN <div class="palette-hex-control"><input type="color" class="palette-color-input" value="#F8F7F2" aria-label="Chọn màu"><input name="palette[__INDEX__][hex]" class="palette-hex-input" value="#F8F7F2" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required></div></label>
    <label>SIZE (CÁCH NHAU BẰNG DẤU PHẨY) <input name="palette[__INDEX__][sizes]" value="S, M, L" placeholder="S, M, L, XL hoặc One Size" required></label>
    <label>TỒN KHO CHO MỖI SIZE <input type="number" name="palette[__INDEX__][new_stock]" value="5" min="0" max="9999" required></label>
    <label class="palette-span-2">ẢNH RIÊNG CHO MÀU (URL) <input type="text" class="palette-url-input" name="palette[__INDEX__][image_url]" placeholder="https://... (có thể để trống)"></label>
    <label class="palette-span-2">HOẶC TẢI ẢNH MÀU TỪ MÁY <input type="file" class="palette-file-input" name="palette_image_file[__INDEX__]" accept="image/jpeg,image/png,image/webp"></label>
   </div>
   <div class="palette-bottom"><div class="palette-photo-preview"><img class="palette-preview-image" alt="Xem trước ảnh cho màu mới" src="<?= BASE_URL ?>/assets/images/wing-favicon.png"></div></div>
  </article>
 </template>

</section>
<script src="<?= BASE_URL ?>/assets/js/wing-palette-editor.js?v=20261112-v2"></script>

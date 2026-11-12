<?php
/** WinG product palette: one product, multiple swatches, sizes, stock and photos. */
declare(strict_types=1);

function wing_palette_colors(): array {
    return [
        'đen'=>'#181918','trắng'=>'#F8F7F2','trang'=>'#F8F7F2','be'=>'#C9B698',
        'kem'=>'#E9DCC3','nâu'=>'#76533D','đỏ'=>'#A53F45','xanh navy'=>'#1D324B',
        'navy'=>'#1D324B','xanh lá'=>'#55755C','xanh'=>'#526B87',
        'hồng'=>'#DCA9BB','xám'=>'#919593','ghi'=>'#A2A5A1','vàng'=>'#D9BC54',
        'tím'=>'#876D9C','cam'=>'#CB8150'
    ];
}
function wing_palette_hex(string $color): string {
    return wing_palette_colors()[mb_strtolower(trim($color))] ?? '#C6BAA5';
}
function wing_palette_image_upload(array $files, int $idx, array &$newFiles): ?string {
    if(empty($files['name'][$idx])) return null;
    $error=(int)($files['error'][$idx]??UPLOAD_ERR_NO_FILE);
    if($error!==UPLOAD_ERR_OK) throw new RuntimeException('Không tải được ảnh màu; hãy thử lại.');
    $size=(int)($files['size'][$idx]??0);
    if($size<1||$size>5*1024*1024) throw new RuntimeException('Ảnh màu cần nhỏ hơn 5MB.');
    $tmp=(string)($files['tmp_name'][$idx]??'');
    if(!is_uploaded_file($tmp)) throw new RuntimeException('Ảnh tải lên không hợp lệ.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($extensions[$mime])) throw new RuntimeException('Ảnh màu chỉ hỗ trợ JPG, PNG hoặc WEBP.');
    $folder=__DIR__.'/../uploads/products';
    if(!is_dir($folder) && !mkdir($folder,0755,true) && !is_dir($folder)) throw new RuntimeException('Không tạo được thư mục ảnh sản phẩm.');
    $name='wing-color-'.bin2hex(random_bytes(9)).'.'.$extensions[$mime];
    $destination=$folder.'/'.$name;
    if(!move_uploaded_file($tmp,$destination)) throw new RuntimeException('Không thể lưu ảnh màu.');
    $newFiles[]=$destination;
    return 'uploads/products/'.$name;
}
function wing_palette_image_value(string $posted): ?string {
    $posted=trim($posted);
    if($posted==='') return null;
    if(preg_match('~^uploads/products/[a-zA-Z0-9._-]+$~D',$posted)) return $posted;
    $parts=parse_url($posted);
    if(!filter_var($posted,FILTER_VALIDATE_URL)||!in_array(strtolower((string)($parts['scheme']??'')),['https','http'],true))
        throw new RuntimeException('Đường dẫn ảnh màu cần là URL HTTP(S) hoặc ảnh đã tải lên.');
    if(strlen($posted)>500) throw new RuntimeException('URL ảnh màu không được dài quá 500 ký tự.');
    return $posted;
}
/** Must run in a product save transaction; edits only variants owned by this product. */
function wing_palette_save(PDO $pdo, int $productId, string $sku, array $post, array $files, array &$newFiles): array {
    if(empty($post['palette_enabled'])) return [0,0];
    $rows=$post['palette']??[];
    if(!is_array($rows)||count($rows)>18) throw new RuntimeException('Mỗi sản phẩm tối đa 18 màu trong một lượt cập nhật.');
    $old=$pdo->prepare('SELECT id,size,color,color_hex,stock_quantity,image_url FROM product_variants WHERE product_id=? FOR UPDATE');
    $old->execute([$productId]);$existing=$old->fetchAll(PDO::FETCH_ASSOC);
    $byColor=[];$stockIds=[];$existingKeys=[];
    foreach($existing as $v){
        $byColor[$v['color']][]=$v;
        $stockIds[(int)$v['id']]=$v;
        $existingKeys[$v['color']."\x1f".$v['size']]=true;
    }
    $stockChanged=false;
    $stockEdit=$post['palette_stock']??[];
    if(!is_array($stockEdit)) throw new RuntimeException('Tồn kho không hợp lệ.');
    $updateStock=$pdo->prepare('UPDATE product_variants SET stock_quantity=? WHERE id=? AND product_id=?');
    foreach($stockEdit as $id=>$count){
        $id=(int)$id;
        if(!isset($stockIds[$id]))throw new RuntimeException('Biến thể không thuộc sản phẩm hiện tại.');
        $qty=filter_var($count,FILTER_VALIDATE_INT);
        if($qty===false || $qty<0 || $qty>9999) throw new RuntimeException('Tồn kho phải trong khoảng 0–9999.');
        if($qty!==(int)$stockIds[$id]['stock_quantity']){
            $updateStock->execute([$qty,$id,$productId]);$stockChanged=true;
        }
    }
    $seen=[];$created=0;$updated=0;$total=0;
    $add=$pdo->prepare('INSERT INTO product_variants(product_id,sku,size,color,color_hex,image_url,stock_quantity) VALUES(?,?,?,?,?,?,?)');
    $editHex=$pdo->prepare('UPDATE product_variants SET color_hex=? WHERE product_id=? AND color=?');
    $editImage=$pdo->prepare('UPDATE product_variants SET color_hex=?,image_url=? WHERE product_id=? AND color=?');
    foreach($rows as $key=>$row){
        if(!is_array($row)) throw new RuntimeException('Dữ liệu màu không hợp lệ.');
        $name=trim((string)($row['color']??''));
        if($name==='') continue;
        if(mb_strlen($name)>50)throw new RuntimeException('Tên màu không được vượt 50 ký tự.');
        $origin=trim((string)($row['origin']??''));
        if($origin!=='' && (!isset($byColor[$origin])||$origin!==$name))
            throw new RuntimeException('Không thể đổi tên nhóm màu cũ ở đây. Hãy dùng trang Biến thể nâng cao.');
        $lower=mb_strtolower($name);
        if(isset($seen[$lower])) throw new RuntimeException('Bạn đã nhập trùng màu '.$name.'.');
        $seen[$lower]=true;
        $hex=strtoupper(trim((string)($row['hex']??'')));
        if(!preg_match('/^#[A-F0-9]{6}$/',$hex))throw new RuntimeException('Mã màu HEX cho '.$name.' phải có dạng #FFFFFF.');
        $sizes=array_values(array_unique(array_filter(array_map('trim',preg_split('/[,;\r\n]+/u',(string)($row['sizes']??''))))));
        if(!$sizes||count($sizes)>12)throw new RuntimeException('Mỗi màu cần từ 1–12 size, phân tách bằng dấu phẩy.');
        foreach($sizes as $s)if(mb_strlen($s)>30)throw new RuntimeException('Tên size không được quá 30 ký tự.');
        $count=filter_var($row['new_stock']??'0',FILTER_VALIDATE_INT);
        if($count===false||$count<0||$count>9999)throw new RuntimeException('Tồn kho ban đầu phải từ 0–9999.');
        $previous=$origin!=='' ? ($byColor[$origin][0]['image_url']??null) : null;
        $image=wing_palette_image_value((string)($row['image_url']??''));
        $upload=wing_palette_image_upload($files,(int)$key,$newFiles);
        $changedImage=$upload!==null || ($origin!=='' && $image!==$previous);
        if($upload!==null)$image=$upload;
        if($origin!==''){
            $oldHex=(string)($byColor[$origin][0]['color_hex']??'');
            $hexChanged=($hex!==$oldHex);
            if($changedImage){
                $editImage->execute([$hex,$image,$productId,$origin]);
                $updated++;
            } elseif($hexChanged){
                $editHex->execute([$hex,$productId,$origin]);
                $updated++;
            }
            if(!$changedImage){
                foreach($byColor[$origin] as $oldVariant){
                    if(!empty($oldVariant['image_url'])){$image=$oldVariant['image_url'];break;}
                }
            }
        }
        foreach($sizes as $size){
            $total++;
            if($total>100)throw new RuntimeException('Tối đa 100 tổ hợp size/màu một lần.');
            $key=$name."\x1f".$size;
            if(isset($existingKeys[$key]))continue;
            $variantSku=substr($sku,0,42).'-'.strtoupper(bin2hex(random_bytes(5)));
            $add->execute([$productId,$variantSku,$size,$name,$hex,$image,$count]);
            $existingKeys[$key]=true;$created++;
        }
    }
    if($created>0||$stockChanged)refresh_product_stock($pdo,$productId);
    return [$created,$updated];
}

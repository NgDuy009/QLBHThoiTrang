<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$exists=(int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 if($exists>0){$message='Đã có tài khoản trong hệ thống. Hãy xóa setup.php khỏi thư mục dự án.';}
 else{
  $name=trim((string)($_POST['full_name']??''));$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
  if($name===''||$username===''||strlen($password)<10){$message='Vui lòng nhập đủ thông tin và mật khẩu tối thiểu 10 ký tự.';}
  else{$st=db()->prepare("INSERT INTO users(full_name,username,password_hash,role,status) VALUES(?,?,?,'admin','active')");$st->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT)]);$message='Tạo tài khoản quản trị thành công. Hãy xóa setup.php ngay sau khi hoàn tất.';$exists=1;}
 }
}
?><!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FSMS Setup</title><link rel="stylesheet" href="assets/css/style.css"><body class="auth-layout"><section class="card" style="width:min(100%,480px)"><span class="eyebrow">INITIAL CONFIGURATION</span><h2 style="font-family:'Playfair Display'">Tạo tài khoản quản trị</h2><?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if(!$exists):?><form method="post" class="form-stack"><?=csrf_field()?><label>Họ tên<input name="full_name" required></label><label>Tên đăng nhập<input name="username" required></label><label>Mật khẩu (ít nhất 10 ký tự)<input type="password" name="password" minlength="10" required></label><button class="btn primary">Tạo admin</button></form><?php else:?><p>Tài khoản đã được khởi tạo. Xóa file setup.php trước khi sử dụng.</p><a class="btn primary" href="index.php?page=login">Đi đến đăng nhập</a><?php endif;?></section></body></html>

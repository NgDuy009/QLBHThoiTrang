<?php
require_once __DIR__ . '/functions.php';
$user = current_user();
$page = active_page();
$flash = get_flash();
$managementUnreadCount = $user ? wing_notification_unread(db(),'user',(int)$user['id']) : 0;
$pageTitles = ['dashboard'=>'Tổng quan','products'=>'Sản phẩm','categories'=>'Danh mục','variants'=>'Biến thể nâng cao','customers'=>'Khách hàng','accounts'=>'Phân quyền tài khoản','payment_settings'=>'Ngân hàng & QR','employees'=>'Nhân viên','orders'=>'Đơn hàng','order_detail'=>'Chi tiết đơn hàng','inventory'=>'Kho hàng','reports'=>'Báo cáo','support'=>'Hỗ trợ khách hàng','notifications'=>'Thông báo'];
$navGroups = [
 ['products','Sản phẩm',[['products','Tất cả sản phẩm'],['variants','Size & bảng màu nâng cao'],['categories','Danh mục']]],
 ['orders','Kinh doanh',[['orders','Đơn hàng'],['inventory','Kho hàng']]],
 ['customers','Khách hàng',[['customers','Khách hàng'],['support','Liên hệ & hỗ trợ']]],
];
if(is_admin()) $navGroups[]=['accounts','Hệ thống',[['accounts','Tài khoản & phân quyền'],['employees','Nhân viên'],['payment_settings','Ngân hàng & VietQR']]];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?> · <?= e($pageTitles[$page] ?? 'WinG') ?></title>
<link rel="icon" type="image/png" sizes="128x128" href="<?= BASE_URL ?>/assets/images/wing-favicon.png?v=wing-v4">
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/images/wing-favicon.ico?v=wing-v4">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/wing-favicon.png?v=wing-v4">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=wing-20261112-speed-clean-v2">
</head><body>
<?php if (!$user): require __DIR__ . '/wing_intro.php'; endif; ?>
<?php if ($user): ?>
<div class="site-shell">
<header class="wing-header">
 <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark">W</span><span class="brand-copy"><b>WinG</b><small>THE FASHION HOUSE</small></span></a>
 <nav class="main-nav admin-nav-compact" id="mainNav" aria-label="Danh mục quản trị">
  <a class="nav-link <?= $page==='dashboard'?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=dashboard">Tổng quan</a>
  <?php foreach($navGroups as [$groupKey,$groupLabel,$items]):
   $activeGroup=in_array($page,array_column($items,0),true); ?>
   <details class="admin-nav-group" <?= $activeGroup?'data-active="1"':'' ?>>
    <summary class="nav-link <?= $activeGroup?'active':'' ?>"><?= e($groupLabel) ?><span aria-hidden="true">⌄</span></summary>
    <div class="admin-nav-panel">
     <?php foreach($items as [$key,$label]): ?><a class="<?= $page===$key?'selected':'' ?>" href="<?= BASE_URL ?>/index.php?page=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
   </details>
  <?php endforeach; ?>
  <a class="nav-link <?= $page==='reports'?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=reports">Báo cáo</a>
 </nav>
 <div class="header-user"><a class="management-notification-link" href="<?= BASE_URL ?>/index.php?page=notifications" title="Thông báo">♧<?php if($managementUnreadCount): ?><em><?= $managementUnreadCount>9?'9+':$managementUnreadCount ?></em><?php endif; ?></a><a class="store-shortcut" href="<?= BASE_URL ?>/index.php?page=shop">MỞ CỬA HÀNG ↗</a><span class="header-date"><?= date('d.m.Y') ?></span><span class="header-divider"></span><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'],0,1))) ?></span><span class="header-user-copy"><b><?= e($user['full_name']) ?></b><small><?= $user['role']==='admin'?'Administrator':'Staff account' ?></small></span><a class="logout" href="<?= BASE_URL ?>/index.php?page=logout" title="Đăng xuất">↗</a></div>
 <button class="mobile-menu" id="menuToggle" aria-label="Mở menu">☰</button>
</header>
<main class="main-content">
<div class="page-masthead"><div><span class="eyebrow">WING FASHION HOUSE <i>/</i> MANAGEMENT</span><h1><?= e($pageTitles[$page] ?? 'WinG') ?></h1></div><div class="masthead-note"><span class="live-dot"></span> SYSTEM ONLINE</div></div>
<?php if($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php else: ?>
<main class="auth-layout">
<?php endif; ?>

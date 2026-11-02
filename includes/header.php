<?php
require_once __DIR__ . '/functions.php';
$user = current_user();
$page = active_page();
$flash = get_flash();
$managementUnreadCount = $user ? wing_notification_unread(db(),'user',(int)$user['id']) : 0;
$pageTitles = ['dashboard'=>'Tổng quan','products'=>'Sản phẩm','categories'=>'Danh mục','variants'=>'Size & màu','customers'=>'Khách hàng','employees'=>'Nhân viên','orders'=>'Đơn hàng','order_detail'=>'Chi tiết đơn hàng','inventory'=>'Kho hàng','reports'=>'Báo cáo','support'=>'Hỗ trợ khách hàng','notifications'=>'Thông báo'];
$nav = [
 ['dashboard','Tổng quan','⌂'],['products','Sản phẩm','◇'],['categories','Danh mục','▦'],
 ['variants','Size & màu','◈'],['customers','Khách hàng','♙'],['support','Hỗ trợ','✧'],
 ['orders','Đơn hàng','▤'],['inventory','Kho hàng','▣'],['reports','Báo cáo','▥']
];
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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=wing-v5-editorial">
</head><body>
<?php require __DIR__ . '/wing_intro.php'; ?>
<?php if ($user): ?>
<div class="site-shell">
<header class="wing-header">
 <a class="brand" href="<?= BASE_URL ?>/index.php"><span class="brand-mark">W</span><span class="brand-copy"><b>WinG</b><small>THE FASHION HOUSE</small></span></a>
 <nav class="main-nav" id="mainNav">
 <?php foreach($nav as [$key,$label,$icon]): ?><a class="nav-link <?= $page===$key?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
 <?php if(is_admin()): ?><a class="nav-link <?= $page==='employees'?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=employees">Nhân viên</a><?php endif; ?>
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

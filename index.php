<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$page = active_page();
if ($page === 'settings') redirect('support');
if (in_array($page, ['support','notifications'], true) && is_logged_in()) {
    require_login();
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/pages/' . $page . '.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}
if ($page === 'payment_mobile') { require __DIR__ . '/pages/payment_mobile.php'; exit; }
if ($page === 'payment_status') { require __DIR__ . '/pages/payment_status.php'; exit; }
if ($page === 'customer_login') redirect('login');
$storefrontPages = ['shop','product','cart','checkout','customer_login','customer_register','customer_logout','customer_orders','customer_profile','wishlist','customer_order_detail','support','notifications'];
if (in_array($page, $storefrontPages, true)) {
    require __DIR__ . '/pages/storefront.php';
    exit;
}
if ($page === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); logout_user(); }
    else { logout_user(); }
    header('Location: ' . BASE_URL . '/index.php?page=login'); exit;
}
if ($page === 'login') {
    if (is_logged_in()) redirect('dashboard');
    if (!empty($_SESSION['customer_id'])) redirect('shop');
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $identity = trim((string)($_POST['identity'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        // Strictly allow staff/admin accounts from users; customers from customers.
        if ($identity !== '' && login_user($identity, $password)) {
            redirect('dashboard');
        }

        try {
            $stmt = db()->prepare("SELECT id, password_hash, account_status FROM customers WHERE LOWER(email)=LOWER(?) AND password_hash IS NOT NULL LIMIT 1");
            $stmt->execute([$identity]);
            $customer = $stmt->fetch();
            if ($customer && $customer['account_status'] === 'active' && password_verify($password, $customer['password_hash'])) {
                session_regenerate_id(true);
                unset($_SESSION['wing_cart_v2'], $_SESSION['wing_guest_cart']);
                unset($_SESSION['user']);
                $_SESSION['customer_id'] = (int)$customer['id'];
                redirect('shop');
            }
        } catch (Throwable $e) {
            // Keep the public sign-in error generic; database details belong in server logs.
        }
        $error = 'Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa.';
    }
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/pages/login.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}
require_login();
$allowed = ['dashboard','products','categories','variants','customers','employees','accounts','payment_settings','orders','order_detail','inventory','reports'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';

// Staff have a deliberately narrower workspace; customer sessions cannot enter this area.
if (!is_admin()) {
    $staffAllowed = ['dashboard','products','categories','variants','customers','orders','order_detail','inventory','reports'];
    if (!in_array($page, $staffAllowed, true)) {
        flash('error', 'Tài khoản nhân viên không có quyền truy cập mục này.');
        redirect('dashboard');
    }
}
if (in_array($page, ['employees','accounts','payment_settings'], true)) require_admin();
if ($page === 'products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/pages/products.php';
    exit;
}
require __DIR__ . '/includes/header.php';
$file = __DIR__ . '/pages/' . $page . '.php';
if (is_file($file)) require $file;
else echo '<div class="card">Trang chưa được triển khai.</div>';
require __DIR__ . '/includes/footer.php';

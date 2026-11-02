<?php
declare(strict_types=1);

define('APP_NAME', 'WinG | Fashion Store Management');
define('BASE_URL', '/FSMS'); // Nếu đổi tên thư mục, cập nhật giá trị này.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'fsms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax'
    ]);
    session_start();
}

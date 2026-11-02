<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function login_user(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT id, full_name, username, password_hash, role, status FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) return false;
    session_regenerate_id(true);
    unset($_SESSION['customer_id']);
    unset($_SESSION['wing_cart_v2']);
    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    return true;
}
function logout_user(): void {
    // One shared logout for Admin, Staff and Customer; no role session survives.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $page): never {
    header('Location: ' . BASE_URL . '/index.php?page=' . urlencode($page));
    exit;
}
function current_user(): ?array {
    static $checked=false, $valid=null;
    if ($checked) return $valid;
    $checked=true;
    $sessionUser=$_SESSION['user']??null;
    if (!is_array($sessionUser) || empty($sessionUser['id'])) return null;
    $st=db()->prepare('SELECT id, full_name, username, role, status FROM users WHERE id=?');
    $st->execute([(int)$sessionUser['id']]);
    $row=$st->fetch();
    if (!$row || $row['status']!=='active' || !in_array($row['role'],['admin','staff'],true)) {
        unset($_SESSION['user']); return null;
    }
    $_SESSION['user']=$row;
    return $valid=$row;
}
function is_logged_in(): bool { return current_user() !== null; }
function require_login(): void {
    if (!is_logged_in()) redirect('login');
}
function is_admin(): bool { return is_logged_in() && current_user()['role'] === 'admin'; }
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang.');
    }
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function money(mixed $value): string { return number_format((float)$value, 0, ',', '.') . ' ₫'; }
function active_page(): string { return (string)($_GET['page'] ?? 'dashboard'); }
function has_column_value(array $data, string $key): string {
    return trim((string)($data[$key] ?? ''));
}

require_once __DIR__ . '/order_helpers.php';

require_once __DIR__ . '/commerce.php';

require_once __DIR__ . '/notifications.php';

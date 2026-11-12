<?php
declare(strict_types=1);
/** WinG two-stage demo validation. The bank transfer receipt must still be verified by Admin. */

function wing_mobile_origin(PDO $pdo): ?string {
    try {
        $st = $pdo->query('SELECT base_url FROM wing_mobile_payment_settings WHERE id=1 LIMIT 1');
        $url = rtrim(trim((string)($st->fetchColumn() ?: '')), '/');
        if ($url !== '' && wing_valid_mobile_base_url($url)) return $url;
    } catch (PDOException $ignored) {
        // Migration may not be installed; checkout must keep working.
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || str_contains($host, '@') || preg_match('/(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/i', $host)) return null;
    // Only auto-derive a URL on explicit local-network IP; public domains require admin configuration.
    $raw = explode(':', $host)[0];
    if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && preg_match('~^(?:10\.|192\.168\.|172\.(?:1[6-9]|2[0-9]|3[01])\.)~', $raw)) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $proto . '://' . $host . BASE_URL;
    }
    return null;
}

function wing_valid_mobile_base_url(string $url): bool {
    if (strlen($url) > 240 || preg_match('/[\s?#@]/u', $url)) return false;
    $parts = parse_url($url);
    return is_array($parts)
        && in_array($parts['scheme'] ?? '', ['http','https'], true)
        && !empty($parts['host'])
        && !in_array(strtolower($parts['host']), ['localhost','127.0.0.1','::1'], true)
        && rtrim((string)($parts['path']??''), '/') === BASE_URL
        && empty($parts['query']) && empty($parts['fragment'])
        && empty($parts['user']) && empty($parts['pass']);
}

/** One random token per order, expires within 24h; token grants only access to payment claim workflow. */
function wing_mobile_token(PDO $pdo, int $orderId): ?string {
    try {
        $st=$pdo->prepare('SELECT token,(expires_at>DATE_ADD(NOW(),INTERVAL 2 MINUTE)) AS valid_token FROM wing_payment_mobile_tokens WHERE order_id=?');
        $st->execute([$orderId]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['valid_token'] === 1) return (string)$row['token'];
        $token=bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO wing_payment_mobile_tokens (order_id,token,expires_at,failed_attempts,blocked_until) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR),0,NULL) ON DUPLICATE KEY UPDATE token=VALUES(token),expires_at=VALUES(expires_at),failed_attempts=0,blocked_until=NULL")
            ->execute([$orderId,$token]);
        return $token;
    } catch (PDOException $ex) {
        error_log('WinG mobile payment token: '.$ex->getMessage());
        return null;
    }
}


/** Short random code for the second step; never used as evidence of receipt. */
function wing_issue_confirmation_code(): string {
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code='';
    for($i=0;$i<8;$i++) $code.=$alphabet[random_int(0,strlen($alphabet)-1)];
    return $code;
}

/** Bind each confirmation code to one order and its unguessable mobile token. */
function wing_confirmation_digest(string $code, string $token, int $orderId): string {
    return hash_hmac('sha256',strtoupper($code).':'.$orderId,$token);
}

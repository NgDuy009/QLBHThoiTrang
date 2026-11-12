<?php
declare(strict_types=1);
// Only the signed-in owner can poll the state of their order.
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if ($_SERVER['REQUEST_METHOD']!=='GET' || empty($_SESSION['customer_id'])) {http_response_code(401);echo json_encode(['error'=>'Unauthorized']);exit;}
$st=db()->prepare('SELECT status,payment_status,payment_claimed_at FROM orders WHERE id=? AND customer_id=?');
$st->execute([(int)($_GET['id']??0),(int)$_SESSION['customer_id']]);
$row=$st->fetch(PDO::FETCH_ASSOC);
if (!$row) {http_response_code(404);echo json_encode(['error'=>'Not found']);exit;}
echo json_encode(['status'=>$row['status'],'payment_status'=>$row['payment_status'],'claimed'=>!empty($row['payment_claimed_at'])],JSON_UNESCAPED_UNICODE);

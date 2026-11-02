<?php
function order_status_label(string $status): string {
    return ['pending'=>'Chờ xử lý','confirmed'=>'Đã xác nhận','completed'=>'Hoàn thành','cancelled'=>'Đã hủy'][$status] ?? $status;
}

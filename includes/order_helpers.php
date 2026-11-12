<?php
function order_status_label(string $status): string {
    return ['pending'=>'Chờ xử lý','confirmed'=>'Đã xác nhận đơn','shipping'=>'Đã giao hàng','completed'=>'Đã nhận hàng','cancelled'=>'Đã hủy'][$status] ?? $status;
}

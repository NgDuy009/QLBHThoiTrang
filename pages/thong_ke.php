<?php
$du_lieu_thang = [
    ['thang' => 'Tháng 1', 'doanh_thu' => 15000000, 'don_hang' => 45],
    ['thang' => 'Tháng 2', 'doanh_thu' => 22500000, 'don_hang' => 68],
    ['thang' => 'Tháng 3', 'doanh_thu' => 18000000, 'don_hang' => 52],
    ['thang' => 'Tháng 4', 'doanh_thu' => 29000000, 'don_hang' => 84],
    ['thang' => 'Tháng 5', 'doanh_thu' => 34500000, 'don_hang' => 96],
    ['thang' => 'Tháng 6', 'doanh_thu' => 41000000, 'don_hang' => 115],
];

$tong_doanh_thu = array_sum(array_column($du_lieu_thang, 'doanh_thu'));
$tong_don_hang = array_sum(array_column($du_lieu_thang, 'don_hang'));
$max_doanh_thu = max(array_column($du_lieu_thang, 'doanh_thu'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống Kê Doanh Thu - QLBH Thời Trang</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2c3e50; padding-bottom: 12px; margin-bottom: 25px; }
        .btn-print { background: #27ae60; color: #fff; border: none; padding: 10px 18px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .cards { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .card { flex: 1; min-width: 200px; background: #3498db; color: #fff; padding: 20px; border-radius: 8px; }
        .card.green { background: #2ecc71; }
        .card.orange { background: #e67e22; }
        .card h4 { margin: 0 0 10px 0; font-weight: normal; opacity: 0.9; }
        .card p { margin: 0; font-size: 22px; font-weight: bold; }
        .chart-container { display: flex; align-items: flex-end; justify-content: space-around; height: 240px; padding: 20px 10px 10px; border-bottom: 2px solid #ccc; background: #fafafa; border-radius: 8px; }
        .bar-group { display: flex; flex-direction: column; align-items: center; width: 12%; }
        .bar { width: 100%; background: #3498db; border-radius: 5px 5px 0 0; transition: 0.3s; }
        .bar:hover { background: #2980b9; }
        .bar-value { font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #333; }
        .bar-label { margin-top: 8px; font-size: 13px; font-weight: bold; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #2c3e50; color: white; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Báo Cáo Thống Kê Doanh Thu Cửa Hàng Thời Trang</h2>
        <button class="btn-print" onclick="window.print()">In / Xuất Báo Cáo</button>
    </div>

    <div class="cards">
        <div class="card">
            <h4>Tổng Doanh Thu (6 Tháng)</h4>
            <p><?php echo number_format($tong_doanh_thu, 0, ',', '.'); ?> VNĐ</p>
        </div>
        <div class="card green">
            <h4>Tổng Đơn Hàng Thành Công</h4>
            <p><?php echo $tong_don_hang; ?> Đơn hàng</p>
        </div>
        <div class="card orange">
            <h4>Trung Bình Giá Trị Đơn</h4>
            <p><?php echo number_format($tong_doanh_thu / $tong_don_hang, 0, ',', '.'); ?> VNĐ</p>
        </div>
    </div>

    <h3>Biểu Đồ Tăng Trưởng Doanh Thu</h3>
    <div class="chart-container">
        <?php foreach ($du_lieu_thang as $item): 
            $chieu_cao = round(($item['doanh_thu'] / $max_doanh_thu) * 180);
        ?>
        <div class="bar-group">
            <div class="bar-value"><?php echo number_format($item['doanh_thu'] / 1000000, 1); ?>Tr</div>
            <div class="bar" style="height: <?php echo $chieu_cao; ?>px;"></div>
            <div class="bar-label"><?php echo $item['thang']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Thời Gian</th>
                <th>Số Lượng Đơn Hàng</th>
                <th>Doanh Thu (VNĐ)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($du_lieu_thang as $item): ?>
            <tr>
                <td><?php echo $item['thang']; ?></td>
                <td><?php echo $item['don_hang']; ?> đơn</td>
                <td><?php echo number_format($item['doanh_thu'], 0, ',', '.'); ?> VNĐ</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>

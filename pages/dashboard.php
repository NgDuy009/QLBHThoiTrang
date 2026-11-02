<?php
$pdo=db();
$isAdmin = is_admin();
if ($isAdmin) {
    $stats=[
        ['NET REVENUE · TODAY',$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status <> 'cancelled'")->fetchColumn(),'revenue'],
        ['ORDERS RECEIVED',$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),'orders'],
        ['ACTIVE PRODUCTS',$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),'products'],
        ['CLIENTS',$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),'clients']
    ];
} else {
    $stats=[
        ['TODAY’S ORDERS',$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn(),'orders'],
        ['ACTIVE PRODUCTS',$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),'products'],
        ['CLIENTS',$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),'clients'],
        ['LOW STOCK ITEMS',$pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity<=5 AND status='active'")->fetchColumn(),'stock']
    ];
}
$recent=$pdo->query("SELECT o.id,o.order_code,o.total_amount,o.status,o.created_at,COALESCE(c.full_name,'Khách lẻ') customer FROM orders o LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 5")->fetchAll();
$low=$pdo->query("SELECT p.name,p.sku,p.stock_quantity,p.image_url FROM products p WHERE p.stock_quantity<=5 ORDER BY p.stock_quantity ASC LIMIT 4")->fetchAll();
$featured=$pdo->query("SELECT name,sku,price,image_url,stock_quantity FROM products WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch();
?>
<section class="editorial-hero">
 <div class="hero-copy"><span class="hero-kicker"><?= $isAdmin ? 'OWNER’S OFFICE / BUSINESS OVERVIEW — 2026' : 'SALES DESK / DAILY OPERATIONS — 2026' ?></span><h2><?= $isAdmin ? 'Where style<br>meets <em>precision.</em>' : 'A better day<br>of <em>service.</em>' ?></h2><p><?= $isAdmin ? 'Không gian điều hành dành cho quản trị viên WinG: hiệu suất, con người và toàn bộ hoạt động bán lẻ.' : 'Không gian làm việc dành cho đội ngũ bán hàng: đơn hàng, khách hàng và tình trạng sản phẩm.' ?></p><div class="hero-actions"><a class="hero-button" href="<?= BASE_URL ?>/index.php?page=orders">TẠO ĐƠN HÀNG <span>↗</span></a><a class="hero-text-link" href="<?= BASE_URL ?>/index.php?page=products"><?= $isAdmin ? 'QUẢN LÝ SẢN PHẨM' : 'XEM DANH MỤC SẢN PHẨM' ?></a></div></div>
 <div class="hero-visual"><img src="<?= BASE_URL ?>/assets/images/wing-hero-luxury.png" alt="WinG Fashion House" width="1672" height="941" fetchpriority="high" decoding="async"></div>
 <div class="hero-side-note">A MORE CONSIDERED WAY TO MANAGE RETAIL</div>
</section>
<section class="overview-heading"><div><span class="eyebrow">AT A GLANCE</span><h2>Business overview</h2></div><span class="overview-period">TODAY · <?= date('d M Y') ?></span></section>
<div class="stats-grid">
<?php foreach($stats as $i=>$s): ?><article class="stat-card"><div class="stat-top"><span><?= e($s[0]) ?></span><span class="stat-number">0<?= $i+1 ?></span></div><strong><?= $s[2]==='revenue'?money($s[1]):number_format((int)$s[1]) ?></strong><div class="stat-foot"><span class="stat-line"></span><small><?= $s[2]==='revenue'?'Tổng doanh thu trong ngày':($s[2]==='stock'?'Cần kiểm tra tồn kho':'Dữ liệu hiện có trong hệ thống') ?></small></div></article><?php endforeach; ?>
</div>
<div class="dashboard-grid">
 <section class="card orders-card"><div class="card-heading"><div><span class="eyebrow">RECENT ACTIVITY</span><h3>Recent orders</h3></div><a class="text-link" href="<?= BASE_URL ?>/index.php?page=orders">VIEW ALL <span>↗</span></a></div>
 <div class="table-wrap"><table><thead><tr><th>ORDER REFERENCE</th><th>CLIENT</th><th>DATE</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>
 <?php foreach($recent as $r): ?><tr><td><b><?= e($r['order_code']) ?></b></td><td><?= e($r['customer']) ?></td><td><?= date('d.m.Y',strtotime($r['created_at'])) ?></td><td><?= money($r['total_amount']) ?></td><td><span class="pill <?= e($r['status']) ?>"><?= e(order_status_label($r['status'])) ?></span></td></tr><?php endforeach; ?>
 <?php if(!$recent): ?><tr><td colspan="5" class="empty">Chưa có đơn hàng. Những giao dịch mới sẽ xuất hiện tại đây.</td></tr><?php endif; ?>
 </tbody></table></div>
 </section>
 <aside class="card stock-card"><div class="card-heading"><div><span class="eyebrow">INVENTORY WATCH</span><h3>Stock attention</h3></div><a class="text-link" href="<?= BASE_URL ?>/index.php?page=inventory">MANAGE ↗</a></div>
 <?php foreach($low as $item): ?><div class="stock-row"><div class="stock-thumb"><?php if($item['image_url']): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"><?php else: ?><span>W</span><?php endif; ?></div><div class="stock-info"><b><?= e($item['name']) ?></b><small><?= e($item['sku']) ?></small></div><span class="stock-qty"><?= (int)$item['stock_quantity'] ?> <small>LEFT</small></span></div><?php endforeach; ?>
 <?php if(!$low): ?><p class="empty">Không có sản phẩm cần chú ý.</p><?php endif; ?>
 <div class="stock-bottom">KEEP EVERY DETAIL IN CHECK <span>✳</span></div>
 </aside>
</div>
<?php if($featured): ?><section class="featured-strip"><div class="featured-copy"><span class="eyebrow">IN THE SPOTLIGHT</span><h3>Product of<br><em>the moment.</em></h3><p><?= e($featured['name']) ?> · <?= e($featured['sku']) ?></p><a href="<?= BASE_URL ?>/index.php?page=products">VIEW PRODUCT DETAILS ↗</a></div><div class="featured-photo"><?php if($featured['image_url']): ?><img src="<?= e($featured['image_url']) ?>" alt="<?= e($featured['name']) ?>"><?php endif; ?><span><?= money($featured['price']) ?></span></div><div class="featured-ornament">W</div></section><?php endif; ?>
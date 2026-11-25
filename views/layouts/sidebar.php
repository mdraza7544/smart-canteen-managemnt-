<?php
$user=current_user(); $role=$user['role']??''; $current=(string)($_GET['action']??'');
$studentLinks=[['student/dashboard','Dashboard'],['student/menu','Food menu'],['student/recommendations','For your budget'],['student/cart','Cart'],['student/orders','My orders'],['student/reservations','Reserve a table'],['student/reservation-history','Reservations'],['student/wallet','My wallet'],['student/notifications','Notifications'],['student/profile','My profile']];
$adminLinks=[['admin/dashboard','Dashboard'],['admin/users','Students'],['admin/categories','Food categories'],['admin/menu','Food menu'],['admin/inventory','Inventory'],['admin/orders','Orders'],['admin/tables','Tables and slots'],['admin/reservations','Reservations'],['admin/transactions','Wallet ledger'],['admin/payments','Demo payments'],['admin/offers','Offers'],['admin/notifications','Notifications'],['admin/reports','Reports'],['admin/settings','Settings']];
$links=$role==='admin'?$adminLinks:$studentLinks;
?>
<aside id="sidebar" class="sidebar">
    <div class="sidebar-label"><?= $role==='admin'?'Canteen operations':'Your canteen' ?></div>
    <nav>
        <?php foreach($links as $link): $href=url('index.php?action='.$link[0]); $active=$current===$link[0] || ($link[0]==='student/orders'&&$current==='student/order'); ?>
        <a class="sidebar-link <?= $active?'active':'' ?>" href="<?= e($href) ?>"><span class="sidebar-dot"></span><span class="link-label"><?= e($link[1]) ?></span></a>
        <?php endforeach; ?>
        <div class="sidebar-divider"></div>
        <a class="sidebar-link" href="<?= e(url('index.php?action=change-password')) ?>"><span class="sidebar-dot"></span><span class="link-label">Change password</span></a>
    </nav>
</aside>
<main class="app-content">

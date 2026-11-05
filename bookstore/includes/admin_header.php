<?php
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' - ' : '' ?>Ark4nnzzStore Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="admin-wrap">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="admin-logo-circle-img">
      <div>
        <div class="name">Ark4nnzzStore</div>
        <div class="sub">Admin Panel</div>
      </div>
    </div>
    <hr>
    <nav class="admin-menu">
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $active==='dashboard'?'active':'' ?>"><span class="ic">🏠</span> Dashboard</a>
      <a href="<?= BASE_URL ?>/admin/books.php" class="<?= $active==='books'?'active':'' ?>"><span class="ic">📖</span> Data Buku</a>
      <a href="<?= BASE_URL ?>/admin/categories.php" class="<?= $active==='categories'?'active':'' ?>"><span class="ic">📋</span> Kategori</a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $active==='users'?'active':'' ?>"><span class="ic">👥</span> User</a>
      <a href="<?= BASE_URL ?>/admin/orders.php" class="<?= $active==='orders'?'active':'' ?>">
        <span class="ic">🧾</span> Pesanan
        <?php
          $newOrders = (int) $pdo->query("SELECT COUNT(*) c FROM orders WHERE status = 'Baru'")->fetch()['c'];
          if ($newOrders > 0): ?><span class="menu-badge"><?= $newOrders ?></span><?php endif;
        ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/messages.php" class="<?= $active==='messages'?'active':'' ?>">
        <span class="ic">✉️</span> Pesan
        <?php
          $unreadMsg = (int) $pdo->query("SELECT COUNT(*) c FROM messages WHERE is_read = 0")->fetch()['c'];
          if ($unreadMsg > 0): ?><span class="menu-badge"><?= $unreadMsg ?></span><?php endif;
        ?>
      </a>
      <a href="<?= BASE_URL ?>/admin/reports.php" class="<?= $active==='reports'?'active':'' ?>"><span class="ic">📊</span> Laporan</a>
      <a href="<?= BASE_URL ?>/admin/memberships.php" class="<?= $active==='memberships'?'active':'' ?>">
        <span class="ic">👑</span> Membership
        <?php
          $pendingMembership = (int) $pdo->query("SELECT COUNT(*) c FROM memberships WHERE status = 'pending'")->fetch()['c'];
          if ($pendingMembership > 0): ?><span class="menu-badge"><?= $pendingMembership ?></span><?php endif;
        ?>
      </a>
    </nav>
    <hr>
    <a href="<?= BASE_URL ?>/admin/logout.php" class="admin-logout"><span class="ic">🚪</span> Logout</a>
  </aside>
  <main class="admin-main">

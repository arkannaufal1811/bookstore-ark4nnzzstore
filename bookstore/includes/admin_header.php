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
</head>
<body>
<div class="admin-wrap">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <div class="logo-circle">📚</div>
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
      <a href="<?= BASE_URL ?>/admin/orders.php" class="<?= $active==='orders'?'active':'' ?>"><span class="ic">🧾</span> Pesanan</a>
    </nav>
    <hr>
    <a href="<?= BASE_URL ?>/admin/logout.php" class="admin-logout"><span class="ic">🚪</span> Logout</a>
  </aside>
  <main class="admin-main">

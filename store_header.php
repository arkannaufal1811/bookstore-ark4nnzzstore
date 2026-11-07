<?php
// $active dipakai untuk highlight menu aktif (home/books/about/contact)
$active = $active ?? '';
$cCount = cart_count($pdo);
$wCount = wishlist_count($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' - ' : '' ?>Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="promo-strip"><b>Gratis ongkir</b> untuk belanja Rp150.000 ke atas &nbsp;|&nbsp; Bayar COD, transfer, atau QRIS</div>
<header class="store-header">
  <a href="<?= BASE_URL ?>/user/index.php" class="logo">
    <img src="<?= BASE_URL ?>/assets/images/logo-book-stack.png" alt="Logo" class="logo-img">
    ark4nnzzstore
  </a>
  <nav class="store-nav">
    <a href="<?= BASE_URL ?>/user/index.php" class="<?= $active==='home'?'active':'' ?>">Home</a>
    <a href="<?= BASE_URL ?>/user/books.php" class="<?= $active==='books'?'active':'' ?>">Books</a>
    <a href="<?= BASE_URL ?>/user/about.php" class="<?= $active==='about'?'active':'' ?>">About Us</a>
    <a href="<?= BASE_URL ?>/user/contact.php" class="<?= $active==='contact'?'active':'' ?>">Contact</a>
  </nav>
  <form class="store-search" action="<?= BASE_URL ?>/user/books.php" method="get">
    <input type="text" name="q" placeholder="Search books" value="<?= h($_GET['q'] ?? '') ?>">
    <button type="submit" aria-label="Cari">🔍</button>
  </form>
  <div class="store-icons">
    <?php if (is_logged_in()): ?>
      <a href="<?= BASE_URL ?>/user/profile.php" title="<?= h(current_user()['name']) ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/></svg></a>
      <a href="<?= BASE_URL ?>/user/logout.php" title="Logout" onclick="return confirm('Yakin mau logout?');"><svg viewBox="0 0 24 24"><path d="M12 3v9"/><path d="M6.4 6.6a8 8 0 1 0 11.2 0"/></svg></a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/user/login.php" title="Login"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/></svg></a>
    <?php endif; ?>
    <?php if (is_logged_in()): ?>
      <a href="<?= BASE_URL ?>/user/wishlist.php" title="Wishlist">
        <svg viewBox="0 0 24 24"><path d="M12 21s-8-5.2-8-11a4.6 4.6 0 0 1 8-3 4.6 4.6 0 0 1 8 3c0 5.800-8 11-8 11z"/></svg>
        <?php if ($wCount > 0): ?><span class="cart-count"><?= $wCount ?></span><?php endif; ?>
      </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/user/cart.php" title="Cart">
      <svg viewBox="0 0 24 24"><path d="M3 4h2.5l2.2 11h10.600l2-8H6.400"/><circle cx="9" cy="20" r="1.300"/><circle cx="17" cy="20" r="1.300"/></svg>
      <?php if ($cCount > 0): ?><span class="cart-count"><?= $cCount ?></span><?php endif; ?>
    </a>
  </div>
</header>

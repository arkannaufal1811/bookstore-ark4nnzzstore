<?php
// $active dipakai untuk highlight menu aktif (home/books/about/contact)
$active = $active ?? '';
$cCount = cart_count($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' - ' : '' ?>Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="store-header">
  <a href="<?= BASE_URL ?>/user/index.php" class="logo">ark4nnzzstore</a>
  <nav class="store-nav">
    <a href="<?= BASE_URL ?>/user/index.php" class="<?= $active==='home'?'active':'' ?>">Home</a>
    <a href="<?= BASE_URL ?>/user/books.php" class="<?= $active==='books'?'active':'' ?>">Books</a>
    <a href="<?= BASE_URL ?>/user/about.php" class="<?= $active==='about'?'active':'' ?>">About Us</a>
    <a href="<?= BASE_URL ?>/user/contact.php" class="<?= $active==='contact'?'active':'' ?>">Contact</a>
  </nav>
  <form class="store-search" action="<?= BASE_URL ?>/user/books.php" method="get">
    <input type="text" name="q" placeholder="Search books" value="<?= h($_GET['q'] ?? '') ?>">
    <button type="submit">🔍</button>
  </form>
  <div class="store-icons">
    <?php if (is_logged_in()): ?>
      <a href="<?= BASE_URL ?>/user/profile.php" title="<?= h(current_user()['name']) ?>">👤</a>
      <a href="<?= BASE_URL ?>/user/logout.php" title="Logout">🚪</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/user/login.php" title="Login">👤</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/user/cart.php" title="Cart">
      🛒
      <?php if ($cCount > 0): ?><span class="cart-count"><?= $cCount ?></span><?php endif; ?>
    </a>
  </div>
</header>

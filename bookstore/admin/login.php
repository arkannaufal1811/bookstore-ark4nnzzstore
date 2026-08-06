<?php
require_once __DIR__ . '/../includes/auth.php';
if (is_admin()){ header("Location: " . BASE_URL . "/admin/dashboard.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Username bisa berupa email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])){
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_name'] = $admin['name'];
        $_SESSION['user_email'] = $admin['email'];
        $_SESSION['user_role'] = $admin['role'];
        header("Location: " . BASE_URL . "/admin/dashboard.php");
        exit;
    } else {
        $error = 'Username atau password admin salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <img src="<?= BASE_URL ?>/assets/images/logo-books-text.png" alt="Books" class="brand-mark-img">
    <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="logo-circle-img">
    <h2>Ark4nnzzStore</h2>
    <p>Sistem Penjualan Buku Online<br>Kelola data buku, kategori, user dan pesanan dengan mudah</p>
    <img src="<?= BASE_URL ?>/assets/images/books-illustration.png" alt="Buku" class="books-illustration-img">
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h2>Ark4nnzzStore</h2>
      <div class="sub">Admin Login</div>
      <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="form-group">
          <label>Username</label>
          <div class="input-wrap">
            👤 <input type="text" name="username" placeholder="Enter username" required autofocus>
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            🔑 <input type="password" name="password" placeholder="Enter password" required>
          </div>
        </div>
        <div class="check-row">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">Ingatkan Saya</label>
        </div>
        <button type="submit" class="btn btn-blue">LOGIN</button>
      </form>
      <div class="auth-switch">Login sebagai user? <a href="<?= BASE_URL ?>/user/login.php">Klik di sini</a></div>
    </div>
  </div>
</div>
</body>
</html>

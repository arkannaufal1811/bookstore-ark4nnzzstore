<?php
require_once __DIR__ . '/../includes/auth.php';
if (is_logged_in()){ header("Location: " . BASE_URL . "/user/index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])){
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        if (!empty($_POST['remember'])) remember_issue($pdo, $user['id']);
        if ($user['role'] === 'admin'){
            header("Location: " . BASE_URL . "/admin/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "/user/index.php");
        }
        exit;
    } else {
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <img src="<?= BASE_URL ?>/assets/images/logo-books-text.png" alt="Books" class="brand-mark-img">
    <div class="auth-eyebrow">Selamat Datang Kembali</div>
    <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="logo-circle-img">
    <h2>Ark4nnzzStore</h2>
    <div class="auth-gold-rule"></div>
    <p>Sistem Penjualan Buku Online<br>Ribuan buku pilihan menanti kamu</p>
    <img src="<?= BASE_URL ?>/assets/images/books-illustration.png" alt="Buku" class="books-illustration-img">
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h2>Ark4nnzzStore</h2>
      <div class="sub">User Login</div>
      <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="form-group">
          <label>Email</label>
          <div class="input-wrap">👤 <input type="email" name="email" placeholder="Enter email" required autofocus></div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">🔑 <input type="password" name="password" placeholder="Enter password" required></div>
        </div>
        <div class="check-row" style="justify-content:space-between;">
          <span><input type="checkbox" id="remember" name="remember"> <label for="remember" style="display:inline;">Ingatkan Saya</label></span>
          <a href="<?= BASE_URL ?>/user/forgot_password.php" style="color:var(--primary); font-weight:700; font-size:13px;">Lupa Password?</a>
        </div>
        <button type="submit" class="btn btn-blue">LOGIN</button>
      </form>
      <div class="auth-trust">🔒 Koneksi aman &middot; data kamu terenkripsi</div>
      <div class="auth-switch">Belum punya akun? <a href="<?= BASE_URL ?>/user/register.php">Daftar di sini</a></div>
      <div class="auth-switch" style="margin-top:6px;">Login sebagai admin? <a href="<?= BASE_URL ?>/admin/login.php">Klik di sini</a></div>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/password-toggle.js"></script>
</body>
</html>

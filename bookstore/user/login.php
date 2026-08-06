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
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <img src="<?= BASE_URL ?>/assets/images/logo-books-text.png" alt="Books" class="brand-mark-img">
    <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="logo-circle-img">
    <h2>Ark4nnzzStore</h2>
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
        <div class="check-row">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">Ingatkan Saya</label>
        </div>
        <button type="submit" class="btn btn-blue">LOGIN</button>
      </form>
      <div class="auth-switch">Belum punya akun? <a href="<?= BASE_URL ?>/user/register.php">Daftar di sini</a></div>
    </div>
  </div>
</div>
</body>
</html>

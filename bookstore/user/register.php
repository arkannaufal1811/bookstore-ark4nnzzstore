<?php
require_once __DIR__ . '/../includes/auth.php';
if (is_logged_in()){ header("Location: " . BASE_URL . "/user/index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($name === '' || $email === '' || $password === ''){
        $error = 'Semua field wajib diisi.';
    } elseif ($password !== $confirm){
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 6){
        $error = 'Password minimal 6 karakter.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()){
            $error = 'Email sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$name, $email, $hash]);
            $newId = $pdo->lastInsertId();

            $_SESSION['user_id'] = $newId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'user';
            header("Location: " . BASE_URL . "/user/index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun - Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <img src="<?= BASE_URL ?>/assets/images/logo-books-text.png" alt="Books" class="brand-mark-img">
    <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="logo-circle-img">
    <h2>Ark4nnzzStore</h2>
    <p>Sistem Penjualan Buku Online<br>Bergabunglah dan temukan ribuan buku favoritmu</p>
    <img src="<?= BASE_URL ?>/assets/images/books-illustration.png" alt="Buku" class="books-illustration-img">
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h2>Ark4nnzzStore</h2>
      <div class="sub">Daftar Akun</div>
      <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="form-group">
          <label>Nama Lengkap</label>
          <div class="input-wrap">👤 <input type="text" name="name" placeholder="Masukkan nama" value="<?= h($_POST['name'] ?? '') ?>" required autofocus></div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <div class="input-wrap">✉️ <input type="email" name="email" placeholder="Masukkan email" value="<?= h($_POST['email'] ?? '') ?>" required></div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">🔑 <input type="password" name="password" placeholder="Minimal 6 karakter" required></div>
        </div>
        <div class="form-group">
          <label>Konfirmasi Password</label>
          <div class="input-wrap">🔑 <input type="password" name="confirm" placeholder="Ulangi password" required></div>
        </div>
        <button type="submit" class="btn btn-blue">DAFTAR</button>
      </form>
      <div class="auth-switch">Sudah punya akun? <a href="<?= BASE_URL ?>/user/login.php">Login di sini</a></div>
    </div>
  </div>
</div>
</body>
</html>

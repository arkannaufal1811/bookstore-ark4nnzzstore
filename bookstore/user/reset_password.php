<?php
require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = false;

if (!$token){
    header("Location: " . BASE_URL . "/user/forgot_password.php");
    exit;
}

$stmt = $pdo->prepare("SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? LIMIT 1");
$stmt->execute([$token]);
$reset = $stmt->fetch();

$tokenValid = $reset && strtotime($reset['expires_at']) > time();

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($password) < 6){
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm){
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $reset['user_id']]);
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$reset['user_id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <img src="<?= BASE_URL ?>/assets/images/logo-books-text.png" alt="Books" class="brand-mark-img">
    <div class="auth-eyebrow">Buat Password Baru</div>
    <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="logo-circle-img">
    <h2>Ark4nnzzStore</h2>
    <div class="auth-gold-rule"></div>
    <p>Pastikan password barumu kuat<br>dan mudah kamu ingat</p>
    <img src="<?= BASE_URL ?>/assets/images/books-illustration.png" alt="Buku" class="books-illustration-img">
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h2>Reset Password</h2>
      <div class="sub"><?= $reset ? h($reset['email']) : '' ?></div>

      <?php if (!$tokenValid): ?>
        <div class="alert alert-error">Link reset password sudah tidak berlaku atau salah. Silakan minta link baru.</div>
        <a href="<?= BASE_URL ?>/user/forgot_password.php" class="btn btn-blue" style="display:block; text-align:center;">Minta Link Baru</a>
      <?php elseif ($success): ?>
        <div class="alert alert-success">Password berhasil diganti! Sekarang kamu bisa login pakai password baru.</div>
        <a href="<?= BASE_URL ?>/user/login.php" class="btn btn-blue" style="display:block; text-align:center;">Login Sekarang</a>
      <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="token" value="<?= h($token) ?>">
          <div class="form-group">
            <label>Password Baru</label>
            <div class="input-wrap">🔑 <input type="password" name="password" placeholder="Minimal 6 karakter" required autofocus></div>
          </div>
          <div class="form-group">
            <label>Konfirmasi Password</label>
            <div class="input-wrap">🔑 <input type="password" name="confirm" placeholder="Ulangi password baru" required></div>
          </div>
          <button type="submit" class="btn btn-blue">SIMPAN PASSWORD BARU</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/password-toggle.js"></script>
</body>
</html>

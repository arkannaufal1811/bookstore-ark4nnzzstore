<?php
require_once __DIR__ . '/../includes/auth.php';
if (is_logged_in()){ header("Location: " . BASE_URL . "/user/index.php"); exit; }

$error = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user){
        $error = 'Email tidak ditemukan.';
    } else {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires]);
        $resetLink = BASE_URL . '/user/reset_password.php?token=' . $token;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lupa Password - Ark4nnzzStore</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <img src="<?= BASE_URL ?>/assets/images/logo-books-text.png" alt="Books" class="brand-mark-img">
    <div class="auth-eyebrow">Pemulihan Akun</div>
    <img src="<?= BASE_URL ?>/assets/images/logo-circle.png" alt="Logo" class="logo-circle-img">
    <h2>Ark4nnzzStore</h2>
    <div class="auth-gold-rule"></div>
    <p>Lupa password bukan masalah,<br>kita bantu kamu masuk lagi</p>
    <img src="<?= BASE_URL ?>/assets/images/books-illustration.png" alt="Buku" class="books-illustration-img">
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h2>Lupa Password</h2>
      <div class="sub">Masukkan email akun kamu</div>
      <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

      <?php if ($resetLink): ?>
        <div class="alert alert-success">Link reset password berhasil dibuat!</div>
        <div style="background:#fff; border:1.5px dashed var(--primary); border-radius:8px; padding:14px; margin-bottom:16px; word-break:break-all; font-size:13px;">
          <a href="<?= h($resetLink) ?>" style="color:var(--primary); font-weight:700;"><?= h($resetLink) ?></a>
        </div>
        <p style="font-size:12.5px; color:var(--gray); margin-bottom:16px;">
          📌 Catatan: karena ini server lokal (belum ada layanan email aktif), link reset ditampilkan langsung di sini. Klik link di atas untuk lanjut ganti password. Link berlaku 1 jam.
        </p>
      <?php else: ?>
        <form method="post">
          <div class="form-group">
            <label>Email</label>
            <div class="input-wrap">✉️ <input type="email" name="email" placeholder="Masukkan email akun kamu" required autofocus></div>
          </div>
          <button type="submit" class="btn btn-blue">KIRIM LINK RESET</button>
        </form>
      <?php endif; ?>
      <div class="auth-switch">Ingat password? <a href="<?= BASE_URL ?>/user/login.php">Login di sini</a></div>
    </div>
  </div>
</div>
</body>
</html>

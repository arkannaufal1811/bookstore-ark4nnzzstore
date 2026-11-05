<?php
require_once __DIR__ . '/../includes/auth.php';

$success = false;
$error = '';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === ''){
        $error = 'Semua field wajib diisi.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, name, email, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'] ?? null, $name, $email, $message]);
        $success = true;
    }
}

$pageTitle = 'Contact';
$active = 'contact';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="contact-wrap">
  <h1>Hubungi Kami</h1>
  <p>Ada pertanyaan atau butuh bantuan? Kirim pesan ke admin kami di bawah ini.</p>

  <?php if ($success): ?>
    <div class="alert alert-success">Pesan kamu berhasil terkirim ke admin! Terima kasih.</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" class="admin-form" style="max-width:100%;">
    <div class="form-group">
      <label>Nama</label>
      <input type="text" name="name" value="<?= h($user['name'] ?? ($_POST['name'] ?? '')) ?>" required>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= h($user['email'] ?? ($_POST['email'] ?? '')) ?>" required>
    </div>
    <div class="form-group">
      <label>Pesan</label>
      <textarea name="message" rows="5" required><?= h($_POST['message'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Kirim Pesan</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>

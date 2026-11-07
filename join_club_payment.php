<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$plans = membership_plans();
$planKey = $_GET['plan'] ?? $_POST['plan'] ?? '';

if (!isset($plans[$planKey])){
    header("Location: " . BASE_URL . "/user/join_club.php");
    exit;
}
$plan = $plans[$planKey];

// Kalau udah punya membership aktif atau pending, gak usah bayar lagi
if (active_membership($pdo) || pending_membership($pdo)){
    header("Location: " . BASE_URL . "/user/join_club.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (empty($_FILES['payment_proof']['name'])){
        $error = 'Bukti transfer wajib diupload.';
    } else {
        $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowedExt)){
            $error = 'Format bukti transfer harus JPG, PNG, atau WEBP.';
        } else {
            $proofName = 'member_' . uniqid() . '.' . $ext;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
            move_uploaded_file($_FILES['payment_proof']['tmp_name'], UPLOAD_DIR . $proofName);
            upload_backup_save($pdo, $proofName);

            $stmt = $pdo->prepare("INSERT INTO memberships (user_id, plan, price, payment_proof, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $planKey, $plan['price'], $proofName]);

            header("Location: " . BASE_URL . "/user/join_club.php?submitted=1");
            exit;
        }
    }
}

$pageTitle = 'Pembayaran Membership';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page" style="max-width:520px;">
  <h1 style="font-size:24px; font-weight:800; margin-bottom:6px;">Pembayaran Paket <?= h($plan['label']) ?></h1>
  <p style="color:var(--gray); margin-bottom:20px;">Total yang harus ditransfer: <strong style="color:var(--primary);"><?= rupiah($plan['price']) ?></strong></p>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <div class="transfer-rekening" style="margin-bottom:20px;">
    🏦 <strong>Bank BSI</strong> &middot; 7370615769 &middot; a.n. Arkan Naufal
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="plan" value="<?= h($planKey) ?>">
    <label style="font-weight:700; font-size:13px; display:block; margin-bottom:6px;">Upload Bukti Transfer</label>
    <input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required>
    <p style="font-size:12px; color:var(--gray); margin:8px 0 20px;">Format JPG/PNG/WEBP. Membership diaktifkan setelah admin verifikasi bukti transfer (biasanya 1x24 jam).</p>
    <button type="submit" class="btn btn-primary" style="width:100%;">Kirim Bukti Pembayaran</button>
  </form>
  <a href="<?= BASE_URL ?>/user/join_club.php" style="display:block; text-align:center; margin-top:16px; color:var(--gray); font-size:14px;">← Pilih paket lain</a>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>

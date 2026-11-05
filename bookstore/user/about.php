<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'About Us';
$active = 'about';
require __DIR__ . '/../includes/store_header.php';
?>
<section class="about-hero">
  <div class="about-text">
    <div class="eyebrow">Tentang Kami</div>
    <h1>Tentang <span>Ark4nnzzstore</span></h1>
    <p>Ark4nnzzstore adalah toko buku online yang berdedikasi untuk menyediakan buku-buku berkualitas dengan harga terbaik. Kami percaya bahwa buku adalah jendela dunia yang dapat mengubah hidup dan membuka peluang baru.</p>
    <div class="about-stats">
      <div><div class="num">📅 10K+</div><div class="lbl">Buku Tersedia</div></div>
      <div><div class="num">✋ 5K+</div><div class="lbl">Pelanggan Puas</div></div>
      <div><div class="num">📗 100%</div><div class="lbl">Buku Original</div></div>
    </div>
  </div>
  <div class="about-img">
    <img src="https://images.unsplash.com/photo-1526243741027-444d633d7365?w=800&q=80" alt="Rak Buku">
  </div>
</section>

<section class="vision-mission">
  <div>
    <h3>Visi Kami</h3>
    <p>Menjadi toko buku online terpercaya yang menyediakan akses mudah ke berbagai buku berkualitas untuk semua orang di Indonesia.</p>
  </div>
  <div>
    <h3>Misi Kami</h3>
    <ul>
      <li>✅ Menyediakan buku berkualitas dengan harga terjangkau</li>
      <li>✅ Memberikan pelayanan terbaik untuk pelanggan</li>
      <li>✅ Mendukung budaya membaca di Indonesia</li>
    </ul>
  </div>
</section>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>

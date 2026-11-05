// =========================================================
// Ark4nnzzStore - main.js
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
  // Konfirmasi sebelum hapus data (fallback jika onclick inline tidak dipakai)
  document.querySelectorAll('.confirm-delete').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm('Yakin ingin menghapus data ini?')) {
        e.preventDefault();
      }
    });
  });
});

<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: " . BASE_URL . "/user/books.php");
    exit;
}

$userId = $_SESSION['user_id'];
$bookId = (int) $_POST['book_id'];
$qty = max(1, (int) ($_POST['qty'] ?? 1));

// Pastikan bukunya beneran ada & stoknya cukup sebelum lanjut ke checkout
$stmt = $pdo->prepare("SELECT stock FROM books WHERE id = ?");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book || $book['stock'] < 1){
    header("Location: " . BASE_URL . "/user/books.php?soldout=1");
    exit;
}
$qty = min($qty, $book['stock']);

// "Beli Langsung" = kosongin keranjang, isi cuma buku ini, terus lempar ke checkout
$pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("INSERT INTO cart_items (user_id, book_id, qty) VALUES (?, ?, ?)")->execute([$userId, $bookId, $qty]);

header("Location: " . BASE_URL . "/user/checkout.php");
exit;

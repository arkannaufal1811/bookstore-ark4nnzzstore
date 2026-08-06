<?php
session_start();
require_once __DIR__ . '/../config/db.php';

function is_logged_in(){
    return isset($_SESSION['user_id']);
}

function current_user(){
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

function is_admin(){
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

function require_login($redirect = '/user/login.php'){
    if (!is_logged_in()){
        header("Location: " . BASE_URL . $redirect);
        exit;
    }
}

function require_admin(){
    if (!is_logged_in() || $_SESSION['user_role'] !== 'admin'){
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

function rupiah($num){
    return "Rp " . number_format($num, 0, ',', '.');
}

function cart_count($pdo){
    if (!is_logged_in()) return 0;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) AS c FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetch()['c'];
}

function h($str){
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

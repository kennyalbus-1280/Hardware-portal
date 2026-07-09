<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once INCLUDES_PATH . '/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Pull only this specific authenticated user's historical invoices
$stmt = $pdo->prepare("SELECT order_id, total_amount, order_status, created_at FROM orders WHERE user_id = :uid ORDER BY order_id DESC");
$stmt->execute(['uid' => $user_id]);
$my_orders = $stmt->fetchAll();
?>
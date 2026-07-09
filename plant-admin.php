<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';

$username = 'admin_demo';
$email = 'admin@hardwarestore.com';
$password = 'AdminSecure2026!';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';
$is_active = 1;
//Tempae0c1a! kennyalbus@gmail.com--admin
//Temp50e376! akeny@gmail.com--client
//Temp1bc1ef! 

try {
    $pdo = "$username, $email, $password, $hashed_password, $role, $is_active";
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (:username, :email, :password, :role, :is_active)");
    $stmt->execute([
        'username'      => $username,
        'email'         => $email,
        'password'      => $hashed_password,
        'role'          => $role,
        'is_active'     => $is_active
    ]);
    echo "Success! Dummy admin account planted successfully.";
} catch (\PDOException $e) {
    echo "Insertion failed: " . htmlspecialchars($e->getMessage());
}
?>
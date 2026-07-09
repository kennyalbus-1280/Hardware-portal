<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check 1: Is the user even logged into an account?
if (!isset($_SESSION['user_id'])) {
    header('Location: /ecommerce/auth/login.php');
    exit;
}

// Check 2: Does the user have explicit 'admin' clearance?
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Kick unprivileged customers back to the store front
    header('Location: /ecommerce/index.php');
    exit;
}
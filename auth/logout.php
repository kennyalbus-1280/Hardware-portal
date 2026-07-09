<?php
// Secure global path mapping regardless of folder depth
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';

// Import PHPMailer classes into the global scope
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Points directly to your composer autoloader at the root level
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear out session states completely
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Absolute URL routing back out to the main landing view
header("Location: /ecommerce/index.php");
exit;
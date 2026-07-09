<?php
// Define the global base URL for your local server path
define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', 'http://localhost/ecommerce/');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('UPLOADS_PATH', APP_ROOT . '/uploads');
define('UPLOADS_URL', BASE_URL . 'uploads/');
define('INCLUDES_PATH', APP_ROOT . '/includes');
$host = 'localhost';
$db   = 'ecommerce_db';
$user = 'root'; // Change to your MySQL username
$pass = '';     // Change to your MySQL password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>

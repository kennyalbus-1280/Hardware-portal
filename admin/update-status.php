<?php
// Load global path configurations and enforce admin protection
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/admin/admin-guard.php';

// Check if data was transmitted via a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $new_status = trim($_POST['status']);
    
    // Whitelist check to prevent invalid status injection into our ENUM column
    $allowed_statuses = ['Pending', 'Shipped', 'Delivered'];
    
    if (in_array($new_status, $allowed_statuses) && $order_id > 0) {
        try {
            // Run an atomic UPDATE query on the target row
            $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE order_id = :id");
            $stmt->execute([
                'status' => $new_status,
                'id'     => $order_id
            ]);
            
            // Redirect back to dashboard with a success flag
            header('Location: ' . BASE_URL . 'admin/dashboard.php?success=1');
            exit;
        } catch (\PDOException $e) {
            die("Fulfillment compilation data transmission failure.");
        }
    }
}

// If anything malicious or incorrect happens, push back cleanly
header('Location: ' . BASE_URL . 'admin/dashboard.php');
exit;
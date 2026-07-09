<?php
// Initialize database context configurations and check authenticated session states
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── CRITICAL SECURITY GUARD ─────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    // Redirect unauthorized guests straight to the login portal route
    header("Location: /ecommerce/auth/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ─── INTERCEPT ACTION INBOUND CONTROLLER ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($product_id <= 0) {
        header("Location: /ecommerce/index.php");
        exit;
    }

    switch ($action) {
        
        // ─── ACTION 1: ADD ITEM TO RELATION CART MATRIX ───
        case 'add_to_cart':
            try {
                // Check if the item is already sitting in this user's cart rows
                $check_stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = :uid AND product_id = :pid");
                $check_stmt->execute(['uid' => $user_id, 'pid' => $product_id]);
                $existing_qty = $check_stmt->fetchColumn();

                // Fetch warehouse baseline metrics to prevent over-allocation bounds
                $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = :pid");
                $stock_stmt->execute(['pid' => $product_id]);
                $max_stock = (int)$stock_stmt->fetchColumn();

                if ($existing_qty !== false) {
                    // Item exists! Increment its quantity value safely if within stock limits
                    $new_qty = (int)$existing_qty + 1;
                    if ($new_qty <= $max_stock) {
                        $update_stmt = $pdo->prepare("UPDATE cart SET quantity = :qty WHERE user_id = :uid AND product_id = :pid");
                        $update_stmt->execute(['qty' => $new_qty, 'uid' => $user_id, 'pid' => $product_id]);
                    }
                } else {
                    // New item entry! Initialize product listing row inside database if stock allows
                    if ($max_stock >= 1) {
                        $insert_stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (:uid, :pid, 1)");
                        $insert_stmt->execute(['uid' => $user_id, 'pid' => $product_id]);
                    }
                }
            } catch (\PDOException $e) {
                // Fail silently or handle error diagnostics in server logs
            }
            // Bounce user directly out back to the shopping layout view smoothly
            header("Location: /ecommerce/cart.php");
            exit;

        // ─── ACTION 2: DYNAMICALLY UPDATE QUANTITY ───
        case 'update_qty':
            $requested_qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if ($requested_qty < 1) { $requested_qty = 1; }

            try {
                // Verify threshold parameters from master product catalog rows
                $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = :pid");
                $stock_stmt->execute(['pid' => $product_id]);
                $max_stock = (int)$stock_stmt->fetchColumn();

                // Constrain values to max stock ceilings
                if ($requested_qty > $max_stock) {
                    $requested_qty = $max_stock;
                }

                $qty_stmt = $pdo->prepare("UPDATE cart SET quantity = :qty WHERE user_id = :uid AND product_id = :pid");
                $qty_stmt->execute(['qty' => $requested_qty, 'uid' => $user_id, 'pid' => $product_id]);
            } catch (\PDOException $e) {
                // Query execution safety catch
            }
            header("Location: /ecommerce/cart.php");
            exit;

        // ─── ACTION 3: DELETE RECOPIED ITEM LINE COMPLETELY ───
        case 'delete':
            try {
                $del_stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :uid AND product_id = :pid");
                $del_stmt->execute(['uid' => $user_id, 'pid' => $product_id]);
            } catch (\PDOException $e) {
                // Query isolation safety catch
            }
            header("Location: /ecommerce/cart.php");
            exit;

        default:
            header("Location: /ecommerce/index.php");
            exit;
    }
} else {
    // Redirect manual direct URL address hits safely back to catalog index
    header("Location: /ecommerce/index.php");
    exit;
}
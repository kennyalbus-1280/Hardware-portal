<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce-site/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Guard: Kick unauthenticated guests over to the login form
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Logic Guard: If the cart is empty, there is nothing to check out
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$cart_items = [];
$grand_total = 0.00;

try {
    // Gather information for items currently in the cart to display a final summary
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->execute(array_keys($_SESSION['cart']));
    $cart_items = $stmt->fetchAll();
    
    // Calculate total costs
    foreach ($cart_items as $item) {
        $qty = $_SESSION['cart'][$item['product_id']];
        $grand_total += $item['price'] * $qty;
    }
} catch (\PDOException $e) {
    $error_message = "System error gathering checkout catalog components.";
}

// Intercept placement form confirmation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    try {
        // 1. Start the ACID Transaction block
        $pdo->beginTransaction();
        
        // 2. Insert the top-level row into the 'orders' table
        $order_stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (:user_id, :total, 'Pending')");
        $order_stmt->execute([
            'user_id' => $user_id,
            'total'   => $grand_total
        ]);
        
        // Capture the auto-incremented Order ID MySQL just generated
        $order_id = $pdo->lastInsertId();
        
        // Prepare statements outside the loop for high execution performance
        $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (:order_id, :prod_id, :qty, :price)");
        $stock_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE product_id = :prod_id AND stock_quantity >= :qty");
        
        // 3. Loop through every cart line item to write detail lines and adjust inventory counts
        foreach ($cart_items as $item) {
            $prod_id = $item['product_id'];
            $qty = $_SESSION['cart'][$prod_id];
            $price = $item['price'];
            
            // Execute inventory stock reduction first
            $stock_stmt->execute(['qty' => $qty, 'prod_id' => $prod_id]);
            
            // Critical Concurrency Safety Check: If the stock update modified 0 rows, 
            // it means someone else bought the item in the last few seconds and stock fell below requested quantity.
            if ($stock_stmt->rowCount() === 0) {
                throw new Exception("Inadequate stock allocation available for: " . $item['name']);
            }
            
            // Insert line record linking back to parent order
            $item_stmt->execute([
                'order_id' => $order_id,
                'prod_id'  => $prod_id,
                'qty'      => $qty,
                'price'    => $price
            ]);
        }
        
        // 4. Everything processed cleanly without issue, permanently commit changes to disk
        $pdo->commit();
        
        // Wipe clean the user's cart session array now that the order is secure
        $_SESSION['cart'] = [];
        $success_message = "Order successfully processed! Your confirmation reference is: <strong>#ORD-" . $order_id . "</strong>.";
        
    } catch (Exception $e) {
        // Safe Failure: Cancel all data queries inside this attempt block instantly
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Transaction Cancelled: " . $e->getMessage();
    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';
?>

<style>
    .checkout-layout { display: flex; gap: 30px; flex-wrap: wrap; margin-top: 20px; }
    .checkout-form-panel { flex: 1.5; min-width: 300px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; }
    .checkout-summary-panel { flex: 1; min-width: 300px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; height: fit-content; }
    
    .panel-title { font-size: 20px; color: #2c3e50; margin-bottom: 20px; font-weight: bold; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
    .summary-item { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 15px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 8px; }
    .summary-total-row { display: flex; justify-content: space-between; font-size: 22px; font-weight: bold; color: #2e7d32; margin-top: 15px; }
    
    .btn-place-order { background-color: #2ecc71; color: white; width: 100%; border: none; font-size: 18px; font-weight: bold; padding: 14px; border-radius: 4px; cursor: pointer; transition: background 0.2s; margin-top: 20px; }
    .btn-place-order:hover { background-color: #27ae60; }
    
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 25px; line-height: 1.5; }
    .alert-error { background-color: #ffeeeb; color: #c62828; border: 1px solid #ffd1c9; }
    .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; text-align: center; }
    .info-note { background: #f8fafc; padding: 15px; border-radius: 6px; border-left: 4px solid #3498db; margin-top: 15px; font-size: 14px; color: #475569; }
</style>

<h2>Secure Order Checkout</h2>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <h3>Thank You for Your Purchase!</h3>
        <p><?php echo $success_message; ?></p>
        <p style="margin-top: 15px;"><a href="index.php" style="color: #2e7d32; font-weight: bold;">Continue Browsing Equipment</a></p>
    </div>
<?php else: ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="checkout-layout">
        <div class="checkout-form-panel">
            <h3 class="panel-title">Shipping & Order Fulfillment</h3>
            <p><strong>Customer Account:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['email']); ?>)</p>
            
            <div class="info-note">
                <strong>Payment Method Notice:</strong><br>
                As configured for Version 1.0 specifications, this checkout uses cash-on-delivery simulation mode. No credit card information is required to submit this request.
            </div>
            
            <form action="checkout.php" method="POST">
                <button type="submit" class="btn-place-order">Confirm & Place Order</button>
            </form>
        </div>
        
        <div class="checkout-summary-panel">
            <h3 class="panel-title">Review Order Summary</h3>
            
            <?php foreach ($cart_items as $item): 
                $qty = $_SESSION['cart'][$item['product_id']];
                $subtotal = $item['price'] * $qty;
            ?>
                <div class="summary-item">
                    <div>
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        <span style="display:block; font-size:12px; color:#64748b;">Qty: <?php echo $qty; ?> @ $<?php echo number_format($item['price'], 2); ?></span>
                    </div>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
            <?php endforeach; ?>
            
            <div class="summary-total-row">
                <span>Grand Total:</span>
                <span>$<?php echo number_format($grand_total, 2); ?></span>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php';
?>

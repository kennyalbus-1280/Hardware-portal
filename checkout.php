<?php
require_once __DIR__ . '/app/bootstrap.php';

// Enforce login boundaries before entering the checkout pipeline
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error_message = '';
$success_message = '';

// 1. DYNAMICALLY READ CURRENT CARTITEMS FOR THIS SESSION
// (Assumes a 'cart' table holds your temporary user selections)
try {
    $cart_stmt = $pdo->prepare("
        SELECT c.quantity, p.product_id, p.name, p.price, p.stock_quantity 
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = :uid
    ");
    $cart_stmt->execute(['uid' => $user_id]);
    $cart_items = $cart_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Cart pipeline synchronization failure.");
}

// Redirect back to shopping catalog if there is nothing to authorize
if (empty($cart_items) && !isset($_POST['process_checkout'])) {
    header("Location: /ecommerce/index.php");
    exit;
}

// Calculate total cart price matching UGX absolute limits
$grand_total = 0;
foreach ($cart_items as $item) {
    $grand_total += (float)$item['price'] * (int)$item['quantity'];
}


// 2. INTERCEPT SECURE CHECKOUT SUBMISSION POST PAYLOADS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_checkout'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $phone_number     = trim($_POST['phone_number']);

    if (empty($shipping_address) || empty($phone_number)) {
        $error_message = "Please provide your delivery location and a valid phone contact number.";
    } else {
        try {
            // OPEN AN ATOMIC TRANSACTION BOUNDARY
            // If any single insertion line fails, the database rolls back to keep financials perfectly clean.
            $pdo->beginTransaction();

            // STEP A: Insert Master Record Into Orders Log
            $order_stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_amount, shipping_address, phone_number, order_status, created_at)
                VALUES (:uid, :total, :address, :phone, 'Pending', NOW())
            ");
            $order_stmt->execute([
                'uid'     => $user_id,
                'total'   => $grand_total,
                'address' => $shipping_address,
                'phone'   => $phone_number
            ]);
            
            // Capture the generated auto-increment order ID to link our child items
            $new_order_id = $pdo->lastInsertId();

            // STEP B: Unpack Cart Lines Into Order Items & Deduct Inventory Stock
            $item_insert_stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price)
                VALUES (:oid, :pid, :qty, :price)
            ");
            
            $stock_update_stmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - :qty 
                WHERE product_id = :pid AND stock_quantity >= :qty
            ");

            foreach ($cart_items as $item) {
                // Safeguard check: Verify inventory hasn't changed mid-session
                if ((int)$item['stock_quantity'] < (int)$item['quantity']) {
                    throw new Exception("Stock depletion conflict: Item '{$item['name']}' has insufficient warehouse capacity.");
                }

                // Write lines into item sub-manifest log
                $item_insert_stmt->execute([
                    'oid'   => $new_order_id,
                    'pid'   => $item['product_id'],
                    'qty'   => $item['quantity'],
                    'price' => $item['price'] // Locked price snapshot code
                ]);

                // Deduct active warehouse unit levels
                $stock_update_stmt->execute([
                    'qty' => $item['quantity'],
                    'pid' => $item['product_id']
                ]);
                
                if ($stock_update_stmt->rowCount() === 0) {
                    throw new Exception("Concurrency race condition error encountered during stock allocation protection loops.");
                }
            }

            // STEP C: Clear Temporary Client Cart Records Upon Payment Success
            $clear_cart_stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :uid");
            $clear_cart_stmt->execute(['uid' => $user_id]);

            // COMMIT EVERYTHING ALL AT ONCE TO STORAGE DRIVES
            $pdo->commit();
            
            // Retain order confirmation token reference to print out inside user landing views
            $_SESSION['last_order_ref'] = $new_order_id;
            header("Location: /ecommerce/checkout-success.php");
            exit;

        } catch (\Exception $e) {
            // An anomaly happened; cancel changes instantly to protect system integrity
            $pdo->rollBack();
            $error_message = "Checkout Pipeline Aborted: " . $e->getMessage();
        }
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<style>
    *{
        font-family: var(--system-font);
    }
    .checkout-wrapper { max-width: 1000px; margin: 40px auto; padding: 0 20px; font-family: var(--system-font); display: flex; gap: 30px; align-items: flex-start; }
    .checkout-form-panel { flex: 1.4; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .checkout-summary-panel { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; }
    
    .checkout-title { font-size: 24px; color: #0f172a; font-weight: 800; margin: 0 0 5px 0; }
    .section-subtitle { font-size: 14px; color: #64748b; margin-bottom: 25px; }
    .summary-title { font-size: 18px; color: #0f172a; font-weight: 700; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background-color: #fff; box-sizing: border-box; }
    .form-control:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    
    .summary-row { display: flex; justify-content: space-between; font-size: 14px; color: #475569; padding: 8px 0; }
    .total-row { display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: #0f172a; padding: 15px 0 0 0; border-top: 2px dashed #cbd5e1; margin-top: 10px; }
    
    .btn-checkout-confirm { background-color: #2563eb; color: white; border: none; width: 100%; padding: 12px; font-weight: 700; font-size: 15px; border-radius: 6px; cursor: pointer; transition: background 0.2s; margin-top: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
    .btn-checkout-confirm:hover { background-color: #1d4ed8; }
    
    .alert-banner { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; line-height: 1.4; }
    .alert-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
</style>

<div class="checkout-wrapper">
    <div class="checkout-form-panel">
        <h2 class="checkout-title">Secure Delivery Checkout</h2>
        <p class="section-subtitle">Provide details below to route your computing hardware assets.</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert-banner alert-danger">⚠️ <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <div class="form-group">
                <label for="shipping_address">Detailed Delivery Address / Directions</label>
                <textarea id="shipping_address" name="shipping_address" class="form-control" rows="4" required placeholder="e.g., Plot 45, Kampala Road, Opp. Bank of Uganda or Lira City Corporate Office Block, Room B3..."></textarea>
            </div>

            <div class="form-group">
                <label for="phone_number">Primary Contact Phone Line (For Courier Calls)</label>
                <input type="text" id="phone_number" name="phone_number" class="form-control" required placeholder="e.g., +256 7xx xxxxxx">
            </div>

            <button type="submit" name="process_checkout" class="btn-checkout-confirm">Authorize Invoice Order</button>
        </form>
    </div>

    <div class="checkout-summary-panel">
        <div class="summary-title">Order Breakdown</div>
        
        <div style="max-height: 240px; overflow-y: auto; margin-bottom: 15px; padding-right: 5px;">
            <?php foreach ($cart_items as $item): ?>
                <div class="summary-row" style="border-bottom: 1px solid #e2e8f0; padding: 10px 0;">
                    <div>
                        <span style="font-weight: 600; color: #0f172a; display: block;"><?php echo htmlspecialchars($item['name']); ?></span>
                        <span style="font-size: 12px; color: #64748b;">Qty: <?php echo $item['quantity']; ?> × <?php echo number_format($item['price'], 0); ?> UGX</span>
                    </div>
                    <div style="font-weight: 600; color: #334155; align-self: center;">
                        <?php echo number_format($item['price'] * $item['quantity'], 0); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="summary-row">
            <span>Subtotal Items</span>
            <span><?php echo number_format($grand_total, 0); ?> UGX</span>
        </div>
        <div class="summary-row">
            <span>Logistics Dispatch Fee</span>
            <span style="color: #10b981; font-weight: 600;">FREE</span>
        </div>
        
        <div class="total-row">
            <span>Amount Due:</span>
            <span><?php echo number_format($grand_total, 0); ?> UGX</span>
        </div>
    </div>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>
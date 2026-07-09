<?php
require_once __DIR__ . '/app/bootstrap.php';

// ─── MISSING COMPONENT 1: AUTHENTICATION ENFORCEMENT GUARD ─────────────────
if (!isset($_SESSION['user_id'])) {
    echo "<div style='max-width:600px; margin:80px auto; text-align:center; padding:40px; border:1px solid #e2e8f0; border-radius:8px; font-family:system-ui;'>
            <h3 style='color:#1e293b;'>Authentication Required</h3>
            <p style='color:#64748b;'>Please sign in to access your persistent hardware allocation cart.</p>
            <a href='/ecommerce/auth/login.php' style='display:inline-block; margin-top:15px; padding:10px 20px; background:#2563eb; color:white; border-radius:6px; text-decoration:none; font-weight:bold;'>Login Portal</a>
          </div>";
    require_once INCLUDES_PATH . '/footer.php';
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ==========================================================================
    PART 1: INTERCEPT ACTION REQUESTS (DATABASE CONTROLLER LOGIC)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    if ($product_id > 0) {
        
        // ACTION: UPDATE QUANTITY SELECTION IN MARIADB ROWS
        if ($action === 'update') {
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            
            if ($quantity <= 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :uid AND product_id = :pid");
                $stmt->execute(['uid' => $user_id, 'pid' => $product_id]);
            } else {
                // Verify max warehouse parameters before writing modifications
                $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = :pid");
                $stock_stmt->execute(['pid' => $product_id]);
                $max_stock = (int)$stock_stmt->fetchColumn();
                
                if ($quantity > $max_stock) {
                    $quantity = $max_stock;
                }

                $stmt = $pdo->prepare("UPDATE cart SET quantity = :qty WHERE user_id = :uid AND product_id = :pid");
                $stmt->execute(['qty' => $quantity, 'uid' => $user_id, 'pid' => $product_id]);
            }
        }
        
        // ACTION: DROP SINGLE MANIFEST LINE ITEM FROM DATABASE
        if ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :uid AND product_id = :pid");
            $stmt->execute(['uid' => $user_id, 'pid' => $product_id]);
        }
    }
    
    // Absolute root routing to clear memory pipelines safely
    header('Location: /ecommerce/cart.php');
    exit;
}

/* ==========================================================================
    PART 2: DISPLAY CONTENT LAYER (PERSISTENT LOGS SELECTION VIEW)
   ========================================================================== */
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';

$cart_items = [];
$grand_total = 0;
$total_items_count = 0;

try {
    // MISSING COMPONENT 2: INTER-TABLE RELATION JOIN STRINGS
    $stmt = $pdo->prepare("
        SELECT c.product_id, c.quantity, p.name, p.price, p.category, p.stock_quantity, p.image 
        FROM cart c
        INNER JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = :uid
    ");
    $stmt->execute(['uid' => $user_id]);
    $cart_items = $stmt->fetchAll();
} catch (\PDOException $e) {
    echo "<div style='padding:20px; font-family:system-ui;' class='alert error'>Error communicating with database storage.</div>";
}
?>

<style>
    .cart-wrapper { max-width: 1200px; margin: 40px auto; padding: 0 20px; font-family: system-ui, -apple-system, sans-serif; }
    h2.cart-title { font-size: 26px; color: #0f172a; margin-bottom: 5px; font-weight: 800; }
    .cart-subtitle { color: #64748b; margin: 0 0 25px 0; font-size: 14px; }
    
    .cart-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 30px; }
    .cart-table th { background-color: #f8fafc; text-align: left; padding: 15px 20px; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
    .cart-table td { padding: 20px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    
    .item-name { font-weight: 700; color: #0f172a; font-size: 15px; display: block; margin-top: 2px; }
    .item-cat { font-size: 11px; text-transform: uppercase; color: #2563eb; font-weight: bold; display: block; letter-spacing: 0.05em; }
    
    .update-form { display: flex; align-items: center; gap: 8px; margin: 0; }
    .cart-qty-input { width: 65px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-size: 14px; background: #f8fafc; }
    
    .btn-update { background-color: #475569; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; }
    .btn-update:hover { background-color: #334155; }
    .btn-remove { color: #ef4444; background: none; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; padding: 0; }
    .btn-remove:hover { color: #b91c1c; text-decoration: underline; }
    
    .cart-summary-wrapper { display: flex; justify-content: flex-end; }
    .summary-box { background: #f8fafc; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; width: 100%; max-width: 380px; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 14px; color: #475569; }
    .summary-total { font-size: 20px; font-weight: 800; color: #0f172a; border-top: 2px solid #e2e8f0; padding-top: 15px; margin-bottom: 20px; }
    
    .btn-checkout { background-color: #10b981; color: white; text-decoration: none; display: block; text-align: center; padding: 12px; border-radius: 6px; font-weight: bold; font-size: 15px; transition: background 0.2s; }
    .btn-checkout:hover { background-color: #059669; }
    
    .empty-cart-msg { text-align: center; padding: 60px 20px; background: white; border: 2px dashed #cbd5e1; border-radius: 8px; }
    .empty-cart-msg p { color: #64748b; margin-bottom: 15px; font-size: 15px; }
    .btn-shop { display: inline-block; background-color: #0f172a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; }
</style>

<div class="cart-wrapper">
    <h2 class="cart-title">Your Shopping Cart</h2>
    <p class="cart-subtitle">Review your allocated equipment and proceed to checkout.</p>

    <?php if (empty($cart_items)): ?>
        <div class="empty-cart-msg">
            <p>Your procurement cart is empty right now.</p>
            <a href="/ecommerce/index.php" class="btn-shop">Browse Products Matrix</a>
        </div>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Product Description</th>
                    <th>Unit Cost</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): 
                    $id = $item['product_id'];
                    $qty = (int)$item['quantity'];
                    $subtotal = (int)$item['price'] * $qty;
                    
                    $grand_total += $subtotal;
                    $total_items_count += $qty;
                ?>
                    <tr>
                        <td>
                            <span class="item-cat"><?php echo htmlspecialchars($item['category']); ?></span>
                            <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                        </td>
                        <td style="color: #334155; font-weight: 500;"><?php echo number_format($item['price'], 0); ?> UGX</td>
                        <td>
                            <form action="/ecommerce/cart.php" method="POST" class="update-form">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <input type="number" name="quantity" value="<?php echo $qty; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>" class="cart-qty-input">
                                <button type="submit" class="btn-update">Update</button>
                            </form>
                        </td>
                        <td style="color: #0f172a; font-weight: 700;"><?php echo number_format($subtotal, 0); ?> UGX</td>
                        <td>
                            <form action="/ecommerce/cart.php" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                <button type="submit" class="btn-remove">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary-wrapper">
            <div class="summary-box">
                <div class="summary-row">
                    <span>Items Allocated:</span>
                    <span style="font-weight: 600; color: #0f172a;"><?php echo $total_items_count; ?> Units</span>
                </div>
                <div class="summary-row">
                    <span>Logistics Handling:</span>
                    <span style="color:#16a34a; font-weight:700;">FREE SHIPPING</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Grand Total:</span>
                    <span><?php echo number_format($grand_total, 0); ?> UGX</span>
                </div>
                
                <a href="ecommerce/checkout.php" class="btn-checkout">Proceed to Checkout Gate</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php';
?>
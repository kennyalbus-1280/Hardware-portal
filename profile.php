<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';

// Security Check: Redirect non-logged-in visitors to the login prompt
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$orders = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Fetch all orders matching this specific user, showing newest transactions first
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $user_id]);
        $orders = $stmt->fetchAll();
    } catch (\PDOException $e) {
        echo "<div class='alert error'>Database Error: Failed to retrieve user purchase records.</div>";
    }
}
?>

<style>
    * { font-family: var(--system-font); }
    .profile-container { max-width: 1000px; margin: 0 auto; padding: 20px 0; }
    .user-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 30px; display: flex; gap: 20px; align-items: center; }
    .avatar-placeholder { width: 50px; height: 50px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 20px; text-transform: uppercase; }
    
    /* Order History Presentation Layouts */
    .order-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .order-header { background: #f1f5f9; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px; }
    .order-meta { font-size: 14px; color: #475569; display: flex; gap: 20px; }
    
    /* Badges matching admin-guard structural color schemes */
    .status-badge { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-shipped { background: #e0f2fe; color: #0284c7; }
    .status-delivered { background: #dcfce7; color: #16a34a; }
    
    .order-body { padding: 20px; }
    .item-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #f1f5f9; font-size: 14px; }
    .item-row:last-child { border-bottom: none; }
    
    .no-orders { text-align: center; padding: 50px; background: white; border: 1px dashed #cbd5e1; border-radius: 8px; }
</style>

<div class="profile-container">
    <h2>Customer Management Account</h2>
    
    <div class="user-card">
        <div class="avatar-placeholder"><?php echo substr($_SESSION['username'], 0, 1); ?></div>
        <div>
            <h3 style="margin:0; color:#0f172a;"><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
            <p style="margin:5px 0 0 0; color:#64748b; font-size:14px;"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
        </div>
    </div>

    <h3>Your Past Purchases & Invoice History</h3>

    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <p style="color:#64748b; margin-bottom:15px;">You haven't placed any hardware orders yet.</p>
            <a href="index.php" style="background:#3b82f6; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; font-weight:500;">Start Shopping</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): 
            // Query itemized line contents mapping back to this exact row item loop instance
            try {
                $item_query = "SELECT oi.*, p.name FROM order_items oi 
                               JOIN products p ON oi.product_id = p.product_id 
                               WHERE oi.order_id = :order_id";
                $item_stmt = $pdo->prepare($item_query);
                $item_stmt->execute(['order_id' => $order['order_id']]);
                $items = $item_stmt->fetchAll();
            } catch (\PDOException $e) {
                $items = [];
            }
            
            // Map the semantic status string to a style class safely
            $status_class = 'status-pending';
            if ($order['status'] === 'Shipped') $status_class = 'status-shipped';
            if ($order['status'] === 'Delivered') $status_class = 'status-delivered';
        ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-meta">
                        <span><strong>Order ID:</strong> #ORD-<?php echo $order['order_id']; ?></span>
                        <span><strong>Placed On:</strong> <?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                        <span><strong>Total Bill:</strong> <strong style="color:#10b981;">$<?php echo number_format($order['total_amount'], 2); ?></strong></span>
                    </div>
                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $order['status']; ?></span>
                </div>
                
                <div class="order-body">
                    <?php foreach ($items as $item): ?>
                        <div class="item-row">
                            <span><?php echo htmlspecialchars($item['name']); ?> <span style="color:#64748b; font-size:12px;">(x<?php echo $item['quantity']; ?>)</span></span>
                            <span style="font-weight:600; color:#475569;">$<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>
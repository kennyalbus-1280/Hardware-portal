<?php
// Enforce path configuration and admin security boundaries
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/admin/admin-guard.php';

$success_message = '';
$error_message = '';

// ─── INTERCEPT STATUS UPDATE POST REQUESTS ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];

    if (in_array($new_status, ['Pending', 'Shipped', 'Delivered'])) {
        try {
            $update_stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE order_id = :order_id");
            $update_stmt->execute([
                'status'   => $new_status,
                'order_id' => $order_id
            ]);
            $success_message = "Order #ORD-{$order_id} status successfully shifted to <strong>{$new_status}</strong>!";
        } catch (\PDOException $e) {
            $error_message = "Fulfillment system database write failure.";
        }
    } else {
        $error_message = "Invalid status parameters submitted.";
    }
}

// ─── RUN ENGINE ANALYTICS QUERIES ────────────────────────────────────
try {
    // 1. Calculate Gross Revenue (Only count Shipped or Delivered orders)
    $revenue_stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status IN ('Shipped', 'Delivered')");
    $gross_revenue = (float)$revenue_stmt->fetchColumn();

    // 2. Count Active Pending Orders
    $pending_stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'");
    $pending_count = (int)$pending_stmt->fetchColumn();

    // 3. Count Total Registered Customers
    $customer_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
    $customer_count = (int)$customer_stmt->fetchColumn();

    // 4. Identify critical low stock inventory
    $stock_stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 3");
    $low_stock_count = (int)$stock_stmt->fetchColumn();

    // 5. Fetch the standard orders ledger list (Sorted by order_date matching database schema)
    $orders_stmt = $pdo->query("SELECT o.*, u.username, u.email FROM orders o 
                                JOIN users u ON o.user_id = u.user_id 
                                ORDER BY o.order_date DESC");
    $orders = $orders_stmt->fetchAll();

} catch (\PDOException $e) {
    die("Analytics processing engine failure: " . htmlspecialchars($e->getMessage()));
}

// Dynamically include global navigation layout framework
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';
?>

<style>
    * { font-family: var(--system-font); }
    .dashboard-shell { padding: 1rem 0 2rem; }
    .dashboard-card { border: 1px solid #e9ecef; box-shadow: none; }
    .metric-card { border-left: 4px solid transparent; }
    .metric-card.revenue { border-left-color: #198754; }
    .metric-card.pending { border-left-color: #ffc107; }
    .metric-card.users { border-left-color: #0d6efd; }
    .metric-card.stock { border-left-color: #dc3545; }
    .status-dropdown { border: 1px solid #ced4da; background-color: #fff; }
    .table thead th { background-color: #0D1D36; color: #fff; }
    .table td, .table th { vertical-align: middle; }
    .btn-plain { border: 1px solid #ced4da; background-color: #fff; color: #212529; }
</style>

<div class="dashboard-shell">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom border-2 border-dark-subtle">
        <div>
            <h2 class="h3 fw-bold mb-1">Manager Command Dashboard</h2>
            <p class="text-muted mb-0">Real-time channel metrics and order fulfillment logs.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
            <a href="/ecommerce/admin/dashboard.php" class="btn btn-dark btn-sm">📋 Orders Ledger</a>
            <a href="/ecommerce/admin/products.php" class="btn btn-plain btn-sm">📦 Warehouse Inventory</a>
            <a href="/ecommerce/admin/users.php" class="btn btn-plain btn-sm">👥 Users Register</a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success border-0 mb-4" role="alert"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger border-0 mb-4" role="alert"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-card h-100 metric-card revenue">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-muted">Gross Channel Revenue</div>
                    <div class="display-6 fw-bold mt-2">$<?php echo number_format($gross_revenue, 2); ?></div>
                    <div class="small text-success mt-2">● Funds settled/in-transit</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-card h-100 metric-card pending">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-muted">Fulfillment Queue</div>
                    <div class="display-6 fw-bold mt-2"><?php echo $pending_count; ?> Orders</div>
                    <div class="small text-warning mt-2">⚠️ Awaiting device dispatch</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-card h-100 metric-card users">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-muted">Registered Clients</div>
                    <div class="display-6 fw-bold mt-2"><?php echo $customer_count; ?> Users</div>
                    <div class="small text-primary mt-2">👤 Active marketplace profiles</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card dashboard-card h-100 metric-card stock">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-muted">Low Stock Warning</div>
                    <div class="display-6 fw-bold mt-2"><?php echo $low_stock_count; ?> Items</div>
                    <?php if ($low_stock_count > 0): ?>
                        <div class="small text-danger mt-2">🚨 Action required: Restock</div>
                    <?php else: ?>
                        <div class="small text-muted mt-2">✔️ Warehouse metrics optimal</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <h3 class="h5 fw-bold mb-3">System Order Ledger Log</h3>

    <div class="card dashboard-card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order Identifier</th>
                        <th>Submission Date</th>
                        <th>Purchasing Account</th>
                        <th>Financial Invoice Total</th>
                        <th>Fulfillment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No transaction entries recorded in the database.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): 
                            $select_color_class = 'text-warning';
                            if ($order['status'] === 'Shipped') $select_color_class = 'text-info';
                            if ($order['status'] === 'Delivered') $select_color_class = 'text-success';
                        ?>
                            <tr>
                                <td><strong>#ORD-<?php echo $order['order_id']; ?></strong></td>
                                <td><?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars($order['email']); ?></div>
                                </td>
                                <td class="fw-semibold text-success">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <form method="POST" action="dashboard.php" class="m-0">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" class="form-select form-select-sm status-dropdown <?php echo $select_color_class; ?>" onchange="this.form.submit()">
                                            <option value="Pending" <?php echo ($order['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Shipped" <?php echo ($order['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="Delivered" <?php echo ($order['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>
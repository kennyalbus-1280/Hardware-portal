<?php
// Enforce path configuration and admin security boundaries
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/admin/admin-guard.php';

$success_message = '';
$error_message = '';

// ─── INTERCEPT STATUS UPDATES (Logistics Management) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = trim($_POST['order_status']);
    
    $allowed_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $status_stmt = $pdo->prepare("UPDATE orders SET order_status = :status WHERE order_id = :id");
            $status_stmt->execute(['status' => $new_status, 'id' => $order_id]);
            $success_message = "Order #INV-{$order_id} has been transitioned to <strong>{$new_status}</strong>.";
        } catch (\PDOException $e) {
            $error_message = "Failed to update logistics status streams.";
        }
    }
}

// ─── FETCH RECONCILED MASTER ORDERSTREAM DATA ────────────────────────
try {
    // Reconciled query matching shipping_address, phone_number, and total layout bounds
    $orders_stmt = $pdo->query("
        SELECT 
            o.order_id, 
            o.total_amount, 
            o.order_status, 
            o.shipping_address, 
            o.phone_number, 
            o.created_at,
            u.username,
            u.email
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_id DESC
    ");
    $master_orders = $orders_stmt->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Data Stream Synchronization Failure: " . htmlspecialchars($e->getMessage());
    $master_orders = [];
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';
?>

<style>
    body { background-color: #f8fafc; color: #1e293b; }
    .dashboard-shell { padding: 1rem 0 2rem; }
    .dashboard-card { border: 1px solid #e9ecef; box-shadow: none; border-radius: 0; }
    .btn, .form-control, .form-select, .table, .alert, .card { border-radius: 0 !important; }
    .table thead th { background-color: #0D1D36; color: #fff; }
    .table td, .table th { vertical-align: middle; }
    .status-select { padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 13px; background-color: #f8fafc; outline: none; }
    .status-select:focus { border-color: #2563eb; }
    .btn-status-save { background: #0D1D36; color: white; border: none; padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: bold; }
    .address-box { font-size: 13px; color: #334155; max-width: 260px; word-wrap: break-word; background: #f8fafc; padding: 10px; border: 1px solid #e2e8f0; line-height: 1.5; }
    .alert-success { background-color: #dcfce7; color: #15803d; border: 0; }
    .alert-danger { background-color: #fee2e2; color: #b91c1c; border: 0; }
</style>

<div class="container-fluid py-4 dashboard-shell">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom border-2 border-dark-subtle">
        <div>
            <h2 class="h3 fw-bold mb-1">Client Orders & Transactions Ledger</h2>
            <p class="text-muted mb-0">Audit sales records, view shipping destinations, and update delivery status.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
            <a href="/ecommerce/admin/orders.php" class="btn btn-dark btn-sm">Orders</a>
            <a href="/ecommerce/admin/products.php" class="btn btn-outline-dark btn-sm">Products</a>
            <a href="/ecommerce/admin/users.php" class="btn btn-outline-dark btn-sm">Users</a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success border-0 mb-4"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger border-0 mb-4"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card dashboard-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Customer Profile</th>
                    <th>Placement Date</th>
                    <th>Shipping & Contact Details</th>
                    <th>Grand Total</th>
                    <th>Logistics Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($master_orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 40px;">No transactional invoices recorded in current stream archives.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($master_orders as $order): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 700; color: #0f172a; font-size: 14px;">
                                #INV-<?php echo $order['order_id']; ?>
                            </td>
                            
                            <td>
                                <strong style="color: #0f172a; font-size: 14px;"><?php echo htmlspecialchars($order['username']); ?></strong>
                                <span style="display: block; font-size: 12px; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars($order['email']); ?></span>
                            </td>
                            
                            <td style="color: #334155; font-size: 13px; font-weight: 500;">
                                <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                            </td>
                            
                            <td>
                                <div class="address-box">
                                    <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 4px;">
                                        <span class="mi" style="color: #64748b; font-size: 18px;">local_shipping</span> 
                                        <strong style="color: #0f172a;">Destination:</strong>
                                    </div>
                                    <span style="color: #475569; display: block; padding-left: 23px;"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></span>
                                    
                                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 8px 0;">
                                    
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span class="mi" style="color: #2563eb; font-size: 18px;">phone_in_talk</span> 
                                        <strong style="color: #0f172a;">Contact Line:</strong> 
                                        <span style="font-weight: 600; color: #2563eb;"><?php echo htmlspecialchars($order['phone_number']); ?></span>
                                    </div>
                                </div>
                            </td>
                            
                            <td style="font-weight: 700; color: #0f172a; white-space: nowrap; font-size: 15px;">
                                <?php echo number_format($order['total_amount'], 0); ?> UGX
                            </td>
                            
                            <td>
                                <form method="POST" action="orders.php" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    
                                    <select name="order_status" class="status-select">
                                        <option value="Pending" <?php echo $order['order_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Processing" <?php echo $order['order_status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Shipped" <?php echo $order['order_status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="Delivered" <?php echo $order['order_status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="Cancelled" <?php echo $order['order_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    
                                    <button type="submit" class="btn-status-save">Update</button>
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
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>
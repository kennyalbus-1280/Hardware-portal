<?php
// Enforce path configuration and admin security boundaries
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/admin/admin-guard.php';

$success_message = '';
$error_message = '';

// ─── INTERCEPT ROLE MODIFICATION REQUESTS ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $new_role = trim($_POST['role']);

    if ($target_user_id === (int)$_SESSION['user_id']) {
        $error_message = "Security Violation: You cannot alter your own administrative status.";
    } elseif (in_array($new_role, ['customer', 'admin'])) {
        try {
            $role_stmt = $pdo->prepare("UPDATE users SET role = :role WHERE user_id = :user_id");
            $role_stmt->execute(['role' => $new_role, 'user_id' => $target_user_id]);
            $success_message = "Account #USR-{$target_user_id} privilege shifted to <strong>" . ucfirst($new_role) . "</strong>.";
        } catch (\PDOException $e) {
            $error_message = "Database write failure during role update: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ─── INTERCEPT STATUS CONTROL (SUSPEND/ACTIVATE) REQUESTS ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $current_status = (int)$_POST['current_status'];
    $new_status = ($current_status === 1) ? 0 : 1; 

    if ($target_user_id === (int)$_SESSION['user_id']) {
        $error_message = "Security Violation: You cannot deactivate your own administrative profile.";
    } else {
        try {
            $status_stmt = $pdo->prepare("UPDATE users SET is_active = :status WHERE user_id = :user_id");
            $status_stmt->execute(['status' => $new_status, 'user_id' => $target_user_id]);
            $status_label = $new_status ? "activated" : "suspended";
            $success_message = "Account #USR-{$target_user_id} access rights have been <strong>{$status_label}</strong>.";
        } catch (\PDOException $e) {
            $error_message = "Status alternation failed: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ─── INTERCEPT PASSWORD OVERRIDE RESET REQUESTS ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    
    $raw_temp_pass = "Temp" . bin2hex(random_bytes(3)) . "!";
    $hashed_password = password_hash($raw_temp_pass, PASSWORD_DEFAULT);

    try {
        $pass_stmt = $pdo->prepare("UPDATE users SET password_hash = :password WHERE user_id = :user_id");
        $pass_stmt->execute([
            'password' => $hashed_password, 
            'user_id'  => $target_user_id
        ]);
        $success_message = "Password reset issued! Temp credential for #USR-{$target_user_id} is: <strong style='font-family:monospace; background:#fff; padding:2px 6px; border:1px solid #cbd5e1; border-radius:3px;'>{$raw_temp_pass}</strong>";
    } catch (\PDOException $e) {
        $error_message = "Credential modification query execution failed: " . htmlspecialchars($e->getMessage());
    }
}

// ─── INTERCEPT ACCOUNT REMOVAL REQUESTS ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user_id = (int)$_POST['target_user_id'];

    if ($target_user_id === (int)$_SESSION['user_id']) {
        $error_message = "Security Violation: Self-destruction prohibited. You cannot delete the account you are logged into.";
    } else {
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
            $delete_stmt->execute(['user_id' => $target_user_id]);
            $success_message = "User profile #USR-{$target_user_id} has been permanently purged from system registries.";
        } catch (\PDOException $e) {
            $error_message = "Removal failed. This user has active relational records tied to their account.";
        }
    }
}

// ─── FETCH ALL REGISTERED SYSTEM ACCOUNTS ───────────────────────────
try {
    // 📝 UPDATED: Added password_hash string retrieval to the master dataset select query
    $users_stmt = $pdo->query("SELECT user_id, username, email, password_hash, role, is_active FROM users ORDER BY user_id DESC");
    $accounts = $users_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Account tracking master registry link failure: " . htmlspecialchars($e->getMessage()));
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
    .role-dropdown { padding: 6px 12px; font-size: 13px; font-weight: bold; cursor: pointer; outline: none; border: 1px solid #cbd5e1; }
    .role-admin { border-left: 4px solid #3b82f6; color: #3b82f6; background-color: #eff6ff; }
    .role-customer { border-left: 4px solid #64748b; color: #64748b; background-color: #f8fafc; }
    .hash-preview-box { font-family: monospace; font-size: 11px; background: #f1f5f9; color: #475569; padding: 6px 10px; border: 1px solid #cbd5e1; max-width: 180px; overflow-x: auto; white-space: nowrap; }
    .btn-action-control { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 5px 10px; font-size: 12px; font-weight: 600; cursor: pointer; }
    .btn-action-control:hover { background: #e2e8f0; color: #1e293b; }
    .btn-status-active { color: #16a34a; background-color: #f0fdf4; border-color: #bbf7d0; }
    .btn-status-active:hover { background-color: #dcfce7; color: #15803d; }
    .btn-status-banned { color: #dc2626; background-color: #fef2f2; border-color: #fecaca; }
    .btn-status-banned:hover { background-color: #fee2e2; color: #b91c1c; }
    .btn-danger-purge { color: #b91c1c; background-color: #fff5f5; border-color: #feb2b2; }
    .btn-danger-purge:hover { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 0; }
    .alert-danger { background-color: #ffeeeb; color: #c62828; border: 0; }
</style>

<div class="container-fluid py-4 dashboard-shell">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom border-2 border-dark-subtle">
        <div>
            <h2 class="h3 fw-bold mb-1">System Users Registry</h2>
            <p class="text-muted mb-0">Audit accounts, review password hashes, reset credentials, and manage access.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
            <a href="/ecommerce/admin/orders.php" class="btn btn-outline-dark btn-sm">Orders</a>
            <a href="/ecommerce/admin/products.php" class="btn btn-outline-dark btn-sm">Products</a>
            <a href="/ecommerce/admin/users.php" class="btn btn-dark btn-sm">Users</a>
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
                <th>User ID</th>
                <th>Username</th>
                <th>Email Address</th>
                <th>Password Hash String (BCrypt)</th>
                <th>Privilege Level</th>
                <th style="text-align: right;">Administrative Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $user): 
                $current_role_class = ($user['role'] === 'admin') ? 'role-admin' : 'role-customer';
                $user_is_active = (int)$user['is_active'];
                $status_btn_text = $user_is_active ? "🟢 Active" : "🔴 Suspended";
                $status_btn_class = $user_is_active ? "btn-status-active" : "btn-status-banned";
            ?>
                <tr>
                    <td style="font-family: monospace; color: #64748b; font-weight: 600;">#USR-<?php echo $user['user_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    
                    <td>
                        <div class="hash-preview-box" title="<?php echo htmlspecialchars($user['password_hash']); ?>">
                            <?php echo !empty($user['password_hash']) ? htmlspecialchars($user['password_hash']) : '<em style="color:#94a3b8;">NULL (No Key Stored)</em>'; ?>
                        </div>
                    </td>

                    <td>
                        <form method="POST" action="users.php" style="margin: 0;">
                            <input type="hidden" name="target_user_id" value="<?php echo $user['user_id']; ?>">
                            <input type="hidden" name="update_role" value="1">
                            
                            <select name="role" class="role-dropdown <?php echo $current_role_class; ?>" onchange="this.form.submit()">
                                <option value="customer" <?php echo ($user['role'] === 'customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </form>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                            
                            <form method="POST" action="users.php" style="margin: 0;" onsubmit="return confirm('Generate a temporary system override password string for <?php echo htmlspecialchars($user['username']); ?>?');">
                                <input type="hidden" name="target_user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="reset_password" value="1">
                                <button type="submit" class="btn-action-control">🔑 Reset</button>
                            </form>

                            <form method="POST" action="users.php" style="margin: 0;" onsubmit="return confirm('Toggle access rights for this user account?');">
                                <input type="hidden" name="target_user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $user_is_active; ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <button type="submit" class="btn-action-control <?php echo $status_btn_class; ?>">
                                    <?php echo $status_btn_text; ?>
                                </button>
                            </form>

                            <form method="POST" action="users.php" style="margin: 0;" onsubmit="return confirm('CRITICAL WARNING: Are you sure you want to permanently delete the account for <?php echo htmlspecialchars($user['username']); ?>? This action cannot be undone.');">
                                <input type="hidden" name="target_user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="btn-action-control btn-danger-purge">🗑️ Delete</button>
                            </form>

                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/admin/admin-guard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authorization shield would go here for production environments

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category = trim($_POST['category']);
    
    // Server-side validation criteria
    if (empty($name) || empty($category)) {
        $error_message = 'Product Name and Category are strict mandatory fields.';
    } elseif ($price <= 0) {
        $error_message = 'Price must evaluate to a positive transactional currency amount.';
    } elseif ($stock < 0) {
        $error_message = 'Inventory stock count allocation cannot resolve to a negative value.';
    } else {
        try {
            // Prepared statement execution to inject the asset securely
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category) 
                                  VALUES (:name, :desc, :price, :stock, :category)");
            
            $stmt->execute([
                'name'        => $name,
                'desc'        => $description,
                'price'       => $price,
                'stock'       => $stock,
                'category'    => $category
            ]);
            
            $success_message = "Inventory asset <strong>" . htmlspecialchars($name) . "</strong> has been cataloged successfully!";
            
            // Clear variables so form values reset on success
            $name = $description = $category = '';
            $price = $stock = '';
            
        } catch (\PDOException $e) {
            $error_message = "Failed to register product record within MySQL schema.";
        }
    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';
?>

<style>
    .admin-nav-tabs { display: flex; gap: 15px; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .tab-link { color: #64748b; text-decoration: none; font-weight: 500; font-size: 15px; padding: 6px 12px; border-radius: 4px; }
    .tab-link:hover { background: #f1f5f9; color: #334155; }
    .tab-active { background: #34495e; color: white; }
    .tab-active:hover { background: #34495e; color: white; }

    .form-box { max-width: 600px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .form-row-split { display: flex; gap: 20px; }
    .form-row-split .form-group { flex: 1; }
    
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 14px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 15px; background-color: #f8fafc; }
    .form-control:focus { border-color: #3498db; background-color: #fff; outline: none; }
    
    .btn-submit { background-color: #3498db; color: white; border: none; padding: 12px 20px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
    .btn-submit:hover { background-color: #2980b9; }
    
    .alert { padding: 12px; border-radius: 4px; font-size: 14px; margin-bottom: 20px; font-weight: 500; }
    .alert-error { background-color: #ffeeeb; color: #c62828; border: 1px solid #ffd1c9; }
    .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
</style>

<div class="admin-nav-tabs">
    <a href="dashboard.php" class="tab-link">Order Ledger</a>
    <a href="add-product.php" class="tab-link tab-active">Add New Product</a>
</div>

<div class="form-box">
    <h3 style="margin-bottom: 20px; color: #2c3e50;">Catalog New Equipment Asset</h3>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <form action="add-product.php" method="POST">
        <div class="form-group">
            <label for="name">Product Name / Model Identifier *</label>
            <input type="text" id="name" name="name" class="form-control" required value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" placeholder="e.g., HP ProBook 450 G9">
        </div>
        
        <div class="form-group">
            <label for="category">Inventory Category *</label>
            <input type="text" id="category" name="category" class="form-control" required value="<?php echo isset($category) ? htmlspecialchars($category) : ''; ?>" placeholder="e.g., Laptops, Accessories, Components">
        </div>
        
        <div class="form-row-split">
            <div class="form-group">
                <label for="price">Unit Selling Price ($) *</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" required value="<?php echo isset($price) ? htmlspecialchars($price) : ''; ?>" placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label for="stock">Initial Stock Quantity *</label>
                <input type="number" id="stock" name="stock" class="form-control" min="0" required value="<?php echo isset($stock) ? htmlspecialchars($stock) : ''; ?>" placeholder="0">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Technical Description & Specifications</label>
            <textarea id="description" name="description" class="form-control" rows="5" placeholder="Provide hardware parameters, processing speeds, memory limits, etc..."><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
        </div>
        
        <button type="submit" class="btn-submit">Save to MySQL Catalog</button>
    </form>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>

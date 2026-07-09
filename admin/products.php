<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/admin/admin-guard.php';

$success_message = '';
$error_message = '';

// Handle Asynchronous AJAX Requests (Stock updates, Details updates, Deletion, & Image Updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // ACTION 1: Inline Stock Update
    if ($_POST['action'] === 'ajax_update_stock') {
        $product_id = (int)$_POST['product_id'];
        $new_stock = (int)$_POST['stock_quantity'];

        if ($new_stock >= 0) {
            try {
                $stock_stmt = $pdo->prepare("UPDATE products SET stock_quantity = :stock WHERE product_id = :id");
                $stock_stmt->execute(['stock' => $new_stock, 'id' => $product_id]);
                
                $is_low = ($new_stock < 3);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Stock updated.',
                    'is_low' => $is_low,
                    'stock' => $new_stock
                ]);
            } catch (\PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Stock cannot be negative.']);
        }
        exit;
    }

    // ACTION 2: Full Product Details Update
    if ($_POST['action'] === 'ajax_update_details') {
        $product_id   = (int)$_POST['product_id'];
        $newName      = trim($_POST['name']);
        $newCategory  = trim($_POST['category']);
        $newDesc      = trim($_POST['description']);
        $newPrice     = (float)str_replace([',', ' '], '', $_POST['price']);

        if (empty($newName) || empty($newCategory) || $newPrice <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data formats provided.']);
        } else {
            try {
                $update_stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = :name, category = :category, description = :description, price = :price 
                    WHERE product_id = :id
                ");
                $update_stmt->execute([
                    'name'        => $newName,
                    'category'    => $newCategory,
                    'description' => $newDesc,
                    'price'       => $newPrice,
                    'id'          => $product_id
                ]);

                echo json_encode([
                    'success'       => true,
                    'message'       => 'Product details synchronized.',
                    'formatted_price'=> number_format($newPrice, 0) . ' UGX'
                ]);
            } catch (\PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database update anomaly.']);
            }
        }
        exit;
    }

    // ACTION 3: Asynchronous Product Removal (AJAX Delete)
    if ($_POST['action'] === 'ajax_delete_product') {
        $product_id = (int)$_POST['product_id'];
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :id");
            $delete_stmt->execute(['id' => $product_id]);
            echo json_encode(['success' => true, 'message' => 'Product removed from system database storage matrix.']);
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Cannot drop product record. It is tied to existing active checkout orders.']);
        }
        exit;
    }

    // ACTION 4: Asynchronous Image Replacement Engine (AJAX Hot-Swap)
    if ($_POST['action'] === 'ajax_update_image') {
        $product_id = (int)$_POST['product_id'];
        
        if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No valid image payload received.']);
            exit;
        }

        $file_tmp = $_FILES['product_image']['tmp_name'];
        $file_name = $_FILES['product_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if ($_FILES['product_image']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image exceeds 2MB threshold file size limit.']);
            exit;
        }

        if (!in_array($file_ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file extension formatting. Only JPG, PNG, and WEBP supported.']);
            exit;
        }

        $image_filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
        $upload_target = $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/uploads/' . $image_filename;

        try {
            if (move_uploaded_file($file_tmp, $upload_target)) {
                $img_stmt = $pdo->prepare("UPDATE products SET image = :img WHERE product_id = :id");
                $img_stmt->execute(['img' => $image_filename, 'id' => $product_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Image swapped successfully.',
                    'new_src' => '/ecommerce/uploads/' . $image_filename
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'File directory storage migration error.']);
            }
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database storage tracking anomaly.']);
        }
        exit;
    }
}

// Add Product Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name        = trim($_POST['name']);
    $product_description = trim($_POST['description']);
    $product_price       = (float)str_replace([',', ' '], '', $_POST['price']);
    $product_stock       = (int)$_POST['stock_quantity'];
    $product_category    = trim($_POST['category']); 
    $image_filename      = 'default-hardware.jpg';

    if (empty($product_name) || empty($product_category) || $product_price <= 0 || $product_stock < 0) {
        $error_message = "Please provide a valid product name, category, price, and stock quantity.";
    } else {
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed_extensions)) {
                $image_filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                $upload_target = $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/uploads/' . $image_filename;
                
                if (!is_dir($_SERVER['DOCUMENT_ROOT'] . '/ecommerce/uploads/')) {
                    mkdir($_SERVER['DOCUMENT_ROOT'] . '/ecommerce/uploads/', 0755, true);
                }
                
                if (!move_uploaded_file($file_tmp, $upload_target)) {
                    $image_filename = 'default-hardware.jpg';
                    $error_message = "Image upload failed. Using default image.";
                }
            } else {
                $error_message = "Invalid file format. Allowed: JPG, JPEG, PNG, WEBP.";
            }
        }

        if (empty($error_message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, description, price, category, stock_quantity, image, created_at) 
                    VALUES (:name, :description, :price, :category, :stock_quantity, :image, NOW())
                ");
                
                $stmt->execute([
                    'name'           => $product_name,
                    'description'    => $product_description,
                    'price'          => $product_price,
                    'category'       => $product_category,
                    'stock_quantity' => $product_stock,
                    'image'          => $image_filename
                ]);
                
                $success_message = "Product added successfully.";
            } catch (\PDOException $e) {
                $error_message = "Database error: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Fetch Products
try {
    $products_stmt = $pdo->query("SELECT * FROM products ORDER BY product_id DESC");
    $inventories = $products_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Error fetching database records.");
}

// Pre-calculate initial backend stats metrics to avoid layout pop-in
$total_unique_items = count($inventories);
$total_investment = 0;
$total_low_stock_count = 0;

foreach ($inventories as $item) {
    $total_investment += ((float)$item['price'] * (int)$item['stock_quantity']);
    if ((int)$item['stock_quantity'] < 3) {
        $total_low_stock_count++;
    }
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
    .stock-badge { padding: 3px 8px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 6px; }
    .stock-normal { background-color: #dcfce7; color: #15803d; }
    .stock-critical { background-color: #fee2e2; color: #b91c1c; }
    .preview-box { margin-top: 8px; display: none; width: 60px; height: 60px; border: 1px dashed #cbd5e1; object-fit: cover; }
    .image-upload-wrapper { position: relative; width: 44px; height: 44px; cursor: pointer; overflow: hidden; }
    .thumb-preview { width: 44px; height: 44px; object-fit: cover; border: 1px solid #e2e8f0; display: block; }
    .image-hover-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s ease; }
    .image-upload-wrapper:hover .image-hover-overlay { opacity: 1; }
    .view-state { display: block; }
    .edit-state { display: none; }
    .editing .view-state { display: none; }
    .editing .edit-state { display: block; }
    .inline-stock-input { width: 60px; padding: 5px; border: 1px solid #cbd5e1; text-align: center; font-size: 13px; font-weight: 600; }
    .btn-submit { background-color: #0D1D36; color: #fff; border: 0; width: 100%; padding: 10px; font-weight: 600; }
    .btn-submit:hover { background-color: #18375b; }
    .btn-inline-save { background: #10b981; color: #fff; border: none; padding: 5px 8px; cursor: pointer; display: flex; }
    .btn-inline-save:hover { background: #059669; }
    .btn-delete-action { background: none; border: none; color: #64748b; cursor: pointer; font-size: 13px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 4px; }
    .btn-delete-action:hover { background-color: #fee2e2; color: #ef4444; }
    .btn-edit-trigger { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 12px; padding: 4px 0; font-weight: 600; display: inline-flex; align-items: center; gap: 2px; }
    .btn-edit-trigger:hover { text-decoration: underline; }
    .btn-save-details { background: #0D1D36; color: white; border: none; padding: 4px 8px; font-size: 11px; font-weight: 600; cursor: pointer; }
    .btn-cancel-details { background: #cbd5e1; color: #334155; border: none; padding: 4px 8px; font-size: 11px; font-weight: 600; cursor: pointer; }
</style>

<div class="container-fluid py-4 dashboard-shell">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 pb-3 border-bottom border-2 border-dark-subtle">
        <div>
            <h2 class="h3 fw-bold mb-1">Products Management</h2>
            <p class="text-muted mb-0">Track inventory, update stock, and manage product details.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
            <a href="/ecommerce/admin/orders.php" class="btn btn-outline-dark btn-sm">Orders</a>
            <a href="/ecommerce/admin/products.php" class="btn btn-dark btn-sm">Products</a>
            <a href="/ecommerce/admin/users.php" class="btn btn-outline-dark btn-sm">Users</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-4">
            <div class="card dashboard-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary p-2"><span class="mi">inventory</span></div>
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Unique Products</div>
                        <div class="h4 fw-bold mb-0" id="stat-unique-items"><?php echo $total_unique_items; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card dashboard-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-success-subtle text-success p-2"><span class="mi">payments</span></div>
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Inventory Valuation</div>
                        <div class="h4 fw-bold mb-0" id="stat-total-valuation"><?php echo number_format($total_investment, 0); ?> UGX</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card dashboard-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-warning-subtle text-warning p-2"><span class="mi">gpp_maybe</span></div>
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Low Stock Alerts</div>
                        <div class="h4 fw-bold mb-0" id="stat-low-stock-count"><?php echo $total_low_stock_count; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($success_message)): ?><div class="alert alert-success border-0 mb-4"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="alert alert-danger border-0 mb-4"><?php echo $error_message; ?></div><?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Add New Product</h5>
                    <form method="POST" action="products.php" enctype="multipart/form-data" id="add-product-form">
                        <input type="hidden" name="add_product" value="1">
                        <div class="mb-3">
                            <label for="name" class="form-label small text-uppercase fw-semibold text-muted">Product Name</label>
                            <input type="text" id="name" name="name" class="form-control" required placeholder="Name/Model">
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label small text-uppercase fw-semibold text-muted">Category</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <option value="Laptops">Laptops</option>
                                <option value="Components">Components</option>
                                <option value="Networking">Networking</option>
                                <option value="Power Systems">Power Systems</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label small text-uppercase fw-semibold text-muted">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="Specifications..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label small text-uppercase fw-semibold text-muted">Price (UGX)</label>
                            <input type="text" id="price" name="price" class="form-control" required placeholder="0">
                        </div>
                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label small text-uppercase fw-semibold text-muted">Stock Quantity</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" required placeholder="0">
                        </div>
                        <div class="mb-3">
                            <label for="product_image" class="form-label small text-uppercase fw-semibold text-muted">Image</label>
                            <input type="file" id="product_image" name="product_image" class="form-control" style="background: transparent; padding: 5px 0; border: none;" accept="image/*">
                            <img id="img-preview" class="preview-box" alt="Upload preview">
                        </div>
                        <button type="submit" class="btn btn-submit"><span class="mi" style="font-size: 16px;">backup</span> Save Product</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card dashboard-card">
                <div class="card-body p-0">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center p-3 border-bottom">
                        <div>
                            <h5 class="fw-bold mb-1">Product List</h5>
                            <p class="text-muted small mb-0">Manage visible inventory and update live stock.</p>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 mt-lg-0" onclick="exportFilteredTableToCSV()"><span class="mi" style="font-size:14px;">download_file</span> Export Filtered List (.CSV)</button>
                    </div>

                    <div class="p-3 border-bottom">
                        <div class="row g-2">
                            <div class="col-12 col-md-6 col-lg-5">
                                <input type="text" id="search-input" class="form-control" placeholder="Search by product name or ID...">
                            </div>
                            <div class="col-12 col-md-4 col-lg-3">
                                <select id="filter-category" class="form-control">
                                    <option value="">All Categories</option>
                                    <option value="Laptops">Laptops</option>
                                    <option value="Components">Components</option>
                                    <option value="Networking">Networking</option>
                                    <option value="Power Systems">Power Systems</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-2 col-lg-4 d-flex align-items-center">
                                <label class="form-check-label small text-muted"><input type="checkbox" id="filter-low-stock" class="form-check-input me-2"> Low Stock (&lt; 3)</label>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="products-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Image</th>
                                    <th>Details</th>
                                    <th style="width: 180px;">Price</th>
                                    <th style="width: 180px;">Stock Status</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventories)): ?>
                                    <tr class="no-data-row"><td colspan="5" class="text-center text-muted py-4">No products found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($inventories as $item): 
                                        $is_low = ((int)$item['stock_quantity'] < 3);
                                        $badge_class = $is_low ? 'stock-critical' : 'stock-normal';
                                        $badge_icon = $is_low ? 'running_with_errors' : 'check_circle';
                                    ?>
                                        <tr class="product-row" 
                                            id="row-<?php echo $item['product_id']; ?>"
                                            data-name="<?php echo strtolower(htmlspecialchars($item['name'])); ?>" 
                                            data-id="<?php echo $item['product_id']; ?>" 
                                            data-category="<?php echo htmlspecialchars($item['category']); ?>"
                                            data-low-stock="<?php echo $is_low ? 'true' : 'false'; ?>">
                                            
                                            <td>
                                                <div class="image-upload-wrapper" title="Click to replace image" onclick="triggerInlineImageChoice(<?php echo $item['product_id']; ?>)">
                                                    <img src="/ecommerce/uploads/<?php echo htmlspecialchars($item['image']); ?>" class="thumb-preview product-thumb-<?php echo $item['product_id']; ?>" alt="Image">
                                                    <div class="image-hover-overlay"><span class="mi" style="color: white; font-size: 16px;">upload</span></div>
                                                </div>
                                                <input type="file" class="inline-file-picker-<?php echo $item['product_id']; ?>" style="display:none;" accept="image/*" onchange="executeInlineImageAjaxUpload(<?php echo $item['product_id']; ?>, this)">
                                            </td>
                                            
                                            <td class="details-cell">
                                                <div class="view-state">
                                                    <span class="small text-muted font-monospace">ID: <span class="csv-raw-id"><?php echo $item['product_id']; ?></span></span>
                                                    <strong class="display-name csv-raw-name d-block mt-1"><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <span class="display-category csv-raw-category small text-muted d-block"><?php echo htmlspecialchars($item['category']); ?></span>
                                                    <p class="display-desc csv-raw-desc small text-muted mt-1 mb-0"><?php echo htmlspecialchars($item['description']); ?></p>
                                                    <button type="button" class="btn-edit-trigger mt-2" onclick="toggleRowEditState(<?php echo $item['product_id']; ?>, true)"><span class="mi" style="font-size: 12px;">edit</span> Quick Edit</button>
                                                </div>
                                                <div class="edit-state" style="max-width: 500px;">
                                                    <div class="mb-2"><input type="text" class="form-control edit-input-name" value="<?php echo htmlspecialchars($item['name']); ?>"></div>
                                                    <div class="mb-2">
                                                        <select class="form-control edit-input-category">
                                                            <option value="Laptops" <?php echo $item['category'] === 'Laptops' ? 'selected' : ''; ?>>Laptops</option>
                                                            <option value="Components" <?php echo $item['category'] === 'Components' ? 'selected' : ''; ?>>Components</option>
                                                            <option value="Networking" <?php echo $item['category'] === 'Networking' ? 'selected' : ''; ?>>Networking</option>
                                                            <option value="Power Systems" <?php echo $item['category'] === 'Power Systems' ? 'selected' : ''; ?>>Power Systems</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-2"><textarea class="form-control edit-input-desc" rows="2"><?php echo htmlspecialchars($item['description']); ?></textarea></div>
                                                </div>
                                            </td>
                                            
                                            <td class="fw-semibold text-dark">
                                                <div class="view-state display-price-label csv-raw-price" data-raw-price="<?php echo $item['price']; ?>"><?php echo number_format($item['price'], 0); ?> UGX</div>
                                                <div class="edit-state">
                                                    <input type="text" class="form-control edit-input-price dynamic-format-price" value="<?php echo number_format($item['price'], 0); ?>" style="width: 130px; font-weight: 600;">
                                                    <div class="d-flex gap-2 mt-2">
                                                        <button type="button" class="btn-save-details" onclick="saveRowDetailsAjax(<?php echo $item['product_id']; ?>)">Save</button>
                                                        <button type="button" class="btn-cancel-details" onclick="toggleRowEditState(<?php echo $item['product_id']; ?>, false)">Cancel</button>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <div class="badge-status-container">
                                                    <span class="stock-badge <?php echo $badge_class; ?>">
                                                        <span class="mi" style="font-size: 12px;"><?php echo $badge_icon; ?></span>
                                                        <span class="stock-display-val csv-raw-stock"><?php echo $item['stock_quantity']; ?></span> left
                                                    </span>
                                                </div>
                                                <form method="POST" action="products.php" class="d-flex gap-2 align-items-center mt-2" onsubmit="handleStockAjaxUpdate(event, this);">
                                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                    <input type="number" name="stock_quantity" value="<?php echo $item['stock_quantity']; ?>" min="0" class="inline-stock-input" required>
                                                    <button type="submit" class="btn-inline-save" title="Update"><span class="mi" style="font-size: 12px; color: white;">save</span></button>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" action="products.php" style="margin:0;" onsubmit="handleProductAjaxDelete(event, <?php echo $item['product_id']; ?>);">
                                                    <button type="submit" class="btn-delete-action"><span class="mi" style="font-size: 14px;">delete</span> Remove</button>
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
    </div>
</div>

<script>
// Client-Side Programmatic Analytics Matrix Recalculation Engine
function runLiveAnalyticsRecalculation() {
    const productRows = document.querySelectorAll('.product-row');
    
    let itemsCounter = 0;
    let totalValuation = 0;
    let lowStockCounter = 0;

    productRows.forEach(row => {
        itemsCounter++;
        
        // Extract exact primitive values from standard DOM markers safely
        const priceLabel = row.querySelector('.display-price-label');
        const rawPrice = parseFloat(priceLabel.getAttribute('data-raw-price')) || 0;
        
        const rawStock = parseInt(row.querySelector('.csv-raw-stock').innerText.trim()) || 0;

        totalValuation += (rawPrice * rawStock);
        
        if (rawStock < 3) {
            lowStockCounter++;
        }
    });

    // Animate DOM content mutations seamlessly
    document.getElementById('stat-unique-items').innerText = itemsCounter;
    document.getElementById('stat-total-valuation').innerText = itemsCounter === 0 ? "0 UGX" : totalValuation.toLocaleString('en-US') + " UGX";
    document.getElementById('stat-low-stock-count').innerText = lowStockCounter;
}

function triggerInlineImageChoice(id) {
    document.querySelector(`.inline-file-picker-${id}`).click();
}

function executeInlineImageAjaxUpload(id, inputElement) {
    const file = inputElement.files[0];
    if (!file) return;

    if(file.size > 2 * 1024 * 1024) {
        alert('Validation failed: Image must be smaller than 2MB.');
        inputElement.value = '';
        return;
    }

    const row = document.getElementById(`row-${id}`);
    const thumbImg = row.querySelector(`.product-thumb-${id}`);
    const originalSrc = thumbImg.src;

    thumbImg.style.opacity = '0.4';

    const formData = new FormData();
    formData.append('action', 'ajax_update_image');
    formData.append('product_id', id);
    formData.append('product_image', file);

    fetch('products.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            thumbImg.src = data.new_src;
            row.style.backgroundColor = '#f0fdf4';
            setTimeout(() => { row.style.backgroundColor = ''; }, 800);
        } else {
            alert('Upload anomaly: ' + data.message);
            thumbImg.src = originalSrc;
        }
    })
    .catch(e => {
        alert('Network transmission connection tracking failure.');
        thumbImg.src = originalSrc;
    })
    .finally(() => {
        thumbImg.style.opacity = '1';
        inputElement.value = '';
    });
}

function handleProductAjaxDelete(event, id) {
    event.preventDefault();
    if(!confirm('Remove this product permanently from the warehouse system?')) return;

    const row = document.getElementById(`row-${id}`);
    const deleteBtn = row.querySelector('.btn-delete-action');
    
    deleteBtn.innerHTML = '<span class="mi" style="font-size:14px; animation:spin 1s linear infinite;">sync</span> Out...';
    deleteBtn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'ajax_delete_product');
    formData.append('product_id', id);

    fetch('products.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            row.style.opacity = '0';
            row.style.transform = 'translateX(50px)';
            setTimeout(() => {
                row.remove();
                
                // Recalculate dashboard counters immediately after DOM node purge
                runLiveAnalyticsRecalculation();

                const remainingRows = document.querySelectorAll('.product-row');
                if(remainingRows.length === 0) {
                    document.querySelector('#products-table tbody').innerHTML = `
                        <tr class="no-data-row"><td colspan="5" style="text-align: center; color: #64748b; padding: 30px;">No products found.</td></tr>`;
                }
            }, 400);
        } else {
            alert('Delete rejected: ' + data.message);
            deleteBtn.innerHTML = '<span class="mi" style="font-size: 14px;">delete</span> Remove';
            deleteBtn.disabled = false;
        }
    })
    .catch(e => alert('Connection tracking disruption.'));
}

function saveRowDetailsAjax(id) {
    const row = document.getElementById(`row-${id}`);
    const nameVal  = row.querySelector('.edit-input-name').value;
    const catVal   = row.querySelector('.edit-input-category').value;
    const descVal  = row.querySelector('.edit-input-desc').value;
    const priceVal = row.querySelector('.edit-input-price').value;
    const saveBtn  = row.querySelector('.btn-save-details');

    saveBtn.innerText = '...';
    saveBtn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'ajax_update_details');
    formData.append('product_id', id);
    formData.append('name', nameVal);
    formData.append('category', catVal);
    formData.append('description', descVal);
    formData.append('price', priceVal);

    fetch('products.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const parsedPriceNumeric = parseFloat(priceVal.replace(/[^\d.]/g, '')) || 0;
            const displayPriceLabel = row.querySelector('.display-price-label');
            
            row.querySelector('.display-name').innerText = nameVal;
            row.querySelector('.display-category').innerText = catVal;
            row.querySelector('.display-desc').innerText = descVal;
            
            // Set values inside internal DOM trackers safely before triggering recalculations
            displayPriceLabel.innerText = data.formatted_price;
            displayPriceLabel.setAttribute('data-raw-price', parsedPriceNumeric);
            
            row.setAttribute('data-name', nameVal.toLowerCase());
            row.setAttribute('data-category', catVal);

            // Recompute inventory valuation statistics live
            runLiveAnalyticsRecalculation();

            toggleRowEditState(id, false);
            row.style.backgroundColor = '#eff6ff';
            setTimeout(() => { row.style.backgroundColor = ''; }, 800);
        } else { alert('Error updating details: ' + data.message); }
    })
    .catch(e => alert('System update processing failure.'))
    .finally(() => { saveBtn.innerText = 'Save'; saveBtn.disabled = false; });
}

function handleStockAjaxUpdate(event, formElement) {
    event.preventDefault();
    const saveButton = formElement.querySelector('.btn-inline-save');
    const inputField = formElement.querySelector('.inline-stock-input');
    const productId = formElement.querySelector('input[name="product_id"]').value;
    const initialButtonContent = saveButton.innerHTML;

    saveButton.style.backgroundColor = '#64748b';
    saveButton.innerHTML = '<span class="mi" style="font-size: 12px; color: white; animation: spin 1s linear infinite;">sync</span>';

    const formData = new FormData();
    formData.append('action', 'ajax_update_stock');
    formData.append('product_id', productId);
    formData.append('stock_quantity', inputField.value);

    fetch('products.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const parentRow = document.getElementById(`row-${productId}`);
            const badgeContainer = parentRow.querySelector('.badge-status-container');
            
            if(data.is_low) {
                badgeContainer.innerHTML = `<span class="stock-badge stock-critical"><span class="mi" style="font-size: 12px;">running_with_errors</span><span class="stock-display-val csv-raw-stock">${data.stock}</span> left</span>`;
                parentRow.setAttribute('data-low-stock', 'true');
            } else {
                badgeContainer.innerHTML = `<span class="stock-badge stock-normal"><span class="mi" style="font-size: 12px;">check_circle</span><span class="stock-display-val csv-raw-stock">${data.stock}</span> left</span>`;
                parentRow.setAttribute('data-low-stock', 'false');
            }

            // Fire synchronous recalculation framework tasks across stats card containers
            runLiveAnalyticsRecalculation();

            saveButton.style.backgroundColor = '#10b981';
            parentRow.style.backgroundColor = '#f0fdf4';
            setTimeout(() => { parentRow.style.backgroundColor = ''; }, 600);
        } else { alert('Error: ' + data.message); }
    })
    .catch(error => alert('Communication breakdown.'))
    .finally(() => { saveButton.innerHTML = initialButtonContent; });
}

function exportFilteredTableToCSV() {
    const activeRows = document.querySelectorAll('.product-row');
    let csvLines = [];
    csvLines.push("Product ID,Product Name,Category,Description,Price,Current Stock");

    let itemsCounter = 0;
    activeRows.forEach(row => {
        if (row.style.display !== 'none') {
            itemsCounter++;
            const pId   = row.querySelector('.csv-raw-id').innerText.trim();
            const pName = row.querySelector('.csv-raw-name').innerText.trim().replace(/"/g, '""');
            const pCat  = row.querySelector('.csv-raw-category').innerText.trim().replace(/"/g, '""');
            const pDesc = row.querySelector('.csv-raw-desc').innerText.trim().replace(/"/g, '""');
            const pPrice = row.querySelector('.display-price-label').getAttribute('data-raw-price');
            const pStock = row.querySelector('.csv-raw-stock').innerText.trim();
            csvLines.push(`"${pId}","${pName}","${pCat}","${pDesc}","${pPrice}","${pStock}"`);
        }
    });

    if(itemsCounter === 0) { alert('No parsed data structures to export.'); return; }
    const csvContent = "\uFEFF" + csvLines.join("\n");
    const blobObject = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const temporaryLink = document.createElement("a");
    temporaryLink.setAttribute("href", URL.createObjectURL(blobObject));
    temporaryLink.setAttribute("download", `Inventory_Report_${new Date().toISOString().slice(0,10)}.csv`);
    temporaryLink.style.visibility = 'hidden';
    document.body.appendChild(temporaryLink);
    temporaryLink.click();
    document.body.removeChild(temporaryLink);
}

function toggleRowEditState(id, showEdit) {
    const row = document.getElementById(`row-${id}`);
    if (row) {
        if (showEdit) { row.classList.add('editing'); } 
        else { row.classList.remove('editing'); }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('product_image');
    const imgPreview = document.getElementById('img-preview');
    if(imageInput && imgPreview) {
        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function() {
                    imgPreview.setAttribute('src', this.result);
                    imgPreview.style.display = 'block';
                });
                reader.readAsDataURL(file);
            } else { imgPreview.style.display = 'none'; }
        });
    }

    document.body.addEventListener('input', function(e) {
        if (e.target && (e.target.id === 'price' || e.target.classList.contains('dynamic-format-price'))) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = (value !== '') ? Number(value).toLocaleString('en-US') : '';
        }
    });

    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('filter-category');
    const lowStockFilter = document.getElementById('filter-low-stock');
    const tableRows = document.querySelectorAll('.product-row');

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedCat = categoryFilter.value;
        const showOnlyLowStock = lowStockFilter.checked;

        tableRows.forEach(row => {
            const productName = row.getAttribute('data-name');
            const productId = row.getAttribute('data-id');
            const productCat = row.getAttribute('data-category');
            const isLowStock = row.getAttribute('data-low-stock') === 'true';

            const matchesSearch = productName.includes(query) || productId.includes(query);
            const matchesCategory = selectedCat === "" || productCat === selectedCat;
            const matchesStockCondition = !showOnlyLowStock || isLowStock;

            row.style.display = (matchesSearch && matchesCategory && matchesStockCondition) ? '' : 'none';
        });
    }

    if(searchInput && categoryFilter && lowStockFilter) {
        searchInput.addEventListener('input', filterTable);
        categoryFilter.addEventListener('change', filterTable);
        lowStockFilter.addEventListener('change', filterTable);
    }
});
</script>

<style>
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; ?>
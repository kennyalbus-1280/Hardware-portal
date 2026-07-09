<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';

// Initialize variables
$product = null;
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        // Use a parameterized/prepared statement to completely prevent SQL injection
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
        $stmt->execute(['id' => $product_id]);
        $product = $stmt->fetch();
    } catch (\PDOException $e) {
        echo "<div class='alert error'>Error pulling database records.</div>";
    }
}

// If the product doesn't exist, stop execution and alert the user
if (!$product) {
    echo "<div class='alert warning'><h3>Product Not Found</h3><p>The system could not locate the piece of equipment requested. <a href='index.php'>Return to Catalog</a></p></div>";
    require_once INCLUDES_PATH . '/footer.php';
    exit;
}
?>

<style>
    .product-shell { padding: 1.5rem 0 3rem; }
    .product-hero {
        background: linear-gradient(135deg, #0D1D36 0%, #18375b 100%);
        color: #fff;
    }
    .product-icon { font-size: 4rem; }
</style>

<div class="container product-shell">
    <a href="index.php" class="btn btn-outline-secondary btn-sm mb-4">← Back to products</a>

    <?php
    // Determine the matching category icon code
    $cat = strtolower($product['category']);
    $display_icon = "⚙️";
    if (strpos($cat, 'laptop') !== false || strpos($cat, 'computer') !== false) { $display_icon = "💻"; }
    elseif (strpos($cat, 'component') !== false || strpos($cat, 'ram') !== false || strpos($cat, 'storage') !== false) { $display_icon = "💾"; }
    elseif (strpos($cat, 'network') !== false) { $display_icon = "🌐"; }
    elseif (strpos($cat, 'power') !== false || strpos($cat, 'ups') !== false) { $display_icon = "⚡"; }
    ?>

    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="row g-0">
            <div class="col-lg-5 product-hero d-flex flex-column justify-content-center align-items-center text-center p-4 p-lg-5">
                <div class="product-icon mb-3"><?php echo $display_icon; ?></div>
                <h2 class="h4 fw-bold mb-2">Secure hardware asset</h2>
                <p class="mb-0 text-white-50 small">Ready for deployment, fulfillment, and delivery.</p>
            </div>

            <div class="col-lg-7 p-4 p-lg-5">
                <span class="badge bg-primary-subtle text-primary mb-3"><?php echo htmlspecialchars($product['category']); ?></span>
                <h1 class="display-6 fw-bold mb-3 text-dark"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="mb-3">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <span class="badge bg-success-subtle text-success">Available · <?php echo $product['stock_quantity']; ?> units left</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger">Temporarily out of stock</span>
                    <?php endif; ?>
                </div>

                <div class="display-5 fw-bold text-dark border-top border-bottom py-3 mb-4">
                    $<?php echo number_format($product['price'], 2); ?>
                </div>

                <p class="text-muted mb-4"><?php echo htmlspecialchars($product['description']); ?></p>

                <div class="card bg-light border-0 rounded-4 p-4">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <form action="cart.php" method="POST" class="row g-3 align-items-end">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">

                            <div class="col-md-4">
                                <label for="quantity" class="form-label fw-semibold">Quantity</label>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" class="form-control">
                            </div>

                            <div class="col-md-8">
                                <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold">Add to cart</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary w-100 py-2" disabled>Item unavailable</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once INCLUDES_PATH . '/footer.php';
?>

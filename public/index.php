<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';

$products = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT product_id, name, description, price, category, stock_quantity, image FROM products ORDER BY product_id DESC");
        $products = $stmt->fetchAll();
    } catch (\PDOException $e) {
        echo "<div style='padding:20px; color:#b91c1c; background:#fee2e2; border-radius:6px; margin:20px auto; max-width:1200px; font-family:system-ui;'>Catalog Sync Failure: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<style>
    body { background-color: #f3f6fb; }
    .product-image { height: 220px; object-fit: cover; transition: transform 0.35s ease; }
    .product-card {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        border: 1px solid rgba(13, 29, 54, 0.08);
        border-radius: 0 !important;
    }
    .product-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 1rem 2.5rem rgba(13, 29, 54, 0.12) !important;
    }
    .product-card:hover .product-image {
        transform: scale(1.04);
    }
    .product-card .card-body {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .card, .rounded-4, .rounded-3, .rounded { border-radius: 0 !important; }
</style>

<div class="container-fluid px-3 px-md-4 px-lg-5 py-4 py-lg-5">
    <section class="bg-primary text-white shadow-lg p-4 p-md-5 mb-4 mb-lg-5 mx-auto" style="max-width: 1400px;">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-light text-primary mb-3">New arrivals</span>
                <h1 class="display-6 fw-bold mb-3">Enterprise hardware, delivered with confidence.</h1>
                <p class="lead text-white-50 mb-4">Browse deployment-ready systems, networking gear, and support assets from one streamlined portal.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn btn-light text-primary fw-semibold">Create account</a>
                    <a href="<?php echo BASE_URL; ?>cart.php" class="btn btn-outline-light fw-semibold">View cart</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="bg-white bg-opacity-10 border border-light border-opacity-25 p-4">
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="p-3 bg-white text-primary">
                                <div class="fw-bold fs-4">24/7</div>
                                <div class="small">Support</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white text-primary">
                                <div class="fw-bold fs-4">100%</div>
                                <div class="small">Verified</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white text-primary">
                                <div class="fw-bold fs-4">Fast</div>
                                <div class="small">Dispatch</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white text-primary">
                                <div class="fw-bold fs-4">Secure</div>
                                <div class="small">Checkout</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (empty($products)): ?>
        <div class="alert alert-light border text-center py-5 shadow-sm">
            <h2 class="h5 fw-bold mb-2">No equipment listings currently active.</h2>
            <p class="mb-0 text-muted">Log into the inventory panel as an administrator to populate the asset register lines.</p>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 mx-auto" style="max-width: 1400px;">
            <div>
                <h2 class="h4 fw-bold mb-1">Featured equipment</h2>
                <p class="text-muted mb-0">Choose from the latest available inventory.</p>
            </div>
            <span class="badge bg-secondary-subtle text-secondary mt-2 mt-md-0"><?php echo count($products); ?> items</span>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mx-auto" style="max-width: 1400px;">
            <?php foreach ($products as $item): 
                $is_out_of_stock = ((int)$item['stock_quantity'] <= 0);
            ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden product-card">
                        <?php if ($is_out_of_stock): ?>
                            <span class="position-absolute top-0 end-0 m-3 badge bg-danger">Out of stock</span>
                        <?php endif; ?>
                        <div class="position-relative bg-light">
                            <img class="card-img-top product-image" src="<?php echo UPLOADS_URL . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-gradient opacity-10"></div>
                        </div>
                        <div class="card-body d-flex flex-column p-4">
                            <span class="badge bg-primary-subtle text-primary align-self-start mb-2"><?php echo htmlspecialchars($item['category']); ?></span>
                            <h3 class="h5 fw-bold mb-2 text-dark"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="text-muted small flex-grow-1 mb-3"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                                <span class="fw-bold text-dark"><?php echo number_format($item['price'], 0); ?> UGX</span>
                                <?php if ($is_out_of_stock): ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled>Unavailable</button>
                                <?php else: ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>cart-process.php" class="m-0">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <button type="submit" class="btn btn-dark btn-sm">Add to cart</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

<?php
// Start the session to track logged-in users and shopping cart data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate total number of items in the cart for the nav badge
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $quantity) {
        $cart_count += $quantity;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Nature IT and Logistics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">

    <style>
        :root {
            --system-font: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--system-font); }
        body { background-color: #f8fafc; color: #0f172a; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px 30px; min-height: 18vh; }
        .navbar { min-height: 30px; }
        .navbar-brand, .nav-link, .navbar-toggler { line-height: 1.2; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm py-0" style="background-color: #0D1D36;">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2 py-0" href="<?php echo BASE_URL; ?>index.php">
            <span class="fs-6">🛠️</span>
            <span class="fs-6">Hardware Portal & Logistics</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="<?php echo BASE_URL; ?>index.php">Catalog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="<?php echo BASE_URL; ?>cart.php">Cart</a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-warning fw-semibold" href="<?php echo BASE_URL; ?>admin/orders.php">Admin Panel</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <span class="nav-link text-white-50">Hello, <strong class="text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger fw-semibold" href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-white-50" href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm fw-semibold text-primary ms-lg-2" href="<?php echo BASE_URL; ?>auth/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container py-4">
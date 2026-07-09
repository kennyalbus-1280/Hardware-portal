<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

$identity = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity !== '' && $password !== '' && isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, role, is_active FROM users WHERE username = :identity OR email = :identity LIMIT 1");
            $stmt->execute(['identity' => $identity]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_active'] = $user['is_active'];
                header('Location: ' . BASE_URL . 'index.php');
                exit;
            } else {
                $error_message = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Unable to sign in right now.';
        }
    } else {
        $error_message = 'Please enter both your credentials.';
    }
}
?>

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="bg-primary text-white p-4 p-md-5">
                        <h2 class="fw-bold mb-2">Welcome back</h2>
                        <p class="mb-0 opacity-75">Sign in to continue shopping and manage your orders.</p>
                    </div>
                    <div class="p-4 p-md-5">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger mb-4" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo BASE_URL; ?>auth/login.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="identity" class="form-label fw-semibold">Username or Email</label>
                                <input id="identity" name="identity" class="form-control" type="text" placeholder="Enter your username or email" value="<?php echo htmlspecialchars($identity); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <input id="password" name="password" class="form-control" type="password" placeholder="Enter your password" required>
                            </div>

                            <button class="btn btn-primary w-100 py-2 fw-bold" type="submit">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </form>

                        

                        <div class="mt-4">
                            <a class="d-flex align-items-center text-primary fw-semibold text-decoration-none" href="<?php echo BASE_URL; ?>auth/register.php">
                                <i class="bi bi-person-plus-fill me-2"></i>Create an account <i class="bi bi-chevron-right ms-2"></i>
                            </a>
                            <a class="d-flex align-items-center text-secondary fw-semibold text-decoration-none mt-2" href="<?php echo BASE_URL; ?>auth/forgot-password.php">
                                <i class="bi bi-key-fill me-2"></i>Forgot password? <i class="bi bi-chevron-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


                            <?php echo"admin=kennyalbus@gmail.com/psw=Tempae0c1a!"?>
                            <?php echo"admin=akeny@gmail.com/psw=Temp1bc1ef!"?>
                        
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Include database configuration file
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect user to home if they are already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error_message = '';
$success_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $address = trim($_POST['address']);
    
    // Simple verification validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = 'Please fill out all required configuration fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please provide a valid email format address.';
    } else {
        try {
            // Check if user or email already exists in MySQL database
            $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :user OR email = :email");
            $check_stmt->execute(['user' => $username, 'email' => $email]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = 'Username or Email is already registered.';
            } else {
                // Securely hash the text password using BCrypt
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Insert account data into MySQL
                $insert_stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, shipping_address) VALUES (:username, :email, :password, :address)");
                $insert_stmt->execute([
                    'username' => $username,
                    'email'    => $email,
                    'password' => $hashed_password,
                    'address'  => $address
                ]);
                
                $success_message = 'Account created successfully! Proceed to <a href="login.php">Login</a>.';
            }
        } catch (\PDOException $e) {
            $error_message = 'Registration failed: Structural system database error.';
        }
    }
}

// Include global layout header (Adjusting path back out of the auth subdirectory)
//require_once '../includes/header.php';
?>

<style>
    .auth-container { max-width: 450px; margin: 40px auto; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .auth-title { font-size: 22px; color: #2c3e50; margin-bottom: 20px; text-align: center; font-weight: bold; }
    
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 14px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 15px; background-color: #f8fafc; }
    .form-control:focus { border-color: #3498db; background-color: #fff; outline: none; }
    
    .btn-auth { background-color: #3498db; color: white; border: none; width: 100%; padding: 12px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
    .btn-auth:hover { background-color: #2980b9; }
    
    .auth-footer { text-align: center; margin-top: 20px; font-size: 14px; color: #64748b; }
    .auth-footer a { color: #3498db; text-decoration: none; font-weight: 500; }
    .auth-footer a:hover { text-decoration: underline; }
    
    /* Notification alerts */
    .alert { padding: 12px; border-radius: 4px; font-size: 14px; margin-bottom: 15px; font-weight: 500; }
    .alert-error { background-color: #ffeeeb; color: #c62828; border: 1px solid #ffd1c9; }
    .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
</style>

<div class="auth-container">
    <h2 class="auth-title">Create an Account</h2>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" class="form-control" required value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" class="form-control" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Password *</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="address">Shipping Delivery Address</label>
            <textarea id="address" name="address" class="form-control" rows="3"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
        </div>
        
        <button type="submit" class="btn-auth">Register</button>
    </form>
    
    <div class="auth-footer">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

<?php 
// Include global layout footer file
//require_once '../includes/footer.php'; 
?>

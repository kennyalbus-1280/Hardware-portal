<?php
// Include database connection configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';

// Ensure session state is initialized cleanly at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in to prevent duplicate sessions
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
        header("Location: /ecommerce/admin/dashboard.php");
    } else {
        header("Location: /ecommerce/index.php");
    }
    exit;
}

$error_message = '';

// ─── PROCESS SUBMITTED AUTHENTICATION MATRICES ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_login'])) {
    $identity = trim($_POST['identity']); // Can accept either username OR email address
    $password = $_POST['password'];

    if (empty($identity) || empty($password)) {
        $error_message = "All validation fields must be populated.";
    } else {
       try {
            // ─── FIXED: Separate placeholders used for username and email ───
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, role, is_active FROM users WHERE username = :username OR email = :email LIMIT 1");
            
            // Pass the same $identity variable to both specific parameters
            $stmt->execute([
                'username' => $identity,
                'email'    => $identity
            ]);
            
            $user = $stmt->fetch();

            if ($user) {
                // 1. SECURITY POLICY CHECK: Is the user banned/suspended?
                if ((int)$user['is_active'] !== 1) {
                    $error_message = "Access Denied: This account profile has been suspended by system administration.";
                } 
                // 2. CRYPTOGRAPHIC VERIFICATION: Match cleartext input against stored BCrypt hash string
                elseif (password_verify($password, $user['password_hash'])) {
                    
                    // Re-generate session ID to completely mitigate Session Fixation risks
                    session_regenerate_id(true);

                    // Hydrate system global session arrays
                    $_SESSION['user_id']  = (int)$user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email']    = $user['email'];
                    $_SESSION['role']     = strtolower($user['role']);

                    // Route user to appropriate portal tier based on status boundaries
                    if ($_SESSION['role'] === 'admin') {
                        header("Location: /ecommerce/admin/dashboard.php");
                    } else {
                        header("Location: /ecommerce/index.php");
                    }
                    exit;
                } else {
                    $error_message = "Invalid identity credentials provided.";
                }
            } else {
                // Shared generic error message pattern to counteract profile mining/harvesting vectors
                $error_message = "Invalid identity credentials provided.";
            }
        } catch (\PDOException $e) {
            $error_message = "Authentication subsystem failure: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Include global header file layout
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';
?>

<style>
    * {
        font-family: var(--system-font);
    }
    .login-wrapper { max-width: 400px; margin: 60px auto; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .login-title { font-size: 22px; font-weight: 700; color: #1e293b; margin: 0 0 5px 0; text-align: center; }
    .login-subtitle { font-size: 14px; color: #64748b; margin: 0 0 25px 0; text-align: center; }
    
    .form-box { display: flex; flex-direction: column; gap: 16px; }
    .input-wrapper { display: flex; flex-direction: column; gap: 6px; }
    .input-wrapper label { font-size: 13px; font-weight: 600; color: #475569; }
    .login-input { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; width: 100%; outline: none; }
    .login-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    
    .btn-login-action { background: #3b82f6; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 0.2s; margin-top: 5px; }
    .btn-login-action:hover { background: #2563eb; }
    
    .login-alert { padding: 12px 16px; border-radius: 6px; font-size: 14px; line-height: 1.5; margin-bottom: 20px; background: #ffeeeb; color: #c62828; border: 1px solid #ffd1c9; }
    .form-footer-options { display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 13px; }
</style>

<div class="login-wrapper">
    <h2 class="login-title">Sign In</h2>
    <p class="login-subtitle">Access your specialized hardware catalog account environment.</p>

    <?php if (!empty($error_message)): ?>
        <div class="login-alert"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="form-box" autocomplete="off">
        <div class="input-wrapper">
            <label for="identity">Username or Email Address</label>
            <input type="text" id="identity" name="identity" class="login-input" placeholder="e.g., admin_demo" value="<?php echo isset($_POST['identity']) ? htmlspecialchars($_POST['identity']) : ''; ?>" required>
        </div>

        <div class="input-wrapper">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="login-input" placeholder="Enter your secret credentials" required>
        </div>

        <div class="form-footer-options">
            <a href="/ecommerce/auth/forgot-password.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Forgot Password?</a>
        </div>

        <button type="submit" name="submit_login" class="btn-login-action">Authenticate Session</button>
    </form>
    

    <div style="color:black; width:100%; font-size:11px; line-height:3px; margin-top:8px;">
                            <p>admin=kennyalbus@gmail.com/psw=Tempae0c1a!</p>
                            <p>admin=akeny@gmail.com/psw=Temp1bc1ef!</p>
                        </div>

    <div style="text-align: center; margin-top: 25px; font-size: 13px; color: #64748b; padding-top: 15px; border-top: 1px solid #f1f5f9;">
        New tracking node user? <a href="/ecommerce/register.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Create an Account</a>
    </div>
</div>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; 
?>
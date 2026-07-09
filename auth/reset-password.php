<?php
// Secure global path mapping regardless of folder depth
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';

// Import PHPMailer classes into the global scope
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Points directly to your composer autoloader at the root level
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/vendor/autoload.php';
$success_message = '';
$error_message = '';
$valid_token = false;

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (!empty($token)) {
    try {
        // Validate token existence and expiration window checks
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE reset_token = :token AND token_expires > NOW() AND is_active = 1");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if ($user) {
            $valid_token = true;

            // Process new password submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
                $new_pass = $_POST['new_password'];

                if (strlen($new_pass) < 6) {
                    $error_message = "Security Violation: Password must contain at least 6 characters.";
                } else {
                    // Hash new credentials cleanly
                    $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

                    // Update password and clear the temporary token keys instantly
                    $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :pass, reset_token = NULL, token_expires = NULL WHERE user_id = :user_id");
                    $update_stmt->execute([
                        'pass'    => $hashed_password,
                        'user_id' => $user['user_id']
                    ]);

                    $success_message = "Success! Your password has been updated. <a href='/ecommerce/login.php' style='font-weight:700; color:#166534;'>Sign In with new credentials</a>";
                    $valid_token = false; // Close down form interface display loops
                }
            }
        } else {
            $error_message = "The authentication key token is invalid or has expired. Please request a new recovery link.";
        }
    } catch (\PDOException $e) {
        $error_message = "Database synchronization error: " . htmlspecialchars($e->getMessage());
    }
} else {
    $error_message = "Missing access parameter token configuration pipeline rules.";
}
?>

<div style="max-width: 420px; margin: 60px auto; background: white; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <h3 style="margin-top:0; color:#1e293b;">Configure New Password</h3>

    <?php if(!empty($success_message)): ?>
        <div style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; padding:12px; border-radius:6px; font-size:14px; margin-bottom:20px;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if(!empty($error_message)): ?>
        <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:12px; border-radius:6px; font-size:14px; margin-bottom:20px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if ($valid_token): ?>
        <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" style="display:flex; flex-direction:column; gap:15px;">
            <div style="display:flex; flex-direction:column; gap:5px;">
                <label style="font-size:13px; font-weight:600; color:#475569;">Enter New Secure Password</label>
                <input type="password" name="new_password" required placeholder="Minimum 6 characters" style="padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
            </div>
            <button type="submit" name="update_password" style="background:#10b981; color:white; border:none; padding:12px; border-radius:6px; font-weight:700; cursor:pointer;">Update System Hash Keys</button>
        </form>
    <?php else: ?>
        <a href="/ecommerce/forgot-password.php" style="display:block; text-align:center; margin-top:15px; color:#3b82f6; text-decoration:none; font-size:14px; font-weight:600;">← Return to Request Screen</a>
    <?php endif; ?>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; ?>
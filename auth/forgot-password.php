<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/header.php';

// Import PHPMailer classes into the global scope
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If installed via Composer, just require the vendor autoloader file directly:
require $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/vendor/autoload.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email address layout.";
    } else {
        try {
            // Check if the user exists - Added username selection for email personalization
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = :email AND is_active = 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a secure single-use token block
                $token = bin2hex(random_bytes(32));
                // Set token lifespan to expire in 1 hour
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Securely store the token inside your database
                $update_stmt = $pdo->prepare("UPDATE users SET reset_token = :token, token_expires = :expires WHERE user_id = :user_id");
                $update_stmt->execute([
                    'token'   => $token,
                    'expires' => $expires_at,
                    'user_id' => $user['user_id']
                ]);

                // Construct the secure adjustment callback URL path
                // Keeps paths clear and prevents subfolder inclusion logic mismatches
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/ecommerce/reset-password.php?token=" . $token;

                // ─── PHPMailer Live Background Dispatch Engine ───
                $mail = new PHPMailer(true);

                // SMTP Engine Server Configurations
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';             // Target Google Mail Infrastructure
                $mail->SMTPAuth   = true;
                $mail->Username   = 'kennyalbus@gmail.com'; // Your full enterprise/personal Gmail account
                $mail->Password   = 'yteummpxmydscwrr';          // Paste your 16-character App Password here (No Spaces)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Sender & Inbound Recipient Mappings
                $mail->setFrom('kennyalbus@gmail.com', 'Hardware Logistics Support');
                $mail->addAddress($email, $user['username']);

                // Transactional Email HTML Template Payload
                $mail->isHTML(true);
                $mail->Subject = 'Account Access Key Password Reset Request';
                $mail->Body    = "
                    <div style='font-family:sans-serif; padding:25px; color:#1e293b; max-width:550px; border:1px solid #e2e8f0; border-radius:8px; background-color:#ffffff;'>
                        <h2 style='color:#0f172a; margin-top:0; border-bottom:2px solid #f1f5f9; padding-bottom:10px;'>Password Reset Request</h2>
                        <p>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                        <p>We received an official request to clear and modify your system password credentials. Click the secure gateway button down below to update your network configuration tracking keys.</p>
                        <div style='margin:30px 0; text-align:center;'>
                            <a href='{$reset_link}' style='background:#2563eb; color:#ffffff; padding:12px 24px; text-decoration:none; font-weight:bold; border-radius:6px; display:inline-block; box-shadow:0 2px 4px rgba(37,99,235,0.2);'>Reset System Password</a>
                        </div>
                        <p style='font-size:13px; color:#64748b; background:#f8fafc; padding:10px; border-radius:4px; border-left:3px solid #cbd5e1;'>
                            <strong>Security Notice:</strong> This authorization token is single-use and expires strictly within 1 hour. If you did not initialize this request, please change your security parameters immediately.
                        </p>
                    </div>
                ";

                $mail->send();
                
                // Safe generic confirmation to the client frame
                $success_message = "If that email matches an active account registry inside our network, a secure recovery link has been dispatched to your inbox.";
            } else {
                // Identical safe response mapping to completely mitigate malicious email scanning/harvesting
                $success_message = "If that email matches an active account registry inside our network, a secure recovery link has been dispatched to your inbox.";
            }
        } catch (Exception $e) {
            $error_message = "Background Mailer Failure: Could not route transmission payload. Line info: {$mail->ErrorInfo}";
        } catch (\PDOException $e) {
            $error_message = "Recovery pipeline database failure: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<div style="max-width: 420px; margin: 60px auto; background: white; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <h3 style="margin-top:0; color:#1e293b;">Recover Account Password</h3>
    <p style="color:#64748b; font-size:14px; line-height:1.5; margin-bottom:20px;">Provide your registered account email. We will issue a secure link configuration to overwrite your password hash string safely.</p>

    <?php if(!empty($success_message)): ?>
        <div style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; padding:12px; border-radius:6px; font-size:14px; margin-bottom:20px; word-break:break-all;"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if(!empty($error_message)): ?>
        <div style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:12px; border-radius:6px; font-size:14px; margin-bottom:20px;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot-password.php" style="display:flex; flex-direction:column; gap:15px;">
        <div style="display:flex; flex-direction:column; gap:5px;">
            <label style="font-size:13px; font-weight:600; color:#475569;">Email Address</label>
            <input type="email" name="email" required placeholder="e.g., customer@email.com" style="padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
        </div>
        <button type="submit" name="request_reset" style="background:#3b82f6; color:white; border:none; padding:12px; border-radius:6px; font-weight:700; cursor:pointer;">Generate Reset Link</button>
    </form>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/includes/footer.php'; ?>
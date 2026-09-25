<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ratbugas@gmail.com';
        $mail->Password   = 'xtko mhzh rfmf xtke';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('ratbugas@gmail.com', 'Inventory System');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Inventory System';
        $mail->Body    = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px;'><div style='background: white; padding: 30px; border-radius: 10px; text-align: center;'><h2 style='color: #667eea; margin-bottom: 20px;'>Password Reset Request</h2><p style='color: #333; font-size: 16px; margin-bottom: 20px;'>You requested to reset your password. Use the OTP below to proceed:</p><div style='background: #f0f0f0; padding: 15px; border-radius: 8px; margin: 20px 0;'><h1 style='color: #764ba2; font-size: 32px; letter-spacing: 5px; margin: 0;'>$otp</h1></div><p style='color: #666; font-size: 14px;'>This OTP is valid for 10 minutes. Do not share this code with anyone.</p><hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'><p style='color: #999; font-size: 12px;'>If you didn't request this, please ignore this email.</p></div></div>";
        $mail->AltBody = "Your OTP for password reset is: $otp. Valid for 10 minutes.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;
$message = '';
$type = '';

// Step 1: Request email
if(isset($_POST['request_otp'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? AND email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $user_email);
    
    if($stmt->num_rows > 0) {
        $stmt->fetch();
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $update = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE id = ?");
        $update->bind_param("ssi", $otp, $expiry, $user_id);
        
        if($update->execute()) {
            if(sendOTP($user_email, $otp)) {
                $_SESSION['reset_user_id'] = $user_id;
                $_SESSION['reset_username'] = $username;
                $_SESSION['reset_step'] = 2;
                $_SESSION['reset_email'] = $user_email;
                $message = "OTP has been sent to your email. Please check your inbox.";
                $type = "success";
                $step = 2;
            } else {
                $message = "Failed to send OTP. Please try again later.";
                $type = "error";
            }
        } else {
            $message = "Database error. Please try again.";
            $type = "error";
        }
    } else {
        $message = "Username and email combination not found!";
        $type = "error";
    }
    $stmt->close();
}
// Step 2: Verify OTP
elseif(isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $user_id = $_SESSION['reset_user_id'];
    
    $stmt = $conn->prepare("SELECT reset_otp, reset_otp_expiry FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($stored_otp, $expiry);
    $stmt->fetch();
    
    if($stored_otp && strtotime($expiry) > time()) {
        if($otp == $stored_otp) {
            $_SESSION['reset_step'] = 3;
            $step = 3;
            $message = "OTP verified! Please set your new password.";
            $type = "success";
        } else {
            $message = "Invalid OTP! Please try again.";
            $type = "error";
        }
    } else {
        $message = "OTP has expired. Please request a new one.";
        $type = "error";
        $_SESSION['reset_step'] = 1;
        $step = 1;
    }
    $stmt->close();
}
// Step 3: Reset password
elseif(isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if($new_password !== $confirm_password) {
        $message = "Passwords do not match!";
        $type = "error";
    } elseif(strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters!";
        $type = "error";
    } else {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $user_id = $_SESSION['reset_user_id'];
        
        $update = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?");
        $update->bind_param("si", $hashed, $user_id);
        
        if($update->execute()) {
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_username']);
            unset($_SESSION['reset_email']);
            $message = "Password has been reset successfully! You can now login.";
            $type = "success";
            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
        } else {
            $message = "Failed to reset password. Please try again.";
            $type = "error";
        }
    }
}
// Resend OTP
elseif(isset($_POST['resend_otp'])) {
    $user_id = $_SESSION['reset_user_id'];
    $email = $_SESSION['reset_email'];
    
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $update = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE id = ?");
    $update->bind_param("ssi", $otp, $expiry, $user_id);
    
    if($update->execute() && sendOTP($email, $otp)) {
        $message = "New OTP has been sent to your email!";
        $type = "success";
    } else {
        $message = "Failed to resend OTP. Please try again.";
        $type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - Inventory Management System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="facebook-login-container">
        <!-- Left Brand Section -->
        <div class="facebook-brand-section">
            <div class="brand-wrapper">
                <div class="brand-logo-wrapper">
                    <i class="fas fa-boxes"></i>
                    <span>InventoryMS</span>
                </div>
                <h1>Reset Your<br>Password</h1>
                <p>Don't worry! We'll help you recover your account securely.</p>
                <div class="brand-features-grid">
                    <div class="feature-item"><i class="fas fa-key"></i><span>Secure OTP verification</span></div>
                    <div class="feature-item"><i class="fas fa-clock"></i><span>10-minute validity</span></div>
                    <div class="feature-item"><i class="fas fa-envelope"></i><span>Instant email delivery</span></div>
                    <div class="feature-item"><i class="fas fa-shield-alt"></i><span>Strong encryption</span></div>
                </div>
            </div>
        </div>

        <!-- Right Form Section -->
        <div class="facebook-login-form">
            <div class="login-card">
                <?php if($step == 1): ?>
                    <h2>Forgot Password?</h2>
                    <p class="login-subtitle">Enter your username and email to reset your password</p>
                    <?php if($message != ''): ?>
                        <div class="message <?php echo $type; ?>">
                            <i class="fas <?php echo $type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="input-field">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                        <div class="input-field">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" placeholder="Email Address" required>
                        </div>
                        <button type="submit" name="request_otp" class="login-btn">
                            <span>Send OTP</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                    <div class="login-footer">
                        <p><a href="login.php" class="forgot-link">← Back to Login</a></p>
                    </div>

                <?php elseif($step == 2): ?>
                    <h2>Verify OTP</h2>
                    <p class="login-subtitle">Enter the 6-digit code sent to <?php echo isset($_SESSION['reset_email']) ? htmlspecialchars($_SESSION['reset_email']) : 'your email'; ?></p>
                    <?php if($message != ''): ?>
                        <div class="message <?php echo $type; ?>">
                            <i class="fas <?php echo $type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="input-field">
                            <i class="fas fa-key"></i>
                            <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" pattern="\d{6}" required>
                        </div>
                        <button type="submit" name="verify_otp" class="login-btn">
                            <span>Verify OTP</span>
                            <i class="fas fa-check-circle"></i>
                        </button>
                    </form>
                    <form method="POST" style="margin-top: 15px;">
                        <button type="submit" name="resend_otp" class="login-btn" style="background: #6c757d; margin-top: 0;">
                            <span>Resend OTP</span>
                            <i class="fas fa-redo-alt"></i>
                        </button>
                    </form>
                    <div class="login-footer">
                        <p><a href="forgot_password.php" class="forgot-link">← Start Over</a></p>
                    </div>

                <?php elseif($step == 3): ?>
                    <h2>Set New Password</h2>
                    <p class="login-subtitle">Create a new password for your account</p>
                    <?php if($message != ''): ?>
                        <div class="message <?php echo $type; ?>">
                            <i class="fas <?php echo $type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="input-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="new_password" placeholder="New Password" required minlength="6">
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                        <div class="input-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                            <i class="fas fa-eye-slash toggle-password"></i>
                        </div>
                        <button type="submit" name="reset_password" class="login-btn">
                            <span>Reset Password</span>
                            <i class="fas fa-key"></i>
                        </button>
                    </form>
                    <div class="login-footer">
                        <p><a href="login.php" class="forgot-link">← Back to Login</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility for any fields that have the toggle icon
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
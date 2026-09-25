<?php
session_start();
include 'db.php';
$message = '';

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $db_username, $db_password, $db_role);
    $stmt->fetch();

    if($stmt->num_rows == 0){
        $message = "Invalid username or password";
    } elseif(!password_verify($password, $db_password)){
        $message = "Invalid username or password";
    } else {
        require_once 'functions.php';
        logActivity($user_id, "Login", "User logged in");
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user'] = $db_username;
        $_SESSION['role'] = $db_role;
        header("Location: index.php");
        exit();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Inventory Management System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="facebook-login-container">
        <div class="facebook-brand-section">
            <div class="brand-wrapper">
                <div class="brand-logo-wrapper"><i class="fas fa-boxes"></i><span>Saurelleia Premium</span></div>
                <h1>Inventory Management System</h1>
                <p>Efficiently manage your products, track stock levels, and streamline your business operations with our modern inventory solution.</p>
                <div class="brand-features-grid">
                    <div class="feature-item"><i class="fas fa-chart-line"></i><span>Real-time tracking</span></div>
                    <div class="feature-item"><i class="fas fa-bell"></i><span>Low stock alerts</span></div>
                    <div class="feature-item"><i class="fas fa-tags"></i><span>Category management</span></div>
                    <div class="feature-item"><i class="fas fa-users"></i><span>Role management</span></div>
                </div>
            </div>
        </div>

        <div class="facebook-login-form">
            <div class="login-card">
                <h2>Welcome Back</h2>
                <p class="login-subtitle">Sign in to continue to your account</p>
                
                <?php if($message != ''){ ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php } ?>
                
                <form method="POST">
                    <div class="input-field"><i class="fas fa-user"></i><input type="text" name="username" placeholder="Username" required></div>
                    <div class="input-field"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Password" required><i class="fas fa-eye-slash toggle-password" style="cursor: pointer;"></i></div>
                    <div class="form-options"><label class="remember-checkbox"><input type="checkbox" name="remember"><span>Remember me</span></label><a href="forgot_password.php" class="forgot-link">Forgot Password?</a></div>
                    <button type="submit" name="login" class="login-btn"><span>Log In</span><i class="fas fa-arrow-right"></i></button>
                </form>
                
                <div class="login-footer"><p><span class="green-check">✓</span> Secure login with password encryption</p></div>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.toggle-password')?.addEventListener('click', function() {
            const passwordInput = document.querySelector('input[name="password"]');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
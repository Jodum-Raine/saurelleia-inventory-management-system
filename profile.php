<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'db.php';
require_once 'functions.php';

$user_id = $_SESSION['user_id'];
$message = '';
$type = '';

// Handle profile update (username, email)
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);

    $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $check->bind_param("ssi", $new_username, $new_email, $user_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $message = "Username or email already in use!";
        $type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_username, $new_email, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user'] = $new_username;
            $message = "Profile updated successfully!";
            $type = "success";
            logActivity($user_id, "Profile Update", "Updated username/email");
        } else {
            $message = "Failed to update profile!";
            $type = "error";
        }
        $stmt->close();
    }
    $check->close();
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($current, $hashed)) {
        $message = "Current password is incorrect!";
        $type = "error";
    } elseif ($new !== $confirm) {
        $message = "New passwords do not match!";
        $type = "error";
    } elseif (strlen($new) < 6) {
        $message = "Password must be at least 6 characters!";
        $type = "error";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
            $type = "success";
            logActivity($user_id, "Password Change", "Changed password");
        } else {
            $message = "Failed to change password!";
            $type = "error";
        }
        $stmt->close();
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_pic']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed)) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $new_name;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($old_pic);
            $stmt->fetch();
            $stmt->close();
            if ($old_pic && file_exists($old_pic)) unlink($old_pic);

            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $destination, $user_id);
            if ($stmt->execute()) {
                $message = "Profile picture updated!";
                $type = "success";
                logActivity($user_id, "Profile Picture", "Uploaded new picture");
            } else {
                $message = "Database error while saving picture!";
                $type = "error";
            }
            $stmt->close();
        } else {
            $message = "Failed to upload image!";
            $type = "error";
        }
    } else {
        $message = "Only JPG, PNG, GIF allowed!";
        $type = "error";
    }
}

// Fetch current user data
$username = $email = $role = $profile_pic = null;
$stmt = $conn->prepare("SELECT username, email, role, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $username = $row['username'];
    $email = $row['email'];
    $role = $row['role'];
    $profile_pic = $row['profile_pic'];
}
$stmt->close();

// Activity log pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$log_query = "SELECT action, details, ip_address, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($log_query);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$logs = $stmt->get_result();

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $limit);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - Inventory System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* ===== PROFILE PAGE — PINK THEME ===== */
    .profile-container { max-width: 1200px; margin: 0 auto; }
    .profile-header {
        display: flex; gap: 30px; background: white;
        border-radius: 24px; padding: 30px; margin-bottom: 30px;
        border: 1px solid rgba(236, 72, 153, 0.15);
        align-items: center;
        box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
    }
    .profile-pic {
        width: 120px; height: 120px; border-radius: 60px;
        background: #fff5f7; display: flex;
        align-items: center; justify-content: center;
        overflow: hidden; border: 3px solid #ec4899;
        box-shadow: 0 5px 20px rgba(236, 72, 153, 0.25);
    }
    .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
    .profile-pic .default-avatar { font-size: 48px; color: #ec4899; }
    .profile-info h2 { color: #831843; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
    .profile-info p { color: #9d174d; font-size: 14px; margin-bottom: 4px; }
    .profile-info p small { opacity: 0.7; font-size: 12px; }
    .profile-tabs {
        display: flex; gap: 10px;
        border-bottom: 1px solid rgba(236, 72, 153, 0.15);
        margin-bottom: 30px;
    }
    .tab-btn {
        background: none; border: none; padding: 12px 24px;
        color: #9d174d; cursor: pointer; font-weight: 500;
        font-size: 14px; transition: all 0.3s;
        font-family: inherit; opacity: 0.7;
    }
    .tab-btn:hover { color: #ec4899; opacity: 1; }
    .tab-btn.active {
        color: #ec4899; border-bottom: 2px solid #ec4899;
        opacity: 1; font-weight: 600;
    }
    .tab-content {
        display: none; background: white;
        border-radius: 24px; padding: 30px;
        border: 1px solid rgba(236, 72, 153, 0.15);
        box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
    }
    .tab-content.active { display: block; }
    .form-group { margin-bottom: 20px; }
    .form-group label {
        display: block; color: #9d174d; font-size: 13px;
        font-weight: 600; margin-bottom: 8px;
    }
    .form-group label i { color: #ec4899; margin-right: 6px; }
    .form-group input, .form-group select {
        width: 100%; padding: 12px 16px;
        background: #fff5f7; border: 1px solid rgba(236, 72, 153, 0.2);
        border-radius: 10px; color: #831843; font-size: 14px;
        transition: all 0.3s ease; font-family: inherit;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none; background: white;
        border-color: #ec4899;
        box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
    }
    .form-group input[type="file"] {
        padding: 10px; background: #fce7f3; cursor: pointer;
    }
    .btn-submit {
        background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
        color: white; border: none; padding: 12px 28px;
        border-radius: 10px; cursor: pointer;
        font-weight: 500; font-size: 14px;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(236, 72, 153, 0.25);
        font-family: inherit;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(236, 72, 153, 0.35);
    }
    .activity-table { width: 100%; border-collapse: collapse; }
    .activity-table th {
        padding: 12px; text-align: left;
        border-bottom: 2px solid rgba(236, 72, 153, 0.15);
        color: #9d174d; font-weight: 600;
        font-size: 13px; opacity: 0.85;
    }
    .activity-table td {
        padding: 12px; text-align: left;
        border-bottom: 1px solid rgba(236, 72, 153, 0.08);
        color: #831843; font-size: 13px;
    }
    .activity-table tr:hover td { background: rgba(236, 72, 153, 0.03); }
    .pagination {
        margin-top: 20px; display: flex;
        justify-content: center; gap: 8px;
    }
    .page-link {
        padding: 8px 12px; background: #fff5f7;
        border: 1px solid rgba(236, 72, 153, 0.2);
        border-radius: 8px; color: #831843;
        text-decoration: none; font-size: 13px;
        transition: all 0.3s ease;
    }
    .page-link:hover {
        background: #ec4899; color: white;
        border-color: #ec4899;
    }
    .page-link.active {
        background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
        border-color: transparent; color: white;
        box-shadow: 0 3px 10px rgba(236, 72, 153, 0.3);
    }
    .message {
        padding: 12px 16px; border-radius: 12px;
        margin-bottom: 24px; display: flex;
        align-items: center; gap: 10px; font-size: 14px;
    }
    .message.success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #059669;
    }
    .message.error {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid rgba(220, 38, 38, 0.3);
        color: #dc2626;
    }
    </style>
</head>
<body>
    <div class="modern-sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-boxes"></i></div>
            <div class="logo-text"><h3>InventoryMS</h3><p>v2.0</p></div>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar-large">
                <?php if ($profile_pic): ?>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['user'], 0, 2)); ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($_SESSION['user']); ?></h4>
                <p><?php echo ucfirst($role); ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="sales.php" class="nav-item"><i class="fas fa-shopping-cart"></i><span>Sales</span></a>
            <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
            <a href="notes.php" class="nav-item"><i class="fas fa-sticky-note"></i><span>Notes</span></a>
            <?php if ($role == 'admin'): ?>
                <a href="admin_add_users.php" class="nav-item"><i class="fas fa-users"></i><span>User Management</span></a>
            <?php endif; ?>
            <a href="profile.php" class="nav-item active"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="modern-main">
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-pic">
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="default-avatar"><i class="fas fa-user-circle"></i></div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($username); ?></h2>
                    <p><?php echo htmlspecialchars($email); ?> · <?php echo ucfirst($role); ?></p>
                    <p><small>Member since: <?php echo date('F j, Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></small></p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="profile-tabs">
                <button class="tab-btn active" onclick="showTab('info', this)">Edit Profile</button>
                <button class="tab-btn" onclick="showTab('password', this)">Change Password</button>
                <button class="tab-btn" onclick="showTab('activity', this)">Activity Log</button>
            </div>

            <div id="info" class="tab-content active">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Profile Picture</label>
                        <input type="file" name="profile_pic" accept="image/*">
                    </div>
                    <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
                </form>
            </div>

            <div id="password" class="tab-content">
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-submit">Change Password</button>
                </form>
            </div>

            <div id="activity" class="tab-content">
                <?php if ($logs->num_rows > 0): ?>
                    <table class="activity-table">
                        <thead>
                            <tr><th>Action</th><th>Details</th><th>IP Address</th><th>Date & Time</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No activity recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>
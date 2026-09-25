<?php
session_start();
include 'db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: index.php");
    exit();
}

$message = '';
$type = '';

if(isset($_POST['add_user'])){
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];  // now 'staff' or 'admin' (matches enum)
    
    if(empty($username) || empty($email) || empty($password)) {
        $message = "All fields are required!";
        $type = "error";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0){
            $message = "Username already exists!";
            $type = "error";
        } else {
            $stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $stmt2->store_result();
            
            if($stmt2->num_rows > 0){
                $message = "Email already registered!";
                $type = "error";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // No 'status' column, role is enum with 'admin','manager','staff'
                $insert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $insert->bind_param("ssss", $username, $email, $hash, $role);
                
                if($insert->execute()){
                    require_once 'functions.php';
                    logActivity($_SESSION['user_id'], "Add User", "Created user: $username with role $role");
                    $message = "User created successfully! Role: " . ($role == 'admin' ? 'Administrator' : 'Staff');
                    $type = "success";
                    echo "<script>setTimeout(function(){ window.location.href = 'admin_add_users.php'; }, 1500);</script>";
                } else {
                    $message = "Failed to create user!";
                    $type = "error";
                }
                $insert->close();
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// Show ALL users
$result = mysqli_query($conn, "SELECT id, username, email, role FROM users ORDER BY id DESC");
$total_users = mysqli_num_rows($result);
$admin_count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$admin_count = mysqli_fetch_assoc($admin_count_result)['count'];
$staff_count = $total_users - $admin_count;
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management - Inventory System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <a href="sales.php" class="nav-item"><i class="fas fa-shopping-cart"></i><span>Sales</span></a>
    <style>
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        border: 1px solid #10b981;
        border-radius: 8px;
        padding: 12px 20px;
        color: #059669;
        z-index: 10001;
        animation: slideUp 0.3s ease;
        font-size: 14px;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.15);
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
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
            <div class="user-avatar-large"><?php echo strtoupper(substr($_SESSION['user'], 0, 2)); ?></div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($_SESSION['user']); ?></h4>
                <p>Administrator</p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="index.php" class="nav-item"><i class="fas fa-box"></i><span>Products</span></a>
            <a href="index.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
            <a href="admin_add_users.php" class="nav-item active"><i class="fas fa-users"></i><span>User Management</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="modern-main">
        <!-- Header removed – no search bar, no icons -->
        
        <div class="welcome-banner">
            <div class="banner-content"><h1>User Management</h1><p>Manage system users, create new accounts, and control access levels.</p></div>
            <div class="banner-stats"><div class="stat-item"><div class="stat-value"><?php echo date('l'); ?></div><div class="stat-label"><?php echo date('F j, Y'); ?></div></div></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card-modern"><div class="stat-icon purple"><i class="fas fa-users"></i></div><div class="stat-details"><h3>Total Staff</h3><div class="stat-number"><?php echo $staff_count; ?></div><span class="stat-trend">Non-admin users</span></div></div>
            <div class="stat-card-modern"><div class="stat-icon blue"><i class="fas fa-user-plus"></i></div><div class="stat-details"><h3>New This Month</h3><div class="stat-number" id="newThisMonth">0</div><span class="stat-trend">This month</span></div></div>
            <div class="stat-card-modern"><div class="stat-icon green"><i class="fas fa-user-check"></i></div><div class="stat-details"><h3>Total Users</h3><div class="stat-number"><?php echo $total_users; ?></div><span class="stat-trend">All users</span></div></div>
            <div class="stat-card-modern"><div class="stat-icon orange"><i class="fas fa-user-shield"></i></div><div class="stat-details"><h3>Admin Accounts</h3><div class="stat-number"><?php echo $admin_count; ?></div><span class="stat-trend">System admins</span></div></div>
        </div>

        <!-- Only "Add New User" action card -->
        <div class="action-cards">
            <div class="action-card add-user-card">
                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                <h3>Add New User</h3>
                <p>Create a new staff account with custom role permissions</p>
                <button class="action-btn" onclick="openAddUserModal()"><i class="fas fa-plus"></i> Create User</button>
            </div>
        </div>

        <div class="products-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> System Users</h2>
                <div class="table-controls">
                    <div class="filter-group"><i class="fas fa-filter"></i><select id="roleFilter" onchange="filterTable()"><option value="">All Roles</option><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
                    <div class="search-group"><i class="fas fa-search"></i><input type="text" id="userSearch" placeholder="Search users..." onkeyup="filterTable()"></div>
                    <button class="refresh-btn" onclick="location.reload()" style="background: #1a1d24; border: 1px solid #2a2e3a; border-radius: 8px; padding: 8px 12px; color: #e0e4f0; cursor: pointer;"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>

            <div class="products-table-modern">
                <table id="usersTable" width="100%">
                    <thead>
                        <tr><th onclick="sortTable(0)" style="cursor: pointer;">ID <i class="fas fa-sort"></i></th><th onclick="sortTable(1)" style="cursor: pointer;">Username <i class="fas fa-sort"></i></th><th onclick="sortTable(2)" style="cursor: pointer;">Email <i class="fas fa-sort"></i></th><th onclick="sortTable(3)" style="cursor: pointer;">Role <i class="fas fa-sort"></i></th><th>Actions</th></tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if($total_users > 0): while($row = mysqli_fetch_assoc($result)): ?>
                            <tr data-role="<?php echo $row['role']; ?>" data-username="<?php echo strtolower($row['username']); ?>" data-email="<?php echo strtolower($row['email']); ?>" data-id="<?php echo $row['id']; ?>">
                                <td>#<?php echo $row['id']; ?></td>
                                <td><div style="display: flex; align-items: center; gap: 10px;"><div style="width: 32px; height: 32px; background: linear-gradient(135deg, <?php echo $row['role'] == 'admin' ? '#f59e0b' : '#10b981'; ?> 0%, <?php echo $row['role'] == 'admin' ? '#d97706' : '#059669'; ?> 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;"><?php echo strtoupper(substr($row['username'], 0, 2)); ?></div><div><div style="color: white; font-weight: 500;"><?php echo htmlspecialchars($row['username']); ?></div></div></div></td>
                                <td><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; background: <?php echo $row['role'] == 'admin' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; color: <?php echo $row['role'] == 'admin' ? '#f59e0b' : '#10b981'; ?>;"><i class="fas <?php echo $row['role'] == 'admin' ? 'fa-user-shield' : 'fa-user'; ?>"></i> <?php echo ucfirst($row['role']); ?></span></td>
                                <td><button onclick="editUser(<?php echo $row['id']; ?>)" style="background: rgba(16, 185, 129, 0.1); border: none; border-radius: 8px; padding: 8px 12px; color: #10b981; cursor: pointer; margin: 0 5px;"><i class="fas fa-edit"></i></button><button onclick="resetPassword(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['username']); ?>')" style="background: rgba(102, 126, 234, 0.1); border: none; border-radius: 8px; padding: 8px 12px; color: #667eea; cursor: pointer; margin: 0 5px;"><i class="fas fa-key"></i></button><button onclick="deleteUser(<?php echo $row['id']; ?>)" style="background: rgba(220, 38, 38, 0.1); border: none; border-radius: 8px; padding: 8px 12px; color: #dc2626; cursor: pointer; margin: 0 5px;"><i class="fas fa-trash-alt"></i></button></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 60px;"><i class="fas fa-users-slash" style="font-size: 48px; color: #2a2e3a;"></i><p style="color: #8b8f9e; margin-top: 16px;">No users found</p><button onclick="openAddUserModal()" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 8px; margin-top: 10px; cursor: pointer;">Create your first user</button></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modern-pagination"><div class="pagination-info">Showing <span id="showingStart">0</span> - <span id="showingEnd">0</span> of <span id="totalCount"><?php echo $total_users; ?></span> users</div><div class="pagination-controls" id="paginationControls"></div></div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: #1a1d24; border-radius: 24px; width: 90%; max-width: 500px; border: 1px solid #2a2e3a;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 24px; border-bottom: 1px solid #2a2e3a;"><h3 style="color: white; margin: 0;"><i class="fas fa-user-plus"></i> Add New User</h3><span onclick="closeAddUserModal()" style="color: #8b8f9e; font-size: 28px; cursor: pointer;">&times;</span></div>
            <form method="POST" style="padding: 24px;">
                <?php if($message != ''){ ?><div style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: <?php echo $type == 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(220,38,38,0.1)'; ?>; color: <?php echo $type == 'success' ? '#10b981' : '#dc2626'; ?>"><i class="fas <?php echo $type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php } ?>
                <div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-user"></i> Username</label><input type="text" name="username" placeholder="Enter username" required style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div>
                <div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-envelope"></i> Email Address</label><input type="email" name="email" placeholder="Enter email address" required style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div>
                <div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-lock"></i> Password</label><input type="password" name="password" placeholder="Enter password" required minlength="6" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div>
                <div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-user-tag"></i> Role</label>
                    <select name="role" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;">
                        <option value="staff">Staff User</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px;"><button type="button" onclick="closeAddUserModal()" style="flex: 1; padding: 12px; background: #2a2e3a; border: none; border-radius: 8px; color: white; cursor: pointer;">Cancel</button><button type="submit" name="add_user" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; cursor: pointer;"><i class="fas fa-save"></i> Create User</button></div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: #1a1d24; border-radius: 24px; width: 90%; max-width: 500px; border: 1px solid #2a2e3a;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 24px; border-bottom: 1px solid #2a2e3a;"><h3 style="color: white; margin: 0;"><i class="fas fa-user-edit"></i> Edit User</h3><span onclick="closeEditUserModal()" style="color: #8b8f9e; font-size: 28px; cursor: pointer;">&times;</span></div>
            <div style="padding: 24px;"><input type="hidden" id="edit_user_id"><div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-user"></i> Username</label><input type="text" id="edit_username" placeholder="Enter username" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div><div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-envelope"></i> Email Address</label><input type="email" id="edit_email" placeholder="Enter email address" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div><div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-user-tag"></i> Role</label><select id="edit_role" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"><option value="staff">Staff User</option><option value="admin">Administrator</option></select></div><div style="display: flex; gap: 12px;"><button type="button" onclick="closeEditUserModal()" style="flex: 1; padding: 12px; background: #2a2e3a; border: none; border-radius: 8px; color: white; cursor: pointer;">Cancel</button><button type="button" onclick="updateUser()" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; cursor: pointer;"><i class="fas fa-save"></i> Update User</button></div></div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: #1a1d24; border-radius: 24px; width: 90%; max-width: 500px; border: 1px solid #2a2e3a;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 24px; border-bottom: 1px solid #2a2e3a;"><h3 style="color: white; margin: 0;"><i class="fas fa-key"></i> Reset Password</h3><span onclick="closeResetModal()" style="color: #8b8f9e; font-size: 28px; cursor: pointer;">&times;</span></div>
            <div style="padding: 24px;"><input type="hidden" id="reset_user_id"><div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-user"></i> Username</label><input type="text" id="reset_username" readonly style="width: 100%; padding: 12px; background: #2a2e3a; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div><div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-lock"></i> New Password</label><input type="password" id="new_password" placeholder="Enter new password" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div><div style="margin-bottom: 20px;"><label style="display: block; color: #8b8f9e; margin-bottom: 8px;"><i class="fas fa-lock"></i> Confirm Password</label><input type="password" id="confirm_password" placeholder="Confirm new password" style="width: 100%; padding: 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: white;"></div><div style="display: flex; gap: 12px;"><button type="button" onclick="closeResetModal()" style="flex: 1; padding: 12px; background: #2a2e3a; border: none; border-radius: 8px; color: white; cursor: pointer;">Cancel</button><button type="button" onclick="confirmReset()" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; cursor: pointer;"><i class="fas fa-key"></i> Reset Password</button></div></div>
        </div>
    </div>

    <script>
        // All JavaScript unchanged except for filter role values – already updated
        let currentPage = 1;
        let rowsPerPage = 10;
        let currentSortColumn = 0;
        let currentSortOrder = 'asc';
        let allRows = [];
        
        function storeRows() {
            const tbody = document.getElementById('usersTableBody');
            allRows = Array.from(tbody.querySelectorAll('tr')).filter(row => row.cells.length > 1);
        }
        
        function filterTable() {
            storeRows();
            const roleFilter = document.getElementById('roleFilter').value;
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            
            let visibleRows = allRows.filter(row => {
                let show = true;
                const role = row.getAttribute('data-role');
                const username = row.getAttribute('data-username');
                const email = row.getAttribute('data-email');
                
                if(roleFilter && role !== roleFilter) show = false;
                if(searchTerm && !username.includes(searchTerm) && !email.includes(searchTerm)) show = false;
                return show;
            });
            
            visibleRows.sort((a, b) => {
                let aVal, bVal;
                if(currentSortColumn === 0) {
                    aVal = parseInt(a.cells[0].innerText.replace('#', ''));
                    bVal = parseInt(b.cells[0].innerText.replace('#', ''));
                } else if(currentSortColumn === 1) {
                    aVal = a.getAttribute('data-username');
                    bVal = b.getAttribute('data-username');
                } else if(currentSortColumn === 2) {
                    aVal = a.getAttribute('data-email');
                    bVal = b.getAttribute('data-email');
                } else {
                    aVal = a.getAttribute('data-role');
                    bVal = b.getAttribute('data-role');
                }
                
                if(currentSortOrder === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
            
            const totalItems = visibleRows.length;
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            allRows.forEach(row => row.style.display = 'none');
            visibleRows.slice(start, end).forEach(row => row.style.display = '');
            
            document.getElementById('totalCount').innerText = totalItems;
            document.getElementById('showingStart').innerText = totalItems ? start + 1 : 0;
            document.getElementById('showingEnd').innerText = Math.min(end, totalItems);
            
            updatePagination(totalItems);
        }
        
        function updatePagination(totalItems) {
            const totalPages = Math.ceil(totalItems / rowsPerPage);
            const container = document.getElementById('paginationControls');
            container.innerHTML = '';
            if(totalPages <= 1) return;
            
            const prevBtn = document.createElement('a');
            prevBtn.className = 'page-btn';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; filterTable(); } };
            prevBtn.style.cssText = 'padding: 8px 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: #e0e4f0; text-decoration: none; cursor: pointer; margin: 0 2px;';
            container.appendChild(prevBtn);
            
            for(let i = 1; i <= Math.min(totalPages, 5); i++) {
                const btn = document.createElement('a');
                btn.className = 'page-btn';
                btn.innerText = i;
                btn.onclick = () => { currentPage = i; filterTable(); };
                btn.style.cssText = 'padding: 8px 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: #e0e4f0; text-decoration: none; cursor: pointer; margin: 0 2px;';
                if(i === currentPage) {
                    btn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    btn.style.color = 'white';
                }
                container.appendChild(btn);
            }
            
            const nextBtn = document.createElement('a');
            nextBtn.className = 'page-btn';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; filterTable(); } };
            nextBtn.style.cssText = 'padding: 8px 12px; background: #0f1117; border: 1px solid #2a2e3a; border-radius: 8px; color: #e0e4f0; text-decoration: none; cursor: pointer; margin: 0 2px;';
            container.appendChild(nextBtn);
        }
        
        function sortTable(column) {
            if(currentSortColumn === column) {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortOrder = 'asc';
            }
            filterTable();
        }
        
        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        function openAddUserModal() { document.getElementById('addUserModal').style.display = 'flex'; }
        function closeAddUserModal() { document.getElementById('addUserModal').style.display = 'none'; }
        
        function editUser(id) {
            fetch('get_user.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_user_id').value = data.id;
                    document.getElementById('edit_username').value = data.username;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('editUserModal').style.display = 'flex';
                })
                .catch(() => showToast('Error loading user'));
        }
        function closeEditUserModal() { document.getElementById('editUserModal').style.display = 'none'; }
        
        function updateUser() {
            const formData = new FormData();
            formData.append('user_id', document.getElementById('edit_user_id').value);
            formData.append('username', document.getElementById('edit_username').value);
            formData.append('email', document.getElementById('edit_email').value);
            formData.append('role', document.getElementById('edit_role').value);
            
            fetch('update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showToast('User updated successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Error: ' + (data.error || 'Update failed'));
                }
            })
            .catch(() => showToast('Error updating user'));
        }
        
        function resetPassword(id, username) {
            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_username').value = username;
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('resetModal').style.display = 'flex';
        }
        function closeResetModal() { document.getElementById('resetModal').style.display = 'none'; }
        
        function confirmReset() {
            const userId = document.getElementById('reset_user_id').value;
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if(newPass !== confirmPass) {
                showToast('Passwords do not match');
                return;
            }
            if(newPass.length < 6) {
                showToast('Password must be at least 6 characters');
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('new_password', newPass);
            
            fetch('reset_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showToast('Password reset successfully');
                    closeResetModal();
                } else {
                    showToast('Error: ' + (data.error || 'Reset failed'));
                }
            })
            .catch(() => showToast('Error resetting password'));
        }
        
        function deleteUser(id) {
            if(confirm('Are you sure you want to delete this user?')) {
                window.location.href = 'delete_user.php?id=' + id;
            }
        }
        
        window.onclick = function(e) {
            const modals = ['addUserModal', 'editUserModal', 'resetModal'];
            modals.forEach(id => {
                const modal = document.getElementById(id);
                if(e.target === modal) modal.style.display = 'none';
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            storeRows();
            filterTable();
            document.getElementById('newThisMonth').innerText = Math.floor(Math.random() * 10) + 1;
        });
    </script>
</body>
</html>
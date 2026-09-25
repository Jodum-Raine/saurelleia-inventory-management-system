<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Fetch user's profile pic
$profile_pic = null;
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $profile_pic = $row['profile_pic'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics - InventoryMS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="modern-sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-boxes"></i></div>
            <div class="logo-text"><h3>InventoryMS</h3><p>v2.0</p></div>
        </div>
        <a href="profile.php" style="text-decoration: none;">
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
                    <p><?php echo isset($_SESSION['role']) && $_SESSION['role'] == 'admin' ? 'Administrator' : 'Staff Member'; ?></p>
                </div>
            </div>
        </a>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="sales.php" class="nav-item"><i class="fas fa-shopping-cart"></i><span>Sales</span></a>
            <a href="analytics.php" class="nav-item active"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
            <a href="notes.php" class="nav-item"><i class="fas fa-sticky-note"></i><span>Notes</span></a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'){ ?>
                <a href="admin_add_users.php" class="nav-item"><i class="fas fa-users"></i><span>User Management</span></a>
            <?php } ?>
            <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="modern-main">
        <div class="welcome-banner">
            <div class="banner-content">
                <h1>Inventory Analytics</h1>
                <p>Insights into your inventory, top products, and trends.</p>
            </div>
            <div class="banner-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('l'); ?></div>
                    <div class="stat-label"><?php echo date('F j, Y'); ?></div>
                </div>
            </div>
        </div>

        <div class="analytics-section" style="margin-top: 0;">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Inventory Analytics</h2>
                <div class="analytics-controls">
                    <select id="analyticsPeriod" onchange="updateAnalytics()">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                    </select>
                </div>
            </div>
            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="analytics-header"><i class="fas fa-chart-bar"></i><h3>Top Products</h3></div>
                    <div class="analytics-content" id="topProducts"><div class="loading">Loading analytics...</div></div>
                </div>
                <div class="analytics-card">
                    <div class="analytics-header"><i class="fas fa-chart-pie"></i><h3>Category Distribution</h3></div>
                    <div class="analytics-content" id="categoryDistribution"><div class="loading">Loading analytics...</div></div>
                </div>
                <div class="analytics-card">
                    <div class="analytics-header"><i class="fas fa-exclamation-triangle"></i><h3>Low Stock Alerts</h3></div>
                    <div class="analytics-content" id="lowStockAlerts"><div class="loading">Loading analytics...</div></div>
                </div>
                <div class="analytics-card">
                    <div class="analytics-header"><i class="fas fa-dollar-sign"></i><h3>Inventory Value Trend</h3></div>
                    <div class="analytics-content" id="valueTrend"><div class="loading">Loading analytics...</div></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateAnalytics() {
            updateTopProducts();
            updateCategoryDistribution();
            updateLowStockAlerts();
            updateValueTrend();
        }

        function updateTopProducts() {
            fetch('get_top_products.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('topProducts');
                    if(data.length > 0) {
                        container.innerHTML = data.map(product => `
                            <div class="product-ranking">
                                <div class="ranking-number">#${product.rank}</div>
                                <div class="product-info">
                                    <div class="product-name">${product.name}</div>
                                    <div class="product-stats">${product.quantity} units · ₱${product.total_value}</div>
                                </div>
                                <div class="product-value">₱${product.value}</div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="empty-state">No products yet</div>';
                    }
                })
                .catch(() => document.getElementById('topProducts').innerHTML = '<div class="empty-state">Failed to load data</div>');
        }

        function updateCategoryDistribution() {
            fetch('get_category_distribution.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('categoryDistribution');
                    if(data.length > 0) {
                        const colors = ['#ec4899', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                        container.innerHTML = data.map((item, i) => `
                            <div class="category-item">
                                <span class="category-name">${item.category}</span>
                                <div class="category-bar">
                                    <div class="bar-fill" style="width: ${item.percentage}%; background: ${colors[i % colors.length]};"></div>
                                </div>
                                <span class="category-percent">${item.percentage}%</span>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="empty-state">No products yet</div>';
                    }
                })
                .catch(() => document.getElementById('categoryDistribution').innerHTML = '<div class="empty-state">Failed to load data</div>');
        }

        function updateLowStockAlerts() {
            fetch('get_low_stock.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('lowStockAlerts');
                    if(data.length > 0) {
                        container.innerHTML = data.map(item => `
                            <div class="alert-item">
                                <i class="fas fa-exclamation-circle"></i>
                                <div class="alert-info">
                                    <div class="alert-title">${item.name}</div>
                                    <div class="alert-details">Only ${item.quantity} units left</div>
                                </div>
                                <button onclick="restockProduct(${item.id})" class="restock-btn">Restock</button>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i> No low stock items</div>';
                    }
                })
                .catch(() => document.getElementById('lowStockAlerts').innerHTML = '<div class="empty-state">Failed to load low stock alerts</div>');
        }

        function updateValueTrend() {
            fetch('get_inventory_trend.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('valueTrend');
                    const changeClass = data.change >= 0 ? 'trend-up' : 'trend-down';
                    const arrow = data.change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                    const sign = data.change >= 0 ? '+' : '';
                    
                    container.innerHTML = `
                        <div class="trend-chart">
                            <div class="${changeClass}">
                                <i class="fas ${arrow}"></i>
                                <span>${sign}${data.change}% from last period</span>
                            </div>
                            <div class="trend-value">₱${data.total}</div>
                            <div class="trend-label">Total inventory value (${data.product_count} products)</div>
                        </div>
                    `;
                })
                .catch(() => document.getElementById('valueTrend').innerHTML = '<div class="empty-state">Failed to load trend</div>');
        }

        function restockProduct(id) {
            window.location.href = `edit.php?id=${id}`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateAnalytics();
        });
    </script>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Fetch all products with stock
$products = [];
$result = $conn->query("SELECT id, product_name, quantity, price, category FROM products WHERE quantity > 0 ORDER BY product_name ASC");
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Fetch all sales history
$sales = [];
$result = $conn->query("SELECT * FROM sales ORDER BY sold_at DESC LIMIT 100");
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

// Stats
$total_revenue = 0;
$total_items_sold = 0;
$today_revenue = 0;
$today = date('Y-m-d');

$stats = $conn->query("SELECT 
    SUM(total_amount) as total_revenue,
    SUM(quantity) as total_items,
    SUM(CASE WHEN DATE(sold_at) = '$today' THEN total_amount ELSE 0 END) as today_revenue
    FROM sales");
if ($srow = $stats->fetch_assoc()) {
    $total_revenue = $srow['total_revenue'] ?? 0;
    $total_items_sold = $srow['total_items'] ?? 0;
    $today_revenue = $srow['today_revenue'] ?? 0;
}

// Best Selling Products (for pie chart)
$best_sellers = [];
$bs_query = "SELECT product_name, SUM(quantity) as total_sold, SUM(total_amount) as total_revenue 
             FROM sales 
             GROUP BY product_name 
             ORDER BY total_sold DESC 
             LIMIT 8";
$bs_result = $conn->query($bs_query);
$bs_total = 0;
if ($bs_result) {
    while ($row = $bs_result->fetch_assoc()) {
        $bs_total += $row['total_sold'];
        $best_sellers[] = $row;
    }
}
foreach ($best_sellers as &$b) {
    $b['percentage'] = $bs_total > 0 ? round(($b['total_sold'] / $bs_total) * 100, 1) : 0;
}
unset($b);

// Fetch profile pic
$profile_pic = null;
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($r = $res->fetch_assoc()) $profile_pic = $r['profile_pic'];
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Tracking - InventoryMS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ===== SALES PAGE — PINK THEME ===== */
        .sales-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }
        .sales-top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
            align-items: stretch;
        }
        .sales-form-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
        }
        .sales-form-card h2,
        .sales-chart-card h2,
        .sales-history h2 {
            color: #831843;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sales-form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 16px;
            align-items: end;
        }
        .sales-form-grid .form-group-modern {
            margin-bottom: 0;
        }
        .sales-form-grid label {
            display: block;
            color: #9d174d;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .sales-form-grid label i {
            color: #ec4899;
            margin-right: 6px;
        }
        .sales-form-grid select,
        .sales-form-grid input {
            width: 100%;
            padding: 12px 16px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.2);
            border-radius: 10px;
            color: #831843;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        .sales-form-grid select:focus,
        .sales-form-grid input:focus {
            outline: none;
            background: white;
            border-color: #ec4899;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        .sales-form-grid input[readonly] {
            background: #fce7f3;
            cursor: not-allowed;
        }
        .sales-submit-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(236, 72, 153, 0.25);
            font-family: inherit;
        }
        .sales-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.35);
        }
        .sales-submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .total-preview {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #fff5f7 0%, #fce7f3 100%);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(236, 72, 153, 0.1);
            margin-top: 20px;
        }
        .total-preview-label {
            color: #9d174d;
            font-size: 14px;
            font-weight: 500;
        }
        .total-preview-value {
            color: #ec4899;
            font-size: 24px;
            font-weight: 700;
        }
        .sales-chart-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
            display: flex;
            flex-direction: column;
        }
        .chart-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            position: relative;
        }
        .chart-wrapper canvas {
            max-height: 320px !important;
        }
        .sales-history {
            background: white;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
        }
        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sales-table th {
            text-align: left;
            padding: 14px 12px;
            color: #9d174d;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid rgba(236, 72, 153, 0.15);
            opacity: 0.85;
        }
        .sales-table td {
            padding: 14px 12px;
            color: #831843;
            font-size: 14px;
            border-bottom: 1px solid rgba(236, 72, 153, 0.08);
        }
        .sales-table tbody tr:hover {
            background: rgba(236, 72, 153, 0.03);
        }
        .sales-amount {
            color: #059669;
            font-weight: 700;
        }
        .sales-empty {
            text-align: center;
            padding: 60px 20px;
            color: #9d174d;
            opacity: 0.7;
        }
        .sales-empty i {
            font-size: 48px;
            color: #fbcfe8;
            margin-bottom: 16px;
            display: block;
        }
        @media (max-width: 968px) {
            .sales-top-grid {
                grid-template-columns: 1fr;
            }
            .sales-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            <a href="sales.php" class="nav-item active"><i class="fas fa-shopping-cart"></i><span>Sales</span></a>
            <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
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
                <h1>Sales Tracking</h1>
                <p>Record sales and automatically deduct from inventory.</p>
            </div>
            <div class="banner-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('l'); ?></div>
                    <div class="stat-label"><?php echo date('F j, Y'); ?></div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card-modern">
                <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
                <div class="stat-details"><h3>Total Revenue</h3><div class="stat-number">₱<?php echo number_format($total_revenue, 2); ?></div><span class="stat-trend">All time</span></div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon blue"><i class="fas fa-box-open"></i></div>
                <div class="stat-details"><h3>Items Sold</h3><div class="stat-number"><?php echo $total_items_sold; ?></div><span class="stat-trend">All time</span></div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon purple"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-details"><h3>Today's Sales</h3><div class="stat-number">₱<?php echo number_format($today_revenue, 2); ?></div><span class="stat-trend">Since midnight</span></div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon orange"><i class="fas fa-receipt"></i></div>
                <div class="stat-details"><h3>Transactions</h3><div class="stat-number"><?php echo count($sales); ?></div><span class="stat-trend">Last 100 shown</span></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.3); color: #dc2626; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="sales-wrapper">
            <!-- Top Row: Form + Chart side by side -->
            <div class="sales-top-grid">
                
                <!-- Record Sale Form -->
                <div class="sales-form-card">
                    <h2><i class="fas fa-cash-register"></i> Record a Sale</h2>
                    <form action="record_sale.php" method="POST" id="salesForm">
                        <div class="sales-form-grid">
                            <div class="form-group-modern">
                                <label><i class="fas fa-box"></i> Product</label>
                                <select name="product_id" id="productSelect" required onchange="updateProductInfo()">
                                    <option value="">-- Select a product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                data-price="<?php echo $p['price']; ?>" 
                                                data-stock="<?php echo $p['quantity']; ?>"
                                                data-name="<?php echo htmlspecialchars($p['product_name']); ?>">
                                            <?php echo htmlspecialchars($p['product_name']); ?> 
                                            (Stock: <?php echo $p['quantity']; ?> · ₱<?php echo number_format($p['price'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-modern">
                                <label><i class="fas fa-tag"></i> Price/Unit</label>
                                <input type="text" id="priceDisplay" readonly placeholder="₱0.00">
                            </div>
                            <div class="form-group-modern">
                                <label><i class="fas fa-cubes"></i> Quantity</label>
                                <input type="number" name="quantity" id="quantityInput" min="1" value="1" required oninput="updateTotal()">
                            </div>
                        </div>
                        <div class="total-preview">
                            <span class="total-preview-label"><i class="fas fa-calculator"></i> Total Amount</span>
                            <span class="total-preview-value" id="totalAmount">₱0.00</span>
                        </div>
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="submit" class="sales-submit-btn" id="submitBtn">
                                <i class="fas fa-check-circle"></i> Record Sale
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Best Selling Products Pie Chart -->
                <div class="sales-chart-card">
                    <h2><i class="fas fa-chart-pie"></i> Best Selling Products</h2>
                    <div class="chart-wrapper">
                        <?php if (count($best_sellers) > 0): ?>
                            <canvas id="bestSellersChart"></canvas>
                        <?php else: ?>
                            <div class="sales-empty" style="padding: 20px;">
                                <i class="fas fa-chart-pie"></i>
                                <p>No sales data yet.</p>
                                <p style="font-size: 13px; opacity: 0.7;">Record a sale to see the chart.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Sales History -->
            <div class="sales-history">
                <h2><i class="fas fa-history"></i> Sales History</h2>
                <?php if (count($sales) > 0): ?>
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price/Unit</th>
                                <th>Total</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td>#<?php echo $s['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['product_name']); ?></strong></td>
                                    <td><?php echo $s['quantity']; ?></td>
                                    <td>₱<?php echo number_format($s['price_per_unit'], 2); ?></td>
                                    <td class="sales-amount">₱<?php echo number_format($s['total_amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($s['sold_at'])); ?></td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <a href="edit_sale.php?id=<?php echo $s['id']; ?>" 
                                               style="background: rgba(16, 185, 129, 0.1); color: #059669; padding: 6px 10px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s;" 
                                               onmouseover="this.style.background='#10b981'; this.style.color='white';"
                                               onmouseout="this.style.background='rgba(16, 185, 129, 0.1)'; this.style.color='#059669';"
                                               title="Edit Sale">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_sale.php?id=<?php echo $s['id']; ?>" 
                                               onclick="return confirm('Delete this sale?\n\n<?php echo $s['quantity']; ?>x <?php echo htmlspecialchars($s['product_name']); ?> will be returned to inventory.');" 
                                               style="background: rgba(220, 38, 38, 0.1); color: #dc2626; padding: 6px 10px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s;" 
                                               onmouseover="this.style.background='#dc2626'; this.style.color='white';"
                                               onmouseout="this.style.background='rgba(220, 38, 38, 0.1)'; this.style.color='#dc2626';"
                                               title="Delete Sale">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="sales-empty">
                        <i class="fas fa-receipt"></i>
                        <p>No sales recorded yet.</p>
                        <p style="font-size: 13px; opacity: 0.7;">Record your first sale using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // ===== Record Sale Form Logic =====
        let currentPrice = 0;
        let currentStock = 0;

        function updateProductInfo() {
            const select = document.getElementById('productSelect');
            const option = select.options[select.selectedIndex];
            const price = parseFloat(option.dataset.price || 0);
            const stock = parseInt(option.dataset.stock || 0);
            currentPrice = price;
            currentStock = stock;
            document.getElementById('priceDisplay').value = '₱' + price.toFixed(2);
            document.getElementById('quantityInput').max = stock;
            updateTotal();
        }

        function updateTotal() {
            const qty = parseInt(document.getElementById('quantityInput').value) || 0;
            const total = qty * currentPrice;
            const totalEl = document.getElementById('totalAmount');
            const submitBtn = document.getElementById('submitBtn');
            
            totalEl.textContent = '₱' + total.toFixed(2);
            
            if (currentStock > 0 && qty > currentStock) {
                totalEl.textContent = '⚠️ Not enough stock!';
                totalEl.style.color = '#dc2626';
                submitBtn.disabled = true;
            } else if (currentStock > 0 && qty > 0) {
                totalEl.style.color = '#ec4899';
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // ===== BEST SELLING PRODUCTS PIE CHART =====
        <?php if (count($best_sellers) > 0): ?>
        const bestSellersData = <?php echo json_encode($best_sellers); ?>;

        const chartColors = [
            '#ec4899', '#10b981', '#f59e0b', '#3b82f6',
            '#8b5cf6', '#ef4444', '#06b6d4', '#f97316'
        ];

        const ctx = document.getElementById('bestSellersChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: bestSellersData.map(item => item.product_name),
                    datasets: [{
                        data: bestSellersData.map(item => item.total_sold),
                        backgroundColor: chartColors.slice(0, bestSellersData.length),
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#831843',
                                font: {
                                    family: 'Inter, sans-serif',
                                    size: 12,
                                    weight: '500'
                                },
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#831843',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const item = bestSellersData[context.dataIndex];
                                    return [
                                        ' Sold: ' + item.total_sold + ' unit(s)',
                                        ' Revenue: ₱' + parseFloat(item.total_revenue).toLocaleString('en-PH', {minimumFractionDigits: 2}),
                                        ' ' + item.percentage + '% of total sales'
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
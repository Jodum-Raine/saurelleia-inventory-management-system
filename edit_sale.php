<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    header("Location: sales.php");
    exit();
}

// Fetch the sale
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();
$sale = $result->fetch_assoc();
$stmt->close();

if (!$sale) {
    $_SESSION['error_message'] = "Sale not found.";
    header("Location: sales.php");
    exit();
}

// Fetch the product's current stock
$stmt = $conn->prepare("SELECT quantity, price FROM products WHERE id = ?");
$stmt->bind_param("i", $sale['product_id']);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

$current_stock = $product ? $product['quantity'] : 0;
$price_per_unit = (float)$sale['price_per_unit'];

// Max allowed quantity = current stock + the quantity already sold in this sale
$max_quantity = $current_stock + $sale['quantity'];

// Fetch profile pic
$profile_pic = null;
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($r = $result->fetch_assoc()) $profile_pic = $r['profile_pic'];
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Sale - InventoryMS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .edit-sale-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.1);
        }
        .edit-sale-preview {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #fff5f7 0%, #fce7f3 100%);
            border-radius: 16px;
            margin-bottom: 32px;
            border: 1px solid rgba(236, 72, 153, 0.1);
        }
        .edit-sale-preview-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: 0 5px 15px rgba(236, 72, 153, 0.25);
        }
        .edit-sale-preview-info h3 {
            color: #831843;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .edit-sale-preview-info p {
            color: #9d174d;
            font-size: 13px;
            opacity: 0.75;
        }
        .form-group-modern {
            margin-bottom: 20px;
        }
        .form-group-modern label {
            display: block;
            color: #9d174d;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group-modern label i {
            color: #ec4899;
            margin-right: 6px;
        }
        .form-group-modern input {
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
        .form-group-modern input:focus {
            outline: none;
            background: white;
            border-color: #ec4899;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        .form-group-modern input[readonly] {
            background: #fce7f3;
            cursor: not-allowed;
        }
        .calc-box {
            background: linear-gradient(135deg, #fff5f7 0%, #fce7f3 100%);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(236, 72, 153, 0.1);
            margin: 20px 0;
        }
        .calc-box-label {
            color: #9d174d;
            font-size: 14px;
            font-weight: 500;
        }
        .calc-box-value {
            color: #ec4899;
            font-size: 24px;
            font-weight: 700;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-update {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.25);
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
        }
        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #fce7f3;
            border: none;
            border-radius: 10px;
            color: #831843;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .btn-cancel:hover {
            background: #fbcfe8;
        }
        .stock-info {
            background: rgba(236, 72, 153, 0.08);
            border-left: 3px solid #ec4899;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #9d174d;
        }
        .stock-info strong {
            color: #831843;
        }
    </style>
</head>
<body>
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
                <h1>Edit Sale</h1>
                <p>Adjust the quantity of a recorded sale. Inventory will be automatically adjusted.</p>
            </div>
            <div class="banner-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('l'); ?></div>
                    <div class="stat-label"><?php echo date('F j, Y'); ?></div>
                </div>
            </div>
        </div>

        <div class="edit-sale-card">
            <div class="edit-sale-preview">
                <div class="edit-sale-preview-icon"><i class="fas fa-receipt"></i></div>
                <div class="edit-sale-preview-info">
                    <h3>Sale #<?php echo $sale['id']; ?></h3>
                    <p><?php echo htmlspecialchars($sale['product_name']); ?> · Originally sold <?php echo $sale['quantity']; ?> unit(s)</p>
                </div>
            </div>

            <div class="stock-info">
                <i class="fas fa-info-circle"></i>
                <strong>Current stock:</strong> <?php echo $current_stock; ?> unit(s)
                &nbsp;·&nbsp;
                <strong>Available to add:</strong> up to <?php echo $max_quantity; ?> units
            </div>

            <form method="POST" action="update_sale.php">
                <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                <input type="hidden" name="price_per_unit" value="<?php echo $price_per_unit; ?>">

                <div class="form-group-modern">
                    <label><i class="fas fa-box"></i> Product</label>
                    <input type="text" value="<?php echo htmlspecialchars($sale['product_name']); ?>" readonly>
                </div>

                <div class="form-group-modern">
                    <label><i class="fas fa-tag"></i> Price per Unit</label>
                    <input type="text" value="₱<?php echo number_format($price_per_unit, 2); ?>" readonly>
                </div>

                <div class="form-group-modern">
                    <label><i class="fas fa-cubes"></i> New Quantity</label>
                    <input type="number" name="quantity" id="quantityInput" 
                           value="<?php echo $sale['quantity']; ?>" 
                           min="1" max="<?php echo $max_quantity; ?>" required>
                </div>

                <div class="calc-box">
                    <span class="calc-box-label"><i class="fas fa-calculator"></i> New Total Amount</span>
                    <span class="calc-box-value" id="totalAmount">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-update"><i class="fas fa-save"></i> Update Sale</button>
                    <a href="sales.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const pricePerUnit = <?php echo $price_per_unit; ?>;
        const quantityInput = document.getElementById('quantityInput');
        const totalAmountEl = document.getElementById('totalAmount');

        quantityInput.addEventListener('input', function() {
            const qty = parseInt(this.value) || 0;
            const total = qty * pricePerUnit;
            totalAmountEl.textContent = '₱' + total.toFixed(2);
        });
    </script>
</body>
</html>
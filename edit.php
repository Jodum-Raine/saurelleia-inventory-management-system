<?php
session_start();
include 'db.php';
require_once 'categories.php';

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submission
if(isset($_POST['update'])){
    $name = trim($_POST['product_name']);
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $category = $_POST['category'];

    $stmt = $conn->prepare("UPDATE products SET product_name=?, quantity=?, price=?, category=? WHERE id=?");
    $stmt->bind_param("sidsi", $name, $quantity, $price, $category, $id);
    if($stmt->execute()){
        require_once 'functions.php';
        logActivity($_SESSION['user_id'], "Edit Product", "Edited product ID $id");
        
        $_SESSION['success_message'] = "Product updated successfully!";
        header("Location: index.php");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch product data
$product_name = $quantity = $price = $category = null;
$stmt = $conn->prepare("SELECT product_name, quantity, price, category FROM products WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $product_name = $row['product_name'];
    $quantity = $row['quantity'];
    $price = $row['price'];
    $category = $row['category'];
}
$stmt->close();

if(!$product_name){
    header("Location: index.php");
    exit();
}

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
    <title>Edit Product - Inventory Management System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== EDIT PRODUCT — PINK THEME ===== */
        .edit-product-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.1);
        }
        .product-preview {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #fff5f7 0%, #fce7f3 100%);
            border-radius: 16px;
            margin-bottom: 32px;
            border: 1px solid rgba(236, 72, 153, 0.1);
        }
        .preview-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 5px 15px rgba(236, 72, 153, 0.25);
        }
        .preview-info h3 {
            color: #831843;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .preview-info p {
            color: #9d174d;
            font-size: 13px;
            opacity: 0.75;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #9d174d;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group label i {
            margin-right: 6px;
            color: #ec4899;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.2);
            border-radius: 10px;
            color: #831843;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            background: white;
            border-color: #ec4899;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .calculated-fields {
            background: linear-gradient(135deg, #fff5f7 0%, #fce7f3 100%);
            border-radius: 12px;
            padding: 16px;
            margin: 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(236, 72, 153, 0.1);
        }
        .calc-item {
            text-align: center;
        }
        .calc-label {
            display: block;
            color: #9d174d;
            font-size: 12px;
            margin-bottom: 6px;
            opacity: 0.75;
        }
        .calc-value {
            font-size: 20px;
            font-weight: 700;
            color: #059669;
        }
        .calc-value.status-low {
            color: #dc2626;
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
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.25);
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
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-cancel:hover {
            background: #fbcfe8;
        }
        .form-footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(236, 72, 153, 0.1);
            text-align: center;
        }
        .form-footer p {
            color: #9d174d;
            font-size: 12px;
            opacity: 0.7;
        }
        @media (max-width: 768px) {
            .edit-product-card { padding: 20px; }
            .form-row { grid-template-columns: 1fr; gap: 16px; }
            .calculated-fields { flex-direction: column; gap: 12px; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <!-- Modern Sidebar -->
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
                <h1>Edit Product</h1>
                <p>Update product information and stock levels</p>
            </div>
            <div class="banner-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('l'); ?></div>
                    <div class="stat-label"><?php echo date('F j, Y'); ?></div>
                </div>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="message error" style="margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="edit-product-card">
            <div class="product-preview">
                <div class="preview-icon">📦</div>
                <div class="preview-info">
                    <h3>Editing Product #<?php echo $id; ?></h3>
                    <p>Update the fields below and save changes</p>
                </div>
            </div>

            <form method="POST" class="edit-form">
                <div class="form-group">
                    <label for="product_name"><i class="fas fa-tag"></i> Product Name</label>
                    <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_name); ?>" required placeholder="Enter product name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity"><i class="fas fa-cubes"></i> Quantity</label>
                        <input type="number" id="quantity" name="quantity" value="<?php echo $quantity; ?>" required placeholder="0">
                    </div>
                    <div class="form-group">
                        <label for="price"><i class="fas fa-dollar-sign"></i> Price (each)</label>
                        <input type="number" step="0.01" id="price" name="price" value="<?php echo $price; ?>" required placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="categorySearch"><i class="fas fa-folder"></i> Category</label>
                    <div class="searchable-category">
                        <input type="text" id="categorySearch" placeholder="🔍 Type to search category..." autocomplete="off" value="<?php echo htmlspecialchars($category); ?>">
                        <div class="category-list" id="categoryList"></div>
                    </div>
                    <input type="hidden" name="category" id="selectedCategory" value="<?php echo htmlspecialchars($category); ?>" required>
                </div>

                <div class="calculated-fields">
                    <div class="calc-item">
                        <span class="calc-label">Total Value:</span>
                        <span class="calc-value" id="totalValue">₱<?php echo number_format($quantity * $price, 2); ?></span>
                    </div>
                    <div class="calc-item">
                        <span class="calc-label">Stock Status:</span>
                        <span class="calc-value <?php echo ($quantity < 6) ? 'status-low' : ''; ?>" id="stockStatus">
                            <?php echo ($quantity < 6) ? '⚠️ Low Stock' : '✅ In Stock'; ?>
                        </span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update" class="btn-update"><i class="fas fa-save"></i> Update Product</button>
                    <a href="index.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>

            <div class="form-footer">
                <p><i class="fas fa-info-circle"></i> Updating this product will affect inventory calculations.</p>
            </div>
        </div>
    </div>

    <script>
        // ===== SEARCHABLE CATEGORY =====
        const ALL_CATEGORIES = <?php echo json_encode($ALL_CATEGORIES); ?>;
        const FLAT_CATEGORIES = [];
        for (const [group, items] of Object.entries(ALL_CATEGORIES)) {
            items.forEach(name => {
                FLAT_CATEGORIES.push({ group, name });
            });
        }

        function renderCategoryList(filter = '') {
            const list = document.getElementById('categoryList');
            const term = filter.toLowerCase().trim();
            const filtered = FLAT_CATEGORIES.filter(item => 
                item.name.toLowerCase().includes(term) || 
                item.group.toLowerCase().includes(term)
            );
            
            if (filtered.length === 0) {
                list.innerHTML = '<div class="category-empty">No matches found</div>';
                list.classList.add('show');
                return;
            }
            
            const grouped = {};
            filtered.forEach(item => {
                if (!grouped[item.group]) grouped[item.group] = [];
                grouped[item.group].push(item.name);
            });
            
            let html = '';
            for (const [group, names] of Object.entries(grouped)) {
                html += `<div class="category-group-label">${group}</div>`;
                names.forEach(name => {
                    const fullLabel = group + ' - ' + name;
                    const safeFull = fullLabel.replace(/'/g, "\\'");
                    html += `<div class="category-option" onclick="selectCategory('${safeFull}')">${name}</div>`;
                });
            }
            list.innerHTML = html;
            list.classList.add('show');
        }

        function selectCategory(fullLabel) {
            document.getElementById('selectedCategory').value = fullLabel;
            const parts = fullLabel.split(' - ');
            document.getElementById('categorySearch').value = parts.length > 1 ? parts[1] : fullLabel;
            document.getElementById('categoryList').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('categorySearch');
            const list = document.getElementById('categoryList');
            
            searchInput.addEventListener('focus', () => renderCategoryList(searchInput.value));
            searchInput.addEventListener('input', () => {
                renderCategoryList(searchInput.value);
                // Clear selected if user is typing something new
                document.getElementById('selectedCategory').value = searchInput.value;
            });
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.searchable-category')) {
                    list.classList.remove('show');
                }
            });
        });

        // ===== LIVE CALCULATIONS =====
        const quantityInput = document.getElementById('quantity');
        const priceInput = document.getElementById('price');
        const totalValueSpan = document.getElementById('totalValue');
        const stockStatusSpan = document.getElementById('stockStatus');

        function updateCalculations() {
            let quantity = parseInt(quantityInput.value) || 0;
            let price = parseFloat(priceInput.value) || 0;
            let total = quantity * price;
            totalValueSpan.textContent = '₱' + total.toFixed(2);
            if(quantity < 6) {
                stockStatusSpan.innerHTML = '⚠️ Low Stock';
                stockStatusSpan.className = 'calc-value status-low';
            } else {
                stockStatusSpan.innerHTML = '✅ In Stock';
                stockStatusSpan.className = 'calc-value';
            }
        }

        quantityInput.addEventListener('input', updateCalculations);
        priceInput.addEventListener('input', updateCalculations);
    </script>
</body>
</html>
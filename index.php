<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
require_once 'categories.php';

// Display session messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="message success" style="position: fixed; top: 20px; right: 20px; z-index: 1000; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 12px 20px; border-radius: 12px; color: #10b981;">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="message error" style="position: fixed; top: 20px; right: 20px; z-index: 1000; background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.3); padding: 12px 20px; border-radius: 12px; color: #dc2626;">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

// Fetch all products for client-side filtering
$all_products = [];
$total_inventory = 0;
$low_stock_threshold = 6;
$low_stock_count = 0;

$product_result = $conn->query("SELECT * FROM products ORDER BY id DESC");
while ($row = $product_result->fetch_assoc()) {
    $row['total_value'] = $row['quantity'] * $row['price'];
    $total_inventory += $row['total_value'];
    if ($row['quantity'] < $low_stock_threshold) {
        $low_stock_count += $row['quantity'];   
    }
    $all_products[] = $row;
}
// Dashboard stats
$totalUsers = 0;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $user_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role != 'admin'");
    $totalUsers = $user_result->fetch_assoc()['total'];
}
$totalProducts = count($all_products);

// Fetch current user's profile picture for sidebar avatar
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
    <title>Dashboard - InventoryMS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Modern Sidebar Navigation -->
    <div class="modern-sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-boxes"></i></div>
            <div class="logo-text"><h3>My Inventory</h3><p>v1.0</p></div>
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
            <a href="index.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
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
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user']); ?>!</h1>
                <p>Here's what's happening with your inventory today.</p>
            </div>
            <div class="banner-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('l'); ?></div>
                    <div class="stat-label"><?php echo date('F j, Y'); ?></div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'){ ?>
                <div class="stat-card-modern">
                    <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                    <div class="stat-details"><h3>Total Staff</h3><div class="stat-number"><?php echo $totalUsers; ?></div><span class="stat-trend">Active members</span></div>
                </div>
            <?php } ?>
            <div class="stat-card-modern">
                <div class="stat-icon blue"><i class="fas fa-box"></i></div>
                <div class="stat-details"><h3>Total Products</h3><div class="stat-number"><?php echo $totalProducts; ?></div><span class="stat-trend">In inventory</span></div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
                <div class="stat-details"><h3>Inventory Value</h3><div class="stat-number">₱<?php echo number_format($total_inventory, 2); ?></div><span class="stat-trend">Total worth</span></div>
            </div>
            <div class="stat-card-modern">
                <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-details"><h3>Low Stock Items</h3><div class="stat-number" id="lowStockCount"><?php echo $low_stock_count; ?></div><span class="stat-trend">Needs attention</span></div>
            </div>
        </div>

        <div class="action-cards">
            <div class="action-card add-product">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <h3>Add New Product</h3>
                <p>Quickly add products to your inventory</p>
                <button class="action-btn" onclick="openAddProductModal()"><i class="fas fa-plus"></i> Add Product</button>
            </div>
            
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'){ ?>
            <div class="action-card manage-users">
                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                <h3>Manage Users</h3>
                <p>Add or remove system users</p>
                <a href="admin_add_users.php" class="action-btn"><i class="fas fa-users"></i> Manage</a>
            </div>
            <?php } ?>
        </div>

        <!-- Products Section -->
        <div id="products-section" class="products-section">
            <div class="section-header">
                <h2><i class="fas fa-boxes"></i> Product Inventory</h2>
                <div class="table-controls">
                    <div class="filter-group">
                        <i class="fas fa-filter"></i>
                        <select id="categoryFilter" onchange="filterProducts()">
                            <option value="">All Categories</option>
                            <option value="💻 AI & Productivity">💻 AI & Productivity</option>
                            <option value="🎬 Streaming">🎬 Streaming</option>
                            <option value="🎨 Design & Creative">🎨 Design & Creative</option>
                            <option value="📚 Education">📚 Education</option>
                            <option value="💻 Developer / Tech">💻 Developer / Tech</option>
                            <option value="🎵 Music">🎵 Music</option>
                        </select>
                    </div>
                    <div class="search-group">
                        <i class="fas fa-search"></i>
                        <input type="text" id="productSearch" placeholder="Search products..." onkeyup="filterProducts()">
                    </div>
                </div>
            </div>

            <div class="products-table-modern">
                <table id="productsTable" width="100%">
                    <thead>
                        <tr>
                            <th onclick="sortProducts(0)" style="cursor: pointer;">ID <i class="fas fa-sort"></i></th>
                            <th onclick="sortProducts(1)" style="cursor: pointer;">Product Name <i class="fas fa-sort"></i></th>
                            <th onclick="sortProducts(2)" style="cursor: pointer;">Quantity <i class="fas fa-sort"></i></th>
                            <th onclick="sortProducts(3)" style="cursor: pointer;">Price <i class="fas fa-sort"></i></th>
                            <th>Total Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php foreach($all_products as $product): ?>
                            <tr data-name="<?php echo strtolower($product['product_name']); ?>" data-category="<?php echo $product['category']; ?>" data-id="<?php echo $product['id']; ?>" data-quantity="<?php echo $product['quantity']; ?>">
                                <td>#<?php echo $product['id']; ?></td>
                                <td>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                        <div class="product-category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category']); ?></div>
                                    </div>
                                </td>
                                <td><div class="quantity-badge <?php echo ($product['quantity'] < $low_stock_threshold) ? 'low' : ''; ?>"><?php echo $product['quantity']; ?> units</div></td>
                                <td>₱<?php echo number_format($product['price'],2); ?></td>
                                <td class="total-value">₱<?php echo number_format($product['quantity'] * $product['price'],2); ?></td>
                                <td class="action-buttons">
                                    <a href="edit.php?id=<?php echo $product['id']; ?>" class="edit-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                    <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="delete-btn" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="summary-row">
                            <td colspan="4"><strong>Subtotal (Current Page):</strong></td>
                            <td><strong id="pageTotal">₱0.00</strong></td>
                            <td></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="4"><strong>Total Inventory Value:</strong></td>
                            <td><strong>₱<?php echo number_format($total_inventory,2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modern-pagination">
                <div class="pagination-info">Showing <span id="showingStart">0</span> - <span id="showingEnd">0</span> of <span id="totalCount"><?php echo $totalProducts; ?></span> products</div>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Add New Product</h3><span class="close" onclick="closeAddProductModal()">&times;</span></div>
            <form action="add.php" method="POST" class="modal-form">
                <div class="form-group-modern"><label><i class="fas fa-tag"></i> Product Name</label><input type="text" name="product_name" placeholder="Enter product name" required></div>
                <div class="form-row-modern">
                    <div class="form-group-modern"><label><i class="fas fa-cubes"></i> Quantity</label><input type="number" name="quantity" placeholder="0" required></div>
                    <div class="form-group-modern"><label><i class="fas fa-dollar-sign"></i> Price (each)</label><input type="number" step="0.01" name="price" placeholder="0.00" required></div>
                </div>
                <div class="form-group-modern">
                    <label><i class="fas fa-folder"></i> Category</label>
                    <div class="searchable-category">
                        <input type="text" id="categorySearch" placeholder="🔍 Type to search category..." autocomplete="off">
                        <div class="category-list" id="categoryList"></div>
                    </div>
                    <input type="hidden" name="category" id="selectedCategory" required>
                    <div id="selectedCategoryDisplay" style="margin-top: 8px; font-size: 13px; color: #9d174d; display: none;">
                        Selected: <strong id="selectedCategoryName"></strong>
                    </div>
                </div>
                <div class="modal-actions"><button type="button" class="cancel-btn" onclick="closeAddProductModal()">Cancel</button><button type="submit" class="submit-btn"><i class="fas fa-save"></i> Add Product</button></div>
            </form>
        </div>
    </div>

    <script>
        // ===== SEARCHABLE CATEGORY DROPDOWN =====
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
            document.getElementById('selectedCategoryName').innerText = fullLabel;
            const parts = fullLabel.split(' - ');
            document.getElementById('categorySearch').value = parts.length > 1 ? parts[1] : fullLabel;
            document.getElementById('selectedCategoryDisplay').style.display = 'block';
            document.getElementById('categoryList').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('categorySearch');
            const list = document.getElementById('categoryList');
            
            if (searchInput) {
                searchInput.addEventListener('focus', () => renderCategoryList(searchInput.value));
                searchInput.addEventListener('input', () => renderCategoryList(searchInput.value));
                
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.searchable-category')) {
                        list.classList.remove('show');
                    }
                });
            }
        });

        // ===== PRODUCT TABLE FILTERING & PAGINATION =====
        const allProductRows = Array.from(document.querySelectorAll('#productsTableBody tr'));
        let currentProductPage = 1;
        let productsPerPage = 10;
        let currentProductSortColumn = 0;
        let currentProductSortOrder = 'asc';
        let filteredProducts = [];

        function filterProducts() {
            const category = document.getElementById('categoryFilter').value;
            const searchTerm = document.getElementById('productSearch').value.toLowerCase();

            filteredProducts = allProductRows.filter(row => {
                let show = true;
                const productCategory = row.getAttribute('data-category');
                const productName = row.getAttribute('data-name');

                if (category && !productCategory.includes(category)) show = false;
                if (searchTerm && !productName.includes(searchTerm)) show = false;
                return show;
            });

            filteredProducts.sort((a, b) => {
                let aVal, bVal;
                if (currentProductSortColumn === 0) {
                    aVal = parseInt(a.cells[0].innerText.replace('#', ''));
                    bVal = parseInt(b.cells[0].innerText.replace('#', ''));
                } else if (currentProductSortColumn === 1) {
                    aVal = a.getAttribute('data-name');
                    bVal = b.getAttribute('data-name');
                } else if (currentProductSortColumn === 2) {
                    aVal = parseInt(a.getAttribute('data-quantity'));
                    bVal = parseInt(b.getAttribute('data-quantity'));
                } else {
                    aVal = parseFloat(a.cells[3].innerText.replace('₱', '').replace(/,/g, ''));
                    bVal = parseFloat(b.cells[3].innerText.replace('₱', '').replace(/,/g, ''));
                }

                if (currentProductSortOrder === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });

            updateProductPagination();
        }

        function updateProductPagination() {
            const totalItems = filteredProducts.length;
            const totalPages = Math.ceil(totalItems / productsPerPage);
            if (currentProductPage > totalPages) currentProductPage = totalPages || 1;

            const start = (currentProductPage - 1) * productsPerPage;
            const end = start + productsPerPage;

            allProductRows.forEach(row => row.style.display = 'none');
            filteredProducts.slice(start, end).forEach(row => row.style.display = '');

            document.getElementById('totalCount').innerText = totalItems;
            document.getElementById('showingStart').innerText = totalItems ? start + 1 : 0;
            document.getElementById('showingEnd').innerText = Math.min(end, totalItems);

            let pageTotal = 0;
            filteredProducts.slice(start, end).forEach(row => {
                const totalCell = row.cells[4];
                const totalValue = parseFloat(totalCell.innerText.replace('₱', '').replace(/,/g, ''));
                pageTotal += totalValue;
            });
            document.getElementById('pageTotal').innerHTML = '₱' + pageTotal.toFixed(2);

            const container = document.getElementById('paginationControls');
            container.innerHTML = '';
            if (totalPages <= 1) return;

            const prevBtn = document.createElement('a');
            prevBtn.className = 'page-btn';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.onclick = () => { if (currentProductPage > 1) { currentProductPage--; updateProductPagination(); } };
            prevBtn.style.cssText = 'padding: 8px 12px; background: #fff5f7; border: 1px solid rgba(236, 72, 153, 0.2); border-radius: 8px; color: #831843; text-decoration: none; cursor: pointer; margin: 0 2px;';
            container.appendChild(prevBtn);

            for (let i = 1; i <= Math.min(totalPages, 5); i++) {
                const btn = document.createElement('a');
                btn.className = 'page-btn';
                btn.innerText = i;
                btn.onclick = () => { currentProductPage = i; updateProductPagination(); };
                btn.style.cssText = 'padding: 8px 12px; background: #fff5f7; border: 1px solid rgba(236, 72, 153, 0.2); border-radius: 8px; color: #831843; text-decoration: none; cursor: pointer; margin: 0 2px;';
                if (i === currentProductPage) {
                    btn.style.background = 'linear-gradient(135deg, #ec4899 0%, #be185d 100%)';
                    btn.style.color = 'white';
                    btn.style.borderColor = 'transparent';
                }
                container.appendChild(btn);
            }

            const nextBtn = document.createElement('a');
            nextBtn.className = 'page-btn';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.onclick = () => { if (currentProductPage < totalPages) { currentProductPage++; updateProductPagination(); } };
            nextBtn.style.cssText = 'padding: 8px 12px; background: #fff5f7; border: 1px solid rgba(236, 72, 153, 0.2); border-radius: 8px; color: #831843; text-decoration: none; cursor: pointer; margin: 0 2px;';
            container.appendChild(nextBtn);
        }

        function sortProducts(column) {
            if (currentProductSortColumn === column) {
                currentProductSortOrder = currentProductSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentProductSortColumn = column;
                currentProductSortOrder = 'asc';
            }
            filterProducts();
        }

        function openAddProductModal() { document.getElementById('addProductModal').style.display = 'flex'; }
        function closeAddProductModal() { 
            document.getElementById('addProductModal').style.display = 'none';
            document.getElementById('categorySearch').value = '';
            document.getElementById('selectedCategory').value = '';
            document.getElementById('selectedCategoryDisplay').style.display = 'none';
        }
        function deleteProduct(id) { if(confirm('Are you sure you want to delete this product?')) { window.location.href = 'delete.php?id=' + id; } }

        window.onclick = function(event) {
            const addModal = document.getElementById('addProductModal');
            if (event.target == addModal) closeAddProductModal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterProducts();
        });
    </script>
</body>
</html>
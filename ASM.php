<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing ASM.php\n", FILE_APPEND);

$host = 'localhost';
$dbname = 'asm_sdlc';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - MySQL connection successful with $dbname\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT username, role FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User information not found for ID {$_SESSION['user_id']}\n", FILE_APPEND);
        die("User information not found.");
    }
    $_SESSION['role'] = $user['role'];
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved user information for ID {$_SESSION['user_id']}\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user information: " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $user_id = $_SESSION['user_id'];
    $cart_items = json_decode($_POST['cart_items'], true);
    $total_price = floatval($_POST['total_price']);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO Orders (customer_id, menu_id, status, order_date) VALUES (:customer_id, :menu_id, 'pending', NOW())");
        $stmt->execute([':customer_id' => $user_id, ':menu_id' => $cart_items[0]['id']]);
        $order_id = $pdo->lastInsertId();
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("INSERT INTO Order_Items (order_id, menu_id, quantity, price) VALUES (:order_id, :menu_id, :quantity, :price)");
            $stmt->execute([
                ':order_id' => $order_id,
                ':menu_id' => $item['id'],
                ':quantity' => $item['quantity'],
                ':price' => $item['price']
            ]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Order placed successfully!']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error placing order: ' . htmlspecialchars($e->getMessage())]);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error placing order: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    exit;
}

// Handle product filtering
$category = isset($_GET['loai']) ? $_GET['loai'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $query = "SELECT menu_id AS id, title AS name, price, description, image_path FROM Menus";
    $params = [];
    if ($category) {
        $query .= " WHERE category = :category";
        $params[':category'] = $category;
    }
    if ($search) {
        $query .= $category ? " AND" : " WHERE";
        $query .= " title LIKE :search";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved menu items, count: " . count($menu_items) . "\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving menu items: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving menu items: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - Place Your Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f5f5f5;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        font-family: 'Poppins', sans-serif;
    }
    .header {
        background-color: #006838;
        padding: 15px 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .logo img {
        height: 100px;
        object-fit: contain;
    }
    .search-form .btn {
        background-color: #198754;
        border-color: #198754;
    }
    .search-form .btn:hover {
        background-color: #e64a19;
        border-color: #e64a19;
    }
    .cart {
        position: relative;
    }
    .cart img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 50%;
    }
    .cart .badge {
        position: absolute;
        top: -10px;
        right: -10px;
        background-color: #ff5722;
        color: white;
        border-radius: 50%;
        padding: 5px 10px;
        font-size: 0.8rem;
    }
    .account img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 50%;
    }
    .navbar {
        background-color: #6B9F31;
    }
    .navbar-nav .nav-link {
        color: #fff;
        text-transform: uppercase;
        font-weight: 500;
    }
    .navbar-nav .nav-link:hover {
        color: #ffccbc;
    }
    .sidebar {
        background-color: #a9dd6fff;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        min-height: calc(100vh - 200px);
    }
    .sidebar h3 {
        color: #02370bff;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
    .sidebar .list-group-item {
        background-color: #a9dd6fff;
        border: none;
        padding: 10px 0;
    }
    .sidebar .list-group-item a {
        background-color: #a9dd6fff;
        color: #333;
        text-decoration: none;
        font-weight: 500;
    }
    .sidebar .list-group-item a:hover {
        color: #11b11cff;
    }
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
    }
    .product-item {
        background-color: #fff;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }
    .product-item:hover {
        transform: translateY(-5px);
    }
    .product-item img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 10px;
    }
    .product-item h4 {
        font-size: 1.2rem;
        color: #333;
        margin: 10px 0;
    }
    .product-item p {
        font-size: 0.9rem;
        color: #666;
    }
    .product-buttons .btn {
        font-size: 0.9rem;
        background-color: #26612eff;
        border-color: #26612eff;
    }
    .product-buttons .btn:hover {
        background-color: #26612eff;
        border-color: #26612eff;
    }
    .footer {
        background-color: #26612eff;
        color: #fff;
        padding: 40px 0;
        margin-top: 40px;
    }
    .footer .contact-info {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        max-width: 1200px;
        margin: 0 auto;
    }
    .footer .contact-info > div {
        flex: 1;
        min-width: 200px;
        margin-bottom: 20px;
    }
    .footer .contact-info p {
        margin: 5px 0;
        font-size: 0.95rem;
    }
    .footer .contact-info .phone {
        color: #ff5722;
        font-weight: bold;
    }
    .footer .social a {
        color: #fff;
        font-size: 1.5rem;
        margin: 0 10px;
    }
    .footer .social a:hover {
        color: #ff5722;
    }
    .dropdown-menu {
        background-color: #27bc56ff;
        border: none;
    }
    .dropdown-item {
        color: #fff;
        font-weight: 500;
    }
    .dropdown-item:hover {
        background-color: #11b11cff;
        color: #ffccbc;
    }
    .carousel-item img {
        height: 300px;
        object-fit: cover;
        border-radius: 10px;
    }
    .carousel-caption {
        background: rgba(0, 0, 0, 0.6);
        border-radius: 10px;
    }
    @media (max-width: 991px) {
        .sidebar {
            display: none;
        }
        .sidebar.show {
            display: block !important;
        }
    }
    .btn-link {
        color: #11b11cff; 
        text-decoration: none; 
    }
    .btn-link:hover {
        color: #11b11cff; 
        background-color: transparent;
        text-decoration: none; 
    }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <div class="row align-items-center">
                <div class="col-6 col-md-2">
                    <div class="logo">
                        <img src="https://thachan.vn/theme/wbthachan/wp-content/uploads/2023/10/thach-an-caobangfood.jpg" alt="">
                    </div>
                </div>
                <div class="col-12 col-md-8">
                    <form class="search-form d-flex" id="searchForm">
                        <input type="text" class="form-control me-2" id="searchInput" placeholder="Search for dishes..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </form>
                </div>
                <div class="col-6 col-md-2 d-flex justify-content-end">
                    <div class="cart me-2" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <img src="https://img.pikbest.com/png-images/qiantu/shopping-cart-icon-png-free-image_2605207.png!sw800" alt="Cart">
                        <span class="badge" id="cartBadge">0</span>
                    </div>
                    <div class="account dropdown">
                        <a href="#" class="dropdown-toggle" id="accountDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="https://cdn.kona-blue.com/upload/kona-blue_com/post/images/2024/09/18/457/avatar-mac-dinh-11.jpg" alt="Avatar">
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="accountDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">Orders</a></li>
                            <li><a class="dropdown-item" href="admin.php">Admin</a></li>
                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'restaurant_owner'): ?>
                                <li><a class="dropdown-item" href="addsanpham.php">Add Dish</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" href="ASM.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#cartModal">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cartModalLabel">Cart</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modal-cart-items"></div>
                        <p id="modal-cart-empty" class="text-center">Cart is empty.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="orderButton">Place Order</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productDetailsModalLabel">Dish Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <img src="" id="detailImage" alt="Menu Image" class="img-fluid" style="max-height: 300px; object-fit: cover;">
                            </div>
                            <div class="col-md-8">
                                <h4 id="detailName"></h4>
                                <p id="detailPrice"></p>
                                <p id="detailDescription"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="content mt-4">
            <div class="row">
                <div class="col-12 mb-3 d-lg-none">
                    <button class="btn sidebar-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
                        <i class="fas fa-bars"></i> Categories
                    </button>
                </div>
                <div class="col-lg-3 col-md-4 col-12 sidebar" id="sidebarCollapse">
                    <h3>Main Dishes</h3>
                    <ul class="list-group">
                        <li class="list-group-item"><a href="#" data-name="Smoked Buffalo Meat">Smoked Buffalo Meat</a></li>
                        <li class="list-group-item"><a href="#" data-name="Seven-Course Duck">Seven-Course Duck</a></li>
                        <li class="list-group-item"><a href="#" data-name="Roasted Suckling Pig">Roasted Suckling Pig</a></li>
                        <li class="list-group-item"><a href="#" data-name="Lap Suon">Lap Suon</a></li>
                        <li class="list-group-item"><a href="#" data-name="Banh Cuon">Banh Cuon</a></li>
                    </ul>
                    <h3 class="mt-4">Desserts</h3>
                    <ul class="list-group">
                        <li class="list-group-item"><a href="#" data-name="Ant Egg Cake">Ant Egg Cake</a></li>
                        <li class="list-group-item"><a href="#" data-name="Che Lam cake">Che Lam cake</a></li>
                        <li class="list-group-item"><a href="#" data-name="Trung Khanh Chestnuts">Trung Khanh Chestnuts</a></li>
                        <li class="list-group-item"><a href="#" data-name="Black Jelly">Black Jelly</a></li>
                    </ul>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div id="foodCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="https://autourasia.com/uploads/Travel-Guide-Vietnam/caobang/discover-12-famous-delicacies-in-cao-bang/700-bo-gac-bep-cao-bang.jpg" class="d-block w-100" alt="Food Promotion 1">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Smoked Buffalo Meat</h5>
                                    <p>Enjoy Smoked Buffalo Meat with fresh ingredients.</p>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <img src="https://autourasia.com/uploads/Travel-Guide-Vietnam/caobang/discover-12-famous-delicacies-in-cao-bang/700-lap-suon-hun-khoi-cao-bang.jpg" class="d-block w-100" alt="Food Promotion 2">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Smoked Sausage</h5>
                                    <p>Enjoy Smoked Sausage with a delightful flavor.</p>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <img src="https://autourasia.com/uploads/Travel-Guide-Vietnam/caobang/discover-12-famous-delicacies-in-cao-bang/700-hat-de-trung-khanh-cao-bang.jpg" class="d-block w-100" alt="Food Promotion 2">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Trung Khanh Chestnuts</h5>
                                    <p>Enjoy the traditional taste of Trung Khanh Chestnuts through the flavor of the dish.</p>
                                </div>
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#foodCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#foodCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <div class="mb-4">
                        <h3>Products</h3>
                        <form class="category-filter" method="GET" action="ASM.php">
                            <div class="input-group">
                                <select name="loai" class="form-select" onchange="this.form.submit()">
                                    <option value="" <?php echo $category === '' ? 'selected' : ''; ?>>All</option>
                                    <option value="Main Dishes" <?php echo $category === 'Món Ăn Chính' ? 'selected' : ''; ?>>Main Dishes</option>
                                    <option value="Desserts" <?php echo $category === 'Các món tráng miệng' ? 'selected' : ''; ?>>Desserts</option>
                                </select>
                                <?php if ($search): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if (empty($menu_items)): ?>
                            <div class="alert alert-info">No dishes found.</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-grid" id="productGrid">
                        <?php foreach ($menu_items as $item): ?>
                            <div class="product-item" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 data-price="<?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 data-description="<?php echo htmlspecialchars($item['description'] ?? 'Description not available.', ENT_QUOTES, 'UTF-8'); ?>">
                                <h4><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1550547660-d7ef11206a3b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <p>Price: <?php echo number_format($item['price'], 0, ',', '.'); ?> VND</p>
                                <div class="product-buttons">
                                    <a href="#" class="btn btn-link">Details</a>
                                    <button class="btn btn-success add-to-cart">Add to Cart</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="no-results" id="noResults" style="display: none;">No dishes found.</p>
                </div>
            </div>
        </div>
        <footer class="footer">
            <div class="contact-info">
                <div>
                    <p>About Us</p>
                    <p>Cao Bang Food brings delicious dishes, fast delivery, and spreads culinary joy!</p>
                </div>
                <div>
                    <p>Contact</p>
                    <p><i class="fas fa-map-marker-alt"></i> 27 Bac Lai Xa</p>
                    <p><i class="fas fa-phone"></i> <span class="phone">0384687885</span></p>
                    <p><i class="fas fa-envelope"></i> lequocdat468@gmail.com</p>
                </div>
                <div class="social">
                    <a href="#">Contact</a>
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cartItems = JSON.parse(localStorage.getItem('cart')) || [];
            const modalCartContainer = document.getElementById('modal-cart-items');
            const modalCartEmptyMessage = document.getElementById('modal-cart-empty');
            const productGrid = document.getElementById('productGrid');
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('searchInput');
            const noResultsMessage = document.getElementById('noResults');
            const orderButton = document.getElementById('orderButton');
            const cartBadge = document.getElementById('cartBadge');

            function updateCart() {
                modalCartContainer.innerHTML = '';
                const totalItems = cartItems.reduce((sum, item) => sum + item.quantity, 0);
                cartBadge.textContent = totalItems;
                cartBadge.style.display = totalItems > 0 ? 'block' : 'none';

                if (cartItems.length === 0) {
                    modalCartEmptyMessage.style.display = 'block';
                    orderButton.disabled = true;
                } else {
                    modalCartEmptyMessage.style.display = 'none';
                    orderButton.disabled = false;
                    cartItems.forEach(item => {
                        const cartItem = document.createElement('div');
                        cartItem.classList.add('cart-item', 'd-flex', 'justify-content-between', 'align-items-center', 'py-2');
                        cartItem.innerHTML = `
                            <span>${item.name} - ${item.price.toLocaleString('vi-VN')} VND x ${item.quantity}</span>
                            <button class="btn btn-danger btn-sm remove-from-cart" data-id="${item.id}">Remove</button>
                        `;
                        modalCartContainer.appendChild(cartItem);
                    });
                }

                document.querySelectorAll('.remove-from-cart').forEach(button => {
                    button.addEventListener('click', () => {
                        const id = button.getAttribute('data-id');
                        const item = cartItems.find(item => item.id === id);
                        if (item) {
                            item.quantity -= 1;
                            if (item.quantity <= 0) {
                                const index = cartItems.findIndex(item => item.id === id);
                                cartItems.splice(index, 1);
                            }
                            localStorage.setItem('cart', JSON.stringify(cartItems));
                            updateCart();
                        }
                    });
                });
            }

            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', () => {
                    const product = button.closest('.product-item');
                    const id = product.getAttribute('data-id');
                    const name = product.getAttribute('data-name');
                    const price = parseFloat(product.getAttribute('data-price'));

                    const existingItem = cartItems.find(item => item.id === id);
                    if (existingItem) {
                        existingItem.quantity += 1;
                    } else {
                        cartItems.push({ id, name, price, quantity: 1 });
                    }

                    localStorage.setItem('cart', JSON.stringify(cartItems));
                    updateCart();
                });
            });

            orderButton.addEventListener('click', () => {
                if (cartItems.length > 0) {
                    const total_price = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                    fetch('ASM.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=place_order&total_price=${total_price}&cart_items=${encodeURIComponent(JSON.stringify(cartItems))}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            cartItems.length = 0;
                            localStorage.setItem('cart', JSON.stringify(cartItems));
                            updateCart();
                            bootstrap.Modal.getInstance(document.getElementById('cartModal')).hide();
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error sending order: ' + error.message);
                    });
                }
            });

            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const searchTerm = searchInput.value.trim();
                window.location.href = `ASM.php?search=${encodeURIComponent(searchTerm)}`;
            });

            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.trim();
                if (searchTerm === '') {
                    window.location.href = `ASM.php`;
                }
            });

            document.querySelectorAll('.sidebar .list-group-item a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productName = link.getAttribute('data-name');
                    searchInput.value = productName;
                    window.location.href = `ASM.php?search=${encodeURIComponent(productName)}`;
                });
            });

            function filterProducts() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const productItems = productGrid.querySelectorAll('.product-item');
                let hasResults = false;

                productItems.forEach(item => {
                    const productName = item.getAttribute('data-name').toLowerCase();
                    if (productName.includes(searchTerm)) {
                        item.style.display = 'block';
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                noResultsMessage.style.display = hasResults || searchTerm === '' ? 'none' : 'block';
            }

            document.querySelectorAll('.btn-link').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const product = button.closest('.product-item');
                    const name = product.getAttribute('data-name');
                    const price = parseFloat(product.getAttribute('data-price'));
                    const image = product.querySelector('img').src;
                    const description = product.getAttribute('data-description');

                    document.getElementById('detailName').textContent = name;
                    document.getElementById('detailPrice').textContent = `Price: ${price.toLocaleString('vi-VN')} VND`;
                    document.getElementById('detailImage').src = image;
                    document.getElementById('detailDescription').textContent = description;

                    const productDetailsModal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
                    productDetailsModal.show();
                });
            });

            updateCart();
            filterProducts();
        });
    </script>
</body>
</html>
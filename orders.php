<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing orders.php\n", FILE_APPEND);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF validation failed.";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed\n", FILE_APPEND);
    } else {
        $order_id = $_POST['order_id'];
        $rating = intval($_POST['rating']);
        $content = trim($_POST['content']);
        if ($rating < 1 || $rating > 5) {
            $error_message = "Rating must be between 1 and 5!";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT order_id FROM Orders WHERE order_id = :order_id AND customer_id = :customer_id");
                $stmt->execute([':order_id' => $order_id, ':customer_id' => $_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    $error_message = "Order does not exist or you do not have permission to submit feedback!";
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid order ID $order_id\n", FILE_APPEND);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO Feedback (order_id, customer_id, content, rating, created_at) VALUES (:order_id, :customer_id, :content, :rating, NOW())");
                    $stmt->execute([
                        ':order_id' => $order_id,
                        ':customer_id' => $_SESSION['user_id'],
                        ':content' => $content,
                        ':rating' => $rating
                    ]);
                    $success_message = "Feedback submitted successfully!";
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Feedback submitted successfully for order ID $order_id\n", FILE_APPEND);
                }
            } catch (PDOException $e) {
                $error_message = "Error submitting feedback: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error submitting feedback: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT o.order_id, o.status, o.order_date, SUM(oi.quantity * oi.price) AS total_price, 
                           GROUP_CONCAT(m.title SEPARATOR ', ') AS product_names
                           FROM Orders o 
                           JOIN Order_Items oi ON o.order_id = oi.order_id 
                           JOIN Menus m ON oi.menu_id = m.menu_id 
                           WHERE o.customer_id = :customer_id 
                           GROUP BY o.order_id");
    $stmt->execute([':customer_id' => $_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving order list: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving order list: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - Order History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #006838;
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .logo img {
            height: 80px;
            object-fit: contain;
        }
        .orders-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .orders-table th, .orders-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .orders-table th {
            background-color: #226f4cff;
            color: #fff;
        }
        .orders-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .form-container {
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }
        .form-container label {
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-container textarea, .form-container select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ff5722;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: #333;
            margin-bottom: 1rem;
        }
        .form-container button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, #ff5722, #d32f2f);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
        }
        .form-container button:hover {
            background: linear-gradient(to right, #d32f2f, #b71c1c);
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
                    <h2 class="text-center text-white">Order History</h2>
                </div>
                <div class="col-6 col-md-2 d-flex justify-content-end">
                    <div class="account dropdown">
                        <a href="#" class="dropdown-toggle" id="accountDropdown" data-bs-toggle="dropdown">
                            <img src="https://cdn.kona-blue.com/upload/kona-blue_com/post/images/2024/09/18/457/avatar-mac-dinh-11.jpg" alt="Avatar" class="rounded-circle" style="width: 50px; height: 50px;">
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="ASM.php">Home</a></li>
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'restaurant_owner'): ?>
                                <li><a class="dropdown-item" href="addsanpham.php">Add Dish</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="orders-container">
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <h2>Order History</h2>
            <table class="orders-table">
                <tr>
                    <th>ID</th>
                    <th>Order Date</th>
                    <th>Product Names</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                        <td><?php echo htmlspecialchars($order['product_names']); ?></td>
                        <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?> VND</td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td>
                            <?php if ($order['status'] === 'delivered'): ?>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $order['order_id']; ?>">Submit Feedback</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <div class="modal fade" id="feedbackModal<?php echo $order['order_id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Submit Feedback for Order #<?php echo $order['order_id']; ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="submit_feedback">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="form-container">
                                            <label for="rating<?php echo $order['order_id']; ?>">Rating (1-5)</label>
                                            <select id="rating<?php echo $order['order_id']; ?>" name="rating" required>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                            <label for="content<?php echo $order['order_id']; ?>">Feedback Content</label>
                                            <textarea id="content<?php echo $order['order_id']; ?>" name="content" rows="4"></textarea>
                                            <button type="submit">Submit Feedback</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </table>
        </div>
        <footer class="footer">
            <div class="contact-info">
                <div>
                    <p>About Us</p>
                    <p>Food Order brings delicious dishes, fast delivery, and spreads culinary joy!</p>
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
</body>
</html>
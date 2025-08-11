<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting processing admin.php\n", FILE_APPEND);

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
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully connected to MySQL database $dbname\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id, username, role FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User not found with ID {$_SESSION['user_id']}\n", FILE_APPEND);
        die("User information not found.");
    }
    $current_role = $user['role'];
    $_SESSION['role'] = $current_role;
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user information: " . htmlspecialchars($e->getMessage()));
}

// Auto role assignment logic
function autoAssignRole($pdo, $user_id) {
    global $logFile;
    try {
        // Check the number of orders by the user
        $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM Orders WHERE customer_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $order_count = $stmt->fetchColumn();

        // Check the number of positive feedbacks (rating >= 4)
        $stmt = $pdo->prepare("SELECT COUNT(*) as feedback_count FROM Feedback WHERE customer_id = :user_id AND rating >= 4");
        $stmt->execute([':user_id' => $user_id]);
        $feedback_count = $stmt->fetchColumn();

        // Auto role assignment rules
        $current_role = null;
        $stmt = $pdo->prepare("SELECT role FROM Users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $current_role = $stmt->fetchColumn();

        if ($order_count >= 10 && $feedback_count >= 5 && $current_role !== 'admin') {
            $stmt = $pdo->prepare("UPDATE Users SET role = 'restaurant_owner' WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Automatically promoted user ID $user_id to restaurant_owner (order_count: $order_count, feedback_count: $feedback_count)\n", FILE_APPEND);
            return "Automatically promoted to restaurant_owner due to meeting requirements.";
        }
        return null;
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error auto-assigning role for user ID $user_id: " . $e->getMessage() . "\n", FILE_APPEND);
        return "Error auto-assigning role.";
    }
}

$auto_role_message = '';
if ($current_role === 'admin') {
    $stmt = $pdo->query("SELECT user_id FROM Users WHERE role NOT IN ('admin')");
    $users_to_check = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($users_to_check as $user_id) {
        $message = autoAssignRole($pdo, $user_id);
        if ($message) {
            $auto_role_message .= "<p class='text-info'>$message</p>";
        }
    }
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_role === 'admin') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF validation failed.";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed\n", FILE_APPEND);
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'promote' && isset($_POST['id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE Users SET role = 'admin' WHERE user_id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - User ID {$_POST['id']} promoted to admin\n", FILE_APPEND);
                $success_message = "User promoted successfully.";
            } catch (PDOException $e) {
                $error_message = "Error promoting user: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error promoting user: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        if (isset($_POST['action']) && $_POST['action'] === 'demote' && isset($_POST['id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE Users SET role = 'customer' WHERE user_id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - User ID {$_POST['id']} demoted to customer\n", FILE_APPEND);
                $success_message = "User demoted successfully.";
            } catch (PDOException $e) {
                $error_message = "Error demoting user: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error demoting user: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['id']) && isset($_POST['status'])) {
            try {
                $stmt = $pdo->prepare("UPDATE Orders SET status = :status WHERE order_id = :id");
                $stmt->execute([':status' => $_POST['status'], ':id' => $_POST['id']]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order ID {$_POST['id']} status updated to {$_POST['status']}\n", FILE_APPEND);
                $success_message = "Order status updated successfully.";
            } catch (PDOException $e) {
                $error_message = "Error updating order status: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error updating order status: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        if (isset($_POST['action']) && $_POST['action'] === 'delete_order' && isset($_POST['id'])) {
            try {
                $stmt = $pdo->prepare("SELECT order_id FROM Orders WHERE order_id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                if (!$stmt->fetch()) {
                    $error_message = "Order not found.";
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order ID {$_POST['id']} does not exist\n", FILE_APPEND);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Orders WHERE order_id = :id");
                    $stmt->execute([':id' => $_POST['id']]);
                    $pdo->exec("ALTER TABLE Orders AUTO_INCREMENT = 1");
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Order ID {$_POST['id']} deleted successfully\n", FILE_APPEND);
                    $success_message = "Order deleted successfully.";
                }
            } catch (PDOException $e) {
                $error_message = "Error deleting order: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error deleting order: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
}

try {
    $stmt = $pdo->query("SELECT user_id, username, email, role FROM Users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user list: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user list: " . htmlspecialchars($e->getMessage()));
}

$orders = [];
if ($current_role === 'admin') {
    try {
        $stmt = $pdo->query("SELECT o.order_id, u.username AS customer_name, o.status, o.order_date, SUM(oi.quantity * oi.price) AS total_price 
                             FROM Orders o 
                             JOIN Users u ON o.customer_id = u.user_id 
                             JOIN Order_Items oi ON o.order_id = oi.order_id 
                             GROUP BY o.order_id");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving order list: " . $e->getMessage() . "\n", FILE_APPEND);
        die("Error retrieving order list: " . htmlspecialchars($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - Admin Dashboard</title>
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
            height: 80px;
            object-fit: contain;
        }
        .admin-dashboard {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .admin-table th, .admin-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .admin-table th {
            background-color: #226f4cff;
            color: #fff;
        }
        .admin-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .btn-warning {
            background-color: #ff9800;
            border-color: #ff9800;
        }
        .btn-warning:hover {
            background-color: #f57c00;
            border-color: #f57c00;
        }
        .btn-success {
            background-color: #4caf50;
            border-color: #4caf50;
        }
        .btn-success:hover {
            background-color: #388e3c;
            border-color: #388e3c;
        }
        .btn-danger {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }
        .btn-danger:hover {
            background-color: #b71c1c;
            border-color: #b71c1c;
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
            background-color: #27bc56ff;
            color: #ffccbc;
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
                    <h2 class="text-center text-white">Admin Dashboard</h2>
                </div>
                <div class="col-6 col-md-2 d-flex justify-content-end">
                    <div class="account dropdown">
                        <a href="#" class="dropdown-toggle" id="accountDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="https://cdn.kona-blue.com/upload/kona-blue_com/post/images/2024/09/18/457/avatar-mac-dinh-11.jpg" alt="Avatar" class="rounded-circle" style="width: 50px; height: 50px;">
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="accountDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="ASM.php">Home</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-dashboard">
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($auto_role_message): ?>
                <div class="alert alert-info"><?php echo $auto_role_message; ?></div>
            <?php endif; ?>
            <h2>Welcome <?php echo htmlspecialchars($user['username']); ?> - System Management</h2>
            <p>Current Role: <strong><?php echo htmlspecialchars($current_role); ?></strong></p>

            <h3>User List</h3>
            <table class="admin-table">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                        <td>
                            <?php if ($current_role === 'admin' && $u['user_id'] != $_SESSION['user_id']): ?>
                                <?php if ($u['role'] !== 'admin'): ?>
                                    <form action="admin.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="promote">
                                        <input type="hidden" name="id" value="<?php echo $u['user_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">Promote to Admin</button>
                                    </form>
                                <?php else: ?>
                                    <form action="admin.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="demote">
                                        <input type="hidden" name="id" value="<?php echo $u['user_id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Demote to Customer</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if ($current_role === 'admin'): ?>
                <h3>Order List</h3>
                <table class="admin-table">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Total Price</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?> VND</td>
                            <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td>
                                <form action="admin.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $order['order_id']; ?>">
                                    <input type="hidden" name="status" value="preparing">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Prepare</button>
                                </form>
                                <form action="admin.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $order['order_id']; ?>">
                                    <input type="hidden" name="status" value="delivered">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Deliver</button>
                                </form>
                                <form action="admin.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_order">
                                    <input type="hidden" name="id" value="<?php echo $order['order_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete order ID <?php echo $order['order_id']; ?>?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p class="text-danger">You do not have permission to access the order list.</p>
            <?php endif; ?>
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
            <div class="copyright text-center mt-3">
                <p>©2025 Food Order. All rights reserved</p>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
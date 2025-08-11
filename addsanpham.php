<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting processing addsanpham.php\n", FILE_APPEND);

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
    $stmt = $pdo->prepare("SELECT role FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !in_array($user['role'], ['admin', 'restaurant_owner'])) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unauthorized access to addsanpham.php by user ID {$_SESSION['user_id']}\n", FILE_APPEND);
        die("You do not have permission to access this page.");
    }
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving user information: " . htmlspecialchars($e->getMessage()));
}

$error_message = '';
$success_message = '';
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$edit_mode = false;
$edit_id = null;
$edit_title = '';
$edit_description = '';
$edit_price = '';
$edit_image_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF validation failed.";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - CSRF validation failed\n", FILE_APPEND);
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'add' && !isset($_POST['id'])) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $image_path = null;

            if (empty($title) || $price <= 0) {
                $error_message = "Please enter valid dish information!";
            } else {
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['image']['tmp_name'];
                    $fileName = $_FILES['image']['name'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $newFileName = uniqid() . '.' . $fileExtension;
                        $dest_path = $uploadDir . $newFileName;
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Attempting to move file to: $dest_path\n", FILE_APPEND);
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $image_path = $dest_path;
                            file_put_contents($logFile, date('Y-m-d H:i:s') . " - File moved successfully to: $image_path\n", FILE_APPEND);
                        } else {
                            $error_message = "Error uploading image! Check directory permissions.";
                            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to move file. Error: " . print_r(error_get_last(), true) . "\n", FILE_APPEND);
                        }
                    } else {
                        $error_message = "Only JPG, JPEG, PNG, or GIF files are allowed!";
                    }
                }

                if (!$error_message) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO Menus (title, description, owner_id, price, image_path, created_at) VALUES (:title, :description, :owner_id, :price, :image_path, NOW())");
                        $stmt->execute([
                            ':title' => $title,
                            ':description' => $description,
                            ':owner_id' => $_SESSION['user_id'],
                            ':price' => $price,
                            ':image_path' => $image_path
                        ]);
                        $success_message = "Dish added successfully!";
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Added dish '$title' successfully by user ID {$_SESSION['user_id']}. Image path: $image_path\n", FILE_APPEND);
                    } catch (PDOException $e) {
                        $error_message = "Error adding dish: " . htmlspecialchars($e->getMessage());
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error adding dish: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['id'])) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $image_path = $_POST['existing_image'];

            if (empty($title) || $price <= 0) {
                $error_message = "Please enter valid dish information!";
            } else {
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['image']['tmp_name'];
                    $fileName = $_FILES['image']['name'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $newFileName = uniqid() . '.' . $fileExtension;
                        $dest_path = $uploadDir . $newFileName;
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Attempting to move file to: $dest_path\n", FILE_APPEND);
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $image_path = $dest_path;
                            file_put_contents($logFile, date('Y-m-d H:i:s') . " - File moved successfully to: $image_path\n", FILE_APPEND);
                            if (!empty($_POST['existing_image']) && file_exists($_POST['existing_image'])) {
                                unlink($_POST['existing_image']);
                            }
                        } else {
                            $error_message = "Error uploading image! Check directory permissions.";
                            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to move file. Error: " . print_r(error_get_last(), true) . "\n", FILE_APPEND);
                        }
                    } else {
                        $error_message = "Only JPG, JPEG, PNG, or GIF files are allowed!";
                    }
                }

                if (!$error_message) {
                    try {
                        $stmt = $pdo->prepare("UPDATE Menus SET title = :title, description = :description, price = :price, image_path = :image_path WHERE menu_id = :id AND owner_id = :owner_id");
                        $stmt->execute([
                            ':title' => $title,
                            ':description' => $description,
                            ':price' => $price,
                            ':image_path' => $image_path,
                            ':id' => $_POST['id'],
                            ':owner_id' => $_SESSION['user_id']
                        ]);
                        $success_message = "Dish updated successfully!";
                        $edit_mode = false;
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updated dish ID {$_POST['id']} successfully. Image path: $image_path\n", FILE_APPEND);
                    } catch (PDOException $e) {
                        $error_message = "Error updating dish: " . htmlspecialchars($e->getMessage());
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error updating dish: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
            try {
                $stmt = $pdo->prepare("SELECT menu_id, image_path FROM Menus WHERE menu_id = :id AND owner_id = :owner_id");
                $stmt->execute([':id' => $_POST['id'], ':owner_id' => $_SESSION['user_id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) {
                    $error_message = "Dish does not exist or you do not have permission to delete!";
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Dish ID {$_POST['id']} does not exist or no permission to delete\n", FILE_APPEND);
                } else {
                    if (!empty($item['image_path']) && file_exists($item['image_path'])) {
                        unlink($item['image_path']);
                    }
                    $stmt = $pdo->prepare("DELETE FROM Menus WHERE menu_id = :id");
                    $stmt->execute([':id' => $_POST['id']]);
                    $pdo->exec("ALTER TABLE Menus AUTO_INCREMENT = 1");
                    $success_message = "Dish deleted successfully!";
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Deleted dish ID {$_POST['id']} successfully\n", FILE_APPEND);
                }
            } catch (PDOException $e) {
                $error_message = "Error deleting dish: " . htmlspecialchars($e->getMessage());
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error deleting dish: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_mode' && isset($_POST['id'])) {
            $edit_id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT title, description, price, image_path FROM Menus WHERE menu_id = :id AND owner_id = :owner_id");
            $stmt->execute([':id' => $edit_id, ':owner_id' => $_SESSION['user_id']]);
            $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($edit_item) {
                $edit_mode = true;
                $edit_title = htmlspecialchars($edit_item['title']);
                $edit_description = htmlspecialchars($edit_item['description'] ?? '');
                $edit_price = htmlspecialchars($edit_item['price']);
                $edit_image_path = htmlspecialchars($edit_item['image_path'] ?? '');
            }
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT menu_id AS id, title, description, price, image_path FROM Menus WHERE owner_id = :owner_id");
    $stmt->execute([':owner_id' => $_SESSION['user_id']]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving dish list: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error retrieving dish list: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - Dish Management</title>
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
        .form-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .form-container h2 {
            color: #006838;
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-container label {
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-container input, .form-container textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #006838;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: #333;
            margin-bottom: 1rem;
        }
        .form-container input[type="file"] {
            padding: 0.5rem;
        }
        .form-container button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, #006838, #006838);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
        }
        .form-container button:hover {
            background: linear-gradient(to right, #006838, #006838);
        }
        .menu-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .menu-table th, .menu-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .menu-table th {
            background-color: #006838;
            color: #fff;
        }
        .menu-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .menu-table tr:hover {
            background-color: #ffccbc;
        }
        .menu-table img {
            max-width: 100px;
            height: auto;
            border-radius: 5px;
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
                    <h2 class="text-center text-white">Dish Management</h2>
                </div>
                <div class="col-6 col-md-2 d-flex justify-content-end">
                    <div class="account dropdown">
                        <a href="#" class="dropdown-toggle" id="accountDropdown" data-bs-toggle="dropdown">
                            <img src="https://cdn.kona-blue.com/upload/kona-blue_com/post/images/2024/09/18/457/avatar-mac-dinh-11.jpg" alt="Avatar" class="rounded-circle" style="width: 50px; height: 50px;">
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="ASM.php">Home</a></li>
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">Orders</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-container">
            <h2><?php echo $edit_mode ? 'Update Dish' : 'Add Dish'; ?></h2>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div>
                    <label for="title">Dish Name</label>
                    <input type="text" id="title" name="title" value="<?php echo $edit_mode ? $edit_title : ''; ?>" required>
                </div>
                <div>
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?php echo $edit_mode ? $edit_description : ''; ?></textarea>
                </div>
                <div>
                    <label for="price">Price (VND)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $edit_mode ? $edit_price : ''; ?>" required>
                </div>
                <div>
                    <label for="image">Image</label>
                    <?php if ($edit_mode && !empty($edit_image_path)): ?>
                        <img src="<?php echo $edit_image_path; ?>" alt="Current Image" style="max-width: 100px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/*">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="existing_image" value="<?php echo $edit_image_path; ?>">
                    <?php endif; ?>
                </div>
                <button type="submit"><?php echo $edit_mode ? 'Update Dish' : 'Add Dish'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="addsanpham.php" class="btn btn-secondary mt-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="form-container">
            <h2>Dish List</h2>
            <table class="menu-table">
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Dish Name</th>
                    <th>Price</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($menu_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['id']); ?></td>
                        <td>
                            <?php if (!empty($item['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                No image
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                        <td><?php echo number_format($item['price'], 0, ',', '.'); ?> VND</td>
                        <td><?php echo htmlspecialchars($item['description'] ?? 'No description'); ?></td>
                        <td>
                            <form action="" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this dish?');">Delete</button>
                            </form>
                            <form action="" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit_mode">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                            </form>
                        </td>
                    </tr>
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
<?php
session_start();

// Configure logging
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing profile.php\n", FILE_APPEND);

// Database connection configuration
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
    die(json_encode(['success' => false, 'message' => 'Database connection error']));
}

// Check login
if (!isset($_SESSION['user_id'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Not logged in, redirecting to index.php\n", FILE_APPEND);
    header('Location: index.php?error=not_logged_in');
    exit;
}

// Phone number validation function
function validatePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^0[0-9]{9,10}$/', $phone);
}

// Retrieve user information
try {
    $stmt = $pdo->prepare("SELECT username, email, phone_number, address, role FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - User not found for ID {$_SESSION['user_id']}\n", FILE_APPEND);
        session_destroy();
        header('Location: index.php?error=user_not_found');
        exit;
    }
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully retrieved user information for ID {$_SESSION['user_id']}\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error retrieving user information: " . $e->getMessage() . "\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Error retrieving user information']));
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    // If phone_number or address is empty, use current values from the database
    if (empty($phone_number)) {
        $phone_number = $user['phone_number'] ?? '';
    }
    if (empty($address)) {
        $address = $user['address'] ?? '';
    }

    // Validate data
    if (empty($username) || empty($email)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Required data missing\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Username and email are required']);
        exit;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid email: $email\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }

    // Validate phone number if provided
    if (!empty($phone_number) && !validatePhoneNumber($phone_number)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid phone number: $phone_number\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE (username = :username OR email = :email) AND user_id != :user_id");
        $stmt->execute([':username' => $username, ':email' => $email, ':user_id' => $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username or email already in use!']);
            exit;
        }

        $update_query = "UPDATE Users SET username = :username, email = :email";
        $params = [':username' => $username, ':email' => $email, ':user_id' => $_SESSION['user_id']];

        // Add phone_number and address
        $update_query .= ", phone_number = :phone_number, address = :address";
        $params[':phone_number'] = $phone_number;
        $params[':address'] = $address;

        $stmt = $pdo->prepare($update_query . " WHERE user_id = :user_id");
        $stmt->execute($params);
        $_SESSION['username'] = $username;
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully updated profile for user ID {$_SESSION['user_id']}\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error updating profile for user ID {$_SESSION['user_id']}: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2Tkcm4JDI0+o+rAbh1duNdZ/7e6wrP9Lpi9EA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f1e9;
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
        .profile-form {
            max-width: 600px;
            margin: 20px auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .profile-form h2 {
            color: #2e9b51ff;
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .profile-form label {
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .profile-form input, .profile-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #2e9b51ff;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: #333;
        }
        .profile-form button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, #2e9b51ff, #3eb945ff);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
        }
        .profile-form button:hover {
            background: linear-gradient(to right, #3dac3bff, #3eb965ff);
        }
        .error-message {
            color: red;
            display: none;
            margin-top: 10px;
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
                        <img src="https://thachan.vn/theme/wbthachan/wp-content/uploads/2023/10/thach-an-caobangfood.jpg" alt="Food Order Logo">
                    </div>
                </div>
                <div class="col-6 col-md-10 text-end">
                    <a href="ASM.php" class="btn btn-success me-2">Home</a>
                    <a href="orders.php" class="btn btn-light me-2">Order History</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
        <div class="profile-form">
            <h2>User Profile</h2>
            <div id="errorMessage" class="error-message"></div>
            <form id="profileForm" class="space-y-5">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required pattern="[a-zA-Z0-9]{3,}">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Example: 0987654321">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address"><?php echo htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
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
            // Display error message from URL if present
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            if (error === 'not_logged_in') {
                alert('Please log in to access this page');
            } else if (error === 'user_not_found') {
                alert('User information not found. Please log in again');
            }

            const profileForm = document.getElementById('profileForm');
            const errorMessage = document.getElementById('errorMessage');

            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                errorMessage.style.display = 'none';

                const formData = new FormData(profileForm);
                fetch('profile.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        errorMessage.textContent = data.message;
                        errorMessage.style.display = 'block';
                    }
                })
                .catch(error => {
                    errorMessage.textContent = 'System error, please try again later';
                    errorMessage.style.display = 'block';
                    console.error('Error updating profile:', error);
                });
            });
        });
    </script>
</body>
</html>
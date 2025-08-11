<?php
session_start();
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start processing contact.php\n", FILE_APPEND);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_contact') {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

    try {
        $stmt = $pdo->prepare("INSERT INTO Contact (name, email, message, created_at) VALUES (:name, :email, :message, NOW())");
        $stmt->execute([':name' => $name, ':email' => $email, ':message' => $message]);
        echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error sending message: ' . htmlspecialchars($e->getMessage())]);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error sending message: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Cao Bang Food</title>
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
    .contact-form {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin: 20px auto;
        max-width: 600px;
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
                <div class="col-12 col-md-2">
                    <div class="logo">
                        <img src="https://thachan.vn/theme/wbthachan/wp-content/uploads/2023/10/thach-an-caobangfood.jpg" alt="Logo">
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
                        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="container mt-5">
            <h2 class="text-center mb-4">Contact Us</h2>
            <div class="contact-form">
                <form id="contactForm">
                    <div class="mb-3">
                        <label for="contactName" class="form-label">Your Name</label>
                        <input type="text" class="form-control" id="contactName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="contactEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="contactEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="contactMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="contactMessage" name="message" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
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
            const contactForm = document.getElementById('contactForm');

            contactForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(contactForm);
                formData.append('action', 'submit_contact');

                fetch('contact.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        contactForm.reset();
                    }
                })
                .catch(error => {
                    alert('Error sending message: ' + error.message);
                });
            });
        });
    </script>
</body>
</html>
<?php
session_start();
$register_msg = '';
$login_msg = '';
$error_msg = '';
$reset_msg = '';

$host = 'localhost';
$dbname = 'asm_sdlc';
$username = 'root';
$password = '';
$logFile = 'debug.log';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - MySQL connection successful with $dbname\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection error: " . $e->getMessage() . "\n", FILE_APPEND);
    $error_msg = "<p class='text-red-500 text-center'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    die($error_msg);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['form_type']) && $_POST['form_type'] == 'register') {
        $username = trim($_POST["username"]);
        $password = $_POST["password"];
        $email = trim($_POST["email"]);
        $name = trim($_POST["name"]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_msg = "<p class='text-red-500 text-center'>Invalid email!</p>";
        } elseif (!preg_match("/^[a-zA-Z0-9]{3,}$/", $username)) {
            $register_msg = "<p class='text-red-500 text-center'>Username must be at least 3 characters and contain only letters or numbers!</p>";
        } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{6,}$/", $password)) {
            $register_msg = "<p class='text-red-500 text-center'>Password must be at least 6 characters, including uppercase, lowercase, and numbers!</p>";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE username = :username OR email = :email");
                $stmt->execute([':username' => $username, ':email' => $email]);
                if ($stmt->rowCount() > 0) {
                    $register_msg = "<p class='text-red-500 text-center'>Username or email already exists!</p>";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO Registrations (name, email, submitted_at) VALUES (:name, :email, NOW())");
                    $stmt->execute([':name' => $name, ':email' => $email]);
                    $registration_id = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("INSERT INTO Users (username, password, email, role, created_at) VALUES (:username, :password, :email, 'customer', NOW())");
                    $stmt->execute([':username' => $username, ':password' => $hashed_password, ':email' => $email]);
                    $user_id = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("UPDATE Registrations SET user_id = :user_id WHERE registration_id = :registration_id");
                    $stmt->execute([':user_id' => $user_id, ':registration_id' => $registration_id]);
                    $pdo->commit();
                    $register_msg = "<p class='text-green-500 text-center'>Registration successful! Please log in.</p>";
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { toggleForm('loginForm'); });</script>";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $register_msg = "<p class='text-red-500 text-center'>Registration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Registration error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] == 'login') {
        $email = trim($_POST["email"]);
        $password = $_POST["password"];

        try {
            $stmt = $pdo->prepare("SELECT user_id, username, password, role FROM Users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: ASM.php");
                exit();
            } else {
                $login_msg = "<p class='text-red-500 text-center'>Incorrect email or password!</p>";
            }
        } catch (PDOException $e) {
            $login_msg = "<p class='text-red-500 text-center'>Login error: " . htmlspecialchars($e->getMessage()) . "</p>";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Login error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] == 'forgot') {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reset_msg = "<p class='text-red-500 text-center'>Invalid email!</p>";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                if ($stmt->rowCount() > 0) {
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (:email, :token, :expires_at)");
                    $stmt->execute([':email' => $email, ':token' => $token, ':expires_at' => $expires_at]);
                    $reset_msg = "<p class='text-green-500 text-center'>A password reset link has been sent to your email!</p>";
                    $reset_msg .= "<p class='text-blue-500 text-center'>Token (for demo): <a href='?reset_token=$token'>Reset Link</a></p>";
                } else {
                    $reset_msg = "<p class='text-red-500 text-center'>Email does not exist!</p>";
                }
            } catch (PDOException $e) {
                $reset_msg = "<p class='text-red-500 text-center'>Error creating reset link: " . htmlspecialchars($e->getMessage()) . "</p>";
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Password reset error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] == 'reset') {
        $token = trim($_POST["token"]);
        $new_password = $_POST["new_password"];
        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{6,}$/", $new_password)) {
            $reset_msg = "<p class='text-red-500 text-center'>New password must be at least 6 characters, including uppercase, lowercase, and numbers!</p>";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT email FROM password_reset_tokens WHERE token = :token AND expires_at > NOW()");
                $stmt->execute([':token' => $token]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $email = $row['email'];
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE Users SET password = :password WHERE email = :email");
                    $stmt->execute([':password' => $hashed_password, ':email' => $email]);
                    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
                    $stmt->execute([':token' => $token]);
                    $reset_msg = "<p class='text-green-500 text-center'>Password reset successfully!</p>";
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { toggleForm('loginForm'); });</script>";
                } else {
                    $reset_msg = "<p class='text-red-500 text-center'>Invalid or expired token!</p>";
                }
            } catch (PDOException $e) {
                $reset_msg = "<p class='text-red-500 text-center'>Error resetting password: " . htmlspecialchars($e->getMessage()) . "</p>";
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Password reset error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order - Register & Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

        body {
            background-image: url('https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .form-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            background-color: rgba(255, 255, 255, 0.95);
            transition: all 0.4s ease-in-out;
            z-index: 2;
            position: relative;
        }

        .form-container.hidden {
            transform: translateY(50px);
            opacity: 0;
            pointer-events: none;
            position: absolute;
        }

        .form-container.active {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
            position: relative;
        }

        .form-container h2 {
            color: #19b648ff;
            font-size: 2rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .form-container label {
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .form-container input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #19b648ff;
            border-radius: 0.5rem;
            background-color: #fff;
            font-size: 1rem;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-container input:focus {
            border-color: #19b648ff;
            box-shadow: 0 0 8px rgba(211, 47, 47, 0.3);
            outline: none;
        }

        .form-container button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, #19b648ff, #19b648ff);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .form-container button:hover {
            background: linear-gradient(to right, #19b648ff, #19b648ff);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .form-container a {
            color: #19b648ff;
            font-weight: 500;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .form-container a:hover {
            color: #19b648ff;
            text-decoration: underline;
        }

        .form-container p {
            color: #333;
            margin-top: 1.25rem;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="relative w-full max-w-md p-6">
        <div id="registerForm" class="form-container active">
            <h2>Register</h2>
            <?php echo $error_msg; ?>
            <?php echo $register_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="register">
                <div>
                    <label for="regName" class="block">Full Name</label>
                    <input type="text" id="regName" name="name" class="mt-1" required>
                </div>
                <div>
                    <label for="regEmail" class="block">Email</label>
                    <input type="email" id="regEmail" name="email" class="mt-1" required>
                </div>
                <div>
                    <label for="regUsername" class="block">Username</label>
                    <input type="text" id="regUsername" name="username" class="mt-1" required pattern="[a-zA-Z0-9]{3,}" title="Username must be at least 3 characters and contain only letters or numbers">
                </div>
                <div>
                    <label for="regPassword" class="block">Password</label>
                    <input type="password" id="regPassword" name="password" class="mt-1" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{6,}" title="Password must be at least 6 characters, including uppercase, lowercase, and numbers">
                </div>
                <button type="submit">Register</button>
            </form>
            <p>Already have an account? <a href="#" onclick="toggleForm('loginForm')">Login</a></p>
            <p><a href="#" onclick="toggleForm('forgotForm')">Forgot Password?</a></p>
        </div>

        <div id="loginForm" class="form-container hidden">
            <h2>Login</h2>
            <?php echo $error_msg; ?>
            <?php echo $login_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="login">
                <div>
                    <label for="loginEmail" class="block">Email</label>
                    <input type="email" id="loginEmail" name="email" class="mt-1" required>
                </div>
                <div>
                    <label for="loginPassword" class="block">Password</label>
                    <input type="password" id="loginPassword" name="password" class="mt-1" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <p>Don't have an account? <a href="#" onclick="toggleForm('registerForm')">Register</a></p>
            <p><a href="#" onclick="toggleForm('forgotForm')">Forgot Password?</a></p>
        </div>

        <div id="forgotForm" class="form-container hidden">
            <h2>Forgot Password</h2>
            <?php echo $error_msg; ?>
            <?php echo $reset_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="forgot">
                <div>
                    <label for="forgotEmail" class="block">Email</label>
                    <input type="email" id="forgotEmail" name="email" class="mt-1" required>
                </div>
                <button type="submit">Send Reset Link</button>
            </form>
            <p><a href="#" onclick="toggleForm('loginForm')">Back to Login</a></p>
        </div>

        <?php if (isset($_GET['reset_token'])): ?>
        <div id="resetForm" class="form-container active">
            <h2>Reset Password</h2>
            <?php echo $error_msg; ?>
            <?php echo $reset_msg; ?>
            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="form_type" value="reset">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['reset_token']); ?>">
                <div>
                    <label for="newPassword" class="block">New Password</label>
                    <input type="password" id="newPassword" name="new_password" class="mt-1" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{6,}" title="Password must be at least 6 characters, including uppercase, lowercase, and numbers">
                </div>
                <button type="submit">Reset Password</button>
            </form>
            <p><a href="#" onclick="toggleForm('loginForm')">Back to Login</a></p>
        </div>
        <?php else: ?>
        <div id="resetForm" class="form-container hidden">
            <h2>Reset Password</h2>
            <p class='text-red-500 text-center'>Please use the password reset link!</p>
            <p><a href="#" onclick="toggleForm('loginForm')">Back to Login</a></p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleForm(formId) {
            const forms = ['registerForm', 'loginForm', 'forgotForm', 'resetForm'];
            forms.forEach(form => {
                const element = document.getElementById(form);
                if (form === formId) {
                    element.classList.remove('hidden');
                    element.classList.add('active');
                } else {
                    element.classList.remove('active');
                    element.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>
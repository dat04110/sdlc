<?php
// Check login session
session_start();

// Simulated user data (replace with actual database if needed)
$users = [
    'admin' => ['username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
    'guest' => ['username' => 'guest', 'password' => 'guest123', 'role' => 'guest']
];

// Login function
function login($username, $password, $users) {
    if (isset($users[$username]) && $users[$username]['password'] === $password) {
        $_SESSION['user'] = $users[$username];
        return true;
    }
    return false;
}

// Permission check function
function checkPermission($requiredRole) {
    if (isset($_SESSION['user']) && $_SESSION['user']['role'] === $requiredRole) {
        return true;
    }
    return false;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password, $users)) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Incorrect username or password!";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Permissions</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error { color: red; }
        .content { margin-top: 20px; }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['user'])): ?>
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <label>Username:</label><br>
            <input type="text" name="username" required><br>
            <label>Password:</label><br>
            <input type="password" name="password" required><br><br>
            <button type="submit">Login</button>
        </form>
    <?php else: ?>
        <h2>Hello, <?php echo $_SESSION['user']['username']; ?>!</h2>
        <p>Role: <?php echo $_SESSION['user']['role']; ?></p>
        <a href="?logout=true">Logout</a>
        
        <div class="content">
            <?php if (checkPermission('admin')): ?>
                <h3>Admin Content</h3>
                <p>You have administrator privileges. You can edit, delete, or add content.</p>
            <?php elseif (checkPermission('guest')): ?>
                <h3>Guest Content</h3>
                <p>You only have permission to view content.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
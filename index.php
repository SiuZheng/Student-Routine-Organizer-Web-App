<?php
session_start();
require 'db.php'; // database connection

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Load admin configuration
        $admin_config = require 'admin_config.php';
        $hardcoded_admin = $admin_config['hardcoded_admin'];
        
        if ($email === $hardcoded_admin['email'] && $password === $hardcoded_admin['password']) {
            // Hardcoded admin login
            $_SESSION['user_id'] = 'admin_' . time(); // Unique admin ID
            $_SESSION['username'] = 'Lee (Admin)';
            $_SESSION['email'] = $email;
            $_SESSION['is_hardcoded_admin'] = true;
            
            header("Location: dashboard.php");
            exit;
        } elseif ($user && password_verify($password, $user['password'])) {
            // Regular user login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $email;
            $_SESSION['is_hardcoded_admin'] = false;
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Learning Platform</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🎓</h1>
            <div class="subtitle">Welcome back!</div>
        </div>
        
        <h2>Login</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>

        <div class="divider">
            <span>or</span>
        </div>

        <p>Don't have an account? <a href="register.php" class="link">Register here</a></p>
    </div>
</body>
</html>
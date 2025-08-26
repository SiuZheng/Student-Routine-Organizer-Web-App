<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Learning Platform</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container dashboard-container">
        <div class="logo">
            <h1>🎯</h1>
            <div class="subtitle">Ready to learn?</div>
        </div>
        
        <div class="welcome-section">
            <h2>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
            <p>Choose a module to continue your learning journey</p>
        </div>
        
        <div class="modules-grid">
            <div class="module-card">
                <h3>📚</h3>
                <a href="module1.php">Module 1</a>
            </div>
            
            <div class="module-card">
                <h3>🔬</h3>
                <a href="module2.php">Module 2</a>
            </div>
            
            <div class="module-card">
                <h3>💻</h3>
                <a href="module3.php">Module 3</a>
            </div>
            
            <div class="module-card">
                <h3>🎨</h3>
                <a href="module4.php">Module 4</a>
            </div>
        </div>
        
        <div class="logout-section">
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>
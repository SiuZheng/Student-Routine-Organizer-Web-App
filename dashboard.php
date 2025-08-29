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
    <title>Student Routine - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container dashboard-container">
        <div class="logo">
            <h1>🗓️</h1>
            <div class="subtitle">Plan, track, and build better habits</div>
        </div>
        
        <div class="welcome-section">
            <h2>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
            <p>Choose a tool to manage your daily routines and progress</p>
        </div>
        
        <div class="modules-grid">
            <a class="module-card" href="module1.php">
                <h3>💪</h3>
                <span class="module-card-title">Exercise Tracker</span>
            </a>
            
            <a class="module-card" href="module2.php">
                <h3>📔</h3>
                <span class="module-card-title">Diary Journal</span>
            </a>
            
            <a class="module-card" href="module3.php">
                <h3>💰</h3>
                <span class="module-card-title">Money Tracker</span>
            </a>
            
            <a class="module-card" href="module4.php">
                <h3>📈</h3>
                <span class="module-card-title">Habit Tracker</span>
            </a>
        </div>
        
        <div class="logout-section">
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>
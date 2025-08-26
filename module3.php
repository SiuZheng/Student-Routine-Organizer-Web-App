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
    <title>Module 3 - Learning Platform</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container dashboard-container">
        <div class="logo">
            <h1>💻</h1>
            <div class="subtitle">Technology & Coding</div>
        </div>
        
        <h2>Module 3</h2>
        
        <div class="form-section">
            <h3>Coming Soon!</h3>
            <p>This module is currently under construction. We're working hard to bring you amazing content!</p>
        </div>
        
        <div class="back-section">
            <a href="dashboard.php" class="link">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
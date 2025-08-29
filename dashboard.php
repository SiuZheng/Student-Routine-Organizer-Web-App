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
        
        <?php 
        // Load admin configuration
        $admin_config = require 'admin_config.php';
        $hardcoded_admin = $admin_config['hardcoded_admin'];
        
        $is_hardcoded_admin = isset($_SESSION['email']) && $_SESSION['email'] === $hardcoded_admin['email'];
        
        // Check if user is admin (hardcoded only)
        $is_admin = $is_hardcoded_admin;
        
        // Handle user deletion for admin
        if ($is_admin && (isset($_GET['delete_user']) || isset($_POST['delete_user']))) {
            $user_id_to_delete = (int) ($_GET['delete_user'] ?? $_POST['delete_user']);
            
            // Don't allow admin to delete themselves
            if ($user_id_to_delete != $_SESSION['user_id']) {
                try {
                    require 'db.php';
                    // Delete user and all related data (CASCADE will handle this)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id_to_delete])) {
                        $delete_message = "User deleted successfully!";
                        $delete_message_type = "success";
                    } else {
                        $delete_message = "Error deleting user.";
                        $delete_message_type = "error";
                    }
                } catch (PDOException $e) {
                    $delete_message = "Error deleting user: " . $e->getMessage();
                    $delete_message_type = "error";
                }
            } else {
                $delete_message = "You cannot delete your own account!";
                $delete_message_type = "error";
            }
        }
        
        if ($is_admin): ?>
            <!-- Direct Admin Dashboard Content -->
            <div class="welcome-section">
                <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>! 👑</h2>
                <p>System Administration Dashboard - Monitor and manage the platform</p>
            </div>
            
            <?php if (isset($delete_message)): ?>
                <div class="alert alert-<?= $delete_message_type ?>">
                    <span><?= htmlspecialchars($delete_message) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>
            
            <?php
            // Load admin data directly
            require 'db.php';
            
            // Fetch all users with their statistics
            $users_query = "
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.created_at,
                    COUNT(DISTINCT e.id) as exercise_count,
                    COUNT(DISTINCT d.id) as diary_count,
                    COUNT(DISTINCT t.id) as transaction_count,
                    COUNT(DISTINCT h.id) as habit_count,
                    COALESCE(SUM(e.calories_burned), 0) as total_calories,
                    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expenses,
                    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income
                FROM users u
                LEFT JOIN exercises e ON u.id = e.user_id
                LEFT JOIN diary_entries d ON u.id = d.user_id
                LEFT JOIN transactions t ON u.id = t.user_id
                LEFT JOIN habits h ON u.id = h.user_id
                GROUP BY u.id, u.username, u.email, u.created_at
                ORDER BY u.created_at DESC
            ";
            
            $users_stmt = $pdo->prepare($users_query);
            $users_stmt->execute();
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate system-wide statistics
            $total_users = count($users);
            $total_exercises = array_sum(array_column($users, 'exercise_count'));
            $total_diary_entries = array_sum(array_column($users, 'diary_count'));
            $total_transactions = array_sum(array_column($users, 'transaction_count'));
            $total_habits = array_sum(array_column($users, 'habit_count'));
            $total_calories = array_sum(array_column($users, 'total_calories'));
            $total_expenses = array_sum(array_column($users, 'total_expenses'));
            $total_income = array_sum(array_column($users, 'total_income'));
            
            // Get recent activity (last 7 days)
            $recent_activity_query = "
                SELECT 
                    'exercise' as type,
                    e.exercise_name as title,
                    u.username,
                    e.created_at,
                    CONCAT(e.duration_minutes, ' min - ', e.calories_burned, ' cal') as details
                FROM exercises e
                JOIN users u ON e.user_id = u.id
                WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'diary' as type,
                    LEFT(d.entry_text, 50) as title,
                    u.username,
                    d.created_at,
                    d.mood as details
                FROM diary_entries d
                JOIN users u ON d.user_id = u.id
                WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'transaction' as type,
                    CONCAT(t.type, ' - ', t.category) as title,
                    u.username,
                    t.created_at,
                    CONCAT('$', t.amount) as details
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'habit' as type,
                    h.habit_name as title,
                    u.username,
                    h.created_at,
                    h.status as details
                FROM habits h
                JOIN users u ON h.user_id = u.id
                WHERE h.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                ORDER BY created_at DESC
                LIMIT 15
            ";
            
            $recent_stmt = $pdo->prepare($recent_activity_query);
            $recent_stmt->execute();
            $recent_activities = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <!-- System Overview Statistics -->
            <div class="admin-overview">
                <h3>📊 System Overview</h3>
                <div class="stats-grid admin-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_users ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_exercises ?></div>
                        <div class="stat-label">Exercise Records</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_diary_entries ?></div>
                        <div class="stat-label">Diary Entries</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_transactions ?></div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $total_habits ?></div>
                        <div class="stat-label">Habits Tracked</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($total_calories) ?></div>
                        <div class="stat-label">Total Calories</div>
                    </div>
                </div>
            </div>

            <!-- Financial Overview -->
            <div class="admin-section">
                <h3>💰 Financial Overview</h3>
                <div class="financial-stats">
                    <div class="financial-card income">
                        <div class="financial-label">Total Income</div>
                        <div class="financial-amount">$<?= number_format($total_income, 2) ?></div>
                    </div>
                    <div class="financial-card expense">
                        <div class="financial-label">Total Expenses</div>
                        <div class="financial-amount">$<?= number_format($total_expenses, 2) ?></div>
                    </div>
                    <div class="financial-card balance">
                        <div class="financial-label">Net Balance</div>
                        <div class="financial-amount">$<?= number_format($total_income - $total_expenses, 2) ?></div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="admin-section">
                <h3>👥 Registered Users</h3>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Exercises</th>
                                <th>Diary</th>
                                <th>Transactions</th>
                                <th>Habits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="username"><?= htmlspecialchars($user['username']) ?></div>
                                            <?php if ($user['email'] === $hardcoded_admin['email']): ?>
                                                <span class="admin-badge">👑 Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <span class="count-badge exercise"><?= $user['exercise_count'] ?></span>
                                        <?php if ($user['total_calories'] > 0): ?>
                                            <div class="sub-text"><?= number_format($user['total_calories']) ?> cal</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="count-badge diary"><?= $user['diary_count'] ?></span>
                                    </td>
                                    <td>
                                        <span class="count-badge transaction"><?= $user['transaction_count'] ?></span>
                                        <?php if ($user['total_expenses'] > 0 || $user['total_income'] > 0): ?>
                                            <div class="sub-text">
                                                +$<?= number_format($user['total_income'], 2) ?> / -$<?= number_format($user['total_expenses'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="count-badge habit"><?= $user['habit_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($user['email'] !== $hardcoded_admin['email']): ?>
                                            <button class="btn btn-orange btn-small" 
                                                    onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                Delete
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="admin-section">
                <h3>🕒 Recent Activity (Last 7 Days)</h3>
                <div class="activity-list">
                    <?php if (empty($recent_activities)): ?>
                        <div class="no-activity">
                            <p>No recent activity found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item activity-<?= $activity['type'] ?>">
                                <div class="activity-icon">
                                    <?php
                                    switch($activity['type']) {
                                        case 'exercise': echo '💪'; break;
                                        case 'diary': echo '📔'; break;
                                        case 'transaction': echo '💰'; break;
                                        case 'habit': echo '🎯'; break;
                                        default: echo '📝';
                                    }
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?= htmlspecialchars($activity['title']) ?></div>
                                    <div class="activity-user">by <?= htmlspecialchars($activity['username']) ?></div>
                                    <div class="activity-details"><?= htmlspecialchars($activity['details']) ?></div>
                                </div>
                                <div class="activity-time">
                                    <?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Regular User Dashboard View -->
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
        <?php endif; ?>
        
        <div class="logout-section">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <script>
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone and will remove all their data.`)) {
                // Submit delete request directly to dashboard.php
                window.location.href = `?delete_user=${userId}`;
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
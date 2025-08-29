<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Handle Add Habit
if (isset($_POST['add'])) {
    $habit_name = trim($_POST['habit_name']);
    $habit_description = trim($_POST['habit_description']);
    $date = $_POST['date'];

    if (!empty($habit_name) && !empty($date)) {
        $stmt = $pdo->prepare("INSERT INTO habits (user_id, habit_name, habit_description, date, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$user_id, $habit_name, $habit_description, $date])) {
            $message = "Habit added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding habit. Please try again.";
            $message_type = "error";
        }
    } else {
        $message = "Please fill all required fields.";
        $message_type = "error";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$id, $user_id])) {
        $message = "Habit deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting habit.";
        $message_type = "error";
    }
}

// Handle Status Toggle
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE habits SET status = CASE WHEN status = 'done' THEN 'pending' ELSE 'done' END WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$id, $user_id])) {
        $message = "Habit status updated!";
        $message_type = "success";
    } else {
        $message = "Error updating habit status.";
        $message_type = "error";
    }
}

// Handle Edit (Update)
if (isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $habit_name = trim($_POST['habit_name']);
    $habit_description = trim($_POST['habit_description']);
    $date = $_POST['date'];

    if (!empty($habit_name) && !empty($date)) {
        $stmt = $pdo->prepare("UPDATE habits SET habit_name=?, habit_description=?, date=? WHERE id=? AND user_id=?");
        if ($stmt->execute([$habit_name, $habit_description, $date, $id, $user_id])) {
            $message = "Habit updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating habit.";
            $message_type = "error";
        }
    } else {
        $message = "Please fill all required fields.";
        $message_type = "error";
    }
}

// Fetch all habits for logged-in user
$stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = ? ORDER BY date DESC, created_at DESC");
$stmt->execute([$user_id]);
$habits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_habits = count($habits);
$completed_today = 0;
$pending_today = 0;
$today = date('Y-m-d');

foreach ($habits as $habit) {
    if ($habit['date'] === $today) {
        if ($habit['status'] === 'done') {
            $completed_today++;
        } else {
            $pending_today++;
        }
    }
}

$completion_rate = ($completed_today + $pending_today) > 0 ? round(($completed_today / ($completed_today + $pending_today)) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Tracker - Learning Platform</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-blue));
            color: var(--white);
            padding: 20px;
            border-radius: var(--border-radius-small);
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .stat-card h4 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .habit-item {
            background: var(--white);
            border: 2px solid #e1e5e9;
            border-radius: var(--border-radius-small);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .habit-item:hover {
            border-color: var(--primary-green);
            transform: translateY(-2px);
        }
        
        .habit-item.done {
            border-color: var(--primary-green);
            background: linear-gradient(135deg, #f0fff0, #e6ffe6);
        }
        
        .habit-item.pending {
            border-color: var(--accent-orange);
        }
        
        .habit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .habit-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .habit-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-done {
            background: var(--primary-green);
            color: var(--white);
        }
        
        .status-pending {
            background: var(--accent-orange);
            color: var(--white);
        }
        
        .habit-description {
            color: var(--text-light);
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .habit-date {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .habit-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-toggle {
            background: var(--secondary-blue);
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius-small);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-toggle:hover {
            background: var(--secondary-blue-hover);
            transform: translateY(-2px);
        }
        
        .btn-toggle.done {
            background: var(--primary-green);
        }
        
        .btn-toggle.done:hover {
            background: var(--primary-green-hover);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .habits-section {
            margin-top: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid var(--primary-green);
            background: transparent;
            color: var(--primary-green);
            border-radius: var(--border-radius-small);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active,
        .filter-btn:hover {
            background: var(--primary-green);
            color: var(--white);
        }
        
        .no-habits {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }
        
        .no-habits h4 {
            margin-bottom: 10px;
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <div class="container dashboard-container">
        <div class="logo">
            <h1>🎯</h1>
            <div class="subtitle">Build Positive Routines</div>
        </div>
        
        <h2>Habit Tracker</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4><?= $total_habits ?></h4>
                <p>Total Habits</p>
            </div>
            <div class="stat-card">
                <h4><?= $completed_today ?></h4>
                <p>Completed Today</p>
            </div>
            <div class="stat-card">
                <h4><?= $pending_today ?></h4>
                <p>Pending Today</p>
            </div>
            <div class="stat-card">
                <h4><?= $completion_rate ?>%</h4>
                <p>Today's Progress</p>
            </div>
        </div>

        <div class="form-section">
            <h3>Add New Habit</h3>
            <form method="POST" class="habit-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="habit_name">Habit Name *</label>
                        <input type="text" id="habit_name" name="habit_name" placeholder="e.g., Drink 8 glasses of water" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date">Date *</label>
                        <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group full-width">
                    <label for="habit_description">Description (Optional)</label>
                    <textarea id="habit_description" name="habit_description" rows="3" placeholder="Add details about your habit..."></textarea>
                </div>
                
                <button type="submit" name="add" class="btn">Add Habit</button>
            </form>
        </div>

        <div class="habits-section">
            <div class="section-header">
                <h3>Your Habits</h3>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterHabits('all')">All</button>
                    <button class="filter-btn" onclick="filterHabits('today')">Today</button>
                    <button class="filter-btn" onclick="filterHabits('pending')">Pending</button>
                    <button class="filter-btn" onclick="filterHabits('done')">Completed</button>
                </div>
            </div>
            
            <?php if (empty($habits)): ?>
                <div class="no-habits">
                    <h4>🎯 No habits yet!</h4>
                    <p>Start building positive routines by adding your first habit above.</p>
                </div>
            <?php else: ?>
                <div class="habits-list">
                    <?php foreach ($habits as $habit): ?>
                        <div class="habit-item <?= $habit['status'] ?>" data-status="<?= $habit['status'] ?>" data-date="<?= $habit['date'] ?>">
                            <div class="habit-header">
                                <div class="habit-name"><?= htmlspecialchars($habit['habit_name']) ?></div>
                                <span class="habit-status status-<?= $habit['status'] ?>">
                                    <?= ucfirst($habit['status']) ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($habit['habit_description'])): ?>
                                <div class="habit-description"><?= htmlspecialchars($habit['habit_description']) ?></div>
                            <?php endif; ?>
                            
                            <div class="habit-date">
                                📅 <?= date('M j, Y', strtotime($habit['date'])) ?>
                                <?php if ($habit['date'] === $today): ?>
                                    <span style="color: var(--primary-green); font-weight: 600;">(Today)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="habit-actions">
                                <button class="btn-toggle <?= $habit['status'] ?>" 
                                        onclick="toggleStatus(<?= $habit['id'] ?>)">
                                    <?= $habit['status'] === 'done' ? '✓ Mark Pending' : '✓ Mark Done' ?>
                                </button>
                                <button class="btn btn-secondary btn-small" onclick="editHabit(<?= $habit['id'] ?>)">Edit</button>
                                <a href="?delete=<?= $habit['id'] ?>" class="btn btn-orange btn-small" 
                                   onclick="return confirm('Delete this habit?')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="back-section">
            <a href="dashboard.php" class="link">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        function toggleStatus(id) {
            window.location.href = `?toggle=${id}`;
        }
        
        function editHabit(id) {
            // Find the habit item
            const habitItem = event.target.closest('.habit-item');
            const habitName = habitItem.querySelector('.habit-name').textContent;
            const habitDescription = habitItem.querySelector('.habit-description')?.textContent || '';
            const habitDate = habitItem.querySelector('.habit-date').textContent.match(/📅 (.+)/)[1];
            
            // Create edit form
            const editForm = `
                <form method="POST" class="habit-form">
                    <input type="hidden" name="id" value="${id}">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_habit_name">Habit Name *</label>
                            <input type="text" id="edit_habit_name" name="habit_name" value="${habitName}" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_date">Date *</label>
                            <input type="date" id="edit_date" name="date" value="${new Date(habitDate).toISOString().split('T')[0]}" required>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="edit_habit_description">Description (Optional)</label>
                        <textarea id="edit_habit_description" name="habit_description" rows="3">${habitDescription}</textarea>
                    </div>
                    
                    <div class="habit-actions">
                        <button type="submit" name="update" class="btn">Update Habit</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                    </div>
                </form>
            `;
            
            // Replace habit item with edit form
            habitItem.innerHTML = editForm;
        }
        
        function cancelEdit() {
            location.reload();
        }
        
        function filterHabits(filter) {
            const habits = document.querySelectorAll('.habit-item');
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            // Update active filter button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            const today = new Date().toISOString().split('T')[0];
            
            habits.forEach(habit => {
                const status = habit.dataset.status;
                const date = habit.dataset.date;
                
                let show = true;
                
                switch(filter) {
                    case 'today':
                        show = date === today;
                        break;
                    case 'pending':
                        show = status === 'pending';
                        break;
                    case 'done':
                        show = status === 'done';
                        break;
                    default: // 'all'
                        show = true;
                }
                
                habit.style.display = show ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
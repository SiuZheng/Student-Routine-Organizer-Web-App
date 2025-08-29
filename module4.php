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

        <!-- Two Column Layout -->
        <div class="habit-dashboard-layout">
            <!-- Left Sidebar - Statistics Widgets -->
            <div class="habit-sidebar">
                <!-- Total Habits Widget -->
                <div class="sidebar-widget">
                    <div class="widget-header">
                        <h4>📊 Total Habits</h4>
                    </div>
                    <div class="widget-content">
                        <div class="widget-number"><?= $total_habits ?></div>
                        <div class="widget-label">Active Habits</div>
                    </div>
                </div>

                <!-- Completed Today Widget -->
                <div class="sidebar-widget">
                    <div class="widget-header">
                        <h4>✅ Completed Today</h4>
                    </div>
                    <div class="widget-content">
                        <div class="widget-number"><?= $completed_today ?></div>
                        <div class="widget-label">Tasks Done</div>
                    </div>
                </div>

                <!-- Progress Chart Widget -->
                <div class="sidebar-widget progress-widget">
                    <div class="widget-header">
                        <h4>📊 Progress Overview</h4>
                    </div>
                    <div class="widget-content">
                        <!-- Today's Progress Bar -->
                        <div class="sidebar-progress-item">
                            <div class="sidebar-progress-label">Today's Progress</div>
                            <div class="sidebar-progress-bar">
                                <div class="sidebar-progress-fill <?= $completed_today > 0 ? 'completed' : 'pending' ?>" 
                                     style="width: <?= $completion_rate ?>%"></div>
                            </div>
                            <div class="sidebar-progress-text"><?= $completion_rate ?>%</div>
                        </div>
                        
                        <!-- Completion Rate -->
                        <div class="sidebar-progress-item">
                            <div class="sidebar-progress-label">Completion Rate</div>
                            <div class="sidebar-progress-bar">
                                <div class="sidebar-progress-fill completed" 
                                     style="width: <?= min(100, ($completed_today / max(1, $total_habits)) * 100) ?>%"></div>
                            </div>
                            <div class="sidebar-progress-text"><?= $completed_today ?>/<?= $total_habits ?></div>
                        </div>
                        
                        <!-- Pending Tasks -->
                        <div class="sidebar-progress-item">
                            <div class="sidebar-progress-label">Pending Tasks</div>
                            <div class="sidebar-progress-bar">
                                <div class="sidebar-progress-fill pending" 
                                     style="width: <?= ($completed_today + $pending_today) > 0 ? ($pending_today / ($completed_today + $pending_today)) * 100 : 0 ?>%"></div>
                            </div>
                            <div class="sidebar-progress-text"><?= $pending_today ?></div>
                        </div>
                    </div>
                </div>

                <!-- Add Habit Button -->
                <div class="sidebar-widget add-habit-widget">
                    <button class="add-habit-btn" onclick="toggleHabitForm()">
                        ✨ Add New Habit
                    </button>
                </div>
            </div>

            <!-- Right Main Panel - Habits Display -->
            <div class="habit-main-panel">
                <!-- Header with Date Selector and Filters -->
                <div class="main-panel-header">
                    <div class="header-left">
                        <h3>Your Habits</h3>
                        <div class="date-selector">
                            <label for="habit_date">Date:</label>
                            <input type="date" id="habit_date" value="<?= date('Y-m-d') ?>" onchange="filterHabitsByDate(this.value)">
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button class="filter-btn active" onclick="filterHabits('all')">All</button>
                        <button class="filter-btn" onclick="filterHabits('today')">Today</button>
                        <button class="filter-btn" onclick="filterHabits('pending')">Pending</button>
                        <button class="filter-btn" onclick="filterHabits('done')">Completed</button>
                    </div>
                </div>



                <!-- Add Habit Form (Hidden by default) -->
                <div class="habit-form-section" id="habitFormSection" style="display: none;">
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
                            
                            <div class="form-actions">
                                <button type="submit" name="add" class="btn">Add Habit</button>
                                <button type="button" class="btn btn-secondary" onclick="toggleHabitForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Habits by Category -->
                <div class="habits-categories">
                    <!-- Daily Habits Section -->
                    <div class="category-section">
                        <div class="category-header daily-header">
                            <h4>📅 Daily Habits</h4>
                            <span class="category-count"><?= count(array_filter($habits, function($h) use ($today) { return $h['date'] === $today; })) ?> habits</span>
                        </div>
                        <div class="habits-grid">
                            <?php 
                            $daily_habits = array_filter($habits, function($h) use ($today) { return $h['date'] === $today; });
                            if (empty($daily_habits)): ?>
                                <div class="no-habits">
                                    <p>No daily habits for today. Add some above!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($daily_habits as $habit): ?>
                                    <div class="habit-card <?= $habit['status'] ?>" data-status="<?= $habit['status'] ?>" data-date="<?= $habit['date'] ?>">
                                        <div class="habit-card-content">
                                            <div class="habit-name"><?= htmlspecialchars($habit['habit_name']) ?></div>
                                            <?php if (!empty($habit['habit_description'])): ?>
                                                <div class="habit-description"><?= htmlspecialchars($habit['habit_description']) ?></div>
                                            <?php endif; ?>
                                            <div class="habit-meta">
                                                <span class="habit-date">📅 <?= date('M j, Y', strtotime($habit['date'])) ?></span>
                                                <span class="habit-status-badge status-<?= $habit['status'] ?>"><?= ucfirst($habit['status']) ?></span>
                                            </div>
                                        </div>
                                        <div class="habit-actions">
                                            <button class="btn-toggle <?= $habit['status'] ?>" 
                                                    onclick="toggleStatus(<?= $habit['id'] ?>)">
                                                <?= $habit['status'] === 'done' ? '✓' : '○' ?>
                                            </button>
                                            <button class="btn btn-secondary btn-small" onclick="editHabit(<?= $habit['id'] ?>)">Edit</button>
                                            <a href="?delete=<?= $habit['id'] ?>" class="btn btn-orange btn-small" 
                                               onclick="return confirm('Delete this habit?')">Delete</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- All Habits Section -->
                    <div class="category-section">
                        <div class="category-header all-header">
                            <h4>📋 All Habits</h4>
                            <span class="category-count"><?= count($habits) ?> total</span>
                        </div>
                        <div class="habits-grid">
                            <?php if (empty($habits)): ?>
                                <div class="no-habits">
                                    <h4>🎯 No habits yet!</h4>
                                    <p>Start building positive routines by adding your first habit above.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($habits as $habit): ?>
                                    <div class="habit-card <?= $habit['status'] ?>" data-status="<?= $habit['status'] ?>" data-date="<?= $habit['date'] ?>">
                                        <div class="habit-card-content">
                                            <div class="habit-name"><?= htmlspecialchars($habit['habit_name']) ?></div>
                                            <?php if (!empty($habit['habit_description'])): ?>
                                                <div class="habit-description"><?= htmlspecialchars($habit['habit_description']) ?></div>
                                            <?php endif; ?>
                                            <div class="habit-meta">
                                                <span class="habit-date">📅 <?= date('M j, Y', strtotime($habit['date'])) ?></span>
                                                <span class="habit-status-badge status-<?= $habit['status'] ?>"><?= ucfirst($habit['status']) ?></span>
                                            </div>
                                        </div>
                                        <div class="habit-actions">
                                            <button class="btn-toggle <?= $habit['status'] ?>" 
                                                    onclick="toggleStatus(<?= $habit['id'] ?>)">
                                                <?= $habit['status'] === 'done' ? '✓' : '○' ?>
                                            </button>
                                            <button class="btn btn-secondary btn-small" onclick="editHabit(<?= $habit['id'] ?>)">Edit</button>
                                            <a href="?delete=<?= $habit['id'] ?>" class="btn btn-orange btn-small" 
                                               onclick="return confirm('Delete this habit?')">Delete</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <!-- Back to Dashboard Button - Bottom Center -->
    <div class="back-section-bottom">
        <a href="dashboard.php" class="link">← Back to Dashboard</a>
    </div>

    <script>
        function toggleStatus(id) {
            window.location.href = `?toggle=${id}`;
        }
        
        function editHabit(id) {
            // Find the habit card
            const habitCard = event.target.closest('.habit-card');
            const habitName = habitCard.querySelector('.habit-name').textContent;
            const habitDescription = habitCard.querySelector('.habit-description')?.textContent || '';
            const habitDate = habitCard.querySelector('.habit-date').textContent.match(/📅 (.+)/)[1];
            
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
                    
                    <div class="form-actions">
                        <button type="submit" name="update" class="btn">Update Habit</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                    </div>
                </form>
            `;
            
            // Replace habit card with edit form
            habitCard.innerHTML = editForm;
        }
        
        function cancelEdit() {
            location.reload();
        }
        
        function toggleHabitForm() {
            const formSection = document.getElementById('habitFormSection');
            if (formSection.style.display === 'none') {
                formSection.style.display = 'block';
                formSection.scrollIntoView({ behavior: 'smooth' });
            } else {
                formSection.style.display = 'none';
            }
        }
        
        function filterHabits(filter) {
            const habitCards = document.querySelectorAll('.habit-card');
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            // Update active filter button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            const today = new Date().toISOString().split('T')[0];
            
            habitCards.forEach(card => {
                const status = card.dataset.status;
                const date = card.dataset.date;
                
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
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        function filterHabitsByDate(date) {
            const habitCards = document.querySelectorAll('.habit-card');
            const selectedDate = new Date(date).toISOString().split('T')[0];
            
            habitCards.forEach(card => {
                const cardDate = card.dataset.date;
                card.style.display = (cardDate === selectedDate) ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
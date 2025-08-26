<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle Add Exercise
if (isset($_POST['add'])) {
    $exercise_name = trim($_POST['exercise_name']);
    $duration = (int) $_POST['duration'];
    $calories = (int) $_POST['calories'];
    $date = $_POST['date'];

    if (!empty($exercise_name) && $duration > 0 && $calories > 0 && !empty($date)) {
        $stmt = $pdo->prepare("INSERT INTO exercises (user_id, exercise_name, duration_minutes, calories_burned, date) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $exercise_name, $duration, $calories, $date])) {
            $message = "Exercise added successfully!";
        }
    } else {
        $message = "Please fill all fields correctly.";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM exercises WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $message = "Exercise deleted.";
}

// Handle Edit (Update)
if (isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $exercise_name = trim($_POST['exercise_name']);
    $duration = (int) $_POST['duration'];
    $calories = (int) $_POST['calories'];
    $date = $_POST['date'];

    $stmt = $pdo->prepare("UPDATE exercises SET exercise_name=?, duration_minutes=?, calories_burned=?, date=? WHERE id=? AND user_id=?");
    if ($stmt->execute([$exercise_name, $duration, $calories, $date, $id, $user_id])) {
        $message = "Exercise updated successfully!";
    }
}

// Fetch all exercises for logged-in user
$stmt = $pdo->prepare("SELECT * FROM exercises WHERE user_id = ? ORDER BY date DESC");
$stmt->execute([$user_id]);
$exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exercise Tracker - Learning Platform</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container dashboard-container">
        <div class="logo">
            <h1>💪</h1>
            <div class="subtitle">Track your fitness journey</div>
        </div>
        
        <h2>Exercise Tracker</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h3>Add New Exercise</h3>
            <form method="POST" class="exercise-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="exercise_name">Exercise Name</label>
                        <input type="text" id="exercise_name" name="exercise_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duration (minutes)</label>
                        <input type="number" id="duration" name="duration" required min="1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="calories">Calories Burned</label>
                        <input type="number" id="calories" name="calories" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" required>
                    </div>
                </div>
                
                <button type="submit" name="add" class="btn">Add Exercise</button>
            </form>
        </div>

        <div class="exercises-section">
            <h3>Your Exercise History</h3>
            <?php if (empty($exercises)): ?>
                <p class="no-data">No exercises recorded yet. Start by adding your first exercise above!</p>
            <?php else: ?>
                <div class="exercises-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Exercise</th>
                                <th>Duration</th>
                                <th>Calories</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exercises as $exercise): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exercise['exercise_name']) ?></td>
                                    <td><?= $exercise['duration_minutes'] ?> min</td>
                                    <td><?= $exercise['calories_burned'] ?> cal</td>
                                    <td><?= date('M j, Y', strtotime($exercise['date'])) ?></td>
                                    <td class="actions">
                                        <button class="btn btn-secondary btn-small" onclick="editExercise(<?= $exercise['id'] ?>)">Edit</button>
                                        <a href="?delete=<?= $exercise['id'] ?>" class="btn btn-orange btn-small" onclick="return confirm('Delete this exercise?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="back-section">
            <a href="dashboard.php" class="link">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        function editExercise(id) {
            // Simple edit functionality - you can enhance this with a modal
            const row = event.target.closest('tr');
            const cells = row.cells;
            
            // Replace text with input fields
            cells[0].innerHTML = `<input type="text" value="${cells[0].textContent}" name="edit_name_${id}">`;
            cells[1].innerHTML = `<input type="number" value="${cells[1].textContent.replace(' min', '')}" name="edit_duration_${id}">`;
            cells[2].innerHTML = `<input type="number" value="${cells[2].textContent.replace(' cal', '')}" name="edit_calories_${id}">`;
            cells[3].innerHTML = `<input type="date" value="${cells[3].getAttribute('data-date')}" name="edit_date_${id}">`;
            
            // Replace actions with save/cancel buttons
            cells[4].innerHTML = `
                <button class="btn btn-small" onclick="saveExercise(${id})">Save</button>
                <button class="btn btn-secondary btn-small" onclick="cancelEdit(${id})">Cancel</button>
            `;
        }
        
        function saveExercise(id) {
            // Implement save functionality
            alert('Save functionality would be implemented here');
        }
        
        function cancelEdit(id) {
            // Reload page to cancel edit
            location.reload();
        }
    </script>
</body>
</html>
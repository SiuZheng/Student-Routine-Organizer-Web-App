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
            header("Location: module1.php?success=added");
            exit;
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
    header("Location: module1.php?success=deleted");
    exit;
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
        header("Location: module1.php?success=updated");
        exit;
    }
}

// Handle success messages from redirects
$message = "";
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = "Exercise added successfully!";
            break;
        case 'deleted':
            $message = "Exercise deleted.";
            break;
        case 'updated':
            $message = "Exercise updated successfully!";
            break;
    }
}

// Fetch all exercises for logged-in user
// Sorting controls
$allowedSort = [
    'date' => 'date',
    'calories' => 'calories_burned',
    'duration' => 'duration_minutes',
];
$sortParam = isset($_GET['sort']) ? strtolower($_GET['sort']) : 'date';
$sortColumn = $allowedSort[$sortParam] ?? 'date';

$stmt = $pdo->prepare("SELECT * FROM exercises WHERE user_id = ? ORDER BY {$sortColumn} DESC");
$stmt->execute([$user_id]);
$exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overview statistics
$statsStmt = $pdo->prepare("SELECT COUNT(*) AS total_count, AVG(duration_minutes) AS avg_duration, AVG(calories_burned) AS avg_calories FROM exercises WHERE user_id = ?");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'avg_duration' => 0, 'avg_calories' => 0];
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
        <a href="dashboard.php" class="back-button" aria-label="Back to dashboard">←</a>
        <div class="logo">
            <h1>💪</h1>
            <div class="subtitle">Track your fitness journey</div>
        </div>
        
        <div class="top-bar">
            <h2>Exercise Tracker</h2>
            <button type="button" class="btn btn-small btn-purple" onclick="openAddModal()">Add New Exercise</button>
        </div>

        <div class="overview">
            <div class="overview-card">
                <div class="overview-label">📈 Total Exercises</div>
                <div class="overview-value"><?= (int)($stats['total_count'] ?? 0) ?></div>
            </div>
            <div class="overview-card">
                <div class="overview-label">⏱️ Avg. Duration</div>
                <div class="overview-value"><?= $stats['avg_duration'] ? number_format((float)$stats['avg_duration'], 1) : 0 ?> min</div>
            </div>
            <div class="overview-card">
                <div class="overview-label">🔥 Avg. Calories</div>
                <div class="overview-value"><?= $stats['avg_calories'] ? number_format((float)$stats['avg_calories'], 1) : 0 ?> cal</div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><span><?= htmlspecialchars($message) ?></span><button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button></div>
        <?php endif; ?>

        <!-- Add Exercise Modal -->
        <div id="addModal" class="modal-overlay" style="display:none;">
            <div class="modal">
                <button class="modal-close" aria-label="Close" onclick="closeAddModal()">×</button>
                <h3>Add New Exercise</h3>
                <form method="POST" class="exercise-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="exercise_name">Exercise Name</label>
                            <input type="text" id="exercise_name" name="exercise_name" required>
                            <div class="input-hint">Example: Running, Push-ups, Cycling</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration (minutes)</label>
                            <input type="number" id="duration" name="duration" required min="1">
                            <div class="input-hint">Example: 45</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="calories">Calories Burned</label>
                            <input type="number" id="calories" name="calories" required min="1">
                            <div class="input-hint">Example: 300</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" required value="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="add" class="btn">Add Exercise</button>
                </form>
            </div>
        </div>

        <div class="exercises-section">
            <h3>Your Exercise History</h3>
            <div class="sort-pills">
                <a href="module1.php?sort=date" class="pill <?= ($sortParam==='date')?'active':'' ?>">📅 Date</a>
                <a href="module1.php?sort=calories" class="pill <?= ($sortParam==='calories')?'active':'' ?>">🔥 Calories</a>
                <a href="module1.php?sort=duration" class="pill <?= ($sortParam==='duration')?'active':'' ?>">⏱️ Duration</a>
            </div>
            <?php if (empty($exercises)): ?>
                <p class="no-data">No exercises recorded yet. Start by adding your first exercise above!</p>
            <?php else: ?>
                <div class="exercises-table">
                    <table>
                        <thead>
                            <tr>
                                <th>🏋️ Exercise</th>
                                <th>⏱️ Duration</th>
                                <th>🔥 Calories</th>
                                <th>📅 Date</th>
                                <th>⚙️ Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exercises as $exercise): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exercise['exercise_name']) ?></td>
                                    <td><?= $exercise['duration_minutes'] ?> min</td>
                                    <td><?= $exercise['calories_burned'] ?> cal</td>
                                    <td data-date="<?= htmlspecialchars($exercise['date']) ?>"><?= date('M j, Y', strtotime($exercise['date'])) ?></td>
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
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        function editExercise(id) {
            const row = event.target.closest('tr');
            const cells = row.cells;

            // Store original values to allow cancel without reload
            row.dataset.originalName = cells[0].textContent;
            row.dataset.originalDuration = cells[1].textContent.replace(' min', '').trim();
            row.dataset.originalCalories = cells[2].textContent.replace(' cal', '').trim();
            row.dataset.originalDateText = cells[3].textContent;
            row.dataset.originalDate = cells[3].getAttribute('data-date') || '';

            // Replace text with input fields (ensure full value visibility without expanding row)
            cells[0].innerHTML = `<input type="text" value="${cells[0].textContent}" name="edit_name_${id}" style="width:100%">`;
            cells[1].innerHTML = `<input type="number" value="${row.dataset.originalDuration}" name="edit_duration_${id}" style="width:100%">`;
            cells[2].innerHTML = `<input type="number" value="${row.dataset.originalCalories}" name="edit_calories_${id}" style="width:100%">`;
            cells[3].innerHTML = `<input type="date" value="${row.dataset.originalDate}" name="edit_date_${id}" style="width:100%">`;

            // Replace actions with save/cancel buttons
            cells[4].innerHTML = `
                <button class="btn btn-small" onclick="saveExercise(${id})">Save</button>
                <button class="btn btn-danger btn-small" onclick="cancelEdit(${id})">Cancel</button>
            `;
        }
        
        function saveExercise(id) {
            const row = document.querySelector(`button[onclick="saveExercise(${id})"]`).closest('tr');
            const nameInput = row.querySelector(`input[name="edit_name_${id}"]`);
            const durationInput = row.querySelector(`input[name="edit_duration_${id}"]`);
            const caloriesInput = row.querySelector(`input[name="edit_calories_${id}"]`);
            const dateInput = row.querySelector(`input[name="edit_date_${id}"]`);

            const form = document.getElementById('updateForm');
            form.elements['id'].value = id;
            form.elements['exercise_name'].value = nameInput.value.trim();
            form.elements['duration'].value = durationInput.value;
            form.elements['calories'].value = caloriesInput.value;
            form.elements['date'].value = dateInput.value;
            form.submit();
        }
        
        function cancelEdit(id) {
            const row = document.querySelector(`button[onclick="cancelEdit(${id})"]`).closest('tr');
            const cells = row.cells;

            // Restore original values
            cells[0].textContent = row.dataset.originalName || cells[0].textContent;
            cells[1].textContent = `${row.dataset.originalDuration} min`;
            cells[2].textContent = `${row.dataset.originalCalories} cal`;
            cells[3].setAttribute('data-date', row.dataset.originalDate || '');
            cells[3].textContent = row.dataset.originalDateText || cells[3].textContent;

            // Restore actions
            cells[4].innerHTML = `
                <button class="btn btn-secondary btn-small" onclick="editExercise(${id})">Edit</button>
                <a href="?delete=${id}" class="btn btn-orange btn-small" onclick="return confirm('Delete this exercise?')">Delete</a>
            `;
        }
    </script>
    <form method="POST" id="updateForm" style="display:none;">
        <input type="hidden" name="update" value="1">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="exercise_name" value="">
        <input type="hidden" name="duration" value="">
        <input type="hidden" name="calories" value="">
        <input type="hidden" name="date" value="">
    </form>
</body>
</html>
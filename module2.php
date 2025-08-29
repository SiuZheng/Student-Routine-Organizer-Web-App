<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Add Diary Entry
if (isset($_POST['add'])) {
    $entry_text = trim($_POST['entry_text']);
    $mood = $_POST['mood'];
    $entry_date = $_POST['entry_date'];
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
    $attachment_path = '';

    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            $attachment_path = $upload_dir . uniqid() . '.' . $file_extension;
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path)) {
                $attachment_path = '';
            }
        }
    }

    if (!empty($entry_text) && !empty($mood) && !empty($entry_date)) {
        $stmt = $pdo->prepare("INSERT INTO diary_entries (user_id, entry_text, mood, tags, attachment_path, entry_date) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $entry_text, $mood, $tags, $attachment_path, $entry_date])) {
            header("Location: module2.php?success=added");
            exit;
        }
    } else {
        $message = "Please fill all required fields.";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM diary_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: module2.php?success=deleted");
    exit;
}

// Handle Edit (Update)
if (isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $entry_text = trim($_POST['entry_text']);
    $mood = $_POST['mood'];
    $entry_date = $_POST['entry_date'];
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';

    // Get current attachment
    $stmt = $pdo->prepare("SELECT attachment_path FROM diary_entries WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    $attachment_path = $current ? $current['attachment_path'] : '';

    // If user chose to remove it
    if (!empty($_POST['remove_attachment']) && $attachment_path && file_exists($attachment_path)) {
        unlink($attachment_path);
        $attachment_path = '';
    }

    // If user uploaded a new one
    if (isset($_FILES['new_attachment']) && $_FILES['new_attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_extension = pathinfo($_FILES['new_attachment']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg','jpeg','png','gif','webp'];

        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            // Delete old file if exists
            if ($attachment_path && file_exists($attachment_path)) {
                unlink($attachment_path);
            }

            $attachment_path = $upload_dir . uniqid() . '.' . $file_extension;
            move_uploaded_file($_FILES['new_attachment']['tmp_name'], $attachment_path);
        }
    }

    // Update DB
    $stmt = $pdo->prepare("UPDATE diary_entries SET entry_text=?, mood=?, tags=?, entry_date=?, attachment_path=? WHERE id=? AND user_id=?");
    if ($stmt->execute([$entry_text, $mood, $tags, $entry_date, $attachment_path, $id, $user_id])) {
        header("Location: module2.php?success=updated");
        exit;
    }
}

// Handle success messages from redirects
$message = "";
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = "Diary entry added successfully!";
            break;
        case 'deleted':
            $message = "Diary entry deleted.";
            break;
        case 'updated':
            $message = "Diary entry updated successfully!";
            break;
    }
}

// Handle search and filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$mood_filter = isset($_GET['mood_filter']) ? $_GET['mood_filter'] : '';
$tag_filter = isset($_GET['tag_filter']) ? trim($_GET['tag_filter']) : '';

// Build WHERE clause for filters
$where_conditions = ["user_id = ?"];
$params = [$user_id];

if (!empty($search_query)) {
    $where_conditions[] = "entry_text LIKE ?";
    $params[] = "%{$search_query}%";
}

if (!empty($mood_filter) && $mood_filter !== 'all') {
    $where_conditions[] = "mood = ?";
    $params[] = $mood_filter;
}

if (!empty($tag_filter)) {
    $where_conditions[] = "tags LIKE ?";
    $params[] = "%{$tag_filter}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch filtered diary entries
$stmt = $pdo->prepare("SELECT * FROM diary_entries WHERE {$where_clause} ORDER BY entry_date DESC");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique tags for filter dropdown
$tag_stmt = $pdo->prepare("SELECT DISTINCT tags FROM diary_entries WHERE user_id = ? AND tags IS NOT NULL AND tags != ''");
$tag_stmt->execute([$user_id]);
$all_tags_raw = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
$all_tags = [];
foreach ($all_tags_raw as $tag_string) {
    $individual_tags = array_filter(array_map('trim', explode(',', $tag_string)));
    $all_tags = array_merge($all_tags, $individual_tags);
}
$all_tags = array_unique($all_tags);
sort($all_tags);

// Overview statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_count,
        COUNT(CASE WHEN mood = 'happy' THEN 1 END) AS happy_count,
        COUNT(CASE WHEN mood = 'excited' THEN 1 END) AS excited_count,
        COUNT(CASE WHEN DATE(entry_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS week_count
    FROM diary_entries 
    WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'happy_count' => 0, 'excited_count' => 0, 'week_count' => 0];

// Calculate average words per entry
$total_words = 0;
foreach ($entries as $entry) {
    $total_words += str_word_count($entry['entry_text']);
}
$avg_words = $stats['total_count'] > 0 ? round($total_words / $stats['total_count']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diary Journal - Student Routine Organizer</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container dashboard-container">
        <a href="dashboard.php" class="back-button" aria-label="Back to dashboard">←</a>
        <div class="logo">
            <h1>📔</h1>
            <div class="subtitle">Express your thoughts & feelings</div>
        </div>
        
        <div class="top-bar">
            <h2>Diary Journal</h2>
            <button type="button" class="btn btn-small btn-purple" onclick="openAddModal()">Write New Entry</button>
        </div>

        <div class="overview">
            <div class="overview-card">
                <div class="overview-label">📝 Total Entries</div>
                <div class="overview-value"><?= (int)($stats['total_count'] ?? 0) ?></div>
            </div>
            <div class="overview-card">
                <div class="overview-label">📅 This Month</div>
                <div class="overview-value">
                    <?php
                    $monthStmt = $pdo->prepare("SELECT COUNT(*) FROM diary_entries WHERE user_id = ? AND MONTH(entry_date) = MONTH(CURDATE()) AND YEAR(entry_date) = YEAR(CURDATE())");
                    $monthStmt->execute([$user_id]);
                    echo (int)$monthStmt->fetchColumn();
                    ?>
                </div>
            </div>
            <div class="overview-card">
                <div class="overview-label">🔥 This Week</div>
                <div class="overview-value"><?= (int)($stats['week_count'] ?? 0) ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><span><?= htmlspecialchars($message) ?></span><button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button></div>
        <?php endif; ?>

        <!-- Add Entry Modal -->
        <div id="addModal" class="modal-overlay" style="display:none;">
            <div class="modal">
                <button class="modal-close" aria-label="Close" onclick="closeAddModal()">×</button>
                <h3>Write New Entry</h3>
                <form method="POST" class="exercise-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="entry_text">Your Thoughts</label>
                        <textarea id="entry_text" name="entry_text" required rows="6" placeholder="What's on your mind today?"></textarea>
                        <div class="input-hint">Share your experiences, feelings, or reflections</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mood">Current Mood</label>
                            <select id="mood" name="mood" required>
                                <option value="">Select mood</option>
                                <option value="happy">😊 Happy</option>
                                <option value="excited">🤩 Excited</option>
                                <option value="neutral">😐 Neutral</option>
                                <option value="sad">😢 Sad</option>
                                <option value="angry">😠 Angry</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="entry_date">Entry Date</label>
                            <input type="date" id="entry_date" name="entry_date" required value="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" placeholder="exam, family, friends, work...">
                        <div class="input-hint">Add tags separated by commas (e.g., exam, family, friends)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachment">Attach Image (Optional)</label>
                        <input type="file" id="attachment" name="attachment" accept="image/*">
                        <div class="input-hint">Upload a photo, screenshot, or drawing (JPG, PNG, GIF, WebP)</div>
                    </div>
                    
                    <button type="submit" name="add" class="btn">Save Entry</button>
                </form>
            </div>
        </div>

        <div class="exercises-section">
            <h3>Your Journal History</h3>
            <!-- Search and Filter Section -->
            <div class="search-filter-section">
                <div class="search-row">
                    <div class="search-group">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="🔍 Search your entries..." value="<?= htmlspecialchars($search_query) ?>">
                            <button type="button" class="search-btn" onclick="performSearch()">Search</button>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <select id="moodFilter" onchange="applyFilters()">
                            <option value="all">All Moods</option>
                            <option value="happy" <?= $mood_filter === 'happy' ? 'selected' : '' ?>>😊 Happy</option>
                            <option value="excited" <?= $mood_filter === 'excited' ? 'selected' : '' ?>>🤩 Excited</option>
                            <option value="neutral" <?= $mood_filter === 'neutral' ? 'selected' : '' ?>>😐 Neutral</option>
                            <option value="sad" <?= $mood_filter === 'sad' ? 'selected' : '' ?>>😢 Sad</option>
                            <option value="angry" <?= $mood_filter === 'angry' ? 'selected' : '' ?>>😠 Angry</option>
                        </select>
                        
                        <select id="tagFilter" onchange="applyFilters()">
                            <option value="">All Tags</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?= htmlspecialchars($tag) ?>" <?= $tag_filter === $tag ? 'selected' : '' ?>>
                                    🏷️ <?= htmlspecialchars($tag) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($search_query || $mood_filter || $tag_filter): ?>
                            <button type="button" class="clear-filters-btn" onclick="clearFilters()">Clear Filters</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (empty($entries)): ?>
                <p class="no-data">No journal entries yet. Start documenting your thoughts and feelings!</p>
            <?php else: ?>
                <div class="journal-entries">
                    <?php 
                    $mood_icons = [
                        'happy' => '😊',
                        'sad' => '😢',
                        'neutral' => '😐',
                        'angry' => '😠',
                        'excited' => '🤩'
                    ];
                    $mood_colors = [
                        'happy' => '#10b981',
                        'excited' => '#ce82ff',
                        'neutral' => '#64748b',
                        'sad' => '#1cb0f6',
                        'angry' => '#ff9600'
                    ];
                    ?>
                    <?php foreach ($entries as $entry): ?>
                        <div class="entry-card" data-entry-id="<?= $entry['id'] ?>">
                            <div class="entry-header">
                                <div class="entry-date">
                                    <span class="date-text"><?= date('M j, Y', strtotime($entry['entry_date'])) ?></span>
                                    <span class="time-ago"><?= date('D', strtotime($entry['entry_date'])) ?></span>
                                </div>
                                <div class="mood-indicator" style="background: <?= $mood_colors[$entry['mood']] ?>">
                                    <?= $mood_icons[$entry['mood']] ?>
                                </div>
                            </div>
                            
                            <div class="entry-content">
                                <?php if (!empty($entry['attachment_path']) && file_exists($entry['attachment_path'])): ?>
                                    <div class="entry-attachment">
                                        <img src="<?= htmlspecialchars($entry['attachment_path']) ?>" alt="Journal attachment" class="attachment-image" onclick="openImageModal('<?= htmlspecialchars($entry['attachment_path']) ?>')">
                                    </div>
                                <?php endif; ?>
                                
                                <p class="entry-text"><?= nl2br(htmlspecialchars($entry['entry_text'])) ?></p>
                                
                                <?php if (!empty($entry['tags'])): ?>
                                    <div class="entry-tags">
                                        <?php 
                                        $tags = array_filter(array_map('trim', explode(',', $entry['tags'])));
                                        foreach ($tags as $tag): 
                                        ?>
                                            <span class="tag" onclick="filterByTag('<?= htmlspecialchars($tag) ?>')">
                                                🏷️ <?= htmlspecialchars($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="entry-footer">
                                <div class="entry-stats">
                                    <span class="word-count">💬 <?= str_word_count($entry['entry_text']) ?> words</span>
                                    <span class="char-count">📝 <?= strlen($entry['entry_text']) ?> chars</span>
                                </div>
                                <div class="entry-actions">
                                    <button class="action-btn edit-btn" onclick="quickEditEntry(<?= $entry['id'] ?>)" title="Edit entry">
                                        ✏️
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteEntry(<?= $entry['id'] ?>)" title="Delete entry">
                                        🗑️
                                    </button>
                                </div>
                            </div>

                            <!-- Quick Edit Form (Hidden by default) -->
                            <div class="quick-edit-form" id="edit-form-<?= $entry['id'] ?>" style="display: none;">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update" value="1">
                                    <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                    
                                    <div class="edit-row">
                                        <div class="edit-group">
                                            <label>Date</label>
                                            <input type="date" name="entry_date" required 
                                                value="<?= htmlspecialchars($entry['entry_date']) ?>">
                                        </div>

                                        <div class="edit-group">
                                            <label>Mood</label>
                                            <select name="mood" required>
                                                <option value="happy" <?= $entry['mood'] === 'happy' ? 'selected' : '' ?>>😊 Happy</option>
                                                <option value="excited" <?= $entry['mood'] === 'excited' ? 'selected' : '' ?>>🤩 Excited</option>
                                                <option value="neutral" <?= $entry['mood'] === 'neutral' ? 'selected' : '' ?>>😐 Neutral</option>
                                                <option value="sad" <?= $entry['mood'] === 'sad' ? 'selected' : '' ?>>😢 Sad</option>
                                                <option value="angry" <?= $entry['mood'] === 'angry' ? 'selected' : '' ?>>😠 Angry</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="edit-group">
                                        <label>Attachment</label>
                                        <?php if (!empty($entry['attachment_path']) && file_exists($entry['attachment_path'])): ?>
                                            <div class="current-attachment">
                                                <img src="<?= htmlspecialchars($entry['attachment_path']) ?>" 
                                                    alt="Attachment preview" 
                                                    style="max-width:120px; display:block; margin-bottom:8px;">
                                                <label>
                                                    <input type="checkbox" name="remove_attachment" value="1"> Remove image
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="new_attachment" accept="image/*">
                                        <div class="input-hint">Upload a new photo to replace (JPG, PNG, GIF, WebP)</div>
                                    </div>

                                    <div class="edit-group">
                                        <label>Your thoughts</label>
                                        <textarea name="entry_text" rows="4" required><?= htmlspecialchars($entry['entry_text']) ?></textarea>
                                    </div>
                                    
                                    <div class="edit-actions">
                                        <button type="submit" class="btn btn-small">💾 Save</button>
                                        <button type="button" class="btn btn-secondary btn-small" onclick="cancelQuickEdit(<?= $entry['id'] ?>)">❌ Cancel</button>
                                    </div>
                                </form>
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

    <!-- Image Modal -->
    <div id="imageModal" class="modal-overlay" style="display:none;">
        <div class="image-modal">
            <button class="modal-close" onclick="closeImageModal()">×</button>
            <img id="modalImage" src="" alt="Journal attachment" class="modal-image">
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function quickEditEntry(id) {
            const entryCard = document.querySelector(`[data-entry-id="${id}"]`);
            const editForm = document.getElementById(`edit-form-${id}`);
            const entryContent = entryCard.querySelector('.entry-content');
            
            // Toggle edit form visibility
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
                entryContent.style.display = 'none';
            } else {
                editForm.style.display = 'none';
                entryContent.style.display = 'block';
            }
        }
        
        function cancelQuickEdit(id) {
            const editForm = document.getElementById(`edit-form-${id}`);
            const entryCard = document.querySelector(`[data-entry-id="${id}"]`);
            const entryContent = entryCard.querySelector('.entry-content');
            
            editForm.style.display = 'none';
            entryContent.style.display = 'block';
        }
        
        function toggleEntry(id) {
            const entryCard = document.querySelector(`[data-entry-id="${id}"]`);
            const entryText = entryCard.querySelector('.entry-text');
            
            entryCard.classList.toggle('expanded');
            
            // Update button text
            const expandBtn = entryCard.querySelector('.expand-btn');
            if (entryCard.classList.contains('expanded')) {
                expandBtn.textContent = '📕';
                expandBtn.title = 'Collapse';
            } else {
                expandBtn.textContent = '📖';
                expandBtn.title = 'Expand';
            }
        }
        
        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this journal entry?')) {
                window.location.href = `?delete=${id}`;
            }
        }
        
        // Search and filter functions
        function performSearch() {
            const search = document.getElementById('searchInput').value;
            const mood = document.getElementById('moodFilter').value;
            const tag = document.getElementById('tagFilter').value;
            
            let url = 'module2.php?';
            const params = [];
            
            if (search.trim()) params.push(`search=${encodeURIComponent(search)}`);
            if (mood && mood !== 'all') params.push(`mood_filter=${encodeURIComponent(mood)}`);
            if (tag) params.push(`tag_filter=${encodeURIComponent(tag)}`);
            
            window.location.href = url + params.join('&');
        }
        
        function applyFilters() {
            performSearch();
        }
        
        function clearFilters() {
            window.location.href = 'module2.php';
        }
        
        function filterByTag(tag) {
            document.getElementById('tagFilter').value = tag;
            performSearch();
        }
        
        function openImageModal(imagePath) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imagePath;
            modal.style.display = 'flex';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // Allow Enter key to trigger search
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        });
        
        // // Mood filtering functionality
        // document.addEventListener('DOMContentLoaded', function() {
        //     const moodTabs = document.querySelectorAll('.mood-tab');
        //     const entryCards = document.querySelectorAll('.entry-card');
            
        //     moodTabs.forEach(tab => {
        //         tab.addEventListener('click', function() {
        //             // Update active tab
        //             moodTabs.forEach(t => t.classList.remove('active'));
        //             this.classList.add('active');
                    
        //             const selectedMood = this.getAttribute('data-mood');
                    
        //             // Filter entries
        //             entryCards.forEach(card => {
        //                 if (selectedMood === 'all') {
        //                     card.style.display = 'block';
        //                 } else {
        //                     const cardMood = card.querySelector('.mood-indicator').textContent.trim();
        //                     const moodMap = {
        //                         'happy': '😊',
        //                         'excited': '🤩', 
        //                         'neutral': '😐',
        //                         'sad': '😢',
        //                         'angry': '😠'
        //                     };
                            
        //                     if (cardMood === moodMap[selectedMood]) {
        //                         card.style.display = 'block';
        //                     } else {
        //                         card.style.display = 'none';
        //                     }
        //                 }
        //             });
        //         });
        //     });
        // });
    </script>
    <form method="POST" id="updateForm" style="display:none;">
        <input type="hidden" name="update" value="1">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="entry_date" value="">
        <input type="hidden" name="mood" value="">
        <input type="hidden" name="entry_text" value="">
    </form>

    <style>
        textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e1e5e9;
            border-radius: var(--border-radius-small);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: var(--white);
            resize: vertical;
            min-height: 120px;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(88, 204, 2, 0.1);
        }
        
        select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e1e5e9;
            border-radius: var(--border-radius-small);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        
        select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(88, 204, 2, 0.1);
        }
        
        /* Modern Journal Card Layout */
        .journal-entries {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .entry-card {
            background: var(--white);
            border-radius: var(--border-radius-small);
            box-shadow: var(--shadow);
            padding: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid var(--secondary-blue);
            position: relative;
        }
        
        .entry-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }
        
        .entry-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .entry-date {
            display: flex;
            flex-direction: column;
        }
        
        .date-text {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
        }
        
        .time-ago {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .mood-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            font-weight: bold;
        }
        
        .entry-content {
            margin-bottom: 15px;
        }
        
        .entry-text {
            line-height: 1.6;
            color: var(--text-dark);
            margin: 0;
            max-height: 4.8em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .entry-card.expanded .entry-text {
            max-height: none;
            -webkit-line-clamp: unset;
        }
        
        .entry-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e1e5e9;
        }
        
        .entry-stats {
            display: flex;
            gap: 15px;
        }
        
        .word-count, .char-count {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .entry-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            background: none;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .edit-btn:hover {
            background: var(--secondary-blue);
            transform: scale(1.1);
        }
        
        .expand-btn:hover {
            background: var(--primary-green);
            transform: scale(1.1);
        }
        
        .delete-btn:hover {
            background: var(--accent-orange);
            transform: scale(1.1);
        }
        
        /* Quick Edit Form Styles */
        .quick-edit-form {
            background: var(--background-light);
            border-radius: var(--border-radius-small);
            padding: 20px;
            margin-top: 15px;
            border: 2px solid #e1e5e9;
        }
        
        .edit-row {
            /* display: grid; */
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .edit-group {
            display: flex;
            flex-direction: column;
        }
        
        .edit-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .edit-group input,
        .edit-group select,
        .edit-group textarea {
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .edit-group input:focus,
        .edit-group select:focus,
        .edit-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(88, 204, 2, 0.1);
        }
        
        .edit-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        /* Mood Filter Tabs */
        .mood-filters {
            background: var(--background-light);
            border-radius: var(--border-radius-small);
            padding: 20px;
            margin-top: 20px;
        }
        
        .mood-filters h4 {
            margin: 0 0 15px 0;
            color: var(--text-dark);
            font-size: 1.1rem;
        }
        
        .mood-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .mood-tab {
            background: var(--white);
            border: 2px solid #e1e5e9;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-dark);
        }
        
        .mood-tab:hover {
            border-color: var(--primary-green);
            background: rgba(88, 204, 2, 0.1);
        }
        
        .mood-tab.active {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .entry-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .entry-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .entry-stats {
                order: 2;
            }
            
            .entry-actions {
                order: 1;
                align-self: flex-end;
            }
            
            .edit-row {
                grid-template-columns: 1fr;
            }
            
            .mood-tabs {
                flex-direction: column;
            }
            
            .mood-tab {
                text-align: center;
            }
        }
        
        /* Search and Filter Styles */
        .search-filter-section {
            background: var(--background-light);
            border-radius: var(--border-radius-small);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .search-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            align-items: end;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: var(--border-radius-small);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(88, 204, 2, 0.1);
            outline: none;
        }
        
        .search-btn {
            background: var(--secondary-blue);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--border-radius-small);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .search-btn:hover {
            background: var(--secondary-blue-hover);
            transform: translateY(-2px);
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .filter-group select {
            min-width: 120px;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: var(--border-radius-small);
            font-size: 0.9rem;
            background: white;
        }
        
        .clear-filters-btn {
            background: var(--accent-orange);
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: var(--border-radius-small);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .clear-filters-btn:hover {
            background: var(--accent-orange-hover);
            transform: translateY(-2px);
        }
        
        /* Entry Tags */
        .entry-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        
        .tag {
            background: var(--accent-purple);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .tag:hover {
            background: var(--accent-purple-hover);
            transform: scale(1.05);
        }
        
        /* Image Attachment */
        .entry-attachment {
            margin-bottom: 15px;
        }
        
        .attachment-image {
            max-width: 100%;
            height: auto;
            max-height: 200px;
            border-radius: var(--border-radius-small);
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
        }
        
        .attachment-image:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-hover);
        }
        
        /* Image Modal */
        .image-modal {
            background: var(--white);
            border-radius: var(--border-radius);
            max-width: 90vw;
            max-height: 90vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: var(--border-radius-small);
        }
        
        /* File input styling */
        input[type="file"] {
            padding: 12px;
            border: 2px dashed #e1e5e9;
            border-radius: var(--border-radius-small);
            background: var(--background-light);
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        input[type="file"]:hover {
            border-color: var(--primary-green);
            background: rgba(88, 204, 2, 0.05);
        }
        
        @media (max-width: 768px) {
            .search-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .search-box {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group select,
            .clear-filters-btn {
                width: 100%;
            }
        }
    </style>
</body>
</html>
<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Budget Setting
if (isset($_POST['set_budget'])) {
    $monthly_budget = (float) $_POST['monthly_budget'];
    $budget_month = $_POST['budget_month'];

    if ($monthly_budget > 0 && !empty($budget_month)) {
        // Create budget table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_budgets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            monthly_budget DECIMAL(10,2) NOT NULL DEFAULT 0,
            budget_month VARCHAR(7) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
            UNIQUE KEY unique_user_month (user_id, budget_month)
        ) ENGINE=InnoDB");
        
        $stmt = $pdo->prepare("INSERT INTO user_budgets (user_id, monthly_budget, budget_month) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE monthly_budget = VALUES(monthly_budget), updated_at = CURRENT_TIMESTAMP");
        if ($stmt->execute([$user_id, $monthly_budget, $budget_month])) {
            header("Location: module3.php?success=budget_set");
            exit;
        }
    }
}

// Get current month budget
$current_month = date('Y-m');
try {
    $budget_stmt = $pdo->prepare("SELECT monthly_budget FROM user_budgets WHERE user_id = ? AND budget_month = ?");
    $budget_stmt->execute([$user_id, $current_month]);
    $current_budget = $budget_stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $current_budget = 0;
}

// Handle Add Transaction
if (isset($_POST['add'])) {
    $type = $_POST['type'];
    $category = trim($_POST['category']);
    $amount = (float) $_POST['amount'];
    $transaction_date = $_POST['transaction_date'];

    if (!empty($type) && !empty($category) && $amount > 0 && !empty($transaction_date)) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, category, amount, transaction_date) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $type, $category, $amount, $transaction_date])) {
            header("Location: module3.php?success=added");
            exit;
        }
    } else {
        $message = "Please fill all fields correctly.";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: module3.php?success=deleted");
    exit;
}

// Handle Edit (Update)
if (isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $type = $_POST['type'];
    $category = trim($_POST['category']);
    $amount = (float) $_POST['amount'];
    $transaction_date = $_POST['transaction_date'];

    $stmt = $pdo->prepare("UPDATE transactions SET type=?, category=?, amount=?, transaction_date=? WHERE id=? AND user_id=?");
    if ($stmt->execute([$type, $category, $amount, $transaction_date, $id, $user_id])) {
        header("Location: module3.php?success=updated");
        exit;
    }
}

// Handle success messages from redirects
$message = "";
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = "Transaction added successfully!";
            break;
        case 'deleted':
            $message = "Transaction deleted.";
            break;
        case 'updated':
            $message = "Transaction updated successfully!";
            break;
        case 'budget_set':
            $message = "Monthly budget set successfully!";
            break;
    }
}

$month_filter = $_GET['month_filter'] ?? 'all';
$date_filter  = $_GET['date_filter'] ?? null;

$where_conditions = ["user_id = ?"];
$params = [$user_id];

if ($month_filter !== 'all') {
    // Expect format YYYY-MM from <input type="month">
    $start_date = $month_filter . '-01';
    $end_date   = date('Y-m-t', strtotime($start_date));
    $where_conditions[] = "transaction_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;

} elseif ($date_filter) {
    // Keep your old "this week / last month / custom" fallback
    if ($date_filter === 'current_month') {
        $where_conditions[] = "YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())";
    } elseif ($date_filter === 'last_month') {
        $where_conditions[] = "YEAR(transaction_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                               AND MONTH(transaction_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    } elseif ($date_filter === 'current_week') {
        $where_conditions[] = "YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($date_filter === 'custom' && !empty($_GET['custom_start']) && !empty($_GET['custom_end'])) {
        $where_conditions[] = "transaction_date BETWEEN ? AND ?";
        $params[] = $_GET['custom_start'];
        $params[] = $_GET['custom_end'];
    }
} elseif ($date_filter) {
    // Legacy quick filters
    if ($date_filter === 'current_month') {
        $where_conditions[] = "YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())";
    } elseif ($date_filter === 'last_month') {
        $where_conditions[] = "YEAR(transaction_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                               AND MONTH(transaction_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    } elseif ($date_filter === 'current_week') {
        $where_conditions[] = "YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($date_filter === 'custom' && !empty($_GET['custom_start']) && !empty($_GET['custom_end'])) {
        $where_conditions[] = "transaction_date BETWEEN ? AND ?";
        $params[] = $_GET['custom_start'];
        $params[] = $_GET['custom_end'];
    }
}

// Now build final query
$where_clause = implode(' AND ', $where_conditions);
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE {$where_clause} ORDER BY transaction_date DESC");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all transactions for statistics (unfiltered)
$all_stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC");
$all_stmt->execute([$user_id]);
$all_transactions = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

// Enhanced Statistics Calculations
$income = 0;
$expenses = 0;
$transaction_count = 0;
$recent_transactions = 0;
$week_ago = strtotime('-7 days');
$category_breakdown = [];
$monthly_summary = [];

// Calculate filtered statistics
foreach ($transactions as $transaction) {
    $amount = (float) $transaction['amount'];
    
    if ($transaction['type'] === 'income') {
        $income += $amount;
    } else {
        $expenses += $amount;
        // Build category breakdown for expenses
        $category = $transaction['category'];
        if (!isset($category_breakdown[$category])) {
            $category_breakdown[$category] = 0;
        }
        $category_breakdown[$category] += $amount;
    }
    $transaction_count++;
    
    if (strtotime($transaction['transaction_date']) >= $week_ago) {
        $recent_transactions++;
    }
}

// Monthly summary from all transactions
foreach ($all_transactions as $transaction) {
    $month = date('Y-m', strtotime($transaction['transaction_date']));
    $amount = (float) $transaction['amount'];
    
    if (!isset($monthly_summary[$month])) {
        $monthly_summary[$month] = ['income' => 0, 'expenses' => 0, 'balance' => 0];
    }
    
    if ($transaction['type'] === 'income') {
        $monthly_summary[$month]['income'] += $amount;
    } else {
        $monthly_summary[$month]['expenses'] += $amount;
    }
    $monthly_summary[$month]['balance'] = $monthly_summary[$month]['income'] - $monthly_summary[$month]['expenses'];
}

// Sort monthly summary by date (latest first)
krsort($monthly_summary);

// Build list of available months from monthly_summary
$available_months = array_keys($monthly_summary); // e.g. ["2025-08", "2025-07", ...]


// Calculate overall statistics
$balance = $income - $expenses;
$savings_rate = $income > 0 ? round(($balance / $income) * 100, 1) : 0;

// Current month statistics for budget comparison
$current_month_expenses = 0;
foreach ($all_transactions as $transaction) {
    if ($transaction['type'] === 'expense' && 
        date('Y-m', strtotime($transaction['transaction_date'])) === $current_month) {
        $current_month_expenses += (float) $transaction['amount'];
    }
}

$budget_remaining = $current_budget - $current_month_expenses;
$budget_percentage = $current_budget > 0 ? round(($current_month_expenses / $current_budget) * 100, 1) : 0;
$is_over_budget = $current_month_expenses > $current_budget && $current_budget > 0;

// Sort category breakdown by amount (highest first)
arsort($category_breakdown);
?>

<?php
// Build list of available years and months from monthly_summary
$available_years = [];
$available_months_by_year = [];

foreach (array_keys($monthly_summary) as $month) {
    [$y, $m] = explode('-', $month); // e.g., "2025-08" → ["2025", "08"]
    $available_years[$y] = true;
    $available_months_by_year[$y][] = $m;
}
$available_years = array_keys($available_years);
sort($available_years); // Sort ascending
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Tracker - Student Routine Organizer</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container dashboard-container">
        <a href="dashboard.php" class="back-button" aria-label="Back to dashboard">←</a>
        <div class="logo">
            <h1>💰</h1>
            <div class="subtitle">Track your financial journey</div>
        </div>
        
        <div class="top-bar">
            <h2>Money Tracker</h2>
            <button type="button" class="btn btn-small btn-purple" onclick="openAddModal()">Add Transaction</button>
        </div>

        <!-- Enhanced Balance Overview -->
        <div class="balance-overview">
            <div class="balance-main-card">
                <div class="balance-header">
                    <h3>💰 Financial Overview</h3>
                    <div class="period-indicator">
                        <form method="GET" style="display:flex; gap:10px; align-items:center;">
                            
                            <select id="monthFilter" name="month_filter" required>
                                <option value="all" <?= ($month_filter === 'all') ? 'selected' : '' ?>>📅 All</option>
                                <?php foreach ($available_months as $month): ?>
                                    <option value="<?= $month ?>" <?= ($month_filter === $month) ? 'selected' : '' ?>>
                                        📅 <?= date('F Y', strtotime($month . '-01')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-small">Apply</button>
                        </form>
                    </div>  
                </div>
                <div class="balance-summary">
                    <div class="balance-item balance-total">
                        <div class="balance-label">Net Balance</div>
                        <div class="balance-value <?= $balance >= 0 ? 'positive' : 'negative' ?>">
                            RM<?= number_format($balance, 2) ?>
                        </div>
                    </div>
                    <div class="balance-item">
                        <div class="balance-label">💰 Income</div>
                        <div class="balance-value positive">RM<?= number_format($income, 2) ?></div>
                    </div>
                    <div class="balance-item">
                        <div class="balance-label">💸 Expenses</div>
                        <div class="balance-value negative">RM<?= number_format($expenses, 2) ?></div>
                    </div>
                    <div class="balance-item">
                        <div class="balance-label">📊 Savings Rate</div>
                        <div class="balance-value"><?= $savings_rate ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Overview -->
        <?php if ($current_budget > 0): ?>
            <div class="budget-overview <?= $is_over_budget ? 'over-budget' : '' ?>">
                <div class="budget-header">
                    <h4>🎯 Monthly Budget (<?= date('M Y') ?>)</h4>
                    <button class="btn btn-small btn-secondary" onclick="openBudgetModal()">Edit Budget</button>
                </div>
                <div class="budget-progress">
                    <div class="budget-bar">
                        <div class="budget-fill" style="width: <?= min($budget_percentage, 100) ?>%"></div>
                    </div>
                    <div class="budget-stats">
                        <span>Spent: RM<?= number_format($current_month_expenses, 2) ?> / RM<?= number_format($current_budget, 2) ?></span>
                        <span class="<?= $budget_remaining >= 0 ? 'positive' : 'negative' ?>">
                            <?= $budget_remaining >= 0 ? 'Remaining' : 'Over' ?>: $<?= number_format(abs($budget_remaining), 2) ?>
                        </span>
                    </div>
                </div>
                <?php if ($is_over_budget): ?>
                    <div class="budget-warning">⚠️ You're over budget by $<?= number_format($current_month_expenses - $current_budget, 2) ?>!</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="budget-setup">
                <div class="budget-setup-content">
                    <h4>🎯 Set Monthly Budget</h4>
                    <p>Track your spending by setting a monthly budget limit</p>
                    <button class="btn btn-purple" onclick="openBudgetModal()">Set Budget</button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><span><?= htmlspecialchars($message) ?></span><button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button></div>
        <?php endif; ?>

        <!-- Add Transaction Modal -->
        <div id="addModal" class="modal-overlay" style="display:none;">
            <div class="modal">
                <button class="modal-close" aria-label="Close" onclick="closeAddModal()">×</button>
                <h3>Add New Transaction</h3>
                <form method="POST" class="exercise-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type">Transaction Type</label>
                            <select id="type" name="type" required>
                                <option value="" disabled selected>Select type</option>
                                <option value="income">💰 Income</option>
                                <option value="expense">💸 Expense</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <option value="" disabled selected>Select a category</option>
                                <option value="Food & Drinks">Food & Drinks</option>
                                <option value="Transport">Transport</option>
                                <option value="Bills">Bills</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Shops">Shops</option>
                                <option value="Others">Others</option>
                            </select>
                            <div class="input-hint">Example: Food & Drinks, Transport, Bills, Entertainment, Shops, Others</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount">Amount ($)</label>
                            <input type="number" id="amount" name="amount" required min="0.01" step="0.01" placeholder="0.00">
                            <!-- <div class="input-hint">Enter amount without RM sign</div> -->
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_date">Date</label>
                            <input type="date" id="transaction_date" name="transaction_date" required value="<?= date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="add" class="btn">Add Transaction</button>
                </form>
            </div>
        </div>

        <!-- Budget Modal -->
        <div id="budgetModal" class="modal-overlay" style="display:none;">
            <div class="modal">
                <button class="modal-close" aria-label="Close" onclick="closeBudgetModal()">×</button>
                <h3>🎯 Set Monthly Budget</h3>
                <form method="POST" class="exercise-form">
                    <div class="form-group">
                        <label for="budget_month">Month</label>
                        <input type="month" id="budget_month" name="budget_month" required value="<?= $current_month ?>">
                        <div class="input-hint">Select the month for your budget</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="monthly_budget">Budget Amount (RM)</label>
                        <input type="number" id="monthly_budget" name="monthly_budget" required min="0.01" step="0.01" placeholder="1500.00" value="<?= $current_budget > 0 ? $current_budget : '' ?>">
                        <div class="input-hint">Set your spending limit for this month</div>
                    </div>
                    
                    <button type="submit" name="set_budget" class="btn">Set Budget</button>
                </form>
            </div>
        </div>

        <?php if (!empty($category_breakdown)): ?>
            <div class="spending-insights" style="max-width:300px; margin:auto;">
                <h3>📊 Spending Insights</h3>
                <canvas id="categoryPieChart" width="300" height="300"></canvas>
            </div>
        <?php endif; ?>

        <div class="exercises-section">
            <h3>Transaction History</h3>
            <?php if (empty($transactions)): ?>
                <p class="no-data">No transactions recorded yet. Start by adding your first income or expense!</p>
            <?php else: ?>
                <div class="transaction-cards">
                    <?php 
                    $category_icons = [
                        'food' => '🍔',
                        'salary' => '💼',
                        'transport' => '🚗',
                        'bills' => '📋',
                        'entertainment' => '🎮',
                        'shopping' => '🛒',
                        'healthcare' => '🏥',
                        'education' => '📚',
                        'allowance' => '💰',
                        'job' => '💼'
                    ];
                    
                    function getCategoryIcon($category) {
                        global $category_icons;
                        $cat_lower = strtolower($category);
                        foreach ($category_icons as $key => $icon) {
                            if (strpos($cat_lower, $key) !== false) {
                                return $icon;
                            }
                        }
                        return '💳';
                    }
                    ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <div class="transaction-card <?= $transaction['type'] ?>" data-transaction-id="<?= $transaction['id'] ?>">
                            <div class="transaction-header">
                                <div class="transaction-info">
                                    <div class="category-badge">
                                        <span class="category-icon"><?= getCategoryIcon($transaction['category']) ?></span>
                                        <span class="category-text"><?= htmlspecialchars($transaction['category']) ?></span>
                                    </div>
                                    <div class="transaction-date">
                                        <span class="date-text"><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></span>
                                        <span class="day-text"><?= date('D', strtotime($transaction['transaction_date'])) ?></span>
                                    </div>
                                </div>
                                <div class="amount-display <?= $transaction['type'] ?>">
                                    <div class="amount-value">
                                        <?= $transaction['type'] === 'income' ? '+' : '-' ?>RM<?= number_format($transaction['amount'], 2) ?>
                                    </div>
                                    <div class="transaction-type-label">
                                        <?= $transaction['type'] === 'income' ? '💰 Income' : '💸 Expense' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="transaction-footer">
                                <div class="transaction-meta">
                                    <?php if ($transaction['type'] === 'expense'): ?>
                                        <span class="expense-impact">📉 Balance Impact</span>
                                    <?php else: ?>
                                        <span class="income-boost">📈 Balance Boost</span>
                                    <?php endif; ?>
                                </div>
                                <div class="transaction-actions">
                                    <button class="action-btn edit-btn" onclick="quickEditTransaction(<?= $transaction['id'] ?>)" title="Edit transaction">
                                        ✏️
                                    </button>
                                    <button class="action-btn detail-btn" onclick="toggleTransactionDetail(<?= $transaction['id'] ?>)" title="View details">
                                        📊
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteTransaction(<?= $transaction['id'] ?>)" title="Delete transaction">
                                        🗑️
                                    </button>
                                </div>
                            </div>

                            <!-- Quick Edit Form (Hidden by default) -->
                            <div class="quick-edit-form" id="edit-form-<?= $transaction['id'] ?>" style="display: none;">
                                <form method="POST">
                                    <input type="hidden" name="update" value="1">
                                    <input type="hidden" name="id" value="<?= $transaction['id'] ?>">
                                    
                                    <div class="edit-row">
                                        <div class="edit-group">
                                            <label>Date</label>
                                            <input type="date" name="transaction_date" value="<?= $transaction['transaction_date'] ?>" required>
                                        </div>
                                        <div class="edit-group">
                                            <label>Type</label>
                                            <select name="type" required>
                                                <option value="income" <?= $transaction['type'] === 'income' ? 'selected' : '' ?>>💰 Income</option>
                                                <option value="expense" <?= $transaction['type'] === 'expense' ? 'selected' : '' ?>>💸 Expense</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="edit-group">
                                        <label>Category</label>
                                        <input type="text" name="category" value="<?= htmlspecialchars($transaction['category']) ?>" required placeholder="e.g., Food, Salary">
                                    </div>
                                    
                                    <div class="edit-group">
                                        <label>Amount ($)</label>
                                        <input type="number" name="amount" value="<?= $transaction['amount'] ?>" min="0.01" step="0.01" required>
                                    </div>
                                    
                                    <div class="edit-actions">
                                        <button type="submit" class="btn btn-small">💾 Save</button>
                                        <button type="button" class="btn btn-secondary btn-small" onclick="cancelQuickEditTransaction(<?= $transaction['id'] ?>)">❌ Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Transaction Detail View (Hidden by default) -->
                            <div class="transaction-detail" id="detail-<?= $transaction['id'] ?>" style="display: none;">
                                <div class="detail-stats">
                                    <div class="detail-item">
                                        <span class="detail-label">💰 Amount:</span>
                                        <span class="detail-value">$<?= number_format($transaction['amount'], 2) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">📊 Type:</span>
                                        <span class="detail-value <?= $transaction['type'] ?>"><?= ucfirst($transaction['type']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">🏷️ Category:</span>
                                        <span class="detail-value"><?= htmlspecialchars($transaction['category']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">📅 Date:</span>
                                        <span class="detail-value"><?= date('l, F j, Y', strtotime($transaction['transaction_date'])) ?></span>
                                    </div>
                                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($category_breakdown)): ?>
            const ctx = document.getElementById('categoryPieChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_keys($category_breakdown)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($category_breakdown)) ?>,
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#C9CBCF', '#2ecc71',
                            '#e74c3c', '#9b59b6'
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a,b)=>a+b,0);
                                    const value = context.parsed;
                                    const percentage = ((value/total) * 100).toFixed(1);
                                    return context.label + ': RM' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    });
    </script>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openBudgetModal() {
            document.getElementById('budgetModal').style.display = 'flex';
        }
        function closeBudgetModal() {
            document.getElementById('budgetModal').style.display = 'none';
        }
        
        function applyDateFilter() {
            const dateFilter = document.getElementById('dateFilter').value;
            const customStart = document.getElementById('customStart')?.value || '';
            const customEnd = document.getElementById('customEnd')?.value || '';
            
            let url = 'module3.php?date_filter=' + encodeURIComponent(dateFilter);
            
            if (dateFilter === 'custom' && customStart && customEnd) {
                url += '&custom_start=' + encodeURIComponent(customStart);
                url += '&custom_end=' + encodeURIComponent(customEnd);
            }
            
            window.location.href = url;
        }
        
        // Show/hide custom date range
        document.addEventListener('DOMContentLoaded', function() {
            const dateFilter = document.getElementById('dateFilter');
            const customRange = document.getElementById('customDateRange');
            
            if (dateFilter && customRange) {
                dateFilter.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customRange.style.display = 'flex';
                    } else {
                        customRange.style.display = 'none';
                        if (this.value !== 'custom') {
                            applyDateFilter();
                        }
                    }
                });
            }
        });
        function quickEditTransaction(id) {
            const transactionCard = document.querySelector(`[data-transaction-id="${id}"]`);
            const editForm = document.getElementById(`edit-form-${id}`);
            const transactionHeader = transactionCard.querySelector('.transaction-header');
            
            // Toggle edit form visibility
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
                transactionHeader.style.opacity = '0.5';
            } else {
                editForm.style.display = 'none';
                transactionHeader.style.opacity = '1';
            }
        }
        
        function cancelQuickEditTransaction(id) {
            const editForm = document.getElementById(`edit-form-${id}`);
            const transactionCard = document.querySelector(`[data-transaction-id="${id}"]`);
            const transactionHeader = transactionCard.querySelector('.transaction-header');
            
            editForm.style.display = 'none';
            transactionHeader.style.opacity = '1';
        }
        
        function toggleTransactionDetail(id) {
            const transactionCard = document.querySelector(`[data-transaction-id="${id}"]`);
            const detailView = document.getElementById(`detail-${id}`);
            
            transactionCard.classList.toggle('expanded');
            
            if (transactionCard.classList.contains('expanded')) {
                detailView.style.display = 'block';
            } else {
                detailView.style.display = 'none';
            }
            
            // Update button icon
            const detailBtn = transactionCard.querySelector('.detail-btn');
            if (transactionCard.classList.contains('expanded')) {
                detailBtn.textContent = '📊';
                detailBtn.title = 'Hide details';
            } else {
                detailBtn.textContent = '📊';
                detailBtn.title = 'View details';
            }
        }
        
        function deleteTransaction(id) {
            if (confirm('Are you sure you want to delete this transaction?')) {
                window.location.href = `?delete=${id}`;
            }
        }
        
        // Category filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const categoryTabs = document.querySelectorAll('.category-tab');
            const transactionCards = document.querySelectorAll('.transaction-card');
            
            categoryTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    categoryTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const selectedCategory = this.getAttribute('data-category');
                    
                    // Filter transactions
                    transactionCards.forEach(card => {
                        if (selectedCategory === 'all') {
                            card.style.display = 'block';
                        } else {
                            const cardCategory = card.querySelector('.category-text').textContent.trim();
                            
                            if (cardCategory.toLowerCase() === selectedCategory.toLowerCase()) {
                                card.style.display = 'block';
                            } else {
                                card.style.display = 'none';
                            }
                        }
                    });
                });
            });
        });
    </script>
    <form method="POST" id="updateForm" style="display:none;">
        <input type="hidden" name="update" value="1">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="type" value="">
        <input type="hidden" name="category" value="">
        <input type="hidden" name="amount" value="">
        <input type="hidden" name="transaction_date" value="">
    </form>

    <style>
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
        
        /* Modern Transaction Card Layout */
        .transaction-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .transaction-card {
            background: var(--white);
            border-radius: var(--border-radius-small);
            box-shadow: var(--shadow);
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .transaction-card.income {
            border-left: 4px solid var(--primary-green);
        }
        
        .transaction-card.expense {
            border-left: 4px solid var(--accent-orange);
        }
        
        .transaction-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }
        
        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            transition: opacity 0.3s ease;
        }
        
        .transaction-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .category-badge {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-icon {
            font-size: 1.2rem;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--background-light);
            border-radius: 8px;
        }
        
        .category-text {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
        }
        
        .transaction-date {
            display: flex;
            flex-direction: column;
        }
        
        .date-text {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        .day-text {
            font-size: 0.75rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .amount-display {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .amount-value {
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1.2;
        }
        
        .amount-value.income {
            color: var(--primary-green);
        }
        
        .amount-value.expense {
            color: var(--accent-orange);
        }
        
        .transaction-type-label {
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        
        .transaction-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e1e5e9;
        }
        
        .transaction-meta {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .expense-impact {
            color: var(--accent-orange);
        }
        
        .income-boost {
            color: var(--primary-green);
        }
        
        .transaction-actions {
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
        
        .detail-btn:hover {
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
            display: grid;
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
        .edit-group select {
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .edit-group input:focus,
        .edit-group select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(88, 204, 2, 0.1);
        }
        
        .edit-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* Transaction Detail View */
        .transaction-detail {
            background: var(--background-light);
            border-radius: var(--border-radius-small);
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #e1e5e9;
        }
        
        .detail-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .detail-value.income {
            color: var(--primary-green);
        }
        
        .detail-value.expense {
            color: var(--accent-orange);
        }
        
        /* Category Filter Tabs */
        .category-filters {
            background: var(--background-light);
            border-radius: var(--border-radius-small);
            padding: 20px;
            margin-top: 20px;
        }
        
        .category-filters h4 {
            margin: 0 0 15px 0;
            color: var(--text-dark);
            font-size: 1.1rem;
        }
        
        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .category-tab {
            background: var(--white);
            border: 2px solid #e1e5e9;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .category-tab:hover {
            border-color: var(--primary-green);
            background: rgba(88, 204, 2, 0.1);
        }
        
        .category-tab.active {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .transaction-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .amount-display {
                align-self: flex-end;
                text-align: right;
            }
            
            .transaction-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .transaction-meta {
                order: 2;
            }
            
            .transaction-actions {
                order: 1;
                align-self: flex-end;
            }
            
            .edit-row {
                grid-template-columns: 1fr;
            }
            
            .detail-stats {
                grid-template-columns: 1fr;
            }
            
            .category-tabs {
                flex-direction: column;
            }
            
            .category-tab {
                text-align: center;
                justify-content: center;
            }
        }
        
        /* Enhanced Balance Overview */
        .balance-overview {
            margin-bottom: 25px;
        }
        
        .balance-main-card {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, #026bcc 100%);
            color: white;
            border-radius: var(--border-radius-small);
            padding: 25px;
            box-shadow: var(--shadow);
        }
        
        .balance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .balance-header h3 {
            margin: 0;
            font-size: 1.4rem;
            color: white;
        }
        
        .period-indicator {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .balance-summary {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .balance-item {
            text-align: center;
        }
        
        .balance-total {
            text-align: left;
            border-right: 1px solid rgba(255, 255, 255, 0.3);
            padding-right: 20px;
        }
        
        .balance-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .balance-value {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.2;
        }
        
        .balance-total .balance-value {
            font-size: 2rem;
        }
        
        .positive {
            color: #4ade80;
        }
        
        .negative {
            color: #f87171;
        }
        
        /* Budget Overview */
        .budget-overview {
            background: var(--white);
            border-radius: var(--border-radius-small);
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--accent-purple);
        }
        
        .budget-overview.over-budget {
            border-left-color: var(--accent-orange);
            background: #fff5f5;
        }
        
        .budget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .budget-header h4 {
            margin: 0;
            color: var(--text-dark);
        }
        
        .budget-progress {
            margin-bottom: 10px;
        }
        
        .budget-bar {
            width: 100%;
            height: 12px;
            background: #e1e5e9;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .budget-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-green), var(--accent-purple));
            transition: width 0.3s ease;
        }
        
        .budget-overview.over-budget .budget-fill {
            background: var(--accent-orange);
        }
        
        .budget-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .budget-warning {
            background: rgba(255, 150, 0, 0.1);
            color: var(--accent-orange);
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            margin-top: 10px;
        }
        
        .budget-setup {
            background: linear-gradient(135deg, var(--accent-purple) 0%, var(--secondary-blue) 100%);
            color: white;
            border-radius: var(--border-radius-small);
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .budget-setup h4 {
            margin: 0 0 10px 0;
            color: white;
        }
        
        .budget-setup p {
            margin: 0 0 20px 0;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .balance-summary {
                grid-template-columns: 1fr;
                gap: 15px;
                text-align: center;
            }
            
            .balance-total {
                text-align: center;
                border-right: none;
                padding-right: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.3);
                padding-bottom: 15px;
            }
            
            .budget-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .budget-stats {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</body>
</html>
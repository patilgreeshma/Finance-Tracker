<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'dbconfig.php';
requireAuth();

// Get user_id safely
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}
// --- USER PROFILE FETCH ---
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_profile = $user_result->fetch_assoc();
$username = $user_profile['username'] ?? 'User';
$profile_image = $user_profile['profile_pic'] ?? 'default_profile.png'; // fallback image
$stmt->close();
// Modified income query
$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM incomes 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_income = $result->fetch_assoc()['total'] ?? 0;

// Similarly modify all other queries to include:
// WHERE user_id = $user_id
$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM expenses 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();   
$result = $stmt->get_result();
$total_expenses = $result->fetch_assoc()['total'] ?? 0;
$stmt = $conn->prepare("
    SELECT SUM(current_amount) as total 
    FROM savings_goals 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_savings = $result->fetch_assoc()['total'] ?? 0;
$stmt = $conn->prepare("
    SELECT SUM(current_value) as total 
    FROM investments 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$total_investments = $result->fetch_assoc()['total'] ?? 0;
$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM debts 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_debts = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

$net_worth = ($total_savings + $total_investments) - $total_debts;



$monthly_data = [];
$res = $conn->query("
    SELECT 
        MONTH(date) as month, 
        YEAR(date) as year,
        'income' as type,
        SUM(amount) as total 
    FROM incomes 
    WHERE user_id = $user_id
    GROUP BY YEAR(date), MONTH(date)
    UNION ALL
    SELECT 
        MONTH(date) as month, 
        YEAR(date) as year,
        'expense' as type,
        SUM(amount) as total 
    FROM expenses 
    WHERE user_id = $user_id
    GROUP BY YEAR(date), MONTH(date)
    ORDER BY year, month
    ");



if ($res) {
    while ($row = $res->fetch_assoc()) {
        $month_name = date('M', mktime(0, 0, 0, $row['month'], 1));
        $year = $row['year'];
        $key = "$month_name";
        
        if (!isset($monthly_data[$key])) {
            $monthly_data[$key] = ['income' => 0, 'expense' => 0];
        }
        
        $monthly_data[$key][$row['type']] = floatval($row['total']);
    }
}

// Expense categories for pie chart

$expense_categories = [];
$res = $conn->query("
    SELECT category, SUM(amount) as total 
    FROM expenses 
    WHERE user_id = $user_id
    GROUP BY category 
    ORDER BY total DESC
    LIMIT 10
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $expense_categories[$row['category']] = floatval($row['total']);
    }
}

// Recent transactions
$recent_transactions = [];
$res = $conn->query("
    (SELECT date, source as category, amount, 'Income' as type, note FROM incomes WHERE user_id = $user_id ORDER BY date DESC LIMIT 5)
    UNION ALL
    (SELECT date, category, amount, 'Expense' as type, note FROM expenses WHERE user_id = $user_id ORDER BY date DESC LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - FinanceTracker</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">FinanceTracker</a>
        <nav class="nav">
            <a href="index.php" class="active">Dashboard</a>
            <a href="income.php">Income</a>
            <a href="expenses.php">Expenses</a>
            <a href="savings.php">Savings</a>
            <a href="investments.php">Investments</a>
            <a href="debt.php">Debts</a>
            <a href="reports.php">Reports</a>
            <a href="news.html">News</a>
            <?php if(isset($_SESSION['user_id'])): ?>
    <!-- <a href="logout.php" class="logout-btn">Logout</a> -->
<?php endif; ?>

        </nav>
       
        
        <div class="profile-dropdown" style="position: relative;">
    <div class="profile-trigger" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
        <img src="uploads/<?= htmlspecialchars($profile_image) ?>"
             alt="Profile"
             class="profile-pic"
             style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid #eee;"
             onerror="this.onerror=null;this.src='default_profile.png';">
        <span class="username"><?= htmlspecialchars($username) ?></span>
        <i class="fas fa-caret-down"></i>
    </div>
    <div class="dropdown-menu" style="display:none; position:absolute; right:0; top:110%; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.08); min-width:180px; z-index:1000;">
        <a href="profile.php" class="dropdown-item" style="display:block; padding:10px 16px; color:#222; text-decoration:none;">My Profile</a>
        <a href="logout.php" class="dropdown-item" style="display:block; padding:10px 16px; color:#222; text-decoration:none;">Logout</a>
    </div>
</div>


        
        <!-- <button class="theme-toggle" id="themeToggle">🌙</button> -->
    </header>

    <div class="container">
        <h1 class="page-title">Financial Dashboard</h1>
        <p class="subtitle">Welcome to your personal finance dashboard. Here's an overview of your financial data.</p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #10b981;">↑</span> Income
                </div>
                <div class="stat-value positive">₹<?= number_format($total_income) ?></div>
                <div class="stat-description">Total income received</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #ef4444;">↓</span> Expenses
                </div>
                <div class="stat-value negative">₹<?= number_format($total_expenses) ?></div>
                <div class="stat-description">Total expenses</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #3b82f6;">↗</span> Savings
                </div>
                <div class="stat-value">₹<?= number_format($total_savings) ?></div>
                <div class="stat-description">Total savings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #8b5cf6;">📊</span> Investments
                </div>
                <div class="stat-value">₹<?= number_format($total_investments) ?></div>
                <div class="stat-description">Total investments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: #f59e0b;">💰</span> Debts
                </div>
                <div class="stat-value">₹<?= number_format($total_debts) ?></div>
                <div class="stat-description">Total debts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">
                    <span style="color: <?= $net_worth >= 0 ? '#10b981' : '#ef4444' ?>;">💵</span> Net Worth
                </div>
                <div class="stat-value <?= $net_worth >= 0 ? 'positive' : 'negative' ?>">₹<?= number_format($net_worth) ?></div>
                <div class="stat-description">Total assets minus liabilities</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">Income vs Expenses</h2>
                <p class="subtitle">Monthly comparison of income and expenses</p>
                <canvas id="incomeExpensesChart" height="300"></canvas>
            </div>
            
            <div class="card">
                <h2 class="card-title">Expense Breakdown</h2>
                <p class="subtitle">Distribution by category</p>
                <canvas id="expenseBreakdownChart" height="300"></canvas>
            </div>
        </div>
        
        <div class="card" style="margin-top: 30px;">
            <h2 class="card-title">Recent Transactions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $transaction): ?>
                    <tr>
                        <td><?= date('n/j/Y', strtotime($transaction['date'])) ?></td>
                        <td><?= htmlspecialchars($transaction['category']) ?></td>
                        <td class="<?= $transaction['type'] == 'Income' ? 'positive' : 'negative' ?>">
                            <?= $transaction['type'] == 'Income' ? '+' : '-' ?>$<?= number_format($transaction['amount']) ?>
                        </td>
                        <td><?= $transaction['type'] ?></td>
                        <td><?= htmlspecialchars($transaction['note']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div>
                <h3 class="footer-title">FinanceTracker</h3>
                <p class="footer-text">Track your personal finances, manage expenses, and stay on top of your financial goals.</p>
            </div>
            
            <div>
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="income.php">Income</a></li>
                    <li><a href="expenses.php">Expenses</a></li>
                    <li><a href="savings.php">Savings</a></li>
                    <li><a href="#">Financial News</a></li>
                </ul>
            </div>
            
            <div>
                <h3 class="footer-title">Contact</h3>
                <p class="footer-text">Email: support@financetracker.com</p>
                <p class="footer-text">Phone: +1 (123) 456-7890</p>
                <p class="footer-text">Address: 123 Finance St, Money City</p>
            </div>
        </div>
        
        <div class="copyright">
            © 2025 FinanceTracker. All rights reserved.
        </div>
    </footer>

    <script>
    // Income vs Expenses Chart
    const incomeExpensesCtx = document.getElementById('incomeExpensesChart').getContext('2d');
    const monthlyLabels = <?= json_encode(array_keys($monthly_data)) ?>;
    const incomeData = <?= json_encode(array_map(function($item) { return $item['income']; }, $monthly_data)) ?>;
    const expenseData = <?= json_encode(array_map(function($item) { return $item['expense']; }, $monthly_data)) ?>;
    
    new Chart(incomeExpensesCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Expenses',
                    data: expenseData,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Expense Breakdown Chart
    const expenseBreakdownCtx = document.getElementById('expenseBreakdownChart').getContext('2d');
    const categoryLabels = <?= json_encode(array_keys($expense_categories)) ?>;
    const categoryData = <?= json_encode(array_values($expense_categories)) ?>;
    const backgroundColors = [
        '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', 
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
    ];
    
    // Calculate percentages for labels
    const totalExpenses = categoryData.reduce((sum, value) => sum + value, 0);
    const formattedLabels = categoryLabels.map((label, index) => {
        const percentage = Math.round((categoryData[index] / totalExpenses) * 100);
        return `${label}: ${percentage}%`;
    });
    
    new Chart(expenseBreakdownCtx, {
        type: 'pie',
        data: {
            labels: formattedLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: backgroundColors.slice(0, categoryLabels.length),
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            return `₹${value.toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });

    // Dark mode toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        this.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
    });
    </script>
<script>
document.querySelector('.profile-trigger').onclick = function(e) {
    e.stopPropagation();
    var menu = this.parentElement.querySelector('.dropdown-menu');
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
};
document.addEventListener('click', function() {
    var menu = document.querySelector('.dropdown-menu');
    if(menu) menu.style.display = 'none';
});
</script>


</body>
</html>

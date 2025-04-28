
<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "investment_tracker");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Start session and validate user
session_start();
$user_id = intval($_SESSION['user_id']);
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Helper function for formatting
function fmt($n) { return '₹' . number_format($n); }

// Summary values
$total_income = $conn->query("SELECT SUM(amount) as total FROM incomes WHERE user_id = $user_id")->fetch_assoc()['total'] ?: 0;
$total_expenses = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE user_id = $user_id")->fetch_assoc()['total'] ?: 0;
$total_savings = $conn->query("SELECT SUM(current_amount) as total FROM savings_goals WHERE user_id = $user_id")->fetch_assoc()['total'] ?: 0;
$total_investments = $conn->query("SELECT SUM(current_value) as total FROM investments WHERE user_id = $user_id")->fetch_assoc()['total'] ?: 0;
$total_debts = $conn->query("SELECT SUM(amount) as total FROM debts WHERE user_id = $user_id AND status = 'Upcoming'")->fetch_assoc()['total'] ?: 0;

// Net worth calculations
$assets = $total_savings + $total_investments;
$net_worth = $assets - $total_debts;
// $savings_rate = $total_income > 0 ? round(($total_savings / $total_income) * 100, 1) : 0;
$disposable_income = $total_income - $total_expenses;
$savings_rate = $disposable_income > 0 ? round(($total_savings / $disposable_income) * 100, 1) : 0;
// Monthly data for charts
function get_monthly($table, $field, $user_id) {
    global $conn;
    $data = [];
    $res = $conn->query("SELECT DATE_FORMAT(date, '%c/%Y') as month, SUM($field) as total 
                         FROM $table 
                         WHERE user_id = $user_id 
                         GROUP BY month 
                         ORDER BY STR_TO_DATE(CONCAT('1/', month), '%d/%c/%Y')");
    while ($row = $res->fetch_assoc()) $data[$row['month']] = floatval($row['total']);
    return $data;
}

$income_months = get_monthly('incomes', 'amount', $user_id);
$expense_months = get_monthly('expenses', 'amount', $user_id);
$all_months = array_unique(array_merge(array_keys($income_months), array_keys($expense_months)));
sort($all_months);

// Income sources
$income_sources = [];
$res = $conn->query("SELECT source, SUM(amount) as total 
                     FROM incomes 
                     WHERE user_id = $user_id 
                     GROUP BY source 
                     ORDER BY total DESC");
while ($row = $res->fetch_assoc()) $income_sources[] = $row;

// Expense categories
$expense_categories = [];
$res = $conn->query("SELECT category, SUM(amount) as total 
                     FROM expenses 
                     WHERE user_id = $user_id 
                     GROUP BY category 
                     ORDER BY total DESC");
while ($row = $res->fetch_assoc()) $expense_categories[] = $row;

// Assets Distribution
$assets_dist = [
    ['label' => 'Savings', 'value' => $total_savings],
    ['label' => 'Investments', 'value' => $total_investments]
];

// Financial Health
$financial_health = [
    ['label' => 'Assets', 'value' => $assets],
    ['label' => 'Debts', 'value' => $total_debts],
    ['label' => 'Net Worth', 'value' => $net_worth]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Reports - FinanceTracker</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        
        .card-row { display: flex; gap: 24px; margin-bottom: 24px; }
        .card { background: #fff; border-radius: 14px; padding: 28px 32px; flex: 1; box-shadow: 0 1px 4px #0001; }
        .card-title { font-size: 1.1rem; color: #888; margin-bottom: 7px; }
        .card-value { font-size: 2rem; font-weight: bold; color: #222; }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tab { background: #f3f6fa; color: #222; padding: 8px 18px; border-radius: 8px; cursor: pointer; border: none; }
        .tab.active { background: #e5e7eb; font-weight: bold; }
        .chart-box { background: #fff; border-radius: 14px; box-shadow: 0 1px 4px #0001; margin-bottom: 24px; padding: 28px 26px; }
        .chart-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 2px; }
        .chart-subtitle { color: #888; font-size: 0.99rem; margin-bottom: 10px; }
        .row-2col { display: flex; gap: 24px; }
        .row-2col > div { flex: 1; }
        .bar-label { font-weight: bold; margin-bottom: 4px; }
        a{
            color:black;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;

        }
        a:hover{
            color: #ff7a59;
        }
        a.active{
            border-bottom: 2px solid #ff7a59;
            color: #ff7f50;
        }
    </style>
</head>
<body>
    <nav style="background:#fff; border-bottom:1px solid #eee; padding:0 40px; height:60px; display:flex; align-items:center;  box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <span class="logo" style="margin-right:200px; color:#ff884d; font-weight:700; font-size:1.4rem; gap:100px;">FinanceTracker</span>
        <div style="display:flex; gap:40px; padding: 8px 0; align-items:center;  font-weight: 500;">
            <a href="index.php">Dashboard</a>
            <a href="income.php" >Income</a>
            <a href="expenses.php">Expenses</a>
            <a href="savings.php">Savings</a>
            <a href="investments.php">Investments</a>
            <a href="debt.php">Debts</a>
            <a href="#" class="active">Reports</a>
            <a href="news.html">News</a>
        </div>
        <span style="margin-left:auto; font-size:1.3rem; cursor:pointer;">&#8635;</span>
    </nav>
    <main style="max-width:1200px; margin:32px auto; padding:0 24px;">
        <h1 style="font-size:2.2rem; margin-bottom:0.2em;">Financial Reports</h1>
        <p style="color:#666; margin-top:0;">Analyze your financial data with detailed reports and insights.</p>
        <div class="card-row">
            <div class="card">
                <div class="card-title">₹ Net Worth</div>
                <div class="card-value"><?= fmt($net_worth) ?></div>
            </div>
            <div class="card">
                <div class="card-title">&#8599; Total Income</div>
                <div class="card-value"><?= fmt($total_income) ?></div>
            </div>
            <div class="card">
                <div class="card-title">&#8599; Total Expenses</div>
                <div class="card-value"><?= fmt($total_expenses) ?></div>
            </div>
            <div class="card">
                <div class="card-title">&#128179; Savings Rate</div>
                <div class="card-value"><?= $savings_rate ?>%</div>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <div class="tabs">
                <button class="tab active" onclick="showTab('cashflow')">Cash Flow</button>
                <button class="tab" onclick="showTab('income')">Income</button>
                <button class="tab" onclick="showTab('expenses')">Expenses</button>
                <button class="tab" onclick="showTab('breakdown')">Breakdown</button>
            </div>
            <select id="periodSelect" style="padding:8px 14px; border-radius:8px; border:1px solid #eee;">
                <option>Monthly</option>
                <option>Quarterly</option>
                <option>Yearly</option>
            </select>
        </div>
        <!-- Cash Flow Tab -->
        <div class="chart-box tab-content" id="tab-cashflow">
            <div class="chart-title">Cash Flow Analysis</div>
            <div class="chart-subtitle">Income vs Expenses over time</div>
            <canvas id="cashFlowChart" height="250"></canvas>
        </div>
        <!-- Income Tab -->
        <div class="chart-box tab-content" id="tab-income" style="display:none;">
            <div class="chart-title">Income Trends</div>
            <div class="chart-subtitle">Income over time</div>
            <canvas id="incomeTrendsChart" height="250"></canvas>
            <div class="row-2col" style="margin-top:32px;">
                <div>
                    <div class="bar-label">Income Sources</div>
                    <?php foreach($income_sources as $src): ?>
                        <div style="margin-bottom:10px;">
                            <span><?= htmlspecialchars($src['source']) ?>:</span>
                            <span><?= fmt($src['total']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <div class="bar-label">Income Sources (Bar)</div>
                    <canvas id="incomeSourcesChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <!-- Expenses Tab -->
        <div class="chart-box tab-content" id="tab-expenses" style="display:none;">
            <div class="chart-title">Expense Trends</div>
            <div class="chart-subtitle">Expenses over time</div>
            <canvas id="expenseTrendsChart" height="250"></canvas>
            <div class="row-2col" style="margin-top:32px;">
                <div>
                    <div class="bar-label">Expense Categories</div>
                    <?php foreach($expense_categories as $cat): ?>
                        <div style="margin-bottom:10px;">
                            <span><?= htmlspecialchars($cat['category']) ?>:</span>
                            <span><?= fmt($cat['total']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <div class="bar-label">Expense Categories (Bar)</div>
                    <canvas id="expenseCategoriesChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <!-- Breakdown Tab -->
       <!-- Breakdown Tab -->
<div class="chart-box tab-content" id="tab-breakdown" style="display:none;">
    <div class="row-2col">
        <div>
            <div class="chart-title">Assets Distribution</div>
            <div class="chart-subtitle">Savings and Investments</div>
            <canvas id="assetsDistChart" height="220"></canvas>
        </div>
        <div>
            <div class="chart-title">Financial Health</div>
            <div class="chart-subtitle">Assets vs Liabilities</div>
            <canvas id="financialHealthChart" height="220"></canvas>
        </div>
    </div>
    <div style="margin-top: 30px;">
        <div class="chart-title">Financial Summary</div>
        <div class="chart-subtitle">Complete overview</div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="background-color: #f3f6fa;">
                    <th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee;">Category</th>
                    <th style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;">Amount</th>
                    <th style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;">Percentage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">Total Income</td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= fmt($total_income) ?></td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;">100%</td>
                </tr>
                <tr>
                    <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">Total Expenses</td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= fmt($total_expenses) ?></td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= $total_income>0 ? round(($total_expenses/$total_income)*100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">Savings</td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= fmt($total_savings) ?></td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= $total_income>0 ? round(($total_savings/$total_income)*100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">Investments</td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= fmt($total_investments) ?></td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= $total_income>0 ? round(($total_investments/$total_income)*100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">Debts</td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= fmt($total_debts) ?></td>
                    <td style="padding: 12px 15px; text-align: right; border-bottom: 1px solid #eee;"><?= $total_income>0 ? round(($total_debts/$total_income)*100, 1) : 0 ?>%</td>
                </tr>
                <tr>
                    <td style="padding: 12px 15px; font-weight: bold;">Net Worth</td>
                    <td style="padding: 12px 15px; text-align: right; font-weight: bold;"><?= fmt($net_worth) ?></td>
                    <td style="padding: 12px 15px; text-align: right;">-</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


    </main>
    <script>
    // Tabs logic
    function showTab(tab) {
        document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(div => div.style.display = 'none');
        document.querySelector('.tab[onclick="showTab(\''+tab+'\')"]').classList.add('active');
        document.getElementById('tab-' + tab).style.display = '';
    }
    // Prepare data for charts
    const allMonths = <?= json_encode($all_months) ?>;
    const incomeMonths = <?= json_encode($income_months) ?>;
    const expenseMonths = <?= json_encode($expense_months) ?>;
    const incomeData = allMonths.map(m => incomeMonths[m] || 0);
    const expenseData = allMonths.map(m => expenseMonths[m] || 0);

    // Cash Flow Chart
    new Chart(document.getElementById('cashFlowChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: allMonths,
            datasets: [
                { label: 'Income', data: incomeData, backgroundColor: '#27ae60' },
                { label: 'Expenses', data: expenseData, backgroundColor: '#ff884d' }
            ]
        },
        options: { responsive:true, scales: { y: { beginAtZero:true } } }
    });
    // Income Trends Chart
    new Chart(document.getElementById('incomeTrendsChart').getContext('2d'), {
        type: 'line',
        data: { labels: allMonths, datasets: [{ label: 'Income', data: incomeData, borderColor: '#27ae60', backgroundColor: 'rgba(39,174,96,0.1)', tension: 0.3, fill: false }] },
        options: { responsive:true, scales: { y: { beginAtZero:true } } }
    });
    // Expense Trends Chart
    new Chart(document.getElementById('expenseTrendsChart').getContext('2d'), {
        type: 'line',
        data: { labels: allMonths, datasets: [{ label: 'Expenses', data: expenseData, borderColor: '#ff884d', backgroundColor: 'rgba(255,136,77,0.1)', tension: 0.3, fill: false }] },
        options: { responsive:true, scales: { y: { beginAtZero:true } } }
    });
    // Income Sources Bar
    const incomeSourceLabels = <?= json_encode(array_map(fn($row) => $row['source'], $income_sources)) ?>;
    const incomeSourceData = <?= json_encode(array_map(fn($row) => floatval($row['total']), $income_sources)) ?>;
    new Chart(document.getElementById('incomeSourcesChart').getContext('2d'), {
        type: 'bar',
        data: { labels: incomeSourceLabels, datasets: [{ label: 'Income', data: incomeSourceData, backgroundColor: '#27ae60' }] },
        options: { responsive:true, scales: { y: { beginAtZero:true } } }
    });
    // Expense Categories Bar
    const expenseCatLabels = <?= json_encode(array_map(fn($row) => $row['category'], $expense_categories)) ?>;
    const expenseCatData = <?= json_encode(array_map(fn($row) => floatval($row['total']), $expense_categories)) ?>;
    new Chart(document.getElementById('expenseCategoriesChart').getContext('2d'), {
        type: 'bar',
        data: { labels: expenseCatLabels, datasets: [{ label: 'Expenses', data: expenseCatData, backgroundColor: '#ff884d' }] },
        options: { responsive:true, scales: { y: { beginAtZero:true } } }
    });
    // Assets Distribution Pie
    const assetsDistLabels = <?= json_encode(array_map(fn($row) => $row['label'], $assets_dist)) ?>;
    const assetsDistData = <?= json_encode(array_map(fn($row) => floatval($row['value']), $assets_dist)) ?>;
    new Chart(document.getElementById('assetsDistChart').getContext('2d'), {
        type: 'pie',
        data: { labels: assetsDistLabels, datasets: [{ data: assetsDistData, backgroundColor: ['#27ae60', '#3498db'] }] },
        options: { responsive:true }
    });
    // Financial Health Bar
    const finHealthLabels = <?= json_encode(array_map(fn($row) => $row['label'], $financial_health)) ?>;
    const finHealthData = <?= json_encode(array_map(fn($row) => floatval($row['value']), $financial_health)) ?>;
    new Chart(document.getElementById('financialHealthChart').getContext('2d'), {
        type: 'bar',
        data: { labels: finHealthLabels, datasets: [{ label: 'Financial Health', data: finHealthData, backgroundColor: ['#27ae60', '#ff884d', '#3498db'] }] },
        options: { responsive:true, scales: { y: { beginAtZero:false } } }
    });
    </script>
</body>
</html>
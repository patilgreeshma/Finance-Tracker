<?php
session_start();
require_once 'dbconfig.php';

error_reporting(E_ALL);
ini_set('display_errors', 'On');
$user_id = intval($_SESSION['user_id']); // Ensure $user_id is sanitized

// Handle Add Income (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $source = $conn->real_escape_string($_POST['source']);
    $amount = floatval($_POST['amount']);
    $date = $conn->real_escape_string($_POST['date']);
    $note = $conn->real_escape_string($_POST['note']);

    $conn->query("INSERT INTO incomes (user_id, source, amount, date, note) VALUES ($user_id, '$source', $amount, '$date', '$note')");
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    exit;
   
    // $conn->query("INSERT INTO incomes (source, amount, date, note) VALUES ('$source', $amount, '$date', '$note')");
    // echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    // exit;
}

// Handle Delete Income (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM incomes WHERE id = $id AND user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

// Fetch all incomes
$incomes = [];
$conn->set_charset("utf8mb4"); // Set character set to utf8mb4 for better compatibility
    
$res = $conn->query("SELECT * FROM incomes   WHERE user_id = $user_id ORDER BY date DESC");

while ($row = $res->fetch_assoc()) $incomes[] = $row;

// Total income
$total = 0;
foreach ($incomes as $income) {
    $total += $income['amount'];
}

// Get unique sources for filter
// $sources = [];

// $res = $conn->query("SELECT DISTINCT source FROM incomes   WHERE user_id = $user_id ORDER BY source");

// while ($row = $res->fetch_assoc()) $sources[] = $row['source'];
// if (empty($sources)) {
//     $sources = ['Salary', 'Freelance', 'Investments', 'Side Hustle'];
// }
// Default sources
$default_sources = ['Salary', 'Freelance', 'Investments', 'Side Hustle'];

// Get sources used by the user
$sources = $default_sources;

$res = $conn->query("SELECT DISTINCT source FROM incomes WHERE user_id = $user_id ORDER BY source");
while ($row = $res->fetch_assoc()) {
    if (!in_array($row['source'], $sources)) {
        $sources[] = $row['source'];
    }
}


// Get unique months for filter
$months = [];
$res = $conn->query("SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month_year FROM incomes   WHERE user_id = $user_id ORDER BY month_year DESC");
while ($row = $res->fetch_assoc()) {
    $date = date_create_from_format('Y-m', $row['month_year']);
    $months[$row['month_year']] = date_format($date, 'F Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Tracking - FinanceTracker</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">FinanceTracker</a>
        <nav class="nav">
            <a href="index.php">Dashboard</a>
            <a href="#" class="active">Income</a>
            <a href="expenses.php">Expenses</a>
            <a href="savings.php">Savings</a>
            <a href="investments.php">Investments</a>
            <a href="debt.php">Debts</a>
            <a href="reports.php">Reports</a>
            <a href="news.php">News</a>
        </nav>
        <button class="theme-toggle" id="themeToggle">🌙</button>
    </header>

    <div class="container">
        <h1 class="page-title">Income Tracking</h1>
        <p class="subtitle">Track and manage all your sources of income.</p>

        <div class="filters">
            <input type="text" id="searchInput" class="search-input" placeholder="Search sources or notes">
            
            <select id="monthFilter" class="filter-select">
                <option value="">All Months</option>
                <?php foreach ($months as $key => $label): ?>
                    <option value="<?= $key ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="sourceFilter" class="filter-select">
                <option value="">All Sources</option>
                <?php foreach ($sources as $source): ?>
                    <option value="<?= htmlspecialchars($source) ?>"><?= htmlspecialchars($source) ?></option>
                <?php endforeach; ?>
            </select>
            
            <button id="addIncomeBtn" class="add-button">+ Add Income</button>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">Income Summary</h2>
                <p id="summary-info">Showing <?= count($incomes) ?> entries with a total of <?= number_format($total) ?></p>
                
                <table class="data-table" id="incomeTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Amount</th>
                            <th>Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incomes as $income): ?>
                        <tr data-id="<?= $income['id'] ?>">
                            <td><?= date('n/j/Y', strtotime($income['date'])) ?></td>
                            <td><?= htmlspecialchars($income['source']) ?></td>
                            <td>₹<?= number_format($income['amount']) ?></td>
                            <td><?= htmlspecialchars($income['note']) ?></td>
                            <td>
                                <button class="action-btn delete-btn">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2 class="card-title">Income Trend</h2>
                <p class="subtitle">Monthly income over time</p>
                <canvas id="incomeChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Add Income Modal -->
    <div class="modal" id="addIncomeModal">
        <div class="modal-content">
            <button class="modal-close" id="closeModalBtn">&times;</button>
            <h2 class="modal-title">Add New Income</h2>
            <p class="modal-subtitle">Enter the details of your income. Click save when you're done.</p>
            
            <form id="incomeForm">
                <div class="form-group">
                    <label class="form-label">Source*</label>
                    <select id="sourceInput" class="form-select" required>
                        <option value="">Select a source</option>
                        <?php foreach ($sources as $source): ?>
                            <option value="<?= htmlspecialchars($source) ?>"><?= htmlspecialchars($source) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Amount*</label>
                    <input type="number" id="amountInput" class="form-control" step="0.01" min="0" required value="0.00">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date*</label>
                    <input type="date" id="dateInput" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Note</label>
                    <textarea id="noteInput" class="form-textarea" placeholder="Add note (optional)"></textarea>
                </div>
                
                <button type="submit" class="btn-save">Save</button>
            </form>
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
                    <li><a href="#">Investments</a></li>
                    <li><a href="debt.php">Debts</a></li>
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
    // Set default date to today
    document.getElementById('dateInput').value = new Date().toISOString().split('T')[0];

    // Load income data from PHP
    const incomes = <?= json_encode($incomes) ?>;
    
    // Modal control
    const modal = document.getElementById('addIncomeModal');
    const addBtn = document.getElementById('addIncomeBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    
    addBtn.onclick = function() {
        modal.style.display = 'flex';
    }
    
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    // Form submission
    document.getElementById('incomeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const source = document.getElementById('sourceInput').value;
        const amount = document.getElementById('amountInput').value;
        const date = document.getElementById('dateInput').value;
        const note = document.getElementById('noteInput').value;
        
        // Send AJAX request
        fetch('income.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&source=${encodeURIComponent(source)}&amount=${encodeURIComponent(amount)}&date=${encodeURIComponent(date)}&note=${encodeURIComponent(note)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show new data
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });

    // Delete income
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this income entry?')) {
                fetch('income.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    });

    // Chart rendering
    function renderIncomeChart() {
        const ctx = document.getElementById('incomeChart').getContext('2d');
        
        // Process income data by month
        const monthlyData = {};
        
        incomes.forEach(income => {
            const date = new Date(income.date);
            const month = date.getMonth() + 1;
            const year = date.getFullYear();
            const key = `${month}/${year}`;
            
            if (!monthlyData[key]) {
                monthlyData[key] = 0;
            }
            
            monthlyData[key] += parseFloat(income.amount);
        });
        
        // Sort keys chronologically
        const sortedKeys = Object.keys(monthlyData).sort((a, b) => {
            const [monthA, yearA] = a.split('/').map(Number);
            const [monthB, yearB] = b.split('/').map(Number);
            
            if (yearA !== yearB) {
                return yearA - yearB;
            }
            
            return monthA - monthB;
        });
        
        // Create chart
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: sortedKeys,
                datasets: [{
                    label: 'Income',
                    data: sortedKeys.map(key => monthlyData[key]),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Income: ₹' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Render chart on page load
    document.addEventListener('DOMContentLoaded', renderIncomeChart);
    
    // Filter functionality
    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('monthFilter').addEventListener('change', filterTable);
    document.getElementById('sourceFilter').addEventListener('change', filterTable);
    
    function filterTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const selectedMonth = document.getElementById('monthFilter').value;
        const selectedSource = document.getElementById('sourceFilter').value;
        
        const rows = document.querySelectorAll('#incomeTable tbody tr');
        let visibleCount = 0;
        let totalAmount = 0;
        
        rows.forEach(row => {
            const source = row.cells[1].textContent.toLowerCase();
            const amount = parseFloat(row.cells[2].textContent.replace(/[^0-9.-]+/g, ''));
            const note = row.cells[3].textContent.toLowerCase();
            const date = new Date(row.cells[0].textContent);
            const rowMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
            
            const matchesSearch = source.includes(searchTerm) || note.includes(searchTerm);
            const matchesMonth = !selectedMonth || rowMonth === selectedMonth;
            const matchesSource = !selectedSource || source === selectedSource.toLowerCase();
            
            if (matchesSearch && matchesMonth && matchesSource) {
                row.style.display = '';
                visibleCount++;
                totalAmount += amount;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update summary
        document.getElementById('summary-info').textContent = `Showing ${visibleCount} entries with a total of ₹ ${totalAmount.toLocaleString()}`;
    }
    
    // Theme toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        this.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
    });
    </script>
</body>
</html>

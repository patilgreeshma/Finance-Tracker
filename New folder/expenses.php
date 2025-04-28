<?php

session_start();
require_once "dbconfig.php";
error_reporting(E_ALL); // Enable error reporting for debugging
ini_set('display_errors', 1); // Show errors on the page

$user_id = intval($_SESSION['user_id']);
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Handle Add Expense (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $user_id = intval($_SESSION['user_id']);
    $category = $conn->real_escape_string($_POST['category']);
    $amount = floatval($_POST['amount']);
    $date = $conn->real_escape_string($_POST['date']);
    $note = $conn->real_escape_string($_POST['note']);
    
    $conn->query("INSERT INTO expenses (user_id,category, amount, date, note) VALUES ('$user_id','$category', $amount, '$date', '$note')");

    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    exit;
}

// Handle Delete Expense (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM expenses WHERE id = $id AND user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

// Fetch all expenses
$expenses = [];
$res = $conn->query("SELECT * FROM expenses  where user_id = $user_id ORDER BY date DESC");
while ($row = $res->fetch_assoc()) $expenses[] = $row;

// Total expenses
$total = 0;
foreach ($expenses as $expense) {
    $total += $expense['amount'];
}

// Get unique categories for filter
// $categories = [];
// $res = $conn->query("SELECT DISTINCT category FROM expenses  where user_id = $user_id ORDER BY category");
// while ($row = $res->fetch_assoc()) $categories[] = $row['category'];
// if (empty($categories)) {
//     $categories = ['Housing', 'Food & Dining', 'Transportation', 'Entertainment', 'Utilities', 'Healthcare'];
// }

// Default categories
$default_categories = ['Housing', 'Food & Dining', 'Transportation', 'Entertainment', 'Utilities', 'Healthcare'];

// Get categories used by the user plus defaults
$categories = $default_categories;

$res = $conn->query("SELECT DISTINCT category FROM expenses WHERE user_id = $user_id ORDER BY category");
while ($row = $res->fetch_assoc()) {
    if (!in_array($row['category'], $categories)) {
        $categories[] = $row['category'];
    }
}


// Get unique months for filter
$months = [];
$res = $conn->query("SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month_year FROM expenses WHERE user_id = $user_id ORDER BY month_year DESC");
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
    <title>Expense Tracking - FinanceTracker</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">FinanceTracker</a>
        <nav class="nav">
            <a href="index.php">Dashboard</a>
            <a href="income.php">Income</a>
            <a href="#" class="active">Expenses</a>
            <a href="savings.php">Savings</a>
            <a href="investments.php">Investments</a>
            <a href="debt.php">Debts</a>
            <a href="reports.php">Reports</a>
            <a href="news.html">News</a>
        </nav>
        <button class="theme-toggle" id="themeToggle">🌙</button>
    </header>

    <div class="container">
        <h1 class="page-title">Expense Tracking</h1>
        <p class="subtitle">Track and manage all your expenses.</p>

        <div class="filters">
            <input type="text" id="searchInput" class="search-input" placeholder="Search categories or notes">
            
            <select id="monthFilter" class="filter-select">
                <option value="">All Months</option>
                <?php foreach ($months as $key => $label): ?>
                    <option value="<?= $key ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="categoryFilter" class="filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                <?php endforeach; ?>
            </select>
            
            <button id="addExpenseBtn" class="add-button">+ Add Expense</button>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">Expense Summary</h2>
                <p id="summary-info">Showing <?= count($expenses) ?> entries with a total of ₹<?= number_format($total) ?></p>
                
                <table class="data-table" id="expenseTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr data-id="<?= $expense['id'] ?>">
                            <td><?= date('n/j/Y', strtotime($expense['date'])) ?></td>
                            <td><?= htmlspecialchars($expense['category']) ?></td>
                            <td>₹<?= number_format($expense['amount']) ?></td>
                            <td><?= htmlspecialchars($expense['note']) ?></td>
                            <td>
                                <button class="action-btn delete-btn">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2 class="card-title">Expense Distribution</h2>
                <p class="subtitle">Breakdown by category</p>
                <canvas id="expenseChart" height="300" ></canvas>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal" id="addExpenseModal">
        <div class="modal-content">
            <button class="modal-close" id="closeModalBtn">&times;</button>
            <h2 class="modal-title">Add New Expense</h2>
            <p class="modal-subtitle">Enter the details of your expense. Click save when you're done.</p>
            
            <form id="expenseForm">
                <div class="form-group">
                    <label class="form-label">Category*</label>
                    <select id="categoryInput" class="form-select" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
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

    // Load expense data from PHP
    const expenses = <?= json_encode($expenses) ?>;
    const categories = <?= json_encode($categories) ?>;
    
    // Modal control
    const modal = document.getElementById('addExpenseModal');
    const addBtn = document.getElementById('addExpenseBtn');
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
    document.getElementById('expenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const category = document.getElementById('categoryInput').value;
        const amount = document.getElementById('amountInput').value;
        const date = document.getElementById('dateInput').value;
        const note = document.getElementById('noteInput').value;
        
        // Send AJAX request
        fetch('expenses.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&category=${encodeURIComponent(category)}&amount=${encodeURIComponent(amount)}&date=${encodeURIComponent(date)}&note=${encodeURIComponent(note)}`
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

    // Delete expense
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this expense entry?')) {
                fetch('expenses.php', {
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
    function renderExpenseChart() {
        const ctx = document.getElementById('expenseChart').getContext('2d');
        
        // Calculate totals by category
        const categoryTotals = {};
        let totalExpenses = 0;
        
        expenses.forEach(expense => {
            const amount = parseFloat(expense.amount);
            categoryTotals[expense.category] = (categoryTotals[expense.category] || 0) + amount;
            totalExpenses += amount;
        });
        
        // Prepare chart data with percentages
        const categories = Object.keys(categoryTotals).filter(cat => categoryTotals[cat] > 0);
        const data = categories.map(cat => categoryTotals[cat]);
        const labels = categories.map(cat => {
            const percentage = Math.round((categoryTotals[cat] / totalExpenses) * 100);
            return `${cat}: ${percentage}%`;
        });
        
        // Colors for chart segments
        const colors = [
            '#3498db', '#2ecc71', '#f1c40f', '#e67e22', '#9b59b6', '#e74c3c',
            '#1abc9c', '#34495e', '#7f8c8d', '#d35400', '#c0392b', '#16a085'
        ];
        
        // Create chart
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, categories.length),
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
                        labels: { boxWidth: 15 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `₹${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Render chart on page load
    document.addEventListener('DOMContentLoaded', renderExpenseChart);
    
    // Filter functionality
    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('monthFilter').addEventListener('change', filterTable);
    document.getElementById('categoryFilter').addEventListener('change', filterTable);
    
    function filterTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const selectedMonth = document.getElementById('monthFilter').value;
        const selectedCategory = document.getElementById('categoryFilter').value;
        
        const rows = document.querySelectorAll('#expenseTable tbody tr');
        let visibleCount = 0;
        let totalAmount = 0;
        
        rows.forEach(row => {
            const category = row.cells[1].textContent.toLowerCase();
            const amount = parseFloat(row.cells[2].textContent.replace(/[^0-9.-]+/g, ''));
            const note = row.cells[3].textContent.toLowerCase();
            const date = new Date(row.cells[0].textContent);
            const rowMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
            
            const matchesSearch = category.includes(searchTerm) || note.includes(searchTerm);
            const matchesMonth = !selectedMonth || rowMonth === selectedMonth;
            const matchesCategory = !selectedCategory || category === selectedCategory.toLowerCase();
            
            if (matchesSearch && matchesMonth && matchesCategory) {
                row.style.display = '';
                visibleCount++;
                totalAmount += amount;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update summary
        document.getElementById('summary-info').textContent = `Showing ${visibleCount} entries with a total of ₹${totalAmount.toLocaleString()}`;
    }
    
    // Theme toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        this.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
    });
    </script>
</body>
</html>

<?php
require_once "dbconfig.php";
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$user_id = intval($_SESSION['user_id']);
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Handle Add Savings Goal (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $user_id = intval($_SESSION['user_id']);
    $goalName = $conn->real_escape_string($_POST['goalName']);
    $targetAmount = floatval($_POST['targetAmount']);
    $currentAmount = floatval($_POST['currentAmount']);
    
    $conn->query("INSERT INTO savings_goals (user_id,goal_name, target_amount, current_amount, created_date) VALUES ($user_id,'$goalName', $targetAmount, $currentAmount, NOW())");
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    exit;
}

// Handle Update Savings Goal (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $currentAmount = floatval($_POST['currentAmount']);
    
    $conn->query("UPDATE savings_goals SET current_amount = $currentAmount WHERE id = $id AND user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

// Handle Delete Savings Goal (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM savings_goals WHERE id = $id AND user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

// Fetch all savings goals
$goals = [];
$res = $conn->query("SELECT * FROM savings_goals  where user_id = $user_id ORDER BY created_date DESC");
while ($row = $res->fetch_assoc()) $goals[] = $row;

// Total savings
$total = 0;
foreach ($goals as $goal) {
    $total += $goal['current_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Goals - FinanceTracker</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo">FinanceTracker</a>
        <nav class="nav">
            <a href="index.php">Dashboard</a>
            <a href="income.php">Income</a>
            <a href="expenses.php">Expenses</a>
            <a href="#" class="active">Savings</a>
            <a href="investments.php">Investments</a>
            <a href="debt.php">Debts</a>
            <a href="reports.php">Reports</a>
            <a href="news.php">News</a>
        </nav>
        <button class="theme-toggle" id="themeToggle">🌙</button>
    </header>

    <div class="container">
        <h1 class="page-title">Savings Goals</h1>
        <p class="subtitle">Create and track your progress towards saving goals.</p>

        <div class="filters">
            <input type="text" id="searchInput" class="search-input" placeholder="Search goal names">
            <button id="addSavingsBtn" class="add-button">+ Add Savings Goal</button>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">Savings Goals</h2>
                <p id="summary-info">Showing <?= count($goals) ?> goals with a total of ₹<?= number_format($total) ?></p>
                
                <table class="data-table" id="savingsTable">
                    <thead>
                        <tr>
                            <th>Goal Name</th>
                            <th>Target Amount</th>
                            <th>Current Amount</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): 
                            $percentage = min(100, round(($goal['current_amount'] / $goal['target_amount']) * 100));
                        ?>
                        <tr data-id="<?= $goal['id'] ?>">
                            <td><?= htmlspecialchars($goal['goal_name']) ?></td>
                            <td>₹<?= number_format($goal['target_amount']) ?></td>
                            <td>₹<?= number_format($goal['current_amount']) ?></td>
                            <td>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?= $percentage ?>%</span>
                                </div>
                            </td>
                            <td>
                                <button class="action-btn update-btn">Update</button>
                                <button class="action-btn delete-btn">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2 class="card-title">Overall Progress</h2>
                <p class="subtitle">Progress towards each goal</p>
                <canvas id="progressChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Add Savings Goal Modal -->
    <div class="modal" id="addSavingsModal">
        <div class="modal-content">
            <button class="modal-close" id="closeModalBtn">&times;</button>
            <h2 class="modal-title">Add New Savings Goal</h2>
            <p class="modal-subtitle">Create a new savings goal to track your progress.</p>
            
            <form id="savingsForm">
                <div class="form-group">
                    <label class="form-label">Goal Name*</label>
                    <input type="text" id="goalNameInput" class="form-control" required placeholder="e.g., Emergency Fund, Vacation">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Target Amount*</label>
                    <input type="number" id="targetAmountInput" class="form-control" step="0.01" min="0" required value="0.00">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Amount*</label>
                    <input type="number" id="currentAmountInput" class="form-control" step="0.01" min="0" required value="0.00">
                </div>
                
                <button type="submit" class="btn-save">Save</button>
            </form>
        </div>
    </div>

    <!-- Update Savings Goal Modal -->
    <div class="modal" id="updateSavingsModal">
        <div class="modal-content">
            <button class="modal-close" id="closeUpdateModalBtn">&times;</button>
            <h2 class="modal-title">Update Savings Goal</h2>
            <p class="modal-subtitle">Update your progress on this savings goal.</p>
            
            <form id="updateSavingsForm">
                <input type="hidden" id="updateGoalId">
                
                <div class="form-group">
                    <label class="form-label">Goal Name</label>
                    <input type="text" id="updateGoalName" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Target Amount</label>
                    <input type="number" id="updateTargetAmount" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Amount*</label>
                    <input type="number" id="updateCurrentAmount" class="form-control" step="0.01" min="0" required>
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
                    <li><a href="#">Financial News</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="news.php">News</a></li>

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
    // Load savings goals data from PHP
    const savingsGoals = <?= json_encode($goals) ?>;
    
    // Modal control for Add Goal
    const addModal = document.getElementById('addSavingsModal');
    const addBtn = document.getElementById('addSavingsBtn');
    const closeAddBtn = document.getElementById('closeModalBtn');
    
    addBtn.onclick = function() {
        addModal.style.display = 'flex';
    }
    
    closeAddBtn.onclick = function() {
        addModal.style.display = 'none';
    }
    
    // Modal control for Update Goal
    const updateModal = document.getElementById('updateSavingsModal');
    const closeUpdateBtn = document.getElementById('closeUpdateModalBtn');
    
    closeUpdateBtn.onclick = function() {
        updateModal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target === addModal) {
            addModal.style.display = 'none';
        }
        if (event.target === updateModal) {
            updateModal.style.display = 'none';
        }
    }

    // Add new savings goal
    document.getElementById('savingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const goalName = document.getElementById('goalNameInput').value;
        const targetAmount = document.getElementById('targetAmountInput').value;
        const currentAmount = document.getElementById('currentAmountInput').value;
        
        // Send AJAX request
        fetch('savings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&goalName=${encodeURIComponent(goalName)}&targetAmount=${encodeURIComponent(targetAmount)}&currentAmount=${encodeURIComponent(currentAmount)}`
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

    // Update savings goal
    document.getElementById('updateSavingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const goalId = document.getElementById('updateGoalId').value;
        const currentAmount = document.getElementById('updateCurrentAmount').value;
        
        // Send AJAX request
        fetch('savings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update&id=${encodeURIComponent(goalId)}&currentAmount=${encodeURIComponent(currentAmount)}`
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

    // Delete savings goal
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this savings goal?')) {
                fetch('savings.php', {
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

    // Show update modal
    document.querySelectorAll('.update-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            const goalName = row.cells[0].textContent;
            const targetAmount = parseFloat(row.cells[1].textContent.replace(/[^0-9.-]+/g, ''));
            const currentAmount = parseFloat(row.cells[2].textContent.replace(/[^0-9.-]+/g, ''));
            
            document.getElementById('updateGoalId').value = id;
            document.getElementById('updateGoalName').value = goalName;
            document.getElementById('updateTargetAmount').value = targetAmount;
            document.getElementById('updateCurrentAmount').value = currentAmount;
            
            updateModal.style.display = 'flex';
        });
    });

    // Chart rendering
    function renderProgressChart() {
        const ctx = document.getElementById('progressChart').getContext('2d');
        
        // Process data for chart
        const labels = savingsGoals.map(goal => goal.goal_name);
        const currentAmounts = savingsGoals.map(goal => parseFloat(goal.current_amount));
        const remainingAmounts = savingsGoals.map(goal => {
            const target = parseFloat(goal.target_amount);
            const current = parseFloat(goal.current_amount);
            return Math.max(0, target - current);
        });
        
        // Create chart
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Current Amount',
                        data: currentAmounts,
                        backgroundColor: '#10b981'
                    },
                    {
                        label: 'Remaining',
                        data: remainingAmounts,
                        backgroundColor: '#e5e7eb'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Render chart on page load
    document.addEventListener('DOMContentLoaded', renderProgressChart);
    
    // Filter functionality
    document.getElementById('searchInput').addEventListener('input', filterTable);
    
    function filterTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        
        const rows = document.querySelectorAll('#savingsTable tbody tr');
        let visibleCount = 0;
        let totalAmount = 0;
        
        rows.forEach(row => {
            const goalName = row.cells[0].textContent.toLowerCase();
            const currentAmount = parseFloat(row.cells[2].textContent.replace(/[^0-9.-]+/g, ''));
            
            if (goalName.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
                totalAmount += currentAmount;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update summary
        document.getElementById('summary-info').textContent = `Showing ${visibleCount} goals with a total of $${totalAmount.toLocaleString()}`;
    }
    
    // Theme toggle
    document.getElementById('themeToggle').addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        this.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
    });
    </script>
</body>
</html>

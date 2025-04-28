<?php
session_start();
require_once 'dbconfig.php';
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$user_id = $_SESSION['user_id'];

$user_id = intval($_SESSION['user_id']);
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Get total invested amount
$sql_total = "SELECT SUM(amount) as total FROM investments WHERE user_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $user_id);
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total = $total_result->fetch_assoc()['total'] ?? 0;

// Get current value
$sql_value = "SELECT SUM(current_value) as total_value FROM investments WHERE user_id = ?";
$stmt_value = $conn->prepare($sql_value);
$stmt_value->bind_param("i", $user_id);
$stmt_value->execute();
$value_result = $stmt_value->get_result();
$total_value = $value_result->fetch_assoc()['total_value'] ?? 0;

// Calculate returns
$returns = $total_value - $total;
$avg_return = ($total > 0) ? ($returns / $total) * 100 : 0;

// Get investments
$sql_investments = "SELECT i.*, t.type_name 
                    FROM investments i 
                    JOIN investment_types t ON i.type_id = t.id 
                    WHERE i.user_id = ?
                    ORDER BY i.purchase_date DESC";
$stmt_investments = $conn->prepare($sql_investments);
$stmt_investments->bind_param("i", $user_id);
$stmt_investments->execute();
$investments_result = $stmt_investments->get_result();

// Get investment types for dropdown
$sql_types = "SELECT id, type_name FROM investment_types ORDER BY type_name";
$types_result = $conn->query($sql_types);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinanceTracker - Investment Portfolio</title>
    <link rel="stylesheet" href="inves.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <div class="logo">
            <a href="index.php">FinanceTracker</a>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="income.php">Income</a></li>
                <li><a href="expenses.php">Expenses</a></li>
                <li><a href="savings.php">Savings</a></li>
                <li><a href="investments.php" class="active">Investments</a></li>
                <li><a href="debt.php">Debts</a></li>
                <li><a href="reports.php">Reports</a></li>
                <li><a href="news.html">News</a></li>
            </ul>
        </nav>
        <div class="theme-toggle">
            <button id="theme-toggle-btn"><i class="fas fa-moon"></i></button>
        </div>
    </header>

    <main>
        <section class="portfolio-header">
            <h1>Investment Portfolio</h1>
            <p>Track and manage your investment portfolio.</p>
        </section>

        <section class="stats-cards">
            <div class="stat-card">
                <h3>Total Invested</h3>
                <p class="stat-value">₹<?php echo number_format($total, 0); ?></p>
            </div>
            <div class="stat-card">
                <h3>Estimated Returns</h3>
                <p class="stat-value">₹<?php echo number_format($returns, 0); ?></p>
            </div>
            <div class="stat-card">
                <h3>Average Return</h3>
                <p class="stat-value"><?php echo number_format($avg_return, 2); ?>%</p>
            </div>
        </section>

        <section class="investment-actions">
            <div class="search-filter">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-investments" placeholder="Search investments...">
                </div>
                <div class="filter-dropdown">
                    <select id="filter-type">
                        <option value="all">All Types</option>
                        <?php while ($type = $types_result->fetch_assoc()): ?>
                            <option value="<?php echo $type['type_name']; ?>"><?php echo $type['type_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <button class="add-investment-btn">
                <i class="fas fa-plus"></i> Add New Investment
            </button>
        </section>

        <div class="content-container">
            <section class="investment-list">
                <h2>Investment List</h2>
                <p>Showing <?php echo $investments_result->num_rows; ?> investments</p>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Returns (%)</th>
                            <th>Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($investments_result->num_rows > 0): ?>
                            <?php while ($row = $investments_result->fetch_assoc()): ?>
                                <?php
                                    $investment_return_rate = (($row['current_value'] - $row['amount']) / $row['amount']) * 100;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                    <td><?php echo date('n/j/Y', strtotime($row['purchase_date'])); ?></td>
                                    <td>₹<?php echo number_format($row['amount'], 0); ?></td>
                                    <td><?php echo number_format($investment_return_rate, 1); ?>%</td>
                                    <td>₹<?php echo number_format($row['current_value'], 0); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-pen"></i></button>
                                        <button class="delete-btn" data-id="<?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">No investments found. Add your first investment using the button above.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="investment-distribution">
                <h2>Investment Distribution</h2>
                <p>By investment type</p>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </section>
        </div>
    </main>

    <!-- Modal for adding new investment -->
    <div id="investment-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modal-title">Add New Investment</h2>
            <form id="investment-form" action="process_investment.php" method="POST">
                <input type="hidden" id="investment_id" name="investment_id">
                <div class="form-group">
                    <label for="name">Investment Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="type">Investment Type</label>
                    <select id="type" name="type_id" required>
                        <option value="">Select Type</option>
                        <?php 
                        // Reset the pointer to the beginning of the types result
                        $types_result->data_seek(0);
                        while ($type = $types_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo $type['type_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Purchase Date</label>
                    <input type="date" id="date" name="purchase_date" required>
                </div>
                <div class="form-group">
                    <label for="amount">Investment Amount</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="returns">Expected Returns (%)</label>
                    <input type="number" id="returns" name="expected_return" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="current_value">Current Value</label>
                    <input type="number" id="current_value" name="current_value" step="0.01" min="0" required>
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn">Cancel</button>
                    <button type="submit" class="submit-btn">Add Investment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
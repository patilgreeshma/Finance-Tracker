<?php
include 'dbconfig.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 'On');
// Ensure user_id is retrieved and sanitized
$user_id = intval($_SESSION['user_id']);
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Handle Add
if (isset($_POST['add'])) {
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $interest = $_POST['interest'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("INSERT INTO debts (user_id,type, amount, interest, due_date, status) VALUES (?,?, ?, ?, ?, ?)");
    $stmt->bind_param("isddss", $user_id,$type, $amount, $interest, $due_date, $status);
    $stmt->execute();
    header("Location: debt.php");
    exit;
}

// Handle Edit
if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $interest = $_POST['interest'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE debts SET type=?, amount=?, interest=?, due_date=?, status=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sddssii", $type, $amount, $interest, $due_date, $status, $id,$user_id);
    $stmt->execute();
    header("Location: debt.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM debts WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debt Management</title>
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
        <a href="savings.php">Savings</a>
        <a href="investments.php">Investments</a>
        <a href="#">Debts</a>
        <a href="reports.php">Reports</a>
        <a href="news.php">News</a>
    </nav>
   
    <button class="theme-toggle"><i class="fas fa-moon"></i></button>
</header>

    <main>
        <section class="header">
            <h1>Debt Management</h1>
            <p>Track and manage your outstanding debts.</p>
        </section>
        <section class="stats">
        <?php
            $stmt = $conn->prepare("SELECT SUM(amount) as total, SUM(amount * interest / 100) as interest FROM debts WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $total = $row['total'] ?? 0;
            $interest = $row['interest'] ?? 0;
            $avg_rate = $total > 0 ? ($interest / $total) * 100 : 0;

            $stmt = $conn->prepare("SELECT COUNT(*) as c FROM debts WHERE due_date < CURDATE() AND user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $overdue_result = $stmt->get_result();
            $overdue = $overdue_result->fetch_assoc()['c'];
            ?>
            <div class="stat">
                <div>Total Debt</div>
                <div class="stat-value1">₹<?= number_format($total,0) ?></div>
            </div>
            <div class="stat">
                <div>Annual Interest Cost</div>
                <div class="stat-value1">₹<?= number_format($interest,0) ?></div>
            </div>
            <div class="stat">
                <div>Avg. Interest Rate</div>
                <div class="stat-value1"><?= number_format($avg_rate,2) ?>%</div>
            </div>
            <div class="stat">
                <div>Overdue</div>
                <div class="stat-value1"><?= $overdue ?></div>
            </div>
        </section>
        <section class="debt-section">
            <div class="debt-list">
                <div class="debt-list-header">
                    <input type="text" id="searchInput" placeholder="Search debts...">
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <?php
                        $stmt = $conn->prepare("SELECT DISTINCT type FROM debts WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $types_result = $stmt->get_result();
                        while ($t = $types_result->fetch_assoc()) {
                            echo "<option value=\"{$t['type']}\">{$t['type']}</option>";
                        }
                        ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Upcoming">Upcoming</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                    <button id="showAddForm" class="add-btn">+ Add New Debt</button>
                </div>
                <h2>Debt List</h2>
                <div class="debt-list-subtitle">Showing 
                    <span id="debtCount">
                    <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM debts WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $count_result = $stmt->get_result();
                        $count = $count_result->fetch_assoc()['c'];
                        echo $count;
                        ?>
                    </span> debts
                </div>
                <table id="debtTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Interest</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        $stmt = $conn->prepare("SELECT * FROM debts WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $debts_result = $stmt->get_result();
                        while ($d = $debts_result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($d['type']) ?></td>
                            <td>₹<?= number_format($d['amount'],0) ?></td>
                            <td><?= $d['interest'] ?>%</td>
                            <td><?= date('n/j/Y', strtotime($d['due_date'])) ?></td>
                            <td><?= $d['status'] ?></td>
                            <td>
                                <button class="edit-btn" onclick='editDebt(<?= json_encode($d) ?>)'>✎</button>
                                <a href="?delete=<?= $d['id'] ?>" onclick="return confirm('Delete this debt?')" class="delete-btn">🗑️</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="debt-distribution">
                <h2>Debt Distribution</h2>
                <small>By debt type</small>
                <canvas id="debtChart"></canvas>
            </div>
        </section>
    </main>
    <!-- Modal Add/Edit Form -->
    <div id="modalForm" class="modal">
      <div class="modal-content">
        <span class="close" onclick="hideForm()">&times;</span>
        <form method="post" id="debtFormElem">
            <input type="hidden" name="id" id="debtId">
            <!-- Type as dropdown -->
            <select name="type" id="debtType" required>
                <option value="">Select Type</option>
                <option value="Student Loan">Student Loan</option>
                <option value="Mortgage">Mortgage</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Personal Loan">Personal Loan</option>
                <option value="Auto Loan">Auto Loan</option>
            </select>
            <input type="number" name="amount" id="debtAmount" placeholder="Amount" required>
            <input type="number" step="0.01" name="interest" id="debtInterest" placeholder="Interest (%)" required>
            <input type="date" name="due_date" id="debtDueDate" required>
            <select name="status" id="debtStatus" required>
                <option value="Upcoming">Upcoming</option>
                <option value="Overdue">Overdue</option>
            </select>
            <button type="submit" id="saveBtn" name="add">Save</button>
            <button type="button" onclick="hideForm()">Cancel</button>
        </form>
      </div>
    </div>
    <script>
        // Pie chart data from PHP
        const chartData = <?php
            $labels = [];
            $data = [];
            $colors = ['#36A2EB', '#4BC0C0', '#FF6384', '#FFCE56'];
            $stmt = $conn->prepare("SELECT type, SUM(amount) as amt FROM debts WHERE user_id = ? GROUP BY type");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) {
                $labels[] = $row['type'];
                $data[] = $row['amt'];
            }
            echo json_encode(['labels'=>$labels,'data'=>$data,'colors'=>$colors]);
        ?>;
        window.onload = function() {
            const ctx = document.getElementById('debtChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: true }
                    }
                }
            });
        };

        // Modal Add/Edit Form
        document.getElementById('showAddForm').onclick = function() {
            showForm();
        };
        function showForm(edit=false) {
            document.getElementById('modalForm').style.display = 'block';
            document.getElementById('debtFormElem').reset();
            document.getElementById('saveBtn').name = edit ? "edit" : "add";
        }
        function hideForm() {
            document.getElementById('modalForm').style.display = 'none';
        }
        // Edit debt
        function editDebt(debt) {
            showForm(true);
            document.getElementById('debtId').value = debt.id;
            document.getElementById('debtType').value = debt.type;
            document.getElementById('debtAmount').value = debt.amount;
            document.getElementById('debtInterest').value = debt.interest;
            document.getElementById('debtDueDate').value = debt.due_date;
            document.getElementById('debtStatus').value = debt.status;
            document.getElementById('saveBtn').name = "edit";
        }

        // Search & Filter
        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');
        const statusFilter = document.getElementById('statusFilter');
        searchInput.addEventListener('keyup', filterTable);
        typeFilter.addEventListener('change', filterTable);
        statusFilter.addEventListener('change', filterTable);

        function filterTable() {
            const filter = searchInput.value.toLowerCase();
            const typeVal = typeFilter.value;
            const statusVal = statusFilter.value;
            const table = document.getElementById('debtTable');
            const trs = table.getElementsByTagName('tr');
            let count = 0;
            for (let i = 1; i < trs.length; i++) {
                const tds = trs[i].getElementsByTagName('td');
                if (!tds.length) continue;
                const rowText = trs[i].innerText.toLowerCase();
                const typeMatch = !typeVal || tds[0].innerText === typeVal;
                const statusMatch = !statusVal || tds[4].innerText === statusVal;
                const searchMatch = !filter || rowText.indexOf(filter) > -1;
                if (typeMatch && statusMatch && searchMatch) {
                    trs[i].style.display = '';
                    count++;
                } else {
                    trs[i].style.display = 'none';
                }
            }
            document.getElementById('debtCount').textContent = count;
        }
    </script>
</body>
</html>
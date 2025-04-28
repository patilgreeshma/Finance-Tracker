<?php
require_once 'dbconfig.php';
session_start();

// Enhanced error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Validate session
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if (!$user_id) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT t.type_name, 
            SUM(i.amount) as total_invested, 
            SUM(i.current_value - i.amount) as total_returns
            FROM investments i
            JOIN investment_types t ON i.type_id = t.id
            WHERE i.user_id = ?
            GROUP BY t.type_name
            ORDER BY t.type_name";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $conn->error);
    }

    $labels = [];
    $invested = [];
    $returns = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['type_name'];
        $invested[] = $row['total_invested'];
        $returns[] = $row['total_returns'];
    }

    if (empty($labels)) {
        echo json_encode(['warning' => 'No investment data found']);
        exit();
    }

    echo json_encode([
        'labels' => $labels,
        'invested' => $invested,
        'returns' => $returns
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
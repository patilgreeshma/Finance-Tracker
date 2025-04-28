<?php
require_once 'dbconfig.php';
requireLogin();

$conn = connectDB();
$user_id = $_SESSION['user_id'];

// Check if id is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Get investment details
    $sql = "SELECT * FROM investments WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $investment = $result->fetch_assoc();
        
        // Convert date format for HTML date input
        $investment['purchase_date'] = date('Y-m-d', strtotime($investment['purchase_date']));
        
        header('Content-Type: application/json');
        echo json_encode($investment);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Investment not found']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No investment ID provided']);
}
?
<?php
require_once 'dbconfig.php';


session_start();
error_reporting(E_ALL); // Enable error reporting for debugging
ini_set('display_errors', 1); // Show errors on the page
$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if id is provided
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        
        // Check if the investment belongs to the user
        $check_sql = "SELECT id FROM investments WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Delete the investment
            $sql = "DELETE FROM investments WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $user_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Investment deleted successfully";
            } else {
                $response['message'] = "Error deleting investment: " . $conn->error;
            }
        } else {
            $response['message'] = "Investment not found or you don't have permission to delete it";
        }
    } else {
        $response['message'] = "No investment ID provided";
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
<?php
// Include necessary files
require_once "dbconfig.php";
require_once "functions.php";

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 1;

// Define response array for AJAX
$response = ['success' => false, 'errors' => [], 'data' => []];

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Get ID from hidden input
    $id = intval(trim($_POST["id"] ?? 0));
    
    // Validate and sanitize inputs
    $name = trim($_POST["name"] ?? '');
    $type_id = intval(trim($_POST["type"] ?? 0));
    $purchase_date = trim($_POST["date"] ?? '');
    $amount = floatval(trim($_POST["amount"] ?? 0));
    $expected_return = !empty($_POST["returns"]) ? floatval(trim($_POST["returns"])) : 0;
    
    // Calculate current value if not provided
    if (!empty($_POST["current_value"])) {
        $current_value = floatval(trim($_POST["current_value"]));
    } else {
        $current_value = $amount * (1 + ($expected_return/100));
    }
    
    // Validate input
    $errors = validateInvestmentInput($name, $type_id, $purchase_date, $amount);
    
    if(empty($errors)){
        // Update the investment
        if(updateInvestment($id, $user_id, $name, $type_id, $purchase_date, $amount, $expected_return, $current_value)) {
            $response['success'] = true;
            
            // Redirect if not AJAX
            if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
                header("location: investments.php");
                exit();
            }
        } else {
            $response['errors']['general'] = "Error updating investment: " . mysqli_error($conn);
        }
    } else {
        $response['errors'] = $errors;
    }
}

// Handle GET request for getting investment data
if($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["id"]) && !empty($_GET["id"])){
    $id = intval($_GET["id"]);
    $investment = getInvestmentById($id, $user_id);
    
    if($investment) {
        $response['success'] = true;
        $response['data'] = $investment;
    } else {
        $response['errors']['id'] = "Investment not found";
    }
}

// Return JSON for AJAX requests
if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Function to validate input
function validateInvestmentInput($name, $type_id, $date, $amount) {
    $errors = [];
    
    if(empty($name)) {
        $errors['name'] = "Please enter the investment name";
    }
    
    if(empty($type_id)) {
        $errors['type'] = "Please select an investment type";
    }
    
    if(empty($date)) {
        $errors['date'] = "Please select a date";
    }
    
    if(empty($amount) || $amount <= 0) {
        $errors['amount'] = "Please enter a valid positive amount";
    }
    
    return $errors;
}
?>

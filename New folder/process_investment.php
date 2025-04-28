<?php
require_once 'dbconfig.php';

session_start();

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// For debugging: uncomment these lines to see errors during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type_id = mysqli_real_escape_string($conn, $_POST['type_id']);
    $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $expected_return = mysqli_real_escape_string($conn, $_POST['expected_return']);
    $current_value = mysqli_real_escape_string($conn, $_POST['current_value']);
    

    // Check if it's an update or insert
    if (isset($_POST['investment_id']) && !empty($_POST['investment_id'])) {
        // Update existing investment
        $id = mysqli_real_escape_string($conn, $_POST['investment_id']);

        // Check if the investment belongs to the user
        $check_sql = "SELECT id FROM investments WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update the investment
            $sql = "UPDATE investments SET name = ?, type_id = ?, purchase_date = ?, 
                    amount = ?, expected_return = ?, current_value = ? 
                    WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisdddii", $name, $type_id, $purchase_date, $amount, 
                              $expected_return, $current_value, $id, $user_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Investment updated successfully";
            } else {
                $response['message'] = "Error updating investment: " . $conn->error;
            }
        } else {
            $response['message'] = "Investment not found or you don't have permission to edit it";
        }
    } else {
        // Insert new investment
        $sql = "INSERT INTO investments (user_id, name, type_id, purchase_date, amount, 
                expected_return, current_value) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // Fix: 7 parameters, so type string is 'isisddd'
        $stmt->bind_param("isisddd", $user_id, $name, $type_id, $purchase_date, 
                          $amount, $expected_return, $current_value);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Investment added successfully";
        } else {
            $response['message'] = "Error adding investment: " . $conn->error;
        }
    }

    // If it's an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Otherwise, redirect back to the investments page (fix typo)
    if ($response['success']) {
        $_SESSION['flash_message'] = $response['message'];
        header("Location: investments.php");
        exit;
    } else {
        $_SESSION['flash_error'] = $response['message'];
        header("Location: investments.php");
        exit;
    }
}

// If not POST request, redirect to investments page
header("Location: investments.php");
exit;
?>
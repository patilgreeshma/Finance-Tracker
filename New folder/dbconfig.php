<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "", "investment_tracker");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>

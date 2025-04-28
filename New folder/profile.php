<?php
session_start();
require_once 'dbconfig.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div style="max-width:400px;margin:40px auto;padding:24px;background:#fff;border-radius:10px;box-shadow:0 2px 12px #0001;">
        <h2>My Profile</h2>
        <img src="uploads/<?= htmlspecialchars($user['profile_pic'] ?? 'default_profile.png') ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'Not set') ?></p>
        <a href="logout.php" style="display:inline-block;margin-top:20px;color:#fff;background:#ff7a59;padding:8px 20px;border-radius:5px;text-decoration:none;">Logout</a>
    </div>
</body>
</html>

<?php
session_start();
require 'db.php'; // make sure this file connects $conn

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch admin name from DB
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$admin_name = $admin['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Admin
    </div>
    <div class="logout">
  <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

  </div>

  <div class="container">
    <div class="sidebar">
      <ul>
        <li><a href="admin_dashboard.php" class="active">🏠 Dashboard</a></li>
        <li><a href="admin_users.php">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php">💳 View Payments</a></li>
        <li><a href="admin_reports.php">📊 Reports</a></li>
        <li><a href="admin_notices.php"> 🔔 Manage Notices</a></li>
        <li><a href="admin_ads.php"> 📢 Ads Manager</a></li>
      </ul>
    </div>

    <div class="content">
      <h2>Welcome, <?= htmlspecialchars($admin_name) ?> 👋</h2>
      <p>This is your Admin dashboard. Use the sidebar to navigate features.</p>
    </div>
  </div>
</body>
</html>

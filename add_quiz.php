<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $conn->query("INSERT INTO quizzes (title, subject, created_at) VALUES ('$title', '$subject', NOW())");
    header('Location: admin_quizzes.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Quiz - Admin</title>
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
        <li><a href="admin_dashboard.php">🏠 Dashboard</a></li>
        <li><a href="admin_users.php">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php" class="active">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php">💳 View Payments</a></li>
        <li><a href="admin_reports.php">📊 Reports</a></li>
        <li><a href="admin_notices.php">📢 Manage Notices</a></li>
      </ul>
    </div>

    <div class="content">
      <h2>➕ Add New Quiz</h2>
      <form method="POST">
        <label>Quiz Title:</label>
        <input type="text" name="title" required>

        <label>Subject:</label>
        <input type="text" name="subject" required>

        <button type="submit">Add Quiz</button>
      </form>
    </div>
  </div>
</body>
</html>

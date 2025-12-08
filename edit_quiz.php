<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$quiz = $conn->query("SELECT * FROM quizzes WHERE id = $id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $conn->query("UPDATE quizzes SET title='$title', subject='$subject' WHERE id=$id");
    header('Location: admin_quizzes.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Quiz - Admin</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Admin
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
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
      <h2>✏️ Edit Quiz</h2>
      <form method="POST">
        <label>Quiz Title:</label>
        <input type="text" name="title" value="<?= htmlspecialchars($quiz['title']) ?>" required>

        <label>Subject:</label>
        <input type="text" name="subject" value="<?= htmlspecialchars($quiz['subject']) ?>" required>

        <button type="submit">Update Quiz</button>
      </form>
    </div>
  </div>
</body>
</html>

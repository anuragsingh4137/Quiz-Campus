<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $created_by = $_SESSION['user_id']; // admin id

    $conn->query("
        INSERT INTO quizzes (title, subject, created_by, created_at)
        VALUES ('$title', '$subject', $created_by, NOW())
    ");

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
        <li>
  <a href="admin_dashboard.php">
    <i class="fa-solid fa-house"></i> Dashboard
  </a>
</li>

<li>
  <a href="admin_users.php">
    <i class="fa-solid fa-users"></i> Manage Users
  </a>
</li>

<li>
  <a href="admin_quizzes.php" class="active">
    <i class="fa-solid fa-file-lines"></i> Manage Quizzes
  </a>
</li>

<li>
  <a href="admin_payments.php">
    <i class="fa-solid fa-credit-card"></i> View Payments
  </a>
</li>

<li>
  <a href="admin_reports.php">
    <i class="fa-solid fa-chart-column"></i> Reports
  </a>
</li>

<li>
  <a href="admin_notices.php">
    <i class="fa-solid fa-bell"></i> Manage Notices
  </a>
</li>

<li>
  <a href="admin_ads.php">
    <i class="fa-solid fa-bullhorn"></i> Ads Manager
  </a>
</li>

      </ul>
    </div>

    <div class="content">
      <h2>
  <i class="fa-solid fa-circle-plus"></i> Add New Quiz 
</h2>
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

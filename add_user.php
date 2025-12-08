<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role  = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);

    if ($stmt->execute()) {
        header("Location: admin_users.php");
        exit;
    } else {
        $error = "Error adding user!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add User - Admin</title>
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
        <li><a href="admin_users.php" class="active">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php">💳 View Payments</a></li>
        <li><a href="admin_reports.php">📊 Reports</a></li>
        <li><a href="admin_notices.php">📢 Manage Notices</a></li>
      </ul>
    </div>

    <div class="content">
      <h2>➕ Add New User</h2>
      <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
      <form method="POST">
        <label>Name:</label>
        <input type="text" name="name" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <label>Role:</label>
        <select name="role" required>
          <option value="student">Student</option>
          <option value="teacher">Teacher</option>
          <option value="admin">Admin</option>
        </select>

        <button type="submit">Add User</button>
      </form>
    </div>
  </div>
</body>
</html>

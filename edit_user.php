<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$result = $conn->query("SELECT * FROM users WHERE id = $id");
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $role  = $_POST['role'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET name='$name', email='$email', role='$role', password='$password' WHERE id=$id");
    } else {
        $conn->query("UPDATE users SET name='$name', email='$email', role='$role' WHERE id=$id");
    }

    header("Location: admin_users.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit User - Admin</title>
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
        <li><a href="admin_users.php" class="active">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php">💳 View Payments</a></li>
        <li><a href="admin_reports.php">📊 Reports</a></li>
        <li><a href="admin_notices.php">📢 Manage Notices</a></li>
      </ul>
    </div>

    <div class="content">
      <h2>✏️ Edit User</h2>
      <form method="POST">
        <label>Name:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Role:</label>
        <select name="role" required>
          <option value="student" <?= $user['role']=='student'?'selected':'' ?>>Student</option>
          <option value="teacher" <?= $user['role']=='teacher'?'selected':'' ?>>Teacher</option>
          <option value="admin" <?= $user['role']=='admin'?'selected':'' ?>>Admin</option>
        </select>

        <label>New Password (leave blank to keep current):</label>
        <input type="password" name="password">

        <button type="submit">Save Changes</button>
      </form>
    </div>
  </div>
</body>
</html>

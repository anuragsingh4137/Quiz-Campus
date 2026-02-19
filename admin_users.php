<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';

// Handle delete action (safe transactional delete)
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);

    // don't allow deleting yourself accidentally
    if ($id === intval($_SESSION['user_id'])) {
        $message = "You cannot delete your own admin account.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // 1) Delete dependent rows that reference this user.
            // Adjust table/column names if your schema uses different names.
            // quiz_answers -> student_id
            $stmt = $conn->prepare("DELETE FROM quiz_answers WHERE student_id = ?");
            if (!$stmt) throw new Exception("Prepare failed (quiz_answers): " . $conn->error);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // quiz_attempts -> student_id
            $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE student_id = ?");
            if (!$stmt) throw new Exception("Prepare failed (quiz_attempts): " . $conn->error);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // results -> user_id
            $stmt = $conn->prepare("DELETE FROM results WHERE user_id = ?");
            if (!$stmt) throw new Exception("Prepare failed (results): " . $conn->error);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // payments (if you have a payments table)
            if ($conn->query("SHOW TABLES LIKE 'payments'")->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM payments WHERE user_id = ?");
                if (!$stmt) throw new Exception("Prepare failed (payments): " . $conn->error);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            }

            // any other dependent tables: add here as needed
            // e.g. notifications, subscriptions, etc.

            // 2) Finally delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed (users): " . $conn->error);
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                // user not found
                $stmt->close();
                throw new Exception("User not found or already deleted.");
            }
            $stmt->close();

            // commit
            $conn->commit();
            // redirect to make UI friendly
            header("Location: admin_users.php?msg=deleted");
            exit;
        } catch (Exception $e) {
            // rollback and surface friendly error
            $conn->rollback();
            // log actual error for admin debugging (error_log)
            error_log("Failed to delete user id {$id}: " . $e->getMessage());
            $message = "Could not delete user (there are linked records or an internal error).";
        }
    }
}

// Fetch all users
$result = $conn->query("SELECT id, name, email, role FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manage Users - Admin</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    .content h2 { margin-bottom: 20px; }
    .msg { padding:10px 14px; border-radius:8px; margin-bottom:12px; }
    .msg.success { background:#ecfdf5; color:#065f46; border:1px solid #34d399; }
    .msg.error { background:#fff1f2; color:#991b1b; border:1px solid #fecaca; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 10px; border: 1px solid #e5e7eb; text-align: left; }
    th { background: #2563eb; color: white; }
    tr:nth-child(even) { background: #f9fafb; }
    .btn { padding: 6px 12px; border-radius: 5px; font-size: 13px; text-decoration: none; font-weight: 500; color: white; margin-right: 5px; display:inline-block; }
    .btn-edit { background: #2563eb; }
    .btn-delete { background: #dc2626; }
  </style>
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
                <li>
  <a href="admin_dashboard.php">
    <i class="fa-solid fa-house"></i> Dashboard
  </a>
</li>

<li>
  <a href="admin_users.php" class="active">
    <i class="fa-solid fa-users"></i> Manage Users
  </a>
</li>

<li>
  <a href="admin_quizzes.php">
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
      <h2 style="display:flex;justify-content:space-between;align-items:center;">
  
  <span>
    <i class="fa-solid fa-users"></i> Manage Users
  </span>

  <a href="add_user.php" class="btn btn-edit">
    <i class="fa-solid fa-user-plus"></i> Add New User
  </a>

</h2>


      <?php
      if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
        echo '<div class="msg success">User deleted successfully (and related records removed).</div>';
      }
      if (!empty($message)) {
        echo '<div class="msg error">' . htmlspecialchars($message) . '</div>';
      }
      ?>

      <table>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Action</th>
        </tr>
        <?php while ($user = $result->fetch_assoc()) { ?>
        <tr>
          <td><?= (int)$user['id'] ?></td>
          <td><?= htmlspecialchars($user['name']) ?></td>
          <td><?= htmlspecialchars($user['email']) ?></td>
          <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
          <td>
            <a href="edit_user.php?id=<?= (int)$user['id'] ?>" class="btn btn-edit">Edit</a>
            <a href="admin_users.php?delete_id=<?= (int)$user['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this user and all their related data? This action cannot be undone.')">Delete</a>
          </td>
        </tr>
        <?php } ?>
      </table>
    </div>
  </div>
</body>
</html>

<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$msg = '';
$error = '';

// Safe delete quiz (transactional)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // start transaction
    $conn->begin_transaction();
    try {
        // 1) delete quiz answers (student answers)
        $stmt = $conn->prepare("DELETE FROM quiz_answers WHERE quiz_id = ?");
        if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }

        // 2) delete quiz attempts
        $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
        if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }

        // 3) delete results (if results table references quiz_id)
        $stmt = $conn->prepare("DELETE FROM results WHERE quiz_id = ?");
        if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }

        // 4) delete questions for that quiz (so no orphan questions remain)
        $stmt = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?");
        if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }

        // 5) (optional) delete any other dependent records - add if you have them:
        // e.g. $stmt = $conn->prepare("DELETE FROM notifications WHERE quiz_id = ?");
        // if ($stmt) { $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close(); }

        // 6) finally delete quiz
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for quizzes delete: " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            // If no rows affected, quiz may not exist
            throw new Exception("Quiz not found or already deleted.");
        }
        $stmt->close();

        // commit
        $conn->commit();
        $msg = "Quiz and related data deleted successfully.";
        header("Location: admin_quizzes.php?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        // rollback and show friendly error
        $conn->rollback();
        error_log("Delete quiz failed (id=$id): " . $e->getMessage());
        $error = "Could not delete quiz: " . htmlspecialchars($e->getMessage()) .
                 ". This may be because other database records reference this quiz. " .
                 "You can either remove those dependent records first or modify foreign keys to use ON DELETE CASCADE.";
    }
}

// Fetch quizzes
$result = $conn->query("SELECT * FROM quizzes ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Quizzes - Admin</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 12px;
      border: 1px solid #e5e7eb;
      text-align: left;
    }
    th {
      background: #2563eb;
      color: white;
    }
    tr:nth-child(even) {
      background: #f9fafb;
    }
    tr:hover {
      background: #eef2ff;
    }
    .btn {
      padding: 8px 14px;
      border-radius: 6px;
      text-decoration: none;
      color: white;
      margin-right: 5px;
    }
    .btn-edit { background: #2563eb; }
    .btn-delete { background: #dc2626; }
    .btn-add {
      display: inline-block;
      background: #2563eb;
      color: white;
      padding: 10px 18px;
      border-radius: 6px;
      text-decoration: none;
      margin-bottom: 10px;
    }
    .message { padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; display: inline-block; }
    .message.success { background:#ecfdf5; color:#065f46; border:1px solid #34d399; }
    .message.error { background:#fff5f5; color:#b91c1c; border:1px solid #fca5a5; }
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
      <h2 style="display:flex;justify-content:space-between;align-items:center;">
  
  <span>
    <i class="fa-solid fa-file-lines"></i> Manage Quizzes
  </span>

  <a href="add_quiz.php" class="btn-add">
    <i class="fa-solid fa-circle-plus"></i> Add New Quiz
  </a>

</h2>


      <?php if (!empty($_GET['msg'])): ?>
        <div class="message success"><?= htmlspecialchars($_GET['msg']) ?></div>
      <?php endif; ?>

      <?php if ($msg): ?>
        <div class="message success"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="message error"><?= $error ?></div>
      <?php endif; ?>

      <table>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Subject</th>
          <th>Created On</th>
          <th>Action</th>
        </tr>

        <?php while ($quiz = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $quiz['id'] ?></td>
            <td><?= htmlspecialchars($quiz['title']) ?></td>
            <td><?= htmlspecialchars($quiz['subject']) ?></td>
            <td><?= htmlspecialchars($quiz['created_at']) ?></td>
            <td>
              <a href="edit_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-edit">Edit</a>
              <a href="?delete=<?= $quiz['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete this quiz? This will remove its questions, attempts and results.')">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>
</body>
</html>

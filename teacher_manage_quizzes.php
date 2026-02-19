<?php
session_start();
require 'db.php';

// ✅ Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];

// ✅ Fetch quizzes created by this teacher
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE created_by = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage My Quizzes</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>
  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Teacher
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
        <li><a href="teacher_dashboard.php">
  <i class="fa-solid fa-house"></i> Dashboard
</a></li>

<li><a href="teacher_create_quiz.php">
  <i class="fa-solid fa-pen-to-square"></i> Create Quiz
</a></li>

<li><a href="teacher_add_questions.php">
  <i class="fa-solid fa-circle-plus"></i> Add Questions
</a></li>

<li><a href="teacher_bulk_upload.php">
  <i class="fa-solid fa-file-csv"></i> Bulk Upload (CSV)
</a></li>

<li><a href="teacher_manage_quizzes.php" class="active">
  <i class="fa-solid fa-list-check"></i> Manage My Quizzes
</a></li>

<li><a href="teacher_view_results.php">
  <i class="fa-solid fa-chart-line"></i> View Results
</a></li>

<li><a href="teacher_profile.php">
  <i class="fa-solid fa-user"></i> Profile
</a></li>

      </ul>
    </div>

    <!-- Main Content -->
    <div class="content">
      <h2>
  <i class="fa-solid fa-list-check"></i> My Quizzes
</h2>


      <?php if ($result->num_rows > 0): ?>
        <table>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Description</th>
            <th>Premium</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><?= htmlspecialchars($row['description']) ?></td>
              <td><?= $row['is_premium'] ? "✅ Yes" : "❌ No" ?></td>
              <td><?= $row['created_at'] ?></td>
              <td>
                <a class="btn btn-primary" href="teacher_add_questions.php?quiz_id=<?= $row['id'] ?>">Add Questions</a>
                <a class="btn btn-secondary" href="teacher_edit_quiz.php?id=<?= $row['id'] ?>">Edit</a>
                <a class="btn btn-danger" href="teacher_delete_quiz.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      <?php else: ?>
        <p>No quizzes found. <a href="teacher_create_quiz.php">Create one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

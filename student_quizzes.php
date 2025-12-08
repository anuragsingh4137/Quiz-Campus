<?php
session_start();
require 'config.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch available (free) quizzes
$sql = "SELECT * FROM quizzes WHERE is_premium = 0 ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Available Quizzes - Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css">

  <style>
    /* Blue TAKE QUIZ style button */
    .btn-start-quiz {
        background: #2563eb;      /* Same blue as Take a Quiz */
        color: #ffffff !important;
        padding: 10px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s ease;
    }
    .btn-start-quiz:hover {
        background: #1e4fcc;
        text-decoration: none;
        transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo" style="width:40px;height:40px;">
      Quiz Campus - Student
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
        <li><a href="student_dashboard.php">🏠Dashboard</a></li>
        <li><a href="student_quizzes.php" class="active">📝Available Quizzes</a></li>
        <li><a href="student_premium.php">⭐Premium Mock Tests</a></li>
        <li><a href="student_my_results.php">📊My Results</a></li>
        <li><a href="student_profile.php">👤Profile</a></li>
      </ul>
    </div>

    <!-- Main content -->
    <div class="content">
      <div class="quiz-container">
        <h2>🧩 Available Quizzes</h2>

        <?php if ($result && $result->num_rows > 0): ?>
          <table>
            <thead>
              <tr>
                <th>Quiz Title</th>
                <th>Description</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['title']) ?></td>
                  <td><?= htmlspecialchars($row['description']) ?></td>
                  <td>
                    <a href="student_take_quiz.php?quiz_id=<?= $row['id'] ?>" 
                       class="btn-start-quiz"> Start Quiz</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-quizzes">
            🚫 No quizzes available at the moment. Please check back later.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>

<?php
session_start();
require 'config.php';

// Check if the user is logged in as a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];

// Fetch teacher profile details
$stmt = $conn->prepare("SELECT name, email, education_level, gender FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch total quizzes (Free + Premium) created by the teacher
$quiz_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN is_premium = 0 THEN 1 ELSE 0 END) AS free_quiz,
        SUM(CASE WHEN is_premium = 1 THEN 1 ELSE 0 END) AS premium_quiz
    FROM quizzes
    WHERE created_by = ?");
$quiz_stmt->bind_param("i", $teacher_id);
$quiz_stmt->execute();
$quiz_data = $quiz_stmt->get_result()->fetch_assoc();
$free_quiz = $quiz_data['free_quiz'] ?? 0;
$premium_quiz = $quiz_data['premium_quiz'] ?? 0;

// Fetch total quiz attempts by students
$attempt_stmt = $conn->prepare("
    SELECT COUNT(*) AS total_attempts 
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    WHERE q.created_by = ?");
$attempt_stmt->bind_param("i", $teacher_id);
$attempt_stmt->execute();
$attempts_data = $attempt_stmt->get_result()->fetch_assoc();
$total_attempts = $attempts_data['total_attempts'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Dashboard - Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .dashboard-header { margin-bottom: 20px; }
    .cards { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
    .card {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 20px;
      flex: 1;
      min-width: 250px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.05);
      transition: transform 0.2s ease;
    }
    .card:hover { transform: translateY(-4px); }
    .card h3 { margin: 0; color: #2563eb; }
    .card p { margin: 10px 0 0; font-size: 15px; color: #374151; }
    .quick-links { margin-top: 30px; }
    .quick-links a {
      display: inline-block;
      background: #2563eb;
      color: white;
      padding: 10px 18px;
      border-radius: 6px;
      text-decoration: none;
      margin: 8px;
      font-size: 14px;
      transition: background 0.3s ease;
    }
    .quick-links a:hover { background: #1e40af; }

    /* ✅ Notice Board Styling (matching student UI) */
    .notice-board {
      background: #ffffff;
      border: 2px solid #2563eb;
      border-radius: 12px;
      padding: 25px 35px;
      margin-top: 30px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      max-width: 800px;
      width: 100%;
      animation: fadeSlideIn 0.6s ease;
    }
    .notice-board h2 {
      color: #1e3a8a;
      margin-bottom: 15px;
      font-size: 22px;
      font-weight: 700;
      text-align: center;
    }
    .notice-board ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .notice-board li {
      background: #eff6ff;
      margin-bottom: 12px;
      padding: 12px 15px;
      border-radius: 8px;
      border-left: 5px solid #2563eb;
      font-size: 15px;
      color: #374151;
      transition: background 0.3s ease, transform 0.2s ease;
    }
    .notice-board li:hover {
      background: #dbeafe;
      transform: translateX(6px);
    }
    @keyframes fadeSlideIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Teacher
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <div class="container">
    <div class="sidebar"> 
      <ul>
        <li><a href="teacher_dashboard.php" class="active">🏠 Dashboard</a></li>
        <li><a href="teacher_create_quiz.php">✏️ Create Quiz</a></li>
        <li><a href="teacher_add_questions.php">➕ Add Questions</a></li>
        <li><a href="teacher_bulk_upload.php">📂 Bulk Upload (CSV)</a></li>
        <li><a href="teacher_manage_quizzes.php">🧾 Manage My Quizzes</a></li>
        <li><a href="teacher_view_results.php">📈 View Results</a></li>
        <li><a href="teacher_profile.php">👤 Profile</a></li>
      </ul>
    </div>

    <div class="content">
      <div class="dashboard-header">
        <h2>Welcome, <?= htmlspecialchars($user['name']) ?> 👋</h2>
        <p>Here’s your summary and activity overview.</p>
      </div>

      <!-- Profile Section -->
      <div class="cards">
        <div class="card">
          <h3>👤 Profile</h3>
          <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'Not set') ?></p>
          <p><strong>Education:</strong> <?= htmlspecialchars($user['education_level'] ?? 'Not set') ?></p>
          <p><strong>Gender:</strong> <?= htmlspecialchars($user['gender'] ?? 'Not set') ?></p>
        </div>

        <!-- Total Quizzes Created Section -->
        <div class="card">
          <h3>📝 Total Quizzes Created</h3>
          <p><strong>Free Quizzes:</strong> <?= $free_quiz ?></p>
          <p><strong>Premium Quizzes:</strong> <?= $premium_quiz ?></p>
          <p><strong>Total:</strong> <?= $free_quiz + $premium_quiz ?></p>
        </div>

        <!-- Total Quiz Attempts Section -->
        <div class="card">
          <h3>📊 Total Student Attempts</h3>
          <p><strong><?= $total_attempts ?></strong> attempts on your quizzes</p>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="quick-links">
        <h3>Quick Actions</h3>
        <a href="teacher_create_quiz.php">✏️ Create Quiz</a>
        <a href="teacher_manage_quizzes.php">🧾 Manage Quizzes</a>
        <a href="teacher_view_results.php">📈 View Results</a>
        <a href="teacher_profile.php">👤 Profile</a>
      </div>

      <!-- ✅ Notice / Announcement Section -->
      <div class="notice-board">
        <h2>📢 Announcements & Notices</h2>
        <ul>
          <?php
            $role = $_SESSION['role']; // teacher
            $sql = "SELECT * FROM notices WHERE audience = 'all' OR audience = ? ORDER BY created_at DESC LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo "<li><strong>" . htmlspecialchars($row['title']) . ":</strong> " . htmlspecialchars($row['description']) . "</li>";
              }
            } else {
              echo "<li>No new announcements right now.</li>";
            }
          ?>
        </ul>
      </div>

    </div>
  </div>
</body>
</html>

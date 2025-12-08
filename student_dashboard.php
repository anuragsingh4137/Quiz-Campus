<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Fetch student details
$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT name, email, education_level, gender, is_premium_user FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

// Fetch total quizzes attempted
$total_quizzes_query = $conn->query("SELECT COUNT(*) AS total FROM quiz_attempts WHERE student_id = $user_id");
$total_attempts = $total_quizzes_query->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard - Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .dashboard-header {
      margin-bottom: 20px;
    }
    .cards {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 20px;
    }
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
    .card:hover {
      transform: translateY(-4px);
    }
    .card h3 {
      margin: 0;
      color: #2563eb;
    }
    .card p {
      margin: 10px 0 0;
      font-size: 15px;
      color: #374151;
    }
    .quick-links {
      margin-top: 30px;
    }
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
    .quick-links a:hover {
      background: #1e40af;
    }
    /* Notice board styling */
    .notice-board {
      background: #ffffff;
      border: 2px solid #2563eb;
      border-radius: 12px;
      padding: 25px 35px;
      margin-top: 20px;
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
  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Student
    </div>
   <div class="logout">
  <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

  </div>

  <!-- Container -->
  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
        <li><a href="/quiz-campus/student_dashboard.php"class="active">🏠 Dashboard</a></li>
        <li><a href="/quiz-campus/student_quizzes.php">📝 Available Quizzes</a></li>
        <li><a href="/quiz-campus/student_premium.php">⭐ Premium Mock Tests</a></li>
        <li><a href="/quiz-campus/student_my_results.php">📊 My Results</a></li>
        <li><a href="/quiz-campus/student_profile.php">👤Profile</a></li>
      </ul>
    </div>

    <!-- Main Content -->
    <div class="content">
      <div class="dashboard-header">
        <h2>Welcome, <?= htmlspecialchars($user['name']) ?> 👋</h2>
        <p>Here’s your summary and recent activity.</p>
      </div>

      <!-- Profile Summary Cards -->
      <div class="cards">
        <div class="card">
          <h3>👤 Profile</h3>
          <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
          <p><strong>Education:</strong> <?= htmlspecialchars($user['education_level'] ?? 'Not set') ?></p>
          <p><strong>Gender:</strong> <?= htmlspecialchars($user['gender'] ?? 'Not set') ?></p>
        </div>

        <div class="card">
          <h3>📘 Total Quizzes</h3>
          <p><?= $total_attempts ?> attempted quizzes</p>
        </div>

        <div class="card">
          <h3>⭐ Premium Access</h3>
          <p>
            <?php if ($user['is_premium_user']) {
                echo "✅ You are a Premium Member";
            } else {
                echo "❌ Not a Premium Member";
            } ?>
          </p>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="quick-links">
        <h3>Quick Actions</h3>
        <a href="student_quizzes.php">🎯 Take a Quiz</a>
        <a href="student_my_results.php">📊 View Results</a>
        <a href="student_profile.php">👤 Edit Profile</a>
        <a href="student_premium.php">⭐ Upgrade to Premium</a>
        <a href="leaderboard.php" class="quick-action-btn">🏆 View Leaderboard</a>
      </div>

      <!-- Announcements & Notices -->
      <div class="notice-board">
        <h2>📢 Announcements & Notices</h2>
        <ul>
          <?php
            require 'config.php';
            $role = $_SESSION['role']; // student / teacher / admin

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

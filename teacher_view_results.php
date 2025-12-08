<?php
session_start();
require 'config.php';

// ✅ Ensure only teachers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];

// ✅ Fetch quizzes created by the teacher
$quiz_stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE created_by = ?");
$quiz_stmt->bind_param("i", $teacher_id);
$quiz_stmt->execute();
$quizzes = $quiz_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Quiz Results - Teacher</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .results-container {
      max-width: 900px;
      margin: 30px auto;
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      font-size: 15px;
    }
    th, td {
      padding: 10px 12px;
      border: 1px solid #e5e7eb;
      text-align: left;
    }
    th {
      background: #2563eb;
      color: #fff;
    }
    tr:nth-child(even) { background: #f9fafb; }
    tr:hover { background: #eef2ff; }
    select, button {
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 15px;
      margin-right: 8px;
    }
    button {
      background: #2563eb;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover { background: #1e40af; }
    .no-results {
      background: #f9fafb;
      padding: 15px;
      text-align: center;
      border-radius: 8px;
      color: #374151;
    }
  </style>
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
    <div class="sidebar">
      <ul>
        <li><a href="teacher_dashboard.php">🏠 Dashboard</a></li>
        <li><a href="teacher_create_quiz.php">✏️ Create Quiz</a></li>
        <li><a href="teacher_add_questions.php">➕ Add Questions</a></li>
        <li><a href="teacher_bulk_upload.php">📂 Bulk Upload (CSV)</a></li>
        <li><a href="teacher_manage_quizzes.php">🧾 Manage My Quizzes</a></li>
        <li><a href="teacher_view_results.php" class="active">📈 View Results</a></li>
        <li><a href="teacher_profile.php">👤 Profile</a></li>
      </ul>
    </div>

    <div class="content">
      <h2>📊 View Quiz Results</h2>

      <div class="results-container">
        <form method="GET">
          <label for="quiz_id"><strong>Select a Quiz:</strong></label><br>
          <select name="quiz_id" id="quiz_id" required>
            <option value="">-- Choose Quiz --</option>
            <?php foreach ($quizzes as $quiz): ?>
              <option value="<?= $quiz['id'] ?>" <?= isset($_GET['quiz_id']) && $_GET['quiz_id'] == $quiz['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($quiz['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit">View Results</button>
        </form>

        <?php
        if (isset($_GET['quiz_id']) && $_GET['quiz_id'] !== '') {
            $quiz_id = intval($_GET['quiz_id']);

            // ✅ Fetch results for the selected quiz
            $result_stmt = $conn->prepare("
                SELECT s.name AS student_name, qa.score, qa.total_marks, qa.attempt_date
                FROM quiz_attempts qa
                JOIN users s ON qa.student_id = s.id
                WHERE qa.quiz_id = ?
                ORDER BY qa.attempt_date DESC
            ");
            $result_stmt->bind_param("i", $quiz_id);
            $result_stmt->execute();
            $results = $result_stmt->get_result();

            if ($results->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Student Name</th><th>Score</th><th>Total Marks</th><th>Date</th></tr>";
                while ($row = $results->fetch_assoc()) {
                    echo "<tr>
                            <td>" . htmlspecialchars($row['student_name']) . "</td>
                            <td>" . htmlspecialchars($row['score']) . "</td>
                            <td>" . htmlspecialchars($row['total_marks']) . "</td>
                            <td>" . htmlspecialchars($row['attempt_date']) . "</td>
                          </tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='no-results'>No student has attempted this quiz yet.</div>";
            }
        }
        ?>
      </div>
    </div>
  </div>
</body>
</html>

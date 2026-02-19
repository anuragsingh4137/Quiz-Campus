<?php 
session_start();
require 'db.php'; // Your MySQLi connection (must set $conn)

// Ensure student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Prepare and fetch results for this student
$sql = "SELECT q.id AS quiz_id, q.title AS quiz_title, qa.score, qa.total_questions, qa.attempted_on
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.student_id = ? 
        ORDER BY qa.attempted_on DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
$quiz_titles = [];
$scores = [];
$total_scores = 0;
$total_quizzes = 0;
$highest_score = 0;

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        // Normalize fields and store
        $row['score'] = (int)$row['score'];
        $row['total_questions'] = isset($row['total_questions']) ? (int)$row['total_questions'] : 0;
        $row['attempted_on'] = $row['attempted_on']; // string datetime

        $data[] = $row;
        $quiz_titles[] = htmlspecialchars($row['quiz_title'] . " (" . date('d M', strtotime($row['attempted_on'])) . ")", ENT_QUOTES);
        $scores[] = $row['score'];

        $total_scores += $row['score'];
        $total_quizzes++;
        if ($row['score'] > $highest_score) $highest_score = $row['score'];
    }
}

$average_score = $total_quizzes > 0 ? round($total_scores / $total_quizzes, 2) : 0;

// Build running average (moving average) for trend line
$running_avg = [];
$sum = 0;
for ($i = 0; $i < count($scores); $i++) {
    $sum += $scores[$i];
    $running_avg[] = round($sum / ($i + 1), 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>My Quiz Results - Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    /* Small page-specific overrides to match your current style */
    .summary-cards { display:flex; gap:18px; flex-wrap:wrap; margin-bottom:20px; }
    .card { flex:1; min-width:160px; background:#f3f4f6; padding:16px; border-radius:8px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.03); }
    .card h3 { margin:0; font-size:22px; color:#111827; }
    .card p { margin:6px 0 0; color:#6b7280; }
    .chart-wrap { background:#fff; padding:18px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.03); margin-bottom:24px; }
    .results-table { margin-top:16px; }
    .no-results { padding:18px; background:#fff; border-radius:8px; color:#6b7280; }
    .back-btn { display:inline-block; margin-top:18px; text-decoration:none; color:#2563eb; }
    .view-btn {
  background-color: #2563eb;
  color: #fff;
  padding: 6px 12px;
  border-radius: 6px;
  text-decoration: none;
  transition: 0.2s;
}
.view-btn:hover {
  background-color: #1d4ed8;
}

  </style>
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <li><a href="/quiz-campus/student_dashboard.php">
  <i class="fa-solid fa-house"></i> Dashboard
</a></li>

<li><a href="/quiz-campus/student_quizzes.php">
  <i class="fa-solid fa-file-lines"></i> Available Quizzes
</a></li>

<li><a href="/quiz-campus/student_premium.php">
  <i class="fa-solid fa-star"></i> Premium Mock Tests
</a></li>

<li><a href="/quiz-campus/student_my_results.php"class="active">
  <i class="fa-solid fa-chart-column"></i> My Results
</a></li>

<li><a href="/quiz-campus/student_profile.php">
  <i class="fa-solid fa-user"></i> Profile
</a></li>

      </ul>
    </div>

    <!-- Main content -->
    <div class="content">
      <div class="results-header">
        <h2><i class="fa-solid fa-chart-column"></i> My Quiz Performance </h2>
        <p>Overview of your quiz attempts with charts and detailed results.</p>
      </div>

      <!-- Summary cards -->
      <div class="summary-cards">
        <div class="card">
          <h3><?= $total_quizzes ?></h3>
          <p>Quizzes Attempted</p>
        </div>
        <div class="card">
          <h3><?= $average_score ?></h3>
          <p>Average Score</p>
        </div>
        <div class="card">
          <h3><?= $highest_score ?></h3>
          <p>Highest Score</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="chart-wrap">
        <h3 style="margin-top:0;">📈 Performance Overview</h3>
        <canvas id="scoreChart"></canvas>
      </div>

      <!-- Detailed table -->
      <div class="results-table">
        <h3>📝 Detailed Results</h3>

        <?php if (!empty($data)): ?>
        <table>
  <thead>
    <tr>
      <th>Quiz Title</th>
      <th>Score</th>
      <th>Total Questions</th>
      <th>Attempted On</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['quiz_title']) ?></td>
        <td><?= (int)$row['score'] ?></td>
        <td><?= (int)$row['total_questions'] ?></td>
        <td><?= htmlspecialchars($row['attempted_on']) ?></td>
        <td>
  <a href="student_view_answers.php?quiz_id=<?php echo urlencode($row['quiz_id']); ?>&attempted_on=<?php echo urlencode($row['attempted_on']); ?>" 
     class="btn btn-primary btn-sm">View Answers</a>
</td>

      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

        <?php else: ?>
          <div class="no-results">
            You haven't attempted any quizzes yet. Try one now!
          </div>
        <?php endif; ?>

        <a href="student_dashboard.php" class="back-btn">← Back to Dashboard</a>
      </div>
    </div>
    
  </div>

  <!-- Chart rendering -->
  <script>
    // Data from PHP (safe JSON)
    const labels = <?= json_encode($quiz_titles, JSON_UNESCAPED_UNICODE) ?>; // labels like "Quiz (date)"
    const scores = <?= json_encode($scores) ?>;
    const runningAvg = <?= json_encode($running_avg) ?>;

    // Check if there are quiz results
    if (labels.length > 0) {
      const ctx = document.getElementById('scoreChart').getContext('2d');

      // Chart Configuration
      const scoreChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels, // Labels for the x-axis
          datasets: [
            {
              type: 'bar',
              label: 'Score',
              data: scores, // Actual quiz scores
              backgroundColor: 'rgba(37,99,235,0.85)', // Blue
              borderRadius: 6,
              barThickness: 28
            },
            {
              type: 'line',
              label: 'Running Average',
              data: runningAvg, // Running average data
              borderColor: 'rgba(16,185,129,0.95)', // Green
              backgroundColor: 'rgba(16,185,129,0.12)',
              tension: 0.25,
              pointRadius: 4,
              pointHoverRadius: 6
            }
          ]
        },
        options: {
          responsive: true, // Make it responsive
          maintainAspectRatio: true, // Maintain aspect ratio
          scales: {
            y: {
              beginAtZero: true, // Start the y-axis at zero
              ticks: {
                precision: 0 // Remove decimal points
              }
            }
          },
          plugins: {
            legend: { position: 'top' },
            tooltip: { mode: 'index', intersect: false }
          }
        }
      });
    }
  </script>
</body>
</html>

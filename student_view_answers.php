<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];

// --- Get quiz_id and attempted_on from URL ---
if (!isset($_GET['quiz_id']) || !isset($_GET['attempted_on'])) {
  die("Invalid parameters.");
}

$quiz_id = (int) $_GET['quiz_id'];
$attempted_on = urldecode($_GET['attempted_on']);

// Fetch quiz info
$q = $conn->prepare("SELECT title FROM quizzes WHERE id = ?");
$q->bind_param("i", $quiz_id);
$q->execute();
$quiz = $q->get_result()->fetch_assoc();
$q->close();

// Fetch attempt info
$a = $conn->prepare("SELECT score, total_questions, percentage, 
        CASE 
          WHEN percentage >= 90 THEN 'A+' 
          WHEN percentage >= 80 THEN 'A' 
          WHEN percentage >= 70 THEN 'B' 
          WHEN percentage >= 60 THEN 'C' 
          WHEN percentage >= 50 THEN 'D' 
          ELSE 'F' 
        END AS grade
      FROM quiz_attempts 
      WHERE quiz_id = ? AND student_id = ? AND attempted_on = ?
      LIMIT 1");
$a->bind_param("iis", $quiz_id, $user_id, $attempted_on);
$a->execute();
$attempt = $a->get_result()->fetch_assoc();
$a->close();

if ($attempt) {
  $score = $attempt['score'];
  $total_questions = $attempt['total_questions'];
  $percentage = $attempt['percentage'];
  $grade = $attempt['grade'];
} else {
  $score = $total_questions = $percentage = 0;
  $grade = "N/A";
}

if ($grade == "A+" || $grade == "A") $emoji = "🏆 Excellent work!";
elseif ($grade == "B" || $grade == "C") $emoji = "👏 Good effort!";
elseif ($grade == "D") $emoji = "💪 You can do better next time!";
else $emoji = "😔 Don’t give up, try again!";

// Fetch question + answers
$sql = "SELECT q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
               qa.student_answer, qa.correct_answer
        FROM quiz_answers qa
        JOIN questions q ON qa.question_id = q.id
        WHERE qa.quiz_id = ? AND qa.student_id = ? AND qa.attempted_on = ?
        ORDER BY qa.id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $quiz_id, $user_id, $attempted_on);
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
  $results[] = [
    'question' => $row['question_text'],
    'options' => [
      'A' => $row['option_a'],
      'B' => $row['option_b'],
      'C' => $row['option_c'],
      'D' => $row['option_d']
    ],
    'yours' => $row['student_answer'],
    'correct' => $row['correct_answer'],
    'is_correct' => ($row['student_answer'] === $row['correct_answer'])
  ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($quiz['title']); ?> - Answers</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #e0e7ff 0%, #f9fafb 100%);
  margin: 0;
  color: #111827;
}
.container {
  max-width: 950px;
  margin: 40px auto;
  background: #fff;
  padding: 32px;
  border-radius: 16px;
  box-shadow: 0 6px 30px rgba(0, 0, 0, 0.08);
}
.header {
  background: linear-gradient(90deg, #2563eb, #1e40af);
  color: white;
  border-radius: 14px;
  padding: 30px 24px;
  text-align: center;
}
.header h1 {margin: 0;font-size: 28px;}
.header p {margin-top: 8px;opacity: 0.9;}
.stats {
  display: flex;
  justify-content: space-around;
  flex-wrap: wrap;
  gap: 20px;
  margin-top: 25px;
}
.stat-box {
  background: #f9fafb;
  border-radius: 14px;
  padding: 20px;
  text-align: center;
  flex: 1;
  min-width: 180px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
.circle-container {
  position: relative;
  width: 110px;
  height: 110px;
  margin: 0 auto 10px;
}
.circle-bg {
  fill: none;
  stroke: #e5e7eb;
  stroke-width: 10;
}
.circle {
  fill: none;
  stroke-width: 10;
  stroke-linecap: round;
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
  transition: stroke 0.3s ease;
}
.circle-text {
  font-weight: 700;
  font-size: 16px;
  fill: #1e3a8a;
  text-anchor: middle;
  dominant-baseline: middle;
}
.stat-label {color:#6b7280;font-weight:500;margin-top:8px;}
.questions {margin-top: 40px;}
.question-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  padding: 16px 20px;
  border-radius: 10px;
  margin-bottom: 14px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.answer span {
  display: inline-block;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 6px;
}
.correct {color:#16a34a;background:#dcfce7;}
.wrong {color:#dc2626;background:#fee2e2;}
.footer {text-align:center;margin-top:30px;}
.btn {
  display:inline-block;
  background:#2563eb;
  color:white;
  padding:10px 18px;
  border-radius:8px;
  font-weight:600;
  text-decoration:none;
  margin:6px;
}
.btn:hover {background:#1e3a8a;}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
    <p><?php echo $emoji; ?></p>
  </div>

  <div class="stats">
    <?php
    $stats = [
      ['value' => $score, 'label' => 'Correct Answers', 'percent' => ($total_questions>0?($score/$total_questions)*100:0)],
      ['value' => $total_questions - $score, 'label' => 'Wrong Answers', 'percent' => ($total_questions>0?(($total_questions-$score)/$total_questions)*100:0)],
      ['value' => $percentage, 'label' => 'Percentage', 'percent' => $percentage],
      ['value' => $grade, 'label' => 'Grade', 'percent' => is_numeric($percentage) ? $percentage : 100]
    ];
    foreach ($stats as $s):
      $percent = round($s['percent'], 2);
      if ($percent >= 75) $color = "#16a34a";
      elseif ($percent >= 50) $color = "#facc15";
      else $color = "#dc2626";
    ?>
    <div class="stat-box">
      <div class="circle-container">
        <svg width="110" height="110">
          <circle class="circle-bg" cx="55" cy="55" r="45"></circle>
          <circle class="circle" cx="55" cy="55" r="45"
            stroke="<?php echo $color; ?>"
            stroke-dasharray="<?php echo ($percent * 283) / 100; ?>, 283">
          </circle>
          <text x="55" y="60" class="circle-text"><?php echo htmlspecialchars($s['value']); ?></text>
        </svg>
      </div>
      <div class="stat-label"><?php echo $s['label']; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="questions">
    <h3>📝 Detailed Answers</h3>
    <?php if (count($results) > 0): ?>
      <?php foreach($results as $i => $r): ?>
        <div class="question-card">
          <h4>Q<?php echo $i+1; ?>. <?php echo htmlspecialchars($r['question']); ?></h4>
          <div class="answer"><strong>Your Answer:</strong>
            <span class="<?php echo $r['is_correct'] ? 'correct' : 'wrong'; ?>">
              <?php echo $r['yours'] ? htmlspecialchars($r['yours']) : 'Not Answered'; ?>
            </span>
          </div>
          <div class="answer"><strong>Correct Answer:</strong>
            <span class="correct"><?php echo htmlspecialchars($r['correct']); ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No answers found for this attempt.</p>
    <?php endif; ?>
  </div>

  <div class="footer">
    <a href="student_my_results.php" class="btn">⬅ Back to My Results</a>
  </div>
</div>
</body>
</html>

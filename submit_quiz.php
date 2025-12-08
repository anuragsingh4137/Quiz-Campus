<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die('Invalid request');
}

$quiz_id = (int)$_POST['quiz_id'];
$user_id = $_SESSION['user_id'];
$answers = $_POST['answers'] ?? []; // answers are displayed-letters like 'A','B' relative to shuffled options
$option_maps = $_POST['option_map'] ?? []; // option_map[QUESTION_ID] => JSON mapping displayed -> original

// Fetch quiz info
$q = $conn->prepare("SELECT title, description FROM quizzes WHERE id = ?");
$q->bind_param("i", $quiz_id);
$q->execute();
$quiz = $q->get_result()->fetch_assoc();

// Fetch all questions for that quiz (DB original correct_option)
$stmt = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE quiz_id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$res = $stmt->get_result();

$total_questions = $res->num_rows;
$score = 0;
$results = [];
$answersData = [];

// Compare answers
while ($row = $res->fetch_assoc()) {
  $qid = $row['id'];

  // student's submitted displayed-letter (e.g., 'A' meaning the first displayed option)
  $display_answer = isset($answers[$qid]) ? strtoupper(trim($answers[$qid])) : null;

  // default: assume not answered
  $student_answer_original = null;

  // If an option_map was provided for this question, decode it
  if (isset($option_maps[$qid]) && $option_maps[$qid] !== '') {
    // option_map is JSON string e.g. {"A":"C","B":"A","C":"B","D":"D"}
    $map = json_decode($option_maps[$qid], true);
    if (is_array($map) && $display_answer !== null && isset($map[$display_answer])) {
      // map displayed-letter back to original DB letter
      $student_answer_original = strtoupper(trim($map[$display_answer]));
    } else {
      // fallback: if mapping not present, assume display letters are original letters
      $student_answer_original = $display_answer;
    }
  } else {
    // no map provided — assume displayed letters match DB letters (old behavior)
    $student_answer_original = $display_answer;
  }

  $correct_answer = strtoupper(trim($row['correct_option']));
  $is_correct = ($student_answer_original !== null && $student_answer_original === $correct_answer);
  if ($is_correct) $score++;

  $results[] = [
    'question' => $row['question_text'],
    'options' => [
      'A' => $row['option_a'],
      'B' => $row['option_b'],
      'C' => $row['option_c'],
      'D' => $row['option_d']
    ],
    'correct' => $correct_answer,
    'yours' => $student_answer_original,
    'is_correct' => $is_correct
  ];

  $answersData[] = [
    'question_id' => $qid,
    'student_answer' => $student_answer_original,
    'correct_answer' => $correct_answer
  ];
}

// Calculate stats
$percentage = ($total_questions > 0) ? round(($score / $total_questions) * 100, 2) : 0;

// Determine grade
if ($percentage >= 90) $grade = "A+";
elseif ($percentage >= 80) $grade = "A";
elseif ($percentage >= 70) $grade = "B";
elseif ($percentage >= 60) $grade = "C";
elseif ($percentage >= 50) $grade = "D";
else $grade = "F";

// Emoji message
if ($grade == "A+" || $grade == "A") $emoji = "🏆 Excellent work!";
elseif ($grade == "B" || $grade == "C") $emoji = "👏 Good effort!";
elseif ($grade == "D") $emoji = "💪 You can do better next time!";
else $emoji = "😔 Don’t give up, try again!";

// ✅ Store results (same as before)
$save = $conn->prepare("INSERT INTO results (user_id, quiz_id, score, total_questions, percentage, grade, submitted_at)
VALUES (?, ?, ?, ?, ?, ?, NOW())");
$save->bind_param("iiiids", $user_id, $quiz_id, $score, $total_questions, $percentage, $grade);
$save->execute();

// ✅ Store attempts (same as before)
$attempt_date = date('Y-m-d H:i:s');
$total_marks = $total_questions;
$stmt2 = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, score, total_questions, attempted_at, created_at, attempted_on, total_marks, attempt_date, percentage)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt2->bind_param("iiiisssids", $quiz_id, $user_id, $score, $total_questions, $attempt_date, $attempt_date, $attempt_date, $total_marks, $attempt_date, $percentage);
$stmt2->execute();

// ✅ Store answers in quiz_answers
$saveAns = $conn->prepare("INSERT INTO quiz_answers (student_id, quiz_id, question_id, student_answer, correct_answer, attempted_on)
VALUES (?, ?, ?, ?, ?, ?)");
foreach ($answersData as $a) {
  $saveAns->bind_param("iiisss", $user_id, $quiz_id, $a['question_id'], $a['student_answer'], $a['correct_answer'], $attempt_date);
  $saveAns->execute();
}

// RENDER results page (same as before)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($quiz['title']); ?> - Result</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* (styles same as previous results page; unchanged) */
body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #e0e7ff 0%, #f9fafb 100%); margin: 0; color: #111827; }
.container { max-width: 950px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 16px; box-shadow: 0 6px 30px rgba(0, 0, 0, 0.08); }
.header { background: linear-gradient(90deg, #2563eb, #1e40af); color: white; border-radius: 14px; padding: 30px 24px; text-align: center; }
.header h1 {margin: 0;font-size: 28px;}
.header p {margin-top: 8px;opacity: 0.9;}
.stats { display: flex; justify-content: space-around; flex-wrap: wrap; gap: 20px; margin-top: 25px; }
.stat-box { background: #f9fafb; border-radius: 14px; padding: 20px; text-align: center; flex: 1; min-width: 180px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
.circle-container { position: relative; width: 110px; height: 110px; margin: 0 auto 10px; }
.circle-bg { fill: none; stroke: #e5e7eb; stroke-width: 10; }
.circle { fill: none; stroke-width: 10; stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke 0.3s ease; }
.circle-text { font-weight: 700; font-size: 16px; fill: #1e3a8a; text-anchor: middle; dominant-baseline: middle; }
.stat-label { color:#6b7280; font-weight:500; margin-top:8px; }
.questions { margin-top: 40px; }
.question-card { background: #f9fafb; border: 1px solid #e5e7eb; padding: 16px 20px; border-radius: 10px; margin-bottom: 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); }
.answer span { display: inline-block; font-weight: 600; padding: 3px 8px; border-radius: 6px; }
.correct { color:#16a34a;background:#dcfce7; }
.wrong { color:#dc2626;background:#fee2e2; }
.footer { text-align:center;margin-top:30px; }
.btn { display:inline-block; background:#2563eb; color:white; padding:10px 18px; border-radius:8px; font-weight:600; text-decoration:none; margin:6px; }
.btn:hover { background:#1e3a8a; }
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
      ['value' => $score, 'label' => 'Correct Answers', 'percent' => ($total_questions>0?($score/$total_questions*100):0)],
      ['value' => $total_questions - $score, 'label' => 'Wrong Answers', 'percent' => ($total_questions>0?(($total_questions-$score)/$total_questions*100):0)],
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
          <circle class="circle" cx="55" cy="55" r="45" stroke="<?php echo $color; ?>" stroke-dasharray="<?php echo ($percent * 283) / 100; ?>, 283"></circle>
          <text x="55" y="60" class="circle-text"><?php echo htmlspecialchars($s['value']); ?></text>
        </svg>
      </div>
      <div class="stat-label"><?php echo $s['label']; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="questions">
    <h3>📝 Detailed Answers</h3>
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
  </div>

  <div class="footer">
    <a href="student_dashboard.php" class="btn">🏠 Back to Dashboard</a>
    <a href="student_quizzes.php" class="btn" style="background:#16a34a;">🔁 Try Again</a>
  </div>
</div>
</body>
</html>

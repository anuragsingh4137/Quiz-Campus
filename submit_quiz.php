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

$quiz_id = (int)($_POST['quiz_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$answers = $_POST['answers'] ?? [];
$option_maps = $_POST['option_map'] ?? [];

// Fetch quiz info
$q = $conn->prepare("SELECT title FROM quizzes WHERE id = ?");
$q->bind_param("i", $quiz_id);
$q->execute();
$quiz = $q->get_result()->fetch_assoc();
$q->close();

// Fetch questions
$stmt = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option 
                         FROM questions WHERE quiz_id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$res = $stmt->get_result();

$total_questions = $res->num_rows;
$safeTotal = ($total_questions > 0) ? $total_questions : 1;

$score = 0;
$notAnswered = 0;
$results = [];
$answersData = [];

// Compare answers
while ($row = $res->fetch_assoc()) {
    $qid = $row['id'];
    $display_answer = isset($answers[$qid]) ? strtoupper(trim($answers[$qid])) : null;
    $student_answer_original = null;

    if (!empty($option_maps[$qid])) {
        $map = json_decode($option_maps[$qid], true);
        if (is_array($map) && $display_answer !== null && isset($map[$display_answer])) {
            $student_answer_original = strtoupper(trim($map[$display_answer]));
        } else {
            $student_answer_original = $display_answer;
        }
    } else {
        $student_answer_original = $display_answer;
    }

    if ($student_answer_original === null) {
        $notAnswered++;
    }

    $correct_answer = strtoupper(trim($row['correct_option']));
    $is_correct = ($student_answer_original !== null && $student_answer_original === $correct_answer);
    if ($is_correct) $score++;

    $results[] = [
        'question' => $row['question_text'],
        'yours' => $student_answer_original,
        'correct' => $correct_answer,
        'is_correct' => $is_correct
    ];

    $answersData[] = [
        'question_id' => $qid,
        'student_answer' => $student_answer_original,
        'correct_answer' => $correct_answer
    ];
}
$stmt->close();

$wrong = $total_questions - $score - $notAnswered;
$percentage = round(($score / $safeTotal) * 100, 2);

// Grade
if ($percentage >= 90) $grade = "A+";
elseif ($percentage >= 80) $grade = "A";
elseif ($percentage >= 70) $grade = "B";
elseif ($percentage >= 60) $grade = "C";
elseif ($percentage >= 50) $grade = "D";
else $grade = "F";

// Emoji
if ($grade === "A+" || $grade === "A") $emoji = "🏆 Excellent work!";
elseif ($grade === "B" || $grade === "C") $emoji = "👏 Good effort!";
elseif ($grade === "D") $emoji = "💪 You can do better next time!";
else $emoji = "😔 Don’t give up, try again!";

// Save result
$save = $conn->prepare("INSERT INTO results (user_id, quiz_id, score, total_questions, percentage, grade, submitted_at)
VALUES (?, ?, ?, ?, ?, ?, NOW())");
$save->bind_param("iiiids", $user_id, $quiz_id, $score, $total_questions, $percentage, $grade);
$save->execute();
$save->close();

$attempt_date = date('Y-m-d H:i:s');
$total_marks = $total_questions;

$stmt2 = $conn->prepare("INSERT INTO quiz_attempts 
(quiz_id, student_id, score, total_questions, attempted_at, created_at, attempted_on, total_marks, attempt_date, percentage)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt2->bind_param(
    "iiiisssids",
    $quiz_id,
    $user_id,
    $score,
    $total_questions,
    $attempt_date,
    $attempt_date,
    $attempt_date,
    $total_marks,
    $attempt_date,
    $percentage
);
$stmt2->execute();
$stmt2->close();

// Save answers
$saveAns = $conn->prepare("INSERT INTO quiz_answers 
(student_id, quiz_id, question_id, student_answer, correct_answer, attempted_on)
VALUES (?, ?, ?, ?, ?, ?)");
foreach ($answersData as $a) {
    $saveAns->bind_param(
        "iiisss",
        $user_id,
        $quiz_id,
        $a['question_id'],
        $a['student_answer'],
        $a['correct_answer'],
        $attempt_date
    );
    $saveAns->execute();
}
$saveAns->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($quiz['title']); ?> - Result</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Poppins',sans-serif;background:#eef2ff;margin:0}
.container{max-width:950px;margin:40px auto;background:#fff;padding:32px;border-radius:16px}
.header{background:#1e40af;color:#fff;padding:30px;border-radius:14px;text-align:center}
.stats{display:flex;gap:20px;flex-wrap:wrap;margin-top:25px}
.stat-box{flex:1;min-width:180px;text-align:center;background:#f9fafb;padding:20px;border-radius:14px}
.circle-bg{fill:none;stroke:#e5e7eb;stroke-width:10}
.circle{fill:none;stroke-width:10;stroke-linecap:round;transform:rotate(-90deg);transform-origin:50% 50%}
.questions{margin-top:40px}
.question-card{background:#f9fafb;border:1px solid #e5e7eb;padding:16px 20px;border-radius:10px;margin-bottom:14px}
.answer span{display:inline-block;font-weight:600;padding:3px 8px;border-radius:6px}
.correct{color:#16a34a;background:#dcfce7}
.wrong{color:#dc2626;background:#fee2e2}
.na{color:#c2410c;background:#ffedd5}
.back-btn{
  position: fixed;
  top: 20px;
  left: 20px;
  background: #2563eb;
  color: #fff;
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  z-index: 1000;
  transition: 0.3s ease;
}

.back-btn:hover{
  background:#1e40af;
}

</style>
</head>
<body>
<a href="student_dashboard.php" class="back-btn">⬅ Dashboard</a>
<div class="container">
<div class="header">
<h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
<p><?php echo $emoji; ?></p>
</div>

<div class="stats">
<?php
$stats = [
    ['label'=>'Correct Answers','value'=>$score,'percent'=>($score/$safeTotal)*100,'type'=>'correct'],
    ['label'=>'Wrong Answers','value'=>$wrong,'percent'=>($wrong/$safeTotal)*100,'type'=>'wrong'],
    ['label'=>'Not Answered','value'=>$notAnswered,'percent'=>($notAnswered/$safeTotal)*100,'type'=>'na'],
    ['label'=>'Percentage','value'=>$percentage,'percent'=>$percentage,'type'=>'percentage'],
    ['label'=>'Grade','value'=>$grade,'percent'=>100,'type'=>'grade']
];

foreach ($stats as $s):
    $percent = round($s['percent'],2);
    switch ($s['type']) {
        case 'correct': $color="#16a34a"; break;
        case 'wrong': $color=($s['value']==0)?"#9ca3af":"#dc2626"; break;
        case 'na': $color=($s['value']==0)?"#9ca3af":"#f59e0b"; break;
        case 'grade': $color=($grade==="F")?"#dc2626":"#16a34a"; break;
        default:
            $color=($percent>=75)?"#16a34a":(($percent>=50)?"#facc15":"#dc2626");
    }
?>
<div class="stat-box">
<svg width="110" height="110">
<circle class="circle-bg" cx="55" cy="55" r="45"/>
<circle class="circle" cx="55" cy="55" r="45"
stroke="<?php echo $color; ?>"
stroke-dasharray="<?php echo ($percent*283)/100; ?>,283"/>
<text x="55" y="60" fill="#1e3a8a" font-weight="700" text-anchor="middle">
<?php echo htmlspecialchars($s['value']); ?>
</text>
</svg>
<p><?php echo $s['label']; ?></p>
</div>
<?php endforeach; ?>
</div>

<div class="questions">
<h3>📝 Detailed Answers</h3>

<?php foreach ($results as $i => $r): ?>
<div class="question-card">
<h4>Q<?php echo $i+1; ?>. <?php echo htmlspecialchars($r['question']); ?></h4>

<div class="answer">
<strong>Your Answer:</strong>
<span class="<?php
if ($r['yours'] === null) echo 'na';
elseif ($r['is_correct']) echo 'correct';
else echo 'wrong';
?>">
<?php echo $r['yours'] ?? 'Not Answered'; ?>
</span>
</div>

<div class="answer">
<strong>Correct Answer:</strong>
<span class="correct"><?php echo htmlspecialchars($r['correct']); ?></span>
</div>
</div>
<?php endforeach; ?>
</div>

</div>
</body>
</html>

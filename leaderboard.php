<?php
session_start();
require 'db.php';

//Ensure student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Filter inputs
$selected_quiz = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$selected_time = isset($_GET['time_range']) ? $_GET['time_range'] : 'all';

// Build SQL conditions
$conditions = [];
$params = [];
$types = '';

if ($selected_quiz > 0) {
    $conditions[] = "r.quiz_id = ?";
    $params[] = $selected_quiz;
    $types .= 'i';
}

if ($selected_time === 'month') {
    $conditions[] = "MONTH(r.submitted_at) = MONTH(CURRENT_DATE()) AND YEAR(r.submitted_at) = YEAR(CURRENT_DATE())";
} elseif ($selected_time === 'week') {
    $conditions[] = "YEARWEEK(r.submitted_at, 1) = YEARWEEK(CURRENT_DATE(), 1)";
}

$where_sql = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

//  Leaderboard query
$sql = "
    SELECT 
        u.id AS user_id, 
        u.name, 
        u.profile_pic, 
        MAX(r.score) AS highest_score,
        MAX(r.percentage) AS best_percentage,
        MAX(r.grade) AS grade
    FROM results r
    JOIN users u ON u.id = r.user_id
    $where_sql
    GROUP BY u.id
    ORDER BY highest_score DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$leaders = $result->fetch_all(MYSQLI_ASSOC);

//  Quiz list for dropdown
$quiz_list = $conn->query("SELECT id, title FROM quizzes ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leaderboard - Quiz Campus</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body {
  font-family:'Poppins',sans-serif;
  background:#f3f4f6;
  margin:0;
  color:#111827;
}
.navbar {
  background:#2563eb;
  color:white;
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:15px 25px;
  font-weight:600;
}
.navbar img {height:38px;margin-right:8px;vertical-align:middle;}
.container {
  max-width:900px;
  margin:40px auto;
  background:white;
  border-radius:12px;
  box-shadow:0 8px 20px rgba(0,0,0,0.08);
  padding:30px;
}
h2 {text-align:center;color:#1e3a8a;margin-bottom:25px;}
.filter-box {
  display:flex;
  justify-content:center;
  gap:15px;
  margin-bottom:25px;
}
select {
  padding:8px 12px;
  border-radius:8px;
  border:1px solid #d1d5db;
  font-size:15px;
}
button {
  background:#2563eb;
  color:white;
  border:none;
  padding:8px 16px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
}
button:hover {background:#1e40af;}
.top3 {
  display:flex;
  justify-content:center;
  gap:20px;
  margin-bottom:35px;
  flex-wrap:wrap;
  align-items:flex-end; /* podium look */
}
.top3 .card {
  background:#eff6ff;
  padding:15px;
  border-radius:10px;
  width:160px;
  text-align:center;
  box-shadow:0 4px 10px rgba(0,0,0,0.1);
  transition:transform .2s, box-shadow .3s;
}
.top3 .card:hover {transform:scale(1.05);}
.top3 img {
  width:80px;
  height:80px;
  border-radius:50%;
  border:3px solid #2563eb;
  object-fit:cover;
  margin-bottom:8px;
}
.top3 .gold {border-color:#FFD700;}
.top3 .silver {border-color:#C0C0C0;}
.top3 .bronze {border-color:#CD7F32;}
.top3 .gold-card {height:230px; box-shadow:0 0 20px rgba(255,215,0,0.6);}
.top3 .silver-card {height:200px;}
.top3 .bronze-card {height:180px;}
.top3 .name {margin-top:8px;font-weight:700;}
.top3 .score {color:#2563eb;font-weight:600;}
.table {
  width:100%;
  border-collapse:collapse;
}
.table th, .table td {
  padding:10px 12px;
  border-bottom:1px solid #e5e7eb;
  text-align:left;
}
.table th {background:#2563eb;color:white;}
.table tr:hover {background:#f9fafb;}
.rank {font-weight:bold;color:#1e40af;}
.btn {
  display:inline-block;
  background:#2563eb;
  color:white;
  padding:10px 18px;
  text-decoration:none;
  border-radius:8px;
  font-weight:600;
}
.btn:hover {background:#1e40af;}
</style>
</head>
<body>

<div class="navbar">
  <div><img src="css/Quiz Campus  logo.png" alt="">Quiz Campus - Leaderboard</div>
  <a href="student_dashboard.php" class="btn" style="background:white;color:#2563eb;">🏠 Dashboard</a>
</div>

<div class="container">
  <h2> Leaderboard</h2>

  <!-- Filter Section -->
  <form method="GET" class="filter-box">
    <select name="quiz_id">
      <option value="0">All Quizzes</option>
      <?php while ($q = $quiz_list->fetch_assoc()): ?>
        <option value="<?php echo $q['id']; ?>" <?php if ($selected_quiz == $q['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($q['title']); ?>
        </option>
      <?php endwhile; ?>
    </select>

    <select name="time_range">
      <option value="all" <?php if ($selected_time == 'all') echo 'selected'; ?>>All Time</option>
      <option value="month" <?php if ($selected_time == 'month') echo 'selected'; ?>>This Month</option>
      <option value="week" <?php if ($selected_time == 'week') echo 'selected'; ?>>This Week</option>
    </select>

    <button type="submit">Apply</button>
  </form>

  <?php if (count($leaders) > 0): ?>
    <div class="top3">
      <?php
      // 🥉 Bronze left, 🥇 Gold center, 🥈 Silver right
      $podiumOrder = [];
      if (isset($leaders[2])) $podiumOrder[] = ['data'=>$leaders[2],'class'=>'bronze-card','rank'=>2];
      if (isset($leaders[0])) $podiumOrder[] = ['data'=>$leaders[0],'class'=>'gold-card','rank'=>0];
      if (isset($leaders[1])) $podiumOrder[] = ['data'=>$leaders[1],'class'=>'silver-card','rank'=>1];

      foreach ($podiumOrder as $entry):
          $leader = $entry['data'];
          $rank = $entry['rank'];
          $borderClass = ($rank == 0 ? 'gold' : ($rank == 1 ? 'silver' : 'bronze'));
          $medalEmoji = ($rank == 0 ? '🥇' : ($rank == 1 ? '🥈' : '🥉'));
          $topImage = !empty($leader['profile_pic'])
              ? 'uploads/profile/' . htmlspecialchars($leader['profile_pic'])
              : 'uploads/profile/default.png';
      ?>
        <div class="card <?php echo $entry['class']; ?>">
          <img src="<?php echo $topImage; ?>" alt="profile" class="<?php echo $borderClass; ?>">
          <div class="name"><?php echo htmlspecialchars($leader['name']); ?></div>
          <div class="score"><?php echo $medalEmoji . ' ' . (float)$leader['best_percentage']; ?>%</div>
          <div style="font-size:13px;color:#6b7280;">Score: <?php echo (int)$leader['highest_score']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Student</th>
          <th>Score</th>
          <th>Percentage</th>
          <th>Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leaders as $index => $leader): ?>
          <?php 
          $profilePath = !empty($leader['profile_pic']) 
              ? 'uploads/profile/' . htmlspecialchars($leader['profile_pic']) 
              : 'uploads/profile/default.png';
          ?>
          <tr>
            <td class="rank">#<?php echo $index + 1; ?></td>
            <td>
              <img src="<?php echo $profilePath; ?>" 
                   alt="profile" width="32" height="32" 
                   style="border-radius:50%;vertical-align:middle;margin-right:8px;object-fit:cover;">
              <?php echo htmlspecialchars($leader['name']); ?>
            </td>
            <td><?php echo (int)$leader['highest_score']; ?></td>
            <td><?php echo (float)$leader['best_percentage']; ?>%</td>
            <td><?php echo htmlspecialchars($leader['grade']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p style="text-align:center;">No quiz results found for the selected filters.</p>
  <?php endif; ?>
</div>

</body>
</html>

<?php
// student_premium.php
session_start();
require 'db.php'; // your DB connection file

// Require logged-in student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit;
}

// Verify user exists and check premium flag (prepared)
$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, name, is_premium_user FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userRes = $stmt->get_result();
if (!$userRes || $userRes->num_rows === 0) {
    header("Location: logout.php");
    exit;
}
$userRow = $userRes->fetch_assoc();
$stmt->close();

// If student not premium -> redirect to payment page
if ((int)$userRow['is_premium_user'] !== 1) {
    header("Location: payment.php");
    exit;
}

// Display name fallback
$displayName = 'Student';
if (!empty($_SESSION['name'])) {
    $displayName = $_SESSION['name'];
} elseif (!empty($userRow['name'])) {
    $displayName = $userRow['name'];
}
$displayName = htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE);

// Fetch premium quizzes using prepared statement (safer and ready for pagination)
$limit = 50; // change or add offset for pagination later
$sql = "SELECT q.id, q.title, q.description, q.created_at, q.is_premium, u.name AS author
        FROM quizzes q
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.is_premium = 1
        ORDER BY q.created_at DESC
        LIMIT ?";
$qs = $conn->prepare($sql);
$qs->bind_param("i", $limit);
$qs->execute();
$res = $qs->get_result();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Premium Mock Tests — Quiz Campus</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="css/style.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    /* (kept your exact CSS but I shortened for brevity in this snippet)
       Paste the full CSS you already used; below is the same styling included earlier. */
    :root {
      --brand: #2563eb;
      --accent: #6d28d9;
      --muted: #6b7280;
      --bg: #f8fafc;
      --card: #ffffff;
      --table-border: #e6edf3;
    }
    body { 
      margin:0; 
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif; 
      background:var(--bg); 
      color:#0f172a; }
    .topbar { 
      height:64px; 
      background:var(--brand); 
      color:#fff; 
      display:flex; 
      align-items:center; 
      justify-content:space-between; 
      padding:0 20px; 
      position:sticky; 
      top:0; 
      z-index:50; }
    .topbar .left { 
      display:flex; 
      align-items:center; 
      gap:12px; 
      font-weight:700; }
    .topbar img.logo { 
      width:36px; 
      height:36px; 
      border-radius:6px; 
      background:#fff; 
      padding:4px; }
    .topbar .right a { 
      color:#fff; 
      text-decoration:none; 
      font-weight:700; }

    /* Page content */
    .page { 
      flex:1; 
      padding:28px 34px; 
      max-width:1200px; 
      margin:0 auto; }
    .page-header { 
      display:flex; 
      justify-content:space-between; 
      align-items:flex-start; 
      gap:20px; 
      margin-bottom:18px; }
    .title { 
      font-size:26px; 
      font-weight:800; 
      display:flex; 
      align-items:center; 
      gap:12px; }
    .subtitle { 
      color:var(--muted); 
      margin-top:6px; }

    /* Card wrapper */
    .card { 
      background:var(--card); 
      border-radius:12px; 
      padding:0; 
      box-shadow:0 10px 30px rgba(2,6,23,0.04); 
      border:1px solid var(--table-border); 
      overflow:hidden; }

    /* Table styles */
    table.quizzes { 
      width:100%; 
      border-collapse:collapse; 
      table-layout:fixed; }
    table.quizzes thead th {
       background:var(--brand); 
       color:#fff; 
       text-align:left; 
       padding:16px 18px; 
       font-weight:700; 
       font-size:16px; 
       border-bottom:1px solid rgba(255,255,255,0.06); }
    table.quizzes tbody td { 
      padding:18px; 
      border-top:1px solid var(--table-border); 
      vertical-align:middle; 
      color:#0f172a; }
    table.quizzes tbody tr:nth-child(even){ 
      background:#fbfdff; }
    .quiz-title { 
      font-weight:700; 
      color:#111827; 
      margin-bottom:6px; }
    .quiz-by { 
      font-size:13px; 
      color:var(--muted); 
      margin-bottom:8px; }
    .badge-prem { 
      display:inline-block; 
      font-size:12px; 
      padding:6px 10px; 
      border-radius:999px; 
      background: linear-gradient(90deg,#7c3aed,#a78bfa); 
      color:#fff; 
      font-weight:700; }
    .quiz-desc { 
      color:var(--muted); 
      white-space:nowrap; 
      overflow:hidden; 
      text-overflow:ellipsis; 
      max-width:520px; 
      display:inline-block; 
      vertical-align:middle; }
    .action-cell { 
      text-align:left; 
      width:160px; }
    .btn-start { 
      background:transparent; 
      border:none; 
      color:var(--accent); 
      font-weight:700; 
      text-decoration:none; 
      cursor:pointer; 
      padding:8px 10px; 
      border-radius:8px; }
    .btn-start:hover { 
      text-decoration:underline; }

    /* Responsive: collapses on small screens */
    @media (max-width:900px) {
      .sidebar { 
        display:none; }
      .page { 
        padding:18px; 
        max-width:100%; }
      .layout { 
        flex-direction:column; }
    }
    @media (max-width:640px) {
      table.quizzes thead { display:none; }
      table.quizzes, table.quizzes tbody, table.quizzes tr, table.quizzes td { 
        display:block; 
        width:100%; }
      table.quizzes tr { 
        margin-bottom:12px; 
        box-shadow:0 6px 18px rgba(2,6,23,0.03); 
        border-radius:8px; 
        overflow:hidden; 
        background:#fff; }
      table.quizzes td { 
        padding:12px 14px; 
        border:0; 
        display:flex; 
        justify-content:space-between; 
        align-items:center; }
      .quiz-desc { 
        display:block; 
        max-width:60%; 
        white-space:normal; }
      .action-cell { 
        width:auto; }
    }

    .empty { 
      padding:24px; 
      color:var(--muted); 
      text-align:center; }
    .btn-start {
      background: #2563eb; /* Same blue as dashboard */
      color: #ffffff !important;
      padding: 10px 20px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 16px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s ease;
    }
    .btn-start:hover { 
      background: #1e4fcc; 
      transform: translateY(-2px); }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="left">
      <img class="logo" src="css/Quiz Campus  logo.png" alt="logo">
      <div>Quiz Campus - Student</div>
    </div>
    <div class="right">
      <a href="logout.php">Logout</a>
    </div>
  </header>

  <!-- Container -->
  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
      <li><a href="/quiz-campus/student_dashboard.php" >
  <i class="fa-solid fa-house"></i> Dashboard
</a></li>

<li><a href="/quiz-campus/student_quizzes.php">
  <i class="fa-solid fa-file-lines"></i> Available Quizzes
</a></li>

<li><a href="/quiz-campus/student_premium.php"class="active">
  <i class="fa-solid fa-star"></i> Premium Mock Tests
</a></li>

<li><a href="/quiz-campus/student_my_results.php">
  <i class="fa-solid fa-chart-column"></i> My Results
</a></li>

<li><a href="/quiz-campus/student_profile.php">
  <i class="fa-solid fa-user"></i> Profile
</a></li>

      </ul>
    </div>

    <!-- MAIN PAGE -->
    <main class="page" role="main">
      <div class="page-header">
        <div>
          <div class="title">
  <i class="fa-solid fa-crown"></i> Premium Mock Tests
</div>

          <div class="subtitle">Welcome, <strong><?php echo $displayName; ?></strong> exclusive quizzes for premium users.</div>
        </div>
        <div style="align-self:flex-end"></div>
      </div>

      <div class="card" role="region" aria-label="Premium mock tests">
        <?php if ($res && $res->num_rows > 0): ?>
          <table class="quizzes" aria-describedby="premium-quiz-list">
            <thead>
              <tr>
                <th style="width:32%;">Quiz Title</th>
                <th style="width:55%;">Description</th>
                <th style="width:13%;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $res->fetch_assoc()):
                $qid = (int)$row['id'];
                $title = htmlspecialchars($row['title'] ?? 'Untitled', ENT_QUOTES | ENT_SUBSTITUTE);
                $desc  = htmlspecialchars($row['description'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
                $author = htmlspecialchars($row['author'] ?? 'Unknown', ENT_QUOTES | ENT_SUBSTITUTE);
                $is_premium = (int)($row['is_premium'] ?? 1);
                $created_at = $row['created_at'] ?? date('Y-m-d');
              ?>
                <tr>
                  <td>
                    <div class="quiz-title"><?php echo $title; ?></div>
                    <div class="quiz-by">By: <?php echo $author; ?></div>
                    <?php if ($is_premium): ?><div class="badge-prem" aria-hidden="true">Premium</div><?php endif; ?>
                  </td>

                  <td>
                    <div class="quiz-desc" title="<?php echo strip_tags($desc); ?>"><?php echo $desc; ?></div>
                  </td>

                  <td class="action-cell">
                    <a class="btn-start" href="student_take_quiz.php?quiz_id=<?php echo $qid; ?>">Start Quiz</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">
            <h3>No premium quizzes available</h3>
            <p>There are no premium mock tests at the moment. Please check again later.</p>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</body>
</html>

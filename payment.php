<?php 
// payment.php
session_start();
require 'config.php'; // must define $conn and KHALTI_PUBLIC_KEY, KHALTI_SECRET_KEY

// require logged-in student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit;
}
$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name'] ?? 'Student', ENT_QUOTES);

// Plans: amount in NPR and label + interval for server to apply
$plans = [
  'monthly' => ['label'=>'1 Month',   'amount'=>10,  'interval_sql'=>"INTERVAL 1 MONTH"],
  'quarter' => ['label'=>'3 Months',  'amount'=>20,  'interval_sql'=>"INTERVAL 3 MONTH"],
  'lifetime'=> ['label'=>'Lifetime',  'amount'=>30, 'interval_sql'=>null], // null = lifetime
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Upgrade to Premium — Quiz Campus</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* ... (kept same styling as your original) ... */
    :root{ --brand:#2563eb; --muted:#6b7280; --card:#fff; --page:#f4f7fb; }
    body{font-family:Inter,system-ui,Roboto,Arial;margin:0;background:var(--page);color:#0f172a}
    .layout{display:flex}
    .page{flex:1;padding:28px 36px;max-width:1200px;margin:0 auto}
    .title{font-size:28px;font-weight:800;margin-bottom:6px}
    .subtitle{color:var(--muted);margin-bottom:18px}
    .plans { display:grid; grid-template-columns: repeat(1,1fr); gap:18px; margin-bottom:22px; }
    @media(min-width:720px){ .plans { grid-template-columns: repeat(3,1fr); } }
    .plan { background:var(--card); border-radius:12px; padding:18px; box-shadow:0 16px 36px rgba(2,6,23,0.06); border:1px solid rgba(2,6,23,0.04); display:flex;flex-direction:column; justify-content:space-between; }
    .plan h3{ margin:0 0 8px; font-size:20px; }
    .price { font-size:22px; font-weight:800; color:#111827; margin-bottom:10px; }
    .plan p { color:var(--muted); margin:0 0 14px; }
    .select { display:flex; gap:8px; align-items:center; margin-top:10px; }
    .select input[type="radio"]{ width:18px; height:18px; }
    .pay-area { margin-top:22px; display:flex; gap:12px; align-items:center; }
    .khalti-btn { background: linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; padding:12px 20px; border-radius:10px; font-weight:700; text-decoration:none; border:none; cursor:pointer; box-shadow:0 12px 26px rgba(37,99,235,0.14); }
    .khalti-btn:disabled{ opacity:0.6; cursor:not-allowed; }
    .msg { padding:12px 14px; border-radius:8px; margin-top:12px; display:none; }
    .msg.success { background:#ecfdf5; color:#065f46; border:1px solid #bbf7d0; }
    .msg.error { background:#fff1f2; color:#7f1d1d; border:1px solid #fecaca; }
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
        <li><a href="/quiz-campus/student_dashboard.php">
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

    <main class="page">
     <div class="title">
  <i class="fa-solid fa-star"></i> Upgrade to Premium
</div>

      <div class="subtitle">Hi <strong><?php echo $user_name; ?></strong> — select a plan to pay via Khalti.</div>

      <form id="plan-form" method="post" action="khalti_initiate.php">
        <div class="plans" role="list">
          <?php foreach($plans as $key => $p): ?>
            <div class="plan" role="listitem">
              <div>
                <h3><?php echo htmlspecialchars($p['label']); ?></h3>
                <div class="price">Rs <?php echo number_format($p['amount']); ?></div>
                <p>Access exclusive premium mock tests, leaderboards, and more.</p>
              </div>

              <div class="select">
                <input type="radio" name="plan_key" value="<?php echo htmlspecialchars($key); ?>" data-amount="<?php echo (int)$p['amount']; ?>" id="plan-<?php echo htmlspecialchars($key); ?>" <?php if ($key==='monthly') echo 'checked'; ?>>
                <label for="plan-<?php echo htmlspecialchars($key); ?>">Select <?php echo htmlspecialchars($p['label']); ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="pay-area">
          <button id="khalti-pay-btn" class="khalti-btn" type="submit">Pay with Khalti</button>
          <div class="note" style="color:var(--muted)">You will be redirected to Khalti to complete the payment.</div>
        </div>

        <div id="msg-success" class="msg success"></div>
        <div id="msg-error" class="msg error"></div>
      </form>
    </main>
  </div>

  <script>
    // Client-side validation: ensure plan selected and amount >= 10
    document.getElementById('plan-form').addEventListener('submit', function(e){
      var sel = document.querySelector('input[name="plan_key"]:checked');
      if (!sel) {
        e.preventDefault();
        showError('Please select a plan.');
        return false;
      }
      var amount = parseInt(sel.getAttribute('data-amount') || '0');
      if (!amount || amount < 10) {
        e.preventDefault();
        showError('Invalid plan amount (must be >= 10 NPR).');
        return false;
      }
      // form submits to khalti_initiate.php which will initiate and redirect
      showMessage('Redirecting to payment...', 'success');
      return true;
    });

    function showMessage(msg, type){
      var s = document.getElementById('msg-success');
      var e = document.getElementById('msg-error');
      if (type === 'success') { s.textContent = msg; s.style.display = 'block'; e.style.display = 'none'; }
      else { e.textContent = msg; e.style.display = 'block'; s.style.display = 'none'; }
    }
    function showError(msg){ showMessage(msg, 'error'); }
  </script>
</body>
</html>

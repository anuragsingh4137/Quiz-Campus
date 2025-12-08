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
  'monthly' => ['label'=>'1 Month',   'amount'=>299,  'interval_sql'=>"INTERVAL 1 MONTH"],
  'quarter' => ['label'=>'3 Months',  'amount'=>799,  'interval_sql'=>"INTERVAL 3 MONTH"],
  'lifetime'=> ['label'=>'Lifetime',  'amount'=>1999, 'interval_sql'=>null], // null = lifetime
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Upgrade to Premium — Quiz Campus</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="css/style.css">
  <style>
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
        <li><a href="student_dashboard.php">🏠Dashboard</a></li>
        <li><a href="student_quizzes.php">📝Available Quizzes</a></li>
        <li><a href="student_premium.php" class="active">⭐Premium Mock Tests</a></li>
        <li><a href="student_my_results.php">📊My Results</a></li>
        <li><a href="student_profile.php">👤Profile</a></li>
      </ul>
    </div>

    <main class="page">
      <div class="title">Upgrade to Premium</div>
      <div class="subtitle">Hi <strong><?php echo $user_name; ?></strong> — select a plan to pay via Khalti.</div>

      <div class="plans" role="list">
        <?php foreach($plans as $key => $p): ?>
          <div class="plan" role="listitem">
            <div>
              <h3><?php echo htmlspecialchars($p['label']); ?></h3>
              <div class="price">Rs <?php echo number_format($p['amount']); ?></div>
              <p>Access exclusive premium mock tests, leaderboards, and more.</p>
            </div>

            <div class="select">
              <input type="radio" name="plan" value="<?php echo htmlspecialchars($key); ?>" data-amount="<?php echo (int)$p['amount']; ?>" id="plan-<?php echo htmlspecialchars($key); ?>" <?php if ($key==='monthly') echo 'checked'; ?>>
              <label for="plan-<?php echo htmlspecialchars($key); ?>">Select <?php echo htmlspecialchars($p['label']); ?></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pay-area">
        <button id="khalti-pay-btn" class="khalti-btn">Pay with Khalti</button>
        <div class="note" style="color:var(--muted)">You will be redirected to Khalti to complete the payment.</div>
      </div>

      <div id="msg-success" class="msg success"></div>
      <div id="msg-error" class="msg error"></div>
    </main>
  </div>

  <!-- Khalti Checkout JS -->
  <script src="https://khalti.com/static/khalti-checkout.js"></script>
  <script>
    const publicKey = "<?php echo addslashes(KHALTI_PUBLIC_KEY); ?>";
    const payBtn = document.getElementById('khalti-pay-btn');
    const msgSuccess = document.getElementById('msg-success');
    const msgError = document.getElementById('msg-error');

    function getSelectedPlan() {
      const sel = document.querySelector('input[name="plan"]:checked');
      return sel ? { key: sel.value, amount: parseInt(sel.dataset.amount) } : null;
    }

    const checkout = new KhaltiCheckout({
      publicKey: publicKey,
      productIdentity: 'quizcampus-<?php echo $user_id; ?>',
      productName: 'Quiz Campus Premium',
      productUrl: window.location.href,
      eventHandler: {
        onSuccess(payload) {
          // send payload.token and plan to server for verification
          const sel = getSelectedPlan();
          if (!sel) { msgError.textContent = 'Select a plan.'; msgError.style.display = 'block'; return; }
          msgSuccess.style.display = 'none';
          msgError.style.display = 'none';
          payBtn.disabled = true;
          fetch('verify_khalti.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: payload.token, amount: sel.amount, plan: sel.key })
          })
          .then(r => r.json())
          .then(data => {
            payBtn.disabled = false;
            if (data && data.success) {
              msgSuccess.textContent = 'Payment successful. Your account is upgraded to Premium.';
              msgSuccess.style.display = 'block';
              setTimeout(() => { window.location.href = 'student_dashboard.php'; }, 1400);
            } else {
              msgError.textContent = data.message || 'Verification failed.';
              msgError.style.display = 'block';
              console.error('Verify failed', data);
            }
          })
          .catch(err => {
            payBtn.disabled = false;
            msgError.textContent = 'Network error. Please try again.';
            msgError.style.display = 'block';
            console.error(err);
          });
        },
        onError(err) {
          msgError.textContent = 'Payment failed or cancelled.';
          msgError.style.display = 'block';
          console.error('Khalti error', err);
        },
        onClose() { /* closed */ }
      }
    });

    payBtn.addEventListener('click', function() {
      const sel = getSelectedPlan();
      if (!sel) {
        msgError.textContent = 'Please select a plan.';
        msgError.style.display = 'block';
        return;
      }
      // amount to khalti in paisa:
      checkout.show({ amount: sel.amount * 100 });
    });
  </script>
</body>
</html>
